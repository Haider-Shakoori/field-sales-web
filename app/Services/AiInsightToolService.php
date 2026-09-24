<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesmanStockBalance;
use App\Models\SalesReturn;
use App\Models\SalesRoute;
use App\Models\Territory;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AiInsightToolService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly CustomerBalanceService $balances,
        private readonly TenantClock $clock,
    ) {}

    public function definitions(User $user): array
    {
        $tools = [
            $this->tool(
                'get_report',
                'Get a tenant-scoped FieldPulse sales, visits, or performance report for a date range. Use this for totals, comparisons, rankings, and trends.',
                $this->dateRangeSchema([
                    'type' => [
                        'type' => 'string',
                        'enum' => ['sales', 'visits', 'performance'],
                    ],
                ], ['type']),
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
                'search_products',
                'Find active products by name or code, including base price.',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            );
        }

        if ($user->hasPermission('reports:view')) {
            $tools[] = $this->tool(
                'get_top_products',
                'Get top-selling products for a date range by quantity and approved revenue, keeping currencies separate.',
                $this->dateRangeSchema([
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                ]),
            );
        }

        if ($user->hasPermission('expenses:view')) {
            $tools[] = $this->tool(
                'get_expense_summary',
                'Summarize approved expenses by category and currency for a date range.',
                $this->dateRangeSchema([
                    'category' => ['type' => 'string'],
                ]),
            );
        }

        if ($user->hasPermission('stock:view')) {
            $tools[] = $this->tool(
                'get_salesman_stock',
                'Get current sellable and damaged stock held by salesmen. Optionally filter by salesman name or employee code.',
                [
                    'type' => 'object',
                    'properties' => [
                        'salesman' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    ],
                    'additionalProperties' => false,
                ],
            );
        }

        if ($user->hasPermission('returns:view')) {
            $tools[] = $this->tool(
                'get_returns_summary',
                'Summarize customer returns by product, condition, and status for a date range.',
                $this->dateRangeSchema([
                    'status' => [
                        'type' => 'string',
                        'enum' => ['pending', 'approved', 'rejected', 'any'],
                    ],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
                ]),
            );
        }

        if ($user->hasPermission('customers:view')) {
            $tools[] = $this->tool(
                'get_route_coverage',
                'Get territories and routes with customer counts and route coverage metadata. Does not expose customer names.',
                [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'additionalProperties' => false,
                ],
            );
        }

        if ($this->customerDataAllowed($user)) {
            $tools[] = $this->tool(
                'search_customers',
                'Find customers by name, code, phone, or contact person and return credit balances, aging, recent visit, and pending follow-up indicators.',
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

            if ($user->hasPermission('orders:view')) {
                $tools[] = $this->tool(
                    'get_customer_purchase_history',
                    'Get approved purchase history and products for one customer in a date range.',
                    $this->dateRangeSchema([
                        'customer' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
                    ], ['customer']),
                );
                $tools[] = $this->tool(
                    'search_orders',
                    'Find recent orders by order number or customer name, optionally filtering by status.',
                    [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string'],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['pending', 'approved', 'rejected', 'cancelled', 'any'],
                            ],
                            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                        ],
                        'required' => ['query'],
                        'additionalProperties' => false,
                    ],
                );
            }
        }

        return $tools;
    }

    public function execute(User $user, string $name, array $arguments): array
    {
        return match ($name) {
            'get_report' => $this->report($user, $arguments),
            'get_attendance' => $this->attendance($user, $arguments),
            'search_products' => $this->products($user, $arguments),
            'get_top_products' => $this->topProducts($user, $arguments),
            'get_expense_summary' => $this->expenseSummary($user, $arguments),
            'get_salesman_stock' => $this->salesmanStock($user, $arguments),
            'get_returns_summary' => $this->returnsSummary($user, $arguments),
            'get_route_coverage' => $this->routeCoverage($user, $arguments),
            'search_customers' => $this->customerSearch($user, $arguments),
            'get_receivables' => $this->receivables($user, $arguments),
            'get_stale_customers' => $this->staleCustomers($user, $arguments),
            'get_followups' => $this->followups($user, $arguments),
            'get_customer_purchase_history' => $this->purchaseHistory($user, $arguments),
            'search_orders' => $this->orders($user, $arguments),
            default => throw new InvalidArgumentException('Unknown AI insight tool.'),
        };
    }

    public function activityLabel(string $name, array $arguments): string
    {
        return match ($name) {
            'get_report' => 'Checked '.($arguments['type'] ?? 'business').' report',
            'get_attendance' => 'Checked field attendance',
            'search_products' => 'Searched product catalog',
            'get_top_products' => 'Analyzed top-selling products',
            'get_expense_summary' => 'Analyzed approved expenses',
            'get_salesman_stock' => 'Checked salesman stock',
            'get_returns_summary' => 'Analyzed customer returns',
            'get_route_coverage' => 'Checked route and territory coverage',
            'search_customers' => 'Looked up customer records',
            'get_receivables' => 'Checked customer receivables',
            'get_stale_customers' => 'Checked stale customer coverage',
            'get_followups' => 'Checked customer follow-ups',
            'get_customer_purchase_history' => 'Checked customer purchase history',
            'search_orders' => 'Searched order records',
            default => 'Checked FieldPulse data',
        };
    }

    private function report(User $user, array $arguments): array
    {
        $type = (string) ($arguments['type'] ?? '');
        [$from, $to] = $this->range($user, $arguments);

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

    private function products(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('catalog:view'), 403);
        $query = trim((string) ($arguments['query'] ?? ''));
        $limit = min(20, max(1, (int) ($arguments['limit'] ?? 10)));

        return [
            'products' => Product::active()
                ->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%");
                })
                ->orderBy('name')
                ->limit($limit)
                ->get(['uuid', 'code', 'name', 'base_price'])
                ->toArray(),
        ];
    }

    private function topProducts(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('reports:view'), 403);
        [$from, $to] = $this->range($user, $arguments);
        $limit = min(20, max(1, (int) ($arguments['limit'] ?? 10)));

        $items = OrderItem::query()
            ->with(['product', 'order'])
            ->whereHas('order', fn ($query) => $query
                ->where('status', 'approved')
                ->whereBetween('ordered_at', [$from->utc(), $to->endOfDay()->utc()]))
            ->get();

        $rows = $items
            ->groupBy('product_id')
            ->map(function (Collection $group): array {
                $product = $group->first()?->product;
                $totals = $group->groupBy(fn ($item) => $item->order?->currency ?? 'UNKNOWN')
                    ->map(fn (Collection $currencyItems, string $currency): array => [
                        'currency' => $currency,
                        'revenue' => round($currencyItems->sum(fn ($item) => (float) $item->line_total), 4),
                    ])
                    ->values()
                    ->all();

                return [
                    'product' => $product?->name ?? 'Unknown',
                    'code' => $product?->code,
                    'quantity' => round($group->sum(fn ($item) => (float) $item->quantity), 4),
                    'revenue' => $totals,
                ];
            })
            ->sortByDesc('quantity')
            ->take($limit)
            ->values()
            ->all();

        return ['period' => [$from->toDateString(), $to->toDateString()], 'products' => $rows];
    }

    private function expenseSummary(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('expenses:view'), 403);
        [$from, $to] = $this->range($user, $arguments);
        $category = trim((string) ($arguments['category'] ?? ''));

        $rows = Expense::query()
            ->where('status', 'approved')
            ->whereBetween('spent_at', [$from->utc(), $to->endOfDay()->utc()])
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->get(['category', 'currency', 'amount']);

        return [
            'period' => [$from->toDateString(), $to->toDateString()],
            'expenses' => $rows
                ->groupBy(fn ($row) => $row->category.'|'.$row->currency)
                ->map(function (Collection $group): array {
                    $first = $group->first();

                    return [
                        'category' => $first->category,
                        'currency' => $first->currency,
                        'total' => round($group->sum(fn ($row) => (float) $row->amount), 4),
                        'count' => $group->count(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function salesmanStock(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('stock:view'), 403);
        $salesman = trim((string) ($arguments['salesman'] ?? ''));
        $limit = min(100, max(1, (int) ($arguments['limit'] ?? 50)));

        $rows = SalesmanStockBalance::query()
            ->with(['salesman', 'product'])
            ->when($salesman !== '', function ($query) use ($salesman): void {
                $query->whereHas('salesman', fn ($salesmanQuery) => $salesmanQuery
                    ->where('employee_code', 'like', "%{$salesman}%")
                    ->orWhere('first_name', 'like', "%{$salesman}%")
                    ->orWhere('last_name', 'like', "%{$salesman}%"));
            })
            ->limit($limit)
            ->get();

        return [
            'stock' => $rows->map(fn ($row): array => [
                'salesman' => $row->salesman?->full_name,
                'employee_code' => $row->salesman?->employee_code,
                'product' => $row->product?->name,
                'product_code' => $row->product?->code,
                'sellable_qty' => (float) $row->sellable_qty,
                'damaged_qty' => (float) $row->damaged_qty,
            ])->all(),
        ];
    }

    private function returnsSummary(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('returns:view'), 403);
        [$from, $to] = $this->range($user, $arguments);
        $status = (string) ($arguments['status'] ?? 'any');
        $limit = min(30, max(1, (int) ($arguments['limit'] ?? 20)));

        $returns = SalesReturn::query()
            ->with('items.product')
            ->whereBetween('returned_at', [$from->utc(), $to->endOfDay()->utc()])
            ->when($status !== 'any', fn ($query) => $query->where('status', $status))
            ->latest('returned_at')
            ->limit($limit)
            ->get();

        return [
            'period' => [$from->toDateString(), $to->toDateString()],
            'returns' => $returns->map(fn (SalesReturn $return): array => [
                'return_number' => $return->return_number,
                'status' => $return->status,
                'returned_at' => $return->returned_at?->toISOString(),
                'items' => $return->items->map(fn ($item): array => [
                    'product' => $item->product?->name,
                    'quantity' => (float) $item->quantity,
                    'condition' => $item->condition,
                ])->all(),
            ])->all(),
        ];
    }

    private function routeCoverage(User $user, array $arguments): array
    {
        abort_unless($user->hasPermission('customers:view'), 403);
        $limit = min(50, max(1, (int) ($arguments['limit'] ?? 30)));

        return [
            'territories' => Territory::active()
                ->with('branch')
                ->withCount(['customers', 'routes'])
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(fn (Territory $territory): array => [
                    'territory' => $territory->name,
                    'branch' => $territory->branch?->name,
                    'customers' => $territory->customers_count,
                    'routes' => $territory->routes_count,
                ])
                ->all(),
            'routes' => SalesRoute::active()
                ->with('territory')
                ->withCount('customers')
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(fn (SalesRoute $route): array => [
                    'route' => $route->name,
                    'territory' => $route->territory?->name,
                    'customers' => $route->customers_count,
                    'weekdays' => $route->weekdays,
                ])
                ->all(),
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
            ->with('territory')
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'like', "%{$query}%")
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

        return [
            'customers' => collect($this->balances->forCustomers($customers))
                ->flatMap(fn (array $row): array => collect($row['balances'])
                    ->map(fn (array $balance) => [
                        'customer_id' => $row['customer_id'],
                        'customer_name' => $row['customer_name'],
                        ...$balance,
                    ])
                    ->all())
                ->filter(fn (array $row) => (float) $row['outstanding_balance'] > 0)
                ->sortByDesc('outstanding_balance')
                ->take($limit)
                ->values()
                ->all(),
        ];
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
            ->when($priority !== 'any', fn ($query) => $query->where('priority', $priority))
            ->when($overdueOnly, fn ($query) => $query->where('due_at', '<', now()))
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

    private function purchaseHistory(User $user, array $arguments): array
    {
        $this->authorizeCustomerData($user);
        abort_unless($user->hasPermission('orders:view'), 403);
        [$from, $to] = $this->range($user, $arguments);
        $customer = $this->resolveCustomer((string) ($arguments['customer'] ?? ''));
        $limit = min(30, max(1, (int) ($arguments['limit'] ?? 20)));

        $orders = Order::query()
            ->with('items.product')
            ->where('customer_id', $customer->id)
            ->where('status', 'approved')
            ->whereBetween('ordered_at', [$from->utc(), $to->endOfDay()->utc()])
            ->latest('ordered_at')
            ->limit($limit)
            ->get();

        return [
            'customer' => $this->customerRow($customer),
            'period' => [$from->toDateString(), $to->toDateString()],
            'orders' => $orders->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'ordered_at' => $order->ordered_at?->toISOString(),
                'currency' => $order->currency,
                'grand_total' => (float) $order->grand_total,
                'payment_type' => $order->payment_type,
                'items' => $order->items->map(fn ($item): array => [
                    'product' => $item->product?->name,
                    'quantity' => (float) $item->quantity,
                    'line_total' => (float) $item->line_total,
                ])->all(),
            ])->all(),
        ];
    }

    private function orders(User $user, array $arguments): array
    {
        $this->authorizeCustomerData($user);
        abort_unless($user->hasPermission('orders:view'), 403);
        $query = trim((string) ($arguments['query'] ?? ''));
        $status = (string) ($arguments['status'] ?? 'any');
        $limit = min(20, max(1, (int) ($arguments['limit'] ?? 10)));

        $orders = Order::query()
            ->with(['customer', 'salesman'])
            ->where(function ($builder) use ($query): void {
                $builder->where('order_number', 'like', "%{$query}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery
                        ->where('name', 'like', "%{$query}%"));
            })
            ->when($status !== 'any', fn ($builder) => $builder->where('status', $status))
            ->latest('ordered_at')
            ->limit($limit)
            ->get();

        return [
            'orders' => $orders->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'customer' => $order->customer?->name,
                'salesman' => $order->salesman?->full_name,
                'status' => $order->status,
                'ordered_at' => $order->ordered_at?->toISOString(),
                'currency' => $order->currency,
                'grand_total' => (float) $order->grand_total,
                'payment_type' => $order->payment_type,
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

    private function resolveCustomer(string $query): Customer
    {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('Customer is required.');
        }

        return Customer::query()
            ->where('uuid', $query)
            ->orWhere('code', $query)
            ->orWhere('name', 'like', "%{$query}%")
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$query])
            ->firstOrFail();
    }

    private function customerDataAllowed(User $user): bool
    {
        return config('ai.allow_customer_data', false)
            && $user->hasPermission('customers:view');
    }

    private function authorizeCustomerData(User $user): void
    {
        abort_unless($this->customerDataAllowed($user), 403);
    }

    private function range(User $user, array $arguments): array
    {
        $from = $this->date($user, (string) ($arguments['date_from'] ?? ''));
        $to = $this->date($user, (string) ($arguments['date_to'] ?? ''));

        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            throw new InvalidArgumentException('Report date range is invalid or too large.');
        }

        return [$from, $to];
    }

    private function date(User $user, string $value): CarbonImmutable
    {
        try {
            $user->loadMissing('tenant');

            return CarbonImmutable::createFromFormat(
                'Y-m-d',
                $value,
                $this->clock->timezone($user->tenant),
            )->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Dates must use YYYY-MM-DD.');
        }
    }

    private function dateRangeSchema(array $extraProperties = [], array $extraRequired = []): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$extraProperties,
                'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            ],
            'required' => [...$extraRequired, 'date_from', 'date_to'],
            'additionalProperties' => false,
        ];
    }

    private function tool(string $name, string $description, array $parameters): array
    {
        return [
            'type' => 'function',
            'function' => compact('name', 'description', 'parameters'),
        ];
    }
}
