<?php

namespace App\Services\BusinessOs;

use App\Contracts\BusinessOsConnector;
use App\Jobs\SyncBusinessOsRun;
use App\Models\BusinessOsSyncRun;
use App\Models\BusinessOsSyncState;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessOsIntegrationPolicyService;
use Throwable;

final class SyncService
{
    public const STREAMS = [
        'pull:products',
        'pull:customers',
        'pull:prices',
        'push:customers',
        'push:orders',
        'push:collections',
    ];

    public function __construct(
        private readonly BusinessOsConnector $connector,
        private readonly BusinessOsIntegrationPolicyService $policy,
        private readonly MasterDataImporter $importer,
        private readonly OutboxService $outbox,
    ) {}

    public function queue(
        Tenant $tenant,
        ?User $requestedBy = null,
        string $trigger = 'manual',
        ?array $requestedStreams = null,
    ): BusinessOsSyncRun {
        $policy = $this->policy->settingsFor($tenant);
        $streams = $this->allowedStreams($policy, $requestedStreams);

        $run = BusinessOsSyncRun::create([
            'requested_by' => $requestedBy?->id,
            'trigger' => $trigger,
            'status' => $policy['enabled'] && $streams !== []
                ? 'queued'
                : 'skipped',
            'requested_streams' => $streams,
            'counts' => $this->emptyCounts(),
            'finished_at' => $policy['enabled'] && $streams !== []
                ? null
                : now(),
            'error_message' => $policy['enabled']
                ? ($streams === [] ? 'No enabled sync streams were selected.' : null)
                : 'BusinessOS integration is disabled or not configured.',
        ]);

        if ($run->status === 'queued') {
            SyncBusinessOsRun::dispatch($tenant->id, $run->uuid);
        }

        return $run;
    }

    public function execute(Tenant $tenant, BusinessOsSyncRun $run): void
    {
        if (! in_array($run->status, ['queued', 'failed'], true)) {
            return;
        }

        $policy = $this->policy->settingsFor($tenant);
        if (! $policy['enabled']) {
            $run->update([
                'status' => 'skipped',
                'finished_at' => now(),
                'error_message' => 'BusinessOS integration is disabled or not configured.',
            ]);

            return;
        }

        $streams = $this->allowedStreams(
            $policy,
            $run->requested_streams ?? [],
        );

        $run->update([
            'status' => 'running',
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ]);

        $counts = $this->emptyCounts();
        $errors = [];

        foreach (['pull:products', 'pull:prices', 'pull:customers'] as $stream) {
            if (! in_array($stream, $streams, true)) {
                continue;
            }

            $result = $this->pullStream(
                $tenant,
                str($stream)->after(':')->toString(),
            );
            $counts[$stream] = $result['counts'];
            $errors = [
                ...$errors,
                ...$result['errors'],
            ];
        }

        $seeded = $this->outbox->seed($tenant, $policy);
        $counts['outbox_seeded'] = $seeded;

        foreach ([
            'push:customers' => 'customer.upserted',
            'push:orders' => 'order.approved',
            'push:collections' => 'collection.verified',
        ] as $stream => $eventType) {
            if (! in_array($stream, $streams, true)) {
                continue;
            }

            $result = $this->pushStream($tenant, $stream, $eventType);
            $counts[$stream] = $result['counts'];
            $errors = [
                ...$errors,
                ...$result['errors'],
            ];
        }

        $failed = collect($counts)
            ->filter(fn ($value, $key) => str_contains((string) $key, ':'))
            ->sum(fn ($value) => (int) ($value['failed'] ?? 0));
        $processed = collect($counts)
            ->filter(fn ($value, $key) => str_contains((string) $key, ':'))
            ->sum(function ($value): int {
                return (int) ($value['created'] ?? 0)
                    + (int) ($value['updated'] ?? 0)
                    + (int) ($value['sent'] ?? 0)
                    + (int) ($value['skipped'] ?? 0);
            });

        $status = $failed > 0
            ? ($processed > 0 ? 'partial' : 'failed')
            : 'succeeded';

        $run->update([
            'status' => $status,
            'finished_at' => now(),
            'counts' => $counts,
            'error_message' => $errors === []
                ? null
                : mb_substr(implode("\n", array_unique($errors)), 0, 8000),
        ]);
    }

    public function allowedStreams(
        array $policy,
        ?array $requestedStreams = null,
    ): array {
        $allowed = array_values(array_filter([
            $policy['pull_products'] ? 'pull:products' : null,
            $policy['pull_customers'] ? 'pull:customers' : null,
            $policy['pull_prices'] ? 'pull:prices' : null,
            $policy['push_field_customers'] ? 'push:customers' : null,
            $policy['push_orders'] ? 'push:orders' : null,
            $policy['push_collections'] ? 'push:collections' : null,
        ]));

        if ($requestedStreams === null || $requestedStreams === []) {
            return $allowed;
        }

        return array_values(array_intersect(
            $allowed,
            array_values(array_unique($requestedStreams)),
        ));
    }

    public function due(Tenant $tenant): bool
    {
        $policy = $this->policy->settingsFor($tenant);

        if (! $policy['enabled']) {
            return false;
        }

        $active = BusinessOsSyncRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($active) {
            return false;
        }

        $last = BusinessOsSyncRun::query()
            ->whereIn('status', ['succeeded', 'partial', 'failed'])
            ->latest('created_at')
            ->first();

        if (! $last) {
            return true;
        }

        return $last->created_at->lte(
            now()->subMinutes($policy['sync_interval_minutes']),
        );
    }

    private function pullStream(Tenant $tenant, string $type): array
    {
        $stream = 'pull:'.$type;
        $state = $this->state($stream);
        $cursor = $state->cursor;
        $aggregate = [
            'received' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];
        $errors = [];
        $state->update([
            'status' => 'running',
            'last_attempt_at' => now(),
            'last_error' => null,
        ]);

        try {
            for ($page = 0; $page < 20; $page++) {
                $response = $this->connector->pullMasterData(
                    $tenant,
                    [$type],
                    $cursor,
                );
                $records = $this->recordsFor($response, $type);
                $result = $this->importer->import($type, $records);

                foreach ($aggregate as $key => $value) {
                    $aggregate[$key] += (int) ($result['counts'][$key] ?? 0);
                }

                $errors = [
                    ...$errors,
                    ...($result['errors'] ?? []),
                ];

                $nextCursor = $this->nextCursor($response);
                $hasMore = (bool) ($response['has_more'] ?? false);

                if (
                    $nextCursor === null
                    || $nextCursor === ''
                    || $nextCursor === $cursor
                ) {
                    $cursor = $nextCursor ?: $cursor;

                    break;
                }

                $cursor = $nextCursor;

                if (! $hasMore) {
                    break;
                }
            }

            $state->update([
                'cursor' => $cursor,
                'status' => $aggregate['failed'] > 0 ? 'partial' : 'succeeded',
                'last_success_at' => now(),
                'last_error' => $errors === []
                    ? null
                    : mb_substr(implode("\n", $errors), 0, 4000),
                'metadata' => ['last_counts' => $aggregate],
            ]);
        } catch (Throwable $exception) {
            $aggregate['failed']++;
            $errors[] = $exception->getMessage();
            $state->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ]);
        }

        return [
            'counts' => $aggregate,
            'errors' => $errors,
        ];
    }

    private function pushStream(
        Tenant $tenant,
        string $stream,
        string $eventType,
    ): array {
        $state = $this->state($stream);
        $state->update([
            'status' => 'running',
            'last_attempt_at' => now(),
            'last_error' => null,
        ]);

        $events = $this->outbox->pending(
            $eventType,
            (int) config('businessos.push_batch_size', 200),
        );
        $counts = [
            'pending' => $events->count(),
            'sent' => 0,
            'failed' => 0,
        ];
        $errors = [];

        if ($events->isEmpty()) {
            $state->update([
                'status' => 'succeeded',
                'last_success_at' => now(),
                'metadata' => ['last_counts' => $counts],
            ]);

            return compact('counts', 'errors');
        }

        try {
            $response = $this->connector->pushEvents(
                $tenant,
                $events->pluck('payload')->all(),
            );
            $results = collect($response['results'] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->keyBy(fn (array $row) => (string) (
                    $row['idempotency_key']
                    ?? $row['event_key']
                    ?? ''
                ));
            $acceptedCount = is_numeric($response['accepted'] ?? null)
                ? (int) $response['accepted']
                : null;
            $globalSuccess = $acceptedCount !== null
                ? $acceptedCount >= $events->count()
                : in_array(
                    strtolower((string) ($response['status'] ?? '')),
                    ['ok', 'success', 'accepted', 'succeeded'],
                    true,
                );

            foreach ($events as $event) {
                $result = $results->get($event->event_key);

                if ($result !== null) {
                    $status = strtolower((string) ($result['status'] ?? ''));
                    $accepted = (bool) ($result['accepted'] ?? false)
                        || in_array(
                            $status,
                            ['ok', 'success', 'accepted', 'succeeded'],
                            true,
                        );

                    if ($accepted) {
                        $this->outbox->markSent(
                            $event,
                            $this->stringOrNull(
                                $result['external_id'] ?? null,
                            ),
                        );
                        $counts['sent']++;

                        continue;
                    }

                    $message = (string) (
                        $result['error']
                        ?? $result['message']
                        ?? 'BusinessOS rejected the event.'
                    );
                    $this->outbox->markFailed($event, $message);
                    $counts['failed']++;
                    $errors[] = $message;

                    continue;
                }

                if ($globalSuccess) {
                    $this->outbox->markSent($event);
                    $counts['sent']++;

                    continue;
                }

                $message = (string) (
                    $response['error']
                    ?? $response['message']
                    ?? 'BusinessOS did not confirm this event.'
                );
                $this->outbox->markFailed($event, $message);
                $counts['failed']++;
                $errors[] = $message;
            }

            $state->update([
                'status' => $counts['failed'] > 0 ? 'partial' : 'succeeded',
                'last_success_at' => $counts['sent'] > 0 ? now() : $state->last_success_at,
                'last_error' => $errors === []
                    ? null
                    : mb_substr(implode("\n", array_unique($errors)), 0, 4000),
                'metadata' => ['last_counts' => $counts],
            ]);
        } catch (Throwable $exception) {
            foreach ($events as $event) {
                $this->outbox->markFailed($event, $exception->getMessage());
            }

            $counts['failed'] = $events->count();
            $errors[] = $exception->getMessage();
            $state->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'metadata' => ['last_counts' => $counts],
            ]);
        }

        return compact('counts', 'errors');
    }

    private function recordsFor(array $response, string $type): array
    {
        $data = $response['data'] ?? [];

        if (isset($data[$type]) && is_array($data[$type])) {
            return array_values($data[$type]);
        }

        if (array_is_list($data)) {
            return $data;
        }

        if (isset($response[$type]) && is_array($response[$type])) {
            return array_values($response[$type]);
        }

        return [];
    }

    private function nextCursor(array $response): ?string
    {
        return $this->stringOrNull(
            $response['next_cursor']
            ?? $response['cursor']
            ?? data_get($response, 'meta.next_cursor'),
        );
    }

    private function state(string $stream): BusinessOsSyncState
    {
        return BusinessOsSyncState::query()->firstOrCreate(
            ['stream' => $stream],
            ['status' => 'never'],
        );
    }

    private function emptyCounts(): array
    {
        return [
            'pull:products' => [],
            'pull:customers' => [],
            'pull:prices' => [],
            'push:customers' => [],
            'push:orders' => [],
            'push:collections' => [],
            'outbox_seeded' => [],
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
