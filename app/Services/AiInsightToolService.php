<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesmanStockBalance;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AiInsightToolService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly CustomerBalanceService $balances,
        private readonly SupervisorScorecardService $scorecards,
        private readonly AiRecommendationService $recommendations,
        private readonly ManagerBriefingService $briefing,
        private readonly TenantClock $clock,
        private readonly AiPolicyService $policy,
    ) {}

    public function definitions(User $user): array
    {
        $tools = [
            $this->tool(
                'get_report',
                'Get a tenant-scoped FieldPulse sales, visits, or performance report for a date range. Use this for totals, comparisons, rankings, and trends.',
                [
                    'type' => 'object',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'enum' => ['sales', 'visits', 'performance'],
                        ],
                        'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    ],
                    'required' => ['type', 'date_from', 'date_to'],
                    'additionalProperties' => false,
                ],
            ),
            $this->tool(
                'get_recommendations',
                'Get ranked grounded management recommendations with supporting evidence. Use this for questions like what needs attention, priorities, risks, declining sales, reorder opportunities, overdue receivables, stale coverage, pending approvals, or suspicious activity.',
                [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'additionalProperties' => false,
                ],
            ),
            $this->tool(
                'get_manager_briefing',
                'Get the current manager morning briefing with yesterday sales/collections/visits, today attendance, pending approvals, exceptions, and ranked priorities.',
                [
                    'type' => 'object',
                    'properties' => new \stdClass,
                    'additionalProperties' => false,
                ],
            ),
        ];

        if ($user->hasPermission('sales-team:view')) {
            $tools[] = $this->tool(
                'get_attendance',
                'Get attendance/work-session status for active salesmen on one date.',
                [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    ],
                    'required' => ['date'],
                    'additionalProperties' => false,
                ],
            );
        }

        if ($user->hasPermission('catalog:view')) {
            $tools[] = $this->tool(
                'get_top_products',
                'Get top-selling products from approved orders for a date range, separated by currency.',
                [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['date_from', 'date_to'],
                    'additionalProperties' => false,
                ],
            );
        }

        if ($user->hasPermission('expenses:view')) {
            $tools[] = $this->tool(
                'get_expenses',
                'Summarize expenses by category and currency for a date range.',
                [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['approved', 'pending', 'rejected', 'cancelled', 'any'],
                        ],
                    ],
                    'required' => ['date_from', 'date_to'],
                    'additionalProperties' => false,
                ],
            );
        }

        if ($user->hasPermission('stock:view')) {
            $tools[] = $this->tool(
                'get_salesman_stock',
                'Get current salesman stock balances. Optionally filter by salesman name/code or product name/SKU.',
                [
                    'type' => 'object',
                    'properties' => [
                        'salesman_query' => ['type' => 'string'],
                        'product_query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
                    ],
                    'additionalProperties' => false,
                ],
            );
        }

        if ($user->hasPermission('returns:view')) {
            $tools[] = $this->tool(
                'get_returns',
                'Summarize customer returns by product, condition, and status for a date range.',
                [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['approved', 'pending', 'rejected', 'any'],
                        ],
                    ],
                    'required' => ['date_from', 'date_to'],
                    'additionalProperties' => false,
                ],
            );
        }

        if ($user->hasPermission('reports:view')) {
            $tools[] = $this->tool(
                'get_scorecards',
                'Get supervisor/salesman performance scorecards for a date range.',
                [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    ],
                    'required' => ['date_from', 'date_to'],
                    'additionalProperties' => false,
                ],
            );
        }

        if (
            $this->policy->customerDataEnabled($user)
            && $user->hasPermission('customers:view')
        ) {
            if ($user->hasPermission('orders:view')) {
                $tools[] = $this->tool(
                    'get_order_details',
                    'Find a specific order by order number and return its customer, salesman, totals, status, and line items.',
                    [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => ['type' => 'string'],
                        ],
                        'required' => ['order_number'],
                        'additionalProperties' => false,
                    ],
                );
            }

            $tools[] = $this->tool(
                'search_customers',
                'Find customers by name, code, phone, or contact person and return credit balances plus recent visit/follow-up indicators.',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            );
            $tools[] = $this->tool(
                'get_receivables',
                'Get customers with the largest outstanding credit balances.',
                [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'additionalProperties' => false,
                ],
            );
            $tools[] = $this->tool(
                'get_stale_customers',
                'Get active customers that have not been visited recently.',
                [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['days'],
                    'additionalProperties' => false,
                ],
            );
            $tools[] = $this->tool(
                'get_followups',
                'Get pending customer follow-ups, optionally filtered by priority and overdue status.',
                [
                    'type' => 'object',
                    'properties' => [
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['low', 'normal', 'high', 'any'],
                        ],
                        'overdue_only' => ['type' => 'boolean'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'additionalProperties' => false,
                ],
            );
        }

        return $tools;
    }

    public function execute(User $user, string $name, array $arguments): array
    {
        return match ($name) {
            'get_report' => $this->report($user, $arguments),
            'get_recommendations' => $this->recommendationPayload($user, $arguments),
            'get_manager_briefing' => $this->briefingPayload($user),
            'get_attendance' => $this->attendance($user, $arguments),
            'get_top_products' => $this->topProducts($user, $arguments),
            'get_expenses' => $this->expenses($user, $arguments),
            'get_salesman_stock' => $this->salesmanStock($user, $arguments),
            'get_returns' => $this->returns($user, $arguments),
            'get_scorecards' => $this->scorecards($user, $arguments),
            'get_order_details' => $this->orderDetails($user, $arguments),
            'search_customers' => $this->customerSearch($user, $arguments),
            'get_receivables' => $this->receivables($user, $arguments),
            'get_stale_customers' => $this->staleCustomers($user, $arguments),
            'get_followups' => $this->followups($user, $arguments),
            default => throw new InvalidArgumentException('Unknown AI insight tool.'),
        };
    }

    private function recommendationPayload(
        User $user,
        array $arguments,
    ): array {
        $limit = min(20, max(1, (int) ($arguments['limit'] ?? 10)));

        return [
            'recommendations' => $this->externalRecommendationRows(
                $user,
                $this->recommendations->build($user, $limit),
            ),
        ];
    }

    private function briefingPayload(User $user): array
    {
        $briefing = $this->briefing->build($user);
        $briefing['priorities'] = $this->externalRecommendationRows(
            $user,
            $briefing['priorities'],
        );

        return $briefing;
    }

    private function externalRecommendationRows(
        User $user,
        array $recommendations,
    ): array {
        $allowCustomerData = $this->policy->customerDataEnabled($user)
            && $user->hasPermission('customers:view');

        return collect($recommendations)
            ->filter(function (array $item) use ($allowCustomerData): bool {
                if ($allowCustomerData) {
                    return true;
                }

                return ! array_key_exists(
                    'customer_uuid',
                    $item['evidence'] ?? [],
                );
            })
            ->map(fn (array $item) => [
                ...$item,
                'title_text' => __($item['title']),
                'message_text' => __(
                    $item['message'],
                    $item['message_params'] ?? [],
                ),
                'action_text' => __($item['action']),
            ])
            ->values()
            ->all();
    }

    private function report(User $user, array $arguments): array
    {
        $type = (string) ($arguments['type'] ?? '');
        $from = $this->date($user, (string) ($arguments['date_from'] ?? ''));
        $to = $this->date($user, (string) ($arguments['date_to'] ?? ''));

        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            throw new InvalidArgumentException('Report date range is invalid or too large.');
        }

        return $this->reports->build($user, $type, [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ]);
    }

    private function attendance(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('sales-team:view'), 403);

        $date = $this->date($user, (string) ($arguments['date'] ?? ''))->toDateString();
        $salesmen = $this->reports->options($user, $date)['salesmen'];
        $sessions = WorkSession::query()
            ->with('salesman')
            ->whereDate('date', $date)
            ->whereIn('salesman_id', $salesmen->pluck('id'))
            ->get()
            ->keyBy('salesman_id');

        return [
            'date' => $date,
            'salesmen' => $salesmen->map(function ($salesman) use ($sessions): array {
                $session = $sessions->get($salesman->id);

                return [
                    'employee_code' => $salesman->employee_code,
                    'salesman' => $salesman->full_name,
                    'started' => $session !== null,
                    'start_time' => $session?->start_time?->toISOString(),
                    'end_time' => $session?->end_time?->toISOString(),
                    'late_start' => (bool) ($session?->is_late_start ?? false),
                    'early_finish' => (bool) ($session?->is_early_finish ?? false),
                ];
            })->values()->all(),
        ];
    }

    private function topProducts(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('catalog:view'), 403);
        [$from, $to] = $this->dateRange($user, $arguments);
        $limit = min(20, max(1, (int) ($arguments['limit'] ?? 10)));

        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', 'approved')
            ->where('orders.ordered_at', '>=', $from)
            ->where('orders.ordered_at', '<', $to->addDay())
            ->selectRaw(
                'products.sku, products.name, products.unit, orders.currency,
                SUM(order_items.quantity) as quantity,
                SUM(order_items.line_total) as sales_total'
            )
            ->groupBy(
                'products.id',
                'products.sku',
                'products.name',
                'products.unit',
                'orders.currency',
            )
            ->orderByDesc('sales_total')
            ->limit($limit)
            ->get();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'products' => $rows->map(fn ($row) => [
                'sku' => $row->sku,
                'product' => $row->name,
                'unit' => $row->unit,
                'currency' => $row->currency,
                'quantity' => round((float) $row->quantity, 4),
                'sales_total' => round((float) $row->sales_total, 4),
            ])->all(),
        ];
    }

    private function expenses(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('expenses:view'), 403);
        [$from, $to] = $this->dateRange($user, $arguments);
        $status = (string) ($arguments['status'] ?? 'any');

        $rows = Expense::query()
            ->when($status !== 'any', fn ($query) => $query->where('status', $status))
            ->where('spent_at', '>=', $from)
            ->where('spent_at', '<', $to->addDay())
            ->selectRaw(
                'category, currency, status, COUNT(*) as count, SUM(amount) as total'
            )
            ->groupBy('category', 'currency', 'status')
            ->orderByDesc('total')
            ->get();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'expenses' => $rows->map(fn ($row) => [
                'category' => $row->category,
                'currency' => $row->currency,
                'status' => $row->status,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 4),
            ])->all(),
        ];
    }

    private function salesmanStock(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('stock:view'), 403);
        $salesmanQuery = trim((string) ($arguments['salesman_query'] ?? ''));
        $productQuery = trim((string) ($arguments['product_query'] ?? ''));
        $limit = min(30, max(1, (int) ($arguments['limit'] ?? 20)));

        $rows = SalesmanStockBalance::query()
            ->with(['salesman', 'product'])
            ->when($salesmanQuery !== '', function ($query) use ($salesmanQuery): void {
                $query->whereHas('salesman', function ($salesman) use ($salesmanQuery): void {
                    $salesman
                        ->where('first_name', 'like', "%{$salesmanQuery}%")
                        ->orWhere('last_name', 'like', "%{$salesmanQuery}%")
                        ->orWhere('employee_code', 'like', "%{$salesmanQuery}%");
                });
            })
            ->when($productQuery !== '', function ($query) use ($productQuery): void {
                $query->whereHas('product', function ($product) use ($productQuery): void {
                    $product
                        ->where('name', 'like', "%{$productQuery}%")
                        ->orWhere('sku', 'like', "%{$productQuery}%");
                });
            })
            ->orderByDesc('sellable_qty')
            ->limit($limit)
            ->get();

        return [
            'stock' => $rows->map(fn (SalesmanStockBalance $row) => [
                'salesman' => $row->salesman?->full_name,
                'employee_code' => $row->salesman?->employee_code,
                'product' => $row->product?->name,
                'sku' => $row->product?->sku,
                'unit' => $row->product?->unit,
                'sellable_qty' => round((float) $row->sellable_qty, 4),
                'damaged_qty' => round((float) $row->damaged_qty, 4),
            ])->all(),
        ];
    }

    private function returns(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('returns:view'), 403);
        [$from, $to] = $this->dateRange($user, $arguments);
        $status = (string) ($arguments['status'] ?? 'any');

        $rows = SalesReturn::query()
            ->with(['items.product'])
            ->when($status !== 'any', fn ($query) => $query->where('status', $status))
            ->where('returned_at', '>=', $from)
            ->where('returned_at', '<', $to->addDay())
            ->get();

        $items = $rows
            ->flatMap(fn (SalesReturn $return) => $return->items->map(fn ($item) => [
                'product' => $item->product?->name,
                'sku' => $item->product?->sku,
                'condition' => $item->condition,
                'status' => $return->status,
                'quantity' => (float) $item->quantity,
            ]))
            ->groupBy(fn (array $row) => implode('|', [
                $row['sku'],
                $row['condition'],
                $row['status'],
            ]))
            ->map(function ($rows): array {
                $first = $rows->first();

                return [
                    'product' => $first['product'],
                    'sku' => $first['sku'],
                    'condition' => $first['condition'],
                    'status' => $first['status'],
                    'quantity' => round((float) $rows->sum('quantity'), 4),
                ];
            })
            ->values()
            ->all();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'return_count' => $rows->count(),
            'items' => $items,
        ];
    }

    private function scorecards(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('reports:view'), 403);
        [$from, $to] = $this->dateRange($user, $arguments);

        $payload = $this->scorecards->build(
            $user,
            $from->toDateString(),
            $to->toDateString(),
        );

        return [
            'period' => $payload['period'],
            'summary' => $payload['summary'],
            'rows' => collect($payload['rows'])->map(fn (array $row) => [
                'salesman' => $row['salesman']->full_name,
                'employee_code' => $row['salesman']->employee_code,
                'attendance' => $row['attendance'],
                'visits' => $row['visits'],
                'orders' => $row['orders'],
                'collections' => $row['collections'],
                'follow_ups' => $row['follow_ups'],
                'unresolved_flags' => $row['unresolved_flags'],
                'target_average_percent' => $row['target_average_percent'],
            ])->values()->all(),
        ];
    }

    private function orderDetails(User $user, array $arguments): array
    {
        $this->authorizeCustomerData($user);
        abort_unless($user->hasPermission('orders:view'), 403);

        $number = trim((string) ($arguments['order_number'] ?? ''));
        if ($number === '') {
            throw new InvalidArgumentException('Order number is required.');
        }

        $order = Order::query()
            ->with(['customer', 'salesman', 'items.product'])
            ->where('order_number', $number)
            ->firstOrFail();

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'customer' => $order->customer?->name,
            'salesman' => $order->salesman?->full_name,
            'ordered_at' => $order->ordered_at?->toISOString(),
            'currency' => $order->currency,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'grand_total' => (float) $order->grand_total,
            'payment_type' => $order->payment_type,
            'items' => $order->items->map(fn ($item) => [
                'product' => $item->product?->name,
                'sku' => $item->product?->sku,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ])->all(),
        ];
    }

    private function customerSearch(User $user, array $arguments): array
    {
        $this->authorizeCustomerData($user);
        $query = trim((string) ($arguments['query'] ?? ''));
        $limit = min(10, max(1, (int) ($arguments['limit'] ?? 5)));

        if ($query === '') {
            throw new InvalidArgumentException('Customer query is required.');
        }

        $customers = Customer::query()
            ->with(['territory'])
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('contact_person', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return [
            'query' => $query,
            'customers' => $customers->map(
                fn (Customer $customer) => $this->customerRow($customer)
            )->all(),
        ];
    }

    private function receivables(User $user, array $arguments): array
    {
        $this->authorizeCustomerData($user);
        $limit = min(20, max(1, (int) ($arguments['limit'] ?? 10)));
        $customers = Customer::active()->orderBy('name')->get();
        $balanceRows = collect($this->balances->forCustomers($customers))
            ->flatMap(function (array $row): array {
                return collect($row['balances'])
                    ->map(fn (array $balance) => [
                        'customer_id' => $row['customer_id'],
                        'customer_name' => $row['customer_name'],
                        ...$balance,
                    ])
                    ->all();
            })
            ->filter(fn (array $row) => (float) $row['outstanding_balance'] > 0)
            ->sortByDesc('outstanding_balance')
            ->take($limit)
            ->values()
            ->all();

        return ['customers' => $balanceRows];
    }

    private function staleCustomers(User $user, array $arguments): array
    {
        $this->authorizeCustomerData($user);
        $days = min(365, max(1, (int) ($arguments['days'] ?? 30)));
        $limit = min(20, max(1, (int) ($arguments['limit'] ?? 10)));
        $cutoff = now()->subDays($days);

        $customers = Customer::active()
            ->withMax('visits', 'checked_in_at')
            ->whereDoesntHave(
                'visits',
                fn ($query) => $query->where('checked_in_at', '>=', $cutoff),
            )
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return [
            'days' => $days,
            'customers' => $customers->map(fn (Customer $customer) => [
                'customer' => $customer->name,
                'code' => $customer->code,
                'last_visit_at' => $customer->visits_max_checked_in_at,
            ])->all(),
        ];
    }

    private function followups(User $user, array $arguments): array
    {
        $this->authorizeCustomerData($user);
        $limit = min(20, max(1, (int) ($arguments['limit'] ?? 10)));
        $priority = (string) ($arguments['priority'] ?? 'any');
        $overdueOnly = (bool) ($arguments['overdue_only'] ?? false);

        $rows = CustomerFollowUp::query()
            ->with(['customer', 'assignedSalesman'])
            ->where('status', 'pending')
            ->when(
                $priority !== 'any',
                fn ($query) => $query->where('priority', $priority),
            )
            ->when(
                $overdueOnly,
                fn ($query) => $query->where('due_at', '<', now()),
            )
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        return [
            'followups' => $rows->map(fn (CustomerFollowUp $followup) => [
                'customer' => $followup->customer?->name,
                'type' => $followup->type,
                'priority' => $followup->priority,
                'due_at' => $followup->due_at?->toISOString(),
                'salesman' => $followup->assignedSalesman?->full_name,
                'notes' => Str::limit((string) $followup->notes, 250),
            ])->all(),
        ];
    }

    private function customerRow(Customer $customer): array
    {
        $currency = $customer->credit_currency ?: 'AFN';

        return [
            'customer' => $customer->name,
            'code' => $customer->code,
            'phone' => $customer->phone,
            'territory' => $customer->territory?->name,
            'credit_limit' => $customer->credit_limit,
            'credit_currency' => $currency,
            'balance' => $this->balances->snapshot($customer, $currency),
            'aging' => $this->balances->aging($customer),
            'last_visit_at' => $customer->visits()->max('checked_in_at'),
            'pending_followups' => $customer->followUps()
                ->where('status', 'pending')
                ->count(),
        ];
    }

    private function authorizeCustomerData(User $user): void
    {
        abort_unless(
            $this->policy->customerDataEnabled($user)
                && $user->hasPermission('customers:view'),
            403,
        );
    }

    private function dateRange(User $user, array $arguments): array
    {
        $from = $this->date($user, (string) ($arguments['date_from'] ?? ''));
        $to = $this->date($user, (string) ($arguments['date_to'] ?? ''));

        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            throw new InvalidArgumentException(
                'Date range is invalid or exceeds 366 days.',
            );
        }

        return [$from, $to];
    }

    private function date(User $user, string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat(
                'Y-m-d',
                $value,
                $this->clock->timezone($user->tenant),
            )->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Dates must use YYYY-MM-DD.');
        }
    }

    private function tool(string $name, string $description, array $parameters): array
    {
        return [
            'type' => 'function',
            'function' => compact('name', 'description', 'parameters'),
        ];
    }
}
