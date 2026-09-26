<?php

namespace App\Services\BusinessOs;

use App\Models\BusinessOsEntityLink;
use Illuminate\Database\Eloquent\Model;

final class EntityLinkService
{
    public function findLocal(
        string $entityType,
        string $externalId,
        string $modelClass,
    ): ?Model {
        $link = BusinessOsEntityLink::query()
            ->where('entity_type', $entityType)
            ->where('external_id', $externalId)
            ->first();

        if (! $link) {
            return null;
        }

        return $modelClass::query()
            ->where('uuid', $link->local_uuid)
            ->first();
    }

    public function externalId(
        string $entityType,
        string $localUuid,
    ): ?string {
        return BusinessOsEntityLink::query()
            ->where('entity_type', $entityType)
            ->where('local_uuid', $localUuid)
            ->value('external_id');
    }

    public function origin(
        string $entityType,
        string $localUuid,
    ): ?string {
        return BusinessOsEntityLink::query()
            ->where('entity_type', $entityType)
            ->where('local_uuid', $localUuid)
            ->get()
            ->map(fn (BusinessOsEntityLink $link) => data_get(
                $link->metadata,
                'origin',
            ))
            ->filter()
            ->first();
    }

    public function link(
        Model $model,
        string $entityType,
        string $externalId,
        ?string $version = null,
        string $origin = 'businessos',
        array $metadata = [],
    ): BusinessOsEntityLink {
        return BusinessOsEntityLink::query()->updateOrCreate(
            [
                'entity_type' => $entityType,
                'local_uuid' => (string) $model->getAttribute('uuid'),
            ],
            [
                'external_id' => $externalId,
                'external_version' => $version,
                'checksum' => hash(
                    'sha256',
                    json_encode(
                        $metadata,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ) ?: '',
                ),
                'last_synced_at' => now(),
                'metadata' => [
                    ...$metadata,
                    'origin' => $origin,
                ],
            ],
        );
    }
}
