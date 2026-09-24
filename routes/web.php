<?php

use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\Web\AiInsightsController;
use App\Http\Controllers\Web\AlertController;
use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BranchController;
use App\Http\Controllers\Web\CallActivityController;
use App\Http\Controllers\Web\CollectionController;
use App\Http\Controllers\Web\CustomerCommunicationController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\CustomerFollowUpController;
use App\Http\Controllers\Web\CustomerPortalAccessController;
use App\Http\Controllers\Web\CustomerPortalController;
use App\Http\Controllers\Web\CustomerStatementController;
use App\Http\Controllers\Web\DailyRoutePlannerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\LiveMapController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\OrganizationController;
use App\Http\Controllers\Web\Platform\OrganizationController as PlatformOrganizationController;
use App\Http\Controllers\Web\PriceListController;
use App\Http\Controllers\Web\PriceListItemController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\RouteCustomerController;
use App\Http\Controllers\Web\SalesmanAssignmentController;
use App\Http\Controllers\Web\SalesmanController;
use App\Http\Controllers\Web\SalesReturnController;
use App\Http\Controllers\Web\SalesRouteController;
use App\Http\Controllers\Web\SalesTargetController;
use App\Http\Controllers\Web\StockController;
use App\Http\Controllers\Web\SupervisorAssignmentController;
use App\Http\Controllers\Web\SupervisorController;
use App\Http\Controllers\Web\SupervisorScorecardController;
use App\Http\Controllers\Web\TerritoryController;
use App\Http\Controllers\Web\TrackingSettingsController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\VisitController;
use App\Http\Controllers\Web\VisitFormTemplateController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/users');
Route::get('/ready', ReadinessController::class)->middleware('throttle:60,1')->name('ready');

Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:web-login');
Route::get('/portal/{token}', CustomerPortalController::class)
    ->where('token', '[A-Za-z0-9]{32,128}')
    ->middleware('throttle:30,1')
    ->name('customer-portal.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:reports:view')
            ->name('dashboard');
        Route::get('/dashboard/live-locations', [DashboardController::class, 'liveLocations'])
            ->middleware('permission:tracking:view')
            ->name('dashboard.live-locations');

        Route::get('/live-map', [LiveMapController::class, 'index'])
            ->middleware('permission:tracking:view')
            ->name('live-map');

        Route::get('/attendance', [AttendanceController::class, 'index'])
            ->middleware('permission:sales-team:view')
            ->name('attendance.index');

        Route::get('/daily-planner', [DailyRoutePlannerController::class, 'index'])
            ->middleware('permission:sales-team:view')
            ->name('daily-planner.index');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])
            ->name('notifications.read-all');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])
            ->name('notifications.read');
        Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])
            ->name('notifications.preferences');

        Route::get('/ai-insights', [AiInsightsController::class, 'index'])
            ->middleware('permission:reports:view')
            ->name('ai-insights.index');
        Route::get('/ai-insights/usage', [AiInsightsController::class, 'usage'])
            ->middleware('permission:reports:view')
            ->name('ai-insights.usage');
        Route::get('/ai-insights/briefing', [AiInsightsController::class, 'briefing'])
            ->middleware('permission:reports:view')
            ->name('ai-insights.briefing');
        Route::post('/ai-insights/ask', [AiInsightsController::class, 'ask'])
            ->middleware('permission:reports:view')
            ->name('ai-insights.ask');
        Route::delete('/ai-insights/conversations/{conversation}', [AiInsightsController::class, 'archive'])
            ->middleware('permission:reports:view')
            ->name('ai-insights.conversations.archive');

        Route::get('/alerts', [AlertController::class, 'index'])
            ->middleware('permission:reports:view')
            ->name('alerts.index');
        Route::patch('/alerts/{flag}/review', [AlertController::class, 'review'])
            ->middleware('permission:visits:manage')
            ->name('alerts.review');

        Route::get('/reports', [ReportController::class, 'index'])
            ->middleware('permission:reports:view')
            ->name('reports.index');

        Route::get('/scorecards', [SupervisorScorecardController::class, 'index'])
            ->middleware('permission:reports:view')
            ->name('scorecards.index');
        Route::get('/reports/export/csv', [ReportController::class, 'csv'])
            ->middleware('permission:reports:view')
            ->name('reports.csv');

        Route::resource('users', UserController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:users:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:users:manage');

        Route::resource('roles', RoleController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:roles:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:roles:manage');

        Route::resource('branches', BranchController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:branches:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:branches:manage');

        Route::resource('salesmen', SalesmanController::class)
            ->middlewareFor(['index', 'show'], 'permission:sales-team:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:sales-team:manage');

        Route::resource('supervisors', SupervisorController::class)
            ->middlewareFor(['index', 'show'], 'permission:sales-team:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:sales-team:manage');

        Route::get('/devices', [DeviceController::class, 'index'])
            ->middleware('permission:sales-team:view')
            ->name('devices.index');
        Route::get('/devices/{device}', [DeviceController::class, 'show'])
            ->middleware('permission:sales-team:view')
            ->name('devices.show');
        Route::post('/devices/{device}/revoke', [DeviceController::class, 'revoke'])
            ->middleware('permission:sales-team:manage')
            ->name('devices.revoke');

        Route::resource('salesman-assignments', SalesmanAssignmentController::class)
            ->parameters(['salesman-assignments' => 'assignment'])
            ->middlewareFor(['index', 'show'], 'permission:sales-team:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:sales-team:manage');

        Route::resource('supervisor-assignments', SupervisorAssignmentController::class)
            ->parameters(['supervisor-assignments' => 'assignment'])
            ->middlewareFor(['index', 'show'], 'permission:sales-team:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:sales-team:manage');

        Route::resource('territories', TerritoryController::class)
            ->middlewareFor(['index', 'show'], 'permission:customers:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:customers:manage');

        Route::resource('customers', CustomerController::class)
            ->middlewareFor(['index', 'show'], 'permission:customers:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:customers:manage');
        Route::get('/customers/{customer}/statement', CustomerStatementController::class)
            ->middleware('permission:customers:view')
            ->name('customers.statement');
        Route::post('/customers/{customer}/communications', [CustomerCommunicationController::class, 'store'])
            ->middleware('permission:customers:manage')
            ->name('customers.communications.store');
        Route::post('/customers/{customer}/portal-accesses', [CustomerPortalAccessController::class, 'store'])
            ->middleware('permission:customers:manage')
            ->name('customers.portal-accesses.store');
        Route::delete('/customers/{customer}/portal-accesses/{access}', [CustomerPortalAccessController::class, 'destroy'])
            ->middleware('permission:customers:manage')
            ->name('customers.portal-accesses.destroy');

        Route::resource('routes', SalesRouteController::class)
            ->middlewareFor(['index', 'show'], 'permission:customers:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:customers:manage');

        Route::post('/routes/{route}/customers', [RouteCustomerController::class, 'store'])
            ->middleware('permission:customers:manage')
            ->name('routes.customers.store');
        Route::patch('/routes/{route}/customers/reorder', [RouteCustomerController::class, 'reorder'])
            ->middleware('permission:customers:manage')
            ->name('routes.customers.reorder');
        Route::delete('/routes/{route}/customers/{routeCustomer}', [RouteCustomerController::class, 'destroy'])
            ->middleware('permission:customers:manage')
            ->name('routes.customers.destroy');

        Route::resource('products', ProductController::class)
            ->middlewareFor(['index', 'show'], 'permission:catalog:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:catalog:manage');

        Route::resource('price-lists', PriceListController::class)
            ->parameters(['price-lists' => 'priceList'])
            ->middlewareFor(['index', 'show'], 'permission:catalog:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:catalog:manage');

        Route::post('/price-lists/{priceList}/items', [PriceListItemController::class, 'store'])
            ->middleware('permission:catalog:manage')
            ->name('price-lists.items.store');
        Route::put('/price-lists/{priceList}/items/{priceListItem}', [PriceListItemController::class, 'update'])
            ->middleware('permission:catalog:manage')
            ->name('price-lists.items.update');
        Route::delete('/price-lists/{priceList}/items/{priceListItem}', [PriceListItemController::class, 'destroy'])
            ->middleware('permission:catalog:manage')
            ->name('price-lists.items.destroy');

        Route::get('/call-activities', [CallActivityController::class, 'index'])
            ->middleware('permission:customers:view')
            ->name('call-activities.index');

        Route::get('/follow-ups', [CustomerFollowUpController::class, 'index'])
            ->middleware('permission:customers:view')
            ->name('follow-ups.index');
        Route::post('/customers/{customer}/follow-ups', [CustomerFollowUpController::class, 'store'])
            ->middleware('permission:customers:manage')
            ->name('customers.follow-ups.store');
        Route::patch('/follow-ups/{followUp}/status', [CustomerFollowUpController::class, 'updateStatus'])
            ->middleware('permission:customers:manage')
            ->name('follow-ups.status');

        Route::resource('collections', CollectionController::class)
            ->only(['index', 'show'])
            ->middleware('permission:collections:view');
        Route::patch('/collections/{collection}/status', [CollectionController::class, 'updateStatus'])
            ->middleware('permission:collections:manage')
            ->name('collections.status');
        Route::get('/collections/{collection}/receipt', [CollectionController::class, 'receipt'])
            ->middleware('permission:collections:view')
            ->name('collections.receipt');

        Route::resource('expenses', ExpenseController::class)
            ->only(['index', 'show'])
            ->middleware('permission:expenses:view');
        Route::patch('/expenses/{expense}/status', [ExpenseController::class, 'updateStatus'])
            ->middleware('permission:expenses:manage')
            ->name('expenses.status');

        Route::resource('targets', SalesTargetController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:targets:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:targets:manage');

        Route::get('/stock', [StockController::class, 'index'])
            ->middleware('permission:stock:view')
            ->name('stock.index');
        Route::post('/stock/issues', [StockController::class, 'issue'])
            ->middleware('permission:stock:manage')
            ->name('stock.issue');
        Route::patch('/stock/settings', [StockController::class, 'updateSettings'])
            ->middleware('permission:stock:manage')
            ->name('stock.settings');

        Route::resource('returns', SalesReturnController::class)
            ->parameters(['returns' => 'salesReturn'])
            ->only(['index', 'show'])
            ->middleware('permission:returns:view');
        Route::patch('/returns/{salesReturn}/status', [SalesReturnController::class, 'updateStatus'])
            ->middleware('permission:returns:manage')
            ->name('returns.status');

        Route::resource('orders', OrderController::class)
            ->only(['index', 'show'])
            ->middleware('permission:orders:view');
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
            ->middleware('permission:orders:manage')
            ->name('orders.status');
        Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])
            ->middleware('permission:orders:view')
            ->name('orders.invoice');

        Route::resource('visit-forms', VisitFormTemplateController::class)
            ->parameters(['visit-forms' => 'visitForm'])
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->middlewareFor('index', 'permission:visits:view')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'permission:visits:manage');

        Route::resource('visits', VisitController::class)
            ->only(['index', 'show'])
            ->middleware('permission:visits:view');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:audit:view')
            ->name('audit.index');
    });

    Route::get('/admin/tracking-settings', [TrackingSettingsController::class, 'edit'])
        ->middleware('permission:settings:view')
        ->name('tracking.edit');

    Route::put('/admin/tracking-settings', [TrackingSettingsController::class, 'update'])
        ->middleware('permission:settings:manage')
        ->name('tracking.update');

    Route::get('/admin/organization', [OrganizationController::class, 'edit'])
        ->middleware('permission:settings:view')
        ->name('organization.edit');

    Route::put('/admin/organization', [OrganizationController::class, 'update'])
        ->middleware('permission:settings:manage')
        ->name('organization.update');

    Route::middleware('platform')
        ->prefix('admin/organizations')
        ->name('admin.organizations.')
        ->group(function (): void {
            Route::get('/', [PlatformOrganizationController::class, 'index'])->name('index');
            Route::get('/create', [PlatformOrganizationController::class, 'create'])->name('create');
            Route::post('/', [PlatformOrganizationController::class, 'store'])->name('store');
            Route::get('/{organization}/edit', [PlatformOrganizationController::class, 'edit'])->name('edit');
            Route::put('/{organization}', [PlatformOrganizationController::class, 'update'])->name('update');
            Route::patch('/{organization}/status', [PlatformOrganizationController::class, 'updateStatus'])->name('status');
        });
});
