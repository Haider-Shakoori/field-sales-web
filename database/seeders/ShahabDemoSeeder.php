<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class ShahabDemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const KABUL_DISTRICT_GEOJSON_URL = 'https://services7.arcgis.com/bArMqVntXvGojzII/arcgis/rest/services/Gi_HotSpot/FeatureServer/2/query?where=1%3D1&outFields=DistrictName&returnGeometry=true&outSR=4326&f=geojson';

    private const ZONES = [
        'EAST' => [
            'name' => 'Kabul East Zone',
            'manager' => 'Mohammad Naim Rahimi',
            'manager_email' => 'eastmgr@shahab.com',
            'supervisor' => 'Wali Mohammad Ahmadi',
            'supervisor_email' => 'eastsup@shahab.com',
            'districts' => [8, 9, 12, 16, 21, 22],
        ],
        'NORTH' => [
            'name' => 'Kabul North Zone',
            'manager' => 'Ahmad Farid Safi',
            'manager_email' => 'northmgr@shahab.com',
            'supervisor' => 'Sayed Jamal Hashimi',
            'supervisor_email' => 'northsup@shahab.com',
            'districts' => [4, 10, 11, 15, 17, 19],
        ],
        'WEST' => [
            'name' => 'Kabul West Zone',
            'manager' => 'Abdul Wahid Azizi',
            'manager_email' => 'westmgr@shahab.com',
            'supervisor' => 'Noor Agha Mohammadi',
            'supervisor_email' => 'westsup@shahab.com',
            'districts' => [3, 5, 13, 14, 18],
        ],
        'SOUTH' => [
            'name' => 'Kabul South & Central Zone',
            'manager' => 'Hamidullah Noori',
            'manager_email' => 'southmgr@shahab.com',
            'supervisor' => 'Hekmatullah Stanikzai',
            'supervisor_email' => 'southsup@shahab.com',
            'districts' => [1, 2, 6, 7, 20],
        ],
    ];

    /**
     * Public-area reference points are used only as a defensive fallback when
     * placing synthetic customers. Territory and zone geofences come from the
     * Kabul municipal district GeoJSON source.
     */
    private const DISTRICTS = [
        1 => ['area' => 'Old City / Mandawi', 'lat' => 34.5146, 'lng' => 69.1836],
        2 => ['area' => 'Shahr-e-Naw', 'lat' => 34.5359, 'lng' => 69.1668],
        3 => ['area' => 'Karte Char', 'lat' => 34.5094, 'lng' => 69.1468],
        4 => ['area' => 'Taimani / Qala-e-Fatullah', 'lat' => 34.5534, 'lng' => 69.1732],
        5 => ['area' => 'Afshar / Company', 'lat' => 34.5486, 'lng' => 69.1196],
        6 => ['area' => 'Darul Aman', 'lat' => 34.4719, 'lng' => 69.1338],
        7 => ['area' => 'Chihil Sutun', 'lat' => 34.4844, 'lng' => 69.1586],
        8 => ['area' => 'Rahman Mina', 'lat' => 34.5158, 'lng' => 69.2258],
        9 => ['area' => 'Macroyan / Airport Road', 'lat' => 34.5385, 'lng' => 69.2073],
        10 => ['area' => 'Wazir Akbar Khan', 'lat' => 34.5457, 'lng' => 69.1831],
        11 => ['area' => 'Khair Khana South', 'lat' => 34.5848, 'lng' => 69.1517],
        12 => ['area' => 'Ahmad Shah Baba Mina', 'lat' => 34.5221, 'lng' => 69.2967],
        13 => ['area' => 'Dasht-e-Barchi', 'lat' => 34.4930, 'lng' => 69.0876],
        14 => ['area' => 'Qala-e-Qazi / West Kabul', 'lat' => 34.5750, 'lng' => 69.0930],
        15 => ['area' => 'Khair Khana North', 'lat' => 34.6173, 'lng' => 69.1648],
        16 => ['area' => 'Pul-e-Charkhi Corridor', 'lat' => 34.5551, 'lng' => 69.2588],
        17 => ['area' => 'Northwest Kabul', 'lat' => 34.5960, 'lng' => 69.0800],
        18 => ['area' => 'West Kabul / Arghandi Road', 'lat' => 34.5350, 'lng' => 69.0490],
        19 => ['area' => 'Northeast Kabul', 'lat' => 34.5860, 'lng' => 69.2180],
        20 => ['area' => 'Southwest Kabul', 'lat' => 34.4490, 'lng' => 69.1110],
        21 => ['area' => 'Southeast Kabul', 'lat' => 34.4860, 'lng' => 69.2730],
        22 => ['area' => 'Bagrami / Butkhak', 'lat' => 34.4938, 'lng' => 69.3370],
    ];

    private const PRODUCTS = [
        ['SH-DIAPER-S', 'Shahab Baby Diapers Small', 'pack', 420],
        ['SH-DIAPER-M', 'Shahab Baby Diapers Medium', 'pack', 450],
        ['SH-DIAPER-L', 'Shahab Baby Diapers Large', 'pack', 480],
        ['SH-DIAPER-XL', 'Shahab Baby Diapers XL', 'pack', 510],
        ['SH-SHAM-HERB-400', 'Shahab Herbal Shampoo 400ml', 'bottle', 180],
        ['SH-SHAM-AD-400', 'Shahab Anti-Dandruff Shampoo 400ml', 'bottle', 195],
        ['SH-SOAP-BATH-125', 'Shahab Bath Soap 125g', 'pcs', 45],
        ['SH-SOAP-LAUN-250', 'Shahab Laundry Soap 250g', 'pcs', 55],
        ['SH-ENERGY-250', 'Shahab Energy Drink 250ml', 'can', 55],
        ['SH-COLA-330', 'Shahab Cola 330ml', 'can', 35],
        ['SH-COLA-1500', 'Shahab Cola 1.5L', 'bottle', 75],
        ['SH-WATER-500', 'Shahab Water 500ml', 'bottle', 15],
        ['SH-WATER-1500', 'Shahab Water 1.5L', 'bottle', 25],
        ['SH-BISC-CREAM-70', 'Shahab Cream Biscuits 70g', 'pack', 25],
        ['SH-BISC-TEA-90', 'Shahab Tea Biscuits 90g', 'pack', 30],
        ['SH-SNACK-CHIPS-45', 'Shahab Potato Chips 45g', 'pack', 25],
        ['SH-SNACK-CORN-35', 'Shahab Corn Snacks 35g', 'pack', 20],
        ['SH-JUICE-250', 'Shahab Fruit Juice 250ml', 'box', 30],
        ['SH-TISSUE-100', 'Shahab Facial Tissue 100 Sheets', 'box', 65],
        ['SH-DISH-500', 'Shahab Dishwashing Liquid 500ml', 'bottle', 95],
    ];

    public function run(): void
    {
        $districtGeofences = $this->loadDistrictGeofences();

        app(TenantContext::class)->withPlatformScope(function () use ($districtGeofences): void {
            DB::transaction(function () use ($districtGeofences): void {
                $tenant = Tenant::updateOrCreate(
                    ['slug' => 'shahab-demo'],
                    [
                        'uuid' => $this->uuid('tenant'),
                        'name' => 'Shahab Group - Kabul Demo',
                        'timezone' => 'Asia/Kabul',
                        'contact_email' => 'owner@shahab.com',
                        'subscription_status' => 'active',
                    ]
                );

                $provisioning = app(TenantProvisioningService::class);
                $roles = $provisioning->provisionRbac($tenant);
                $provisioning->seedTrackingDefaults($tenant);

                $this->seedOwner($tenant, $roles['owner']);

                [$branches, $managers, $supervisors] = $this->seedLeadership(
                    $tenant,
                    $roles,
                    $districtGeofences
                );

                [$salesmenByZone, $routeBySalesman, $routesByZone, $devices] =
                    $this->seedSalesTeam(
                        $tenant,
                        $roles,
                        $branches,
                        $managers,
                        $supervisors
                    );

                [$priceList] = $this->seedCatalog($tenant);

                [$customersBySalesman, $customerModels] = $this->seedDistrictsAndCustomers(
                    $tenant,
                    $branches,
                    $managers,
                    $salesmenByZone,
                    $routeBySalesman,
                    $routesByZone,
                    $priceList,
                    $districtGeofences
                );

                $this->seedWeeklyVisits(
                    $tenant,
                    $customersBySalesman,
                    $customerModels,
                    $routeBySalesman,
                    $devices
                );
            });
        });
    }

    private function seedLeadership(
        Tenant $tenant,
        array $roles,
        array $districtGeofences
    ): array {
        $branches = [];
        $managers = [];
        $supervisors = [];
        $userCounter = 1;

        foreach (self::ZONES as $zoneCode => $zone) {
            $branch = Branch::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'KBL-'.$zoneCode],
                [
                    'uuid' => $this->uuid('branch:'.$zoneCode),
                    'name' => $zone['name'],
                    'is_active' => true,
                    'geofence_polygon' => $this->zoneGeometry($zone['districts'], $districtGeofences),
                ]
            );
            $branches[$zoneCode] = $branch;

            $manager = $this->upsertUser(
                $tenant,
                $branch,
                $zone['manager'],
                $zone['manager_email'],
                $userCounter++,
                'sales_manager',
                $roles['sales_manager']
            );
            $managers[$zoneCode] = $manager;

            $supervisorUser = $this->upsertUser(
                $tenant,
                $branch,
                $zone['supervisor'],
                $zone['supervisor_email'],
                $userCounter++,
                'supervisor',
                $roles['supervisor']
            );

            $supervisor = Supervisor::updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $supervisorUser->id],
                [
                    'uuid' => $this->uuid('supervisor:'.$zoneCode),
                    'employee_code' => 'SH-SUP-'.$zoneCode,
                    'first_name' => Str::beforeLast($zone['supervisor'], ' '),
                    'last_name' => Str::afterLast($zone['supervisor'], ' '),
                    'is_active' => true,
                ]
            );
            $supervisors[$zoneCode] = $supervisor;

            SupervisorAssignment::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'supervisor_id' => $supervisor->id,
                    'branch_id' => $branch->id,
                    'effective_from' => '2026-01-01',
                ],
                [
                    'uuid' => $this->uuid('supervisor-assignment:'.$zoneCode),
                    'territory_id' => null,
                    'effective_to' => null,
                    'created_by' => $manager->id,
                ]
            );
        }

        return [$branches, $managers, $supervisors];
    }

    private function seedSalesTeam(
        Tenant $tenant,
        array $roles,
        array $branches,
        array $managers,
        array $supervisors
    ): array {
        $salesmenByZone = [];
        $routeBySalesman = [];
        $routesByZone = [];
        $devices = [];
        $personIndex = 100;

        foreach (self::ZONES as $zoneCode => $zone) {
            $branch = $branches[$zoneCode];
            $routesByZone[$zoneCode] = [];

            for ($routeNo = 1; $routeNo <= 2; $routeNo++) {
                $route = SalesRoute::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => 'SH-'.$zoneCode.'-R'.$routeNo],
                    [
                        'uuid' => $this->uuid('route:'.$zoneCode.':'.$routeNo),
                        'branch_id' => $branch->id,
                        'territory_id' => null,
                        'name' => $zone['name'].' Route '.$routeNo,
                        'weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                        'description' => 'Shahab demo route covering assigned customers across '.$zone['name'].'.',
                        'is_active' => true,
                    ]
                );
                $routesByZone[$zoneCode][] = $route;
            }

            for ($index = 0; $index < 13; $index++) {
                $name = $this->personName($personIndex++);
                $user = $this->upsertUser(
                    $tenant,
                    $branch,
                    $name,
                    strtolower($zoneCode).sprintf('%02d', $index + 1).'@shahab.com',
                    $personIndex + 1000,
                    'salesman',
                    $roles['salesman']
                );

                $salesman = Salesman::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                    [
                        'uuid' => $this->uuid('salesman:'.$zoneCode.':'.$index),
                        'employee_code' => sprintf('SH-%s-S%02d', $zoneCode, $index + 1),
                        'first_name' => Str::beforeLast($name, ' '),
                        'last_name' => Str::afterLast($name, ' '),
                        'is_active' => true,
                    ]
                );

                $route = $index >= 11
                    ? $routesByZone[$zoneCode][$index - 11]
                    : null;

                SalesmanAssignment::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'salesman_id' => $salesman->id,
                        'effective_from' => '2026-01-01',
                    ],
                    [
                        'uuid' => $this->uuid('salesman-assignment:'.$zoneCode.':'.$index),
                        'branch_id' => $branch->id,
                        'territory_id' => null,
                        'route_id' => $route?->id,
                        'supervisor_id' => $supervisors[$zoneCode]->id,
                        'effective_to' => null,
                        'created_by' => $managers[$zoneCode]->id,
                    ]
                );

                $device = Device::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'installation_uuid' => 'shahab-seed-'.$zoneCode.'-'.$index,
                    ],
                    [
                        'uuid' => $this->uuid('device:'.$zoneCode.':'.$index),
                        'user_id' => $user->id,
                        'salesman_id' => $salesman->id,
                        'device_uuid' => 'shahab-seed-device-'.$zoneCode.'-'.$index,
                        'device_model' => 'Historical demo device',
                        'manufacturer' => 'FieldPulse Seeder',
                        'platform' => 'android',
                        'os_version' => 'demo',
                        'android_version' => 'demo',
                        'app_version' => '1.0.0',
                        'is_active' => false,
                        'registered_at' => now()->subDays(14),
                        'last_seen_at' => now()->subDays(8),
                        'revoked_at' => now()->subDays(7),
                        'revoked_by' => $managers[$zoneCode]->id,
                        'revocation_reason' => 'Inactive historical device used only by Shahab demo seed data.',
                    ]
                );

                $salesmenByZone[$zoneCode][] = $salesman;
                $devices[$salesman->id] = $device;

                if ($route) {
                    $routeBySalesman[$salesman->id] = $route;
                }
            }
        }

        return [$salesmenByZone, $routeBySalesman, $routesByZone, $devices];
    }

    private function seedCatalog(Tenant $tenant): array
    {
        $priceList = PriceList::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'SHAHAB-RETAIL'],
            [
                'uuid' => $this->uuid('price-list:retail'),
                'name' => 'Shahab Kabul Retail',
                'currency' => 'AFN',
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'is_active' => true,
            ]
        );

        $products = [];

        foreach (self::PRODUCTS as [$sku, $name, $unit, $price]) {
            $product = Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $sku],
                [
                    'uuid' => $this->uuid('product:'.$sku),
                    'barcode' => null,
                    'name' => $name,
                    'description' => 'Shahab demo FMCG product.',
                    'unit' => $unit,
                    'base_price' => $price,
                    'currency' => 'AFN',
                    'is_active' => true,
                ]
            );

            PriceListItem::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'price_list_id' => $priceList->id,
                    'product_id' => $product->id,
                    'min_quantity' => 1,
                ],
                [
                    'uuid' => $this->uuid('price-list-item:'.$sku),
                    'price' => $price,
                ]
            );

            $products[] = $product;
        }

        return [$priceList, $products];
    }

    private function seedDistrictsAndCustomers(
        Tenant $tenant,
        array $branches,
        array $managers,
        array $salesmenByZone,
        array $routeBySalesman,
        array $routesByZone,
        PriceList $priceList,
        array $districtGeofences
    ): array {
        $customersBySalesman = [];
        $customerModels = [];
        $zoneCustomerIndex = array_fill_keys(array_keys(self::ZONES), 0);
        $routeSequence = [];

        foreach ($routesByZone as $routes) {
            foreach ($routes as $route) {
                DB::table('route_customers')->where('route_id', $route->id)->delete();
                $routeSequence[$route->id] = 0;
            }
        }

        $customerCounter = 1;

        foreach (self::DISTRICTS as $districtNo => $district) {
            $zoneCode = $this->zoneForDistrict($districtNo);
            $branch = $branches[$zoneCode];

            $territory = Territory::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => sprintf('KBL-D%02d', $districtNo)],
                [
                    'uuid' => $this->uuid('district:'.$districtNo),
                    'branch_id' => $branch->id,
                    'name' => 'Kabul District '.$districtNo,
                    'description' => $district['area'].' - Shahab demo territory using the public Kabul district boundary layer.',
                    'polygon' => $districtGeofences[$districtNo],
                    'is_active' => true,
                ]
            );

            for ($sequence = 1; $sequence <= 200; $sequence++) {
                $zoneIndex = $zoneCustomerIndex[$zoneCode]++;
                $salesman = $salesmenByZone[$zoneCode][
                    $zoneIndex % count($salesmenByZone[$zoneCode])
                ];
                $contact = $this->personName($customerCounter + 5000);
                $storeType = $this->storeType($customerCounter);
                [$latitude, $longitude] = $this->customerPointInsideGeometry(
                    $districtGeofences[$districtNo],
                    $sequence,
                    $district['lat'],
                    $district['lng']
                );
                $code = sprintf('SH-KBL-D%02d-%03d', $districtNo, $sequence);

                $customer = Customer::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $code],
                    [
                        'uuid' => $this->uuid('customer:'.$code),
                        'branch_id' => $branch->id,
                        'territory_id' => $territory->id,
                        'assigned_salesman_id' => $salesman->id,
                        'price_list_id' => $priceList->id,
                        'name' => $contact.' '.$storeType,
                        'contact_person' => $contact,
                        // Deliberately synthetic Afghan-format demo number; never a scraped/private contact.
                        'phone' => $this->demoPhone($customerCounter + 100),
                        'alternate_phone' => null,
                        'email' => null,
                        'address' => sprintf(
                            'District %d, %s, Kabul, Afghanistan',
                            $districtNo,
                            $district['area']
                        ),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'geofence_radius_meters' => 100,
                        'offline_uuid' => $this->uuid('offline-customer:'.$code),
                        'created_by' => $managers[$zoneCode]->id,
                        'is_active' => true,
                    ]
                );

                $customersBySalesman[$salesman->id][] = $customer->id;
                $customerModels[$customer->id] = $customer;

                if (isset($routeBySalesman[$salesman->id])) {
                    $route = $routeBySalesman[$salesman->id];
                    $routeSequence[$route->id]++;

                    DB::table('route_customers')->updateOrInsert(
                        [
                            'route_id' => $route->id,
                            'customer_id' => $customer->id,
                        ],
                        [
                            'uuid' => $this->uuid(
                                'route-customer:'.$route->id.':'.$customer->id
                            ),
                            'tenant_id' => $tenant->id,
                            'sequence_number' => $routeSequence[$route->id],
                            'planned_visit_minutes' => 10,
                            'notes' => 'Shahab demo assigned route customer.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                $customerCounter++;
            }
        }

        return [$customersBySalesman, $customerModels];
    }

    private function seedWeeklyVisits(
        Tenant $tenant,
        array $customersBySalesman,
        array $customerModels,
        array $routeBySalesman,
        array $devices
    ): void {
        $weekStart = now('Asia/Kabul')->startOfWeek()->subWeek()->startOfDay();
        $outcomes = [
            'order_placed',
            'collection_made',
            'no_stock_needed',
            'customer_unavailable',
        ];

        foreach ($customersBySalesman as $salesmanId => $customerIds) {
            $salesman = Salesman::findOrFail($salesmanId);
            $route = $routeBySalesman[$salesmanId] ?? null;
            $device = $devices[$salesmanId];
            $rows = [];

            for ($visitNo = 0; $visitNo < 100; $visitNo++) {
                $customer = $customerModels[
                    $customerIds[$visitNo % count($customerIds)]
                ];
                $dayOffset = $visitNo % 6;
                $slot = intdiv($visitNo, 6);
                $checkedIn = $weekStart
                    ->copy()
                    ->addDays($dayOffset)
                    ->setTime(8, 0)
                    ->addMinutes($slot * 25);
                $checkedOut = $checkedIn->copy()->addMinutes(12 + ($visitNo % 9));

                $rows[] = [
                    'uuid' => $this->uuid(
                        'visit:'.$salesmanId.':'.$weekStart->toDateString().':'.$visitNo
                    ),
                    'tenant_id' => $tenant->id,
                    'user_id' => $salesman->user_id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'customer_id' => $customer->id,
                    'route_id' => $route?->id,
                    'work_session_id' => null,
                    'is_planned' => $route !== null,
                    'status' => 'completed',
                    'outcome' => $outcomes[$visitNo % count($outcomes)],
                    'notes' => 'SHAHAB-DEMO weekly visit '.$visitNo,
                    'checked_in_at' => $checkedIn,
                    'checked_out_at' => $checkedOut,
                    'checkin_latitude' => $customer->latitude,
                    'checkin_longitude' => $customer->longitude,
                    'checkin_accuracy' => 8 + ($visitNo % 6),
                    'checkout_latitude' => $customer->latitude,
                    'checkout_longitude' => $customer->longitude,
                    'checkout_accuracy' => 9 + ($visitNo % 6),
                    'checkin_distance_meters' => 5 + ($visitNo % 20),
                    'checkout_distance_meters' => 6 + ($visitNo % 20),
                    'checkin_within_geofence' => true,
                    'checkout_within_geofence' => true,
                    'duration_seconds' => $checkedIn->diffInSeconds($checkedOut),
                    'created_at' => $checkedIn,
                    'updated_at' => $checkedOut,
                ];
            }

            DB::table('customer_visits')->upsert(
                $rows,
                ['uuid'],
                [
                    'customer_id',
                    'route_id',
                    'is_planned',
                    'status',
                    'outcome',
                    'notes',
                    'checked_in_at',
                    'checked_out_at',
                    'checkin_latitude',
                    'checkin_longitude',
                    'checkout_latitude',
                    'checkout_longitude',
                    'duration_seconds',
                    'updated_at',
                ]
            );
        }
    }

    private function seedOwner(Tenant $tenant, $role): User
    {
        $user = User::where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->first();

        $user ??= new User([
            'uuid' => $this->uuid('user:owner'),
            'tenant_id' => $tenant->id,
        ]);

        $user->fill([
            'branch_id' => null,
            'name' => 'Shahab Demo Owner',
            'email' => 'owner@shahab.com',
            'phone' => $this->demoPhone(1),
            'password' => Hash::make(self::PASSWORD),
            'role' => 'owner',
            'is_active' => true,
        ])->save();

        $user->syncPrimaryRole($role);

        return $user;
    }

    private function upsertUser(
        Tenant $tenant,
        Branch $branch,
        string $name,
        string $email,
        int $phoneIndex,
        string $roleSlug,
        $role
    ): User {
        $user = User::where('tenant_id', $tenant->id)
            ->where('name', $name)
            ->where('role', $roleSlug)
            ->first();

        $user ??= new User([
            'uuid' => $this->uuid('user:'.$roleSlug.':'.$email),
            'tenant_id' => $tenant->id,
        ]);

        $user->fill([
            'branch_id' => $branch->id,
            'name' => $name,
            'email' => $email,
            'phone' => $this->demoPhone($phoneIndex),
            'password' => Hash::make(self::PASSWORD),
            'role' => $roleSlug,
            'is_active' => true,
        ])->save();

        $user->syncPrimaryRole($role);

        return $user;
    }

    private function zoneForDistrict(int $district): string
    {
        foreach (self::ZONES as $zoneCode => $zone) {
            if (in_array($district, $zone['districts'], true)) {
                return $zoneCode;
            }
        }

        throw new RuntimeException('No Shahab zone configured for Kabul district '.$district);
    }

    private function loadDistrictGeofences(): array
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->retry(2, 300)
            ->get(self::KABUL_DISTRICT_GEOJSON_URL);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Unable to download the Kabul municipal district GeoJSON boundary layer.'
            );
        }

        $features = collect($response->json('features', []));
        $geofences = [];

        foreach ($features as $feature) {
            $district = (int) data_get($feature, 'properties.DistrictName');
            $geometry = data_get($feature, 'geometry');

            if ($district < 1 || $district > 22 || ! is_array($geometry)) {
                continue;
            }

            if (! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                continue;
            }

            $geofences[$district] = $geometry;
        }

        ksort($geofences);

        if (array_keys($geofences) !== range(1, 22)) {
            throw new RuntimeException(
                'The Kabul district boundary source did not return all 22 municipal districts.'
            );
        }

        return $geofences;
    }

    private function zoneGeometry(array $districts, array $districtGeofences): array
    {
        $polygons = [];

        foreach ($districts as $district) {
            $geometry = $districtGeofences[$district];

            if ($geometry['type'] === 'Polygon') {
                $polygons[] = $geometry['coordinates'];

                continue;
            }

            foreach ($geometry['coordinates'] as $polygon) {
                $polygons[] = $polygon;
            }
        }

        return [
            'type' => 'MultiPolygon',
            'coordinates' => $polygons,
        ];
    }

    private function customerPointInsideGeometry(
        array $geometry,
        int $index,
        float $fallbackLat,
        float $fallbackLng
    ): array {
        $polygons = $geometry['type'] === 'Polygon'
            ? [$geometry['coordinates']]
            : $geometry['coordinates'];

        $polygon = $polygons[$index % count($polygons)];
        $outerRing = $polygon[0] ?? [];

        if (count($outerRing) < 4) {
            return [$fallbackLat, $fallbackLng];
        }

        $longitudes = array_column($outerRing, 0);
        $latitudes = array_column($outerRing, 1);
        $minLng = min($longitudes);
        $maxLng = max($longitudes);
        $minLat = min($latitudes);
        $maxLat = max($latitudes);

        for ($attempt = 0; $attempt < 300; $attempt++) {
            $seed = $index * 997 + $attempt * 313;
            $lngRatio = (($seed * 37) % 10000) / 10000;
            $latRatio = (($seed * 91 + 17) % 10000) / 10000;
            $lng = $minLng + (($maxLng - $minLng) * $lngRatio);
            $lat = $minLat + (($maxLat - $minLat) * $latRatio);

            if ($this->pointInRing($lng, $lat, $outerRing)) {
                $insideHole = false;

                foreach (array_slice($polygon, 1) as $hole) {
                    if ($this->pointInRing($lng, $lat, $hole)) {
                        $insideHole = true;
                        break;
                    }
                }

                if (! $insideHole) {
                    return [round($lat, 7), round($lng, 7)];
                }
            }
        }

        return [$fallbackLat, $fallbackLng];
    }

    private function pointInRing(float $lng, float $lat, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < (($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1.0e-12)) + $xi);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function personName(int $index): string
    {
        $first = [
            'Ahmad', 'Mohammad', 'Abdul Rahman', 'Abdul Wahid', 'Abdul Qadir',
            'Farid', 'Hamidullah', 'Naim', 'Wali Mohammad', 'Sayed Jamal',
            'Noor Agha', 'Hekmatullah', 'Zabihullah', 'Najibullah', 'Habibullah',
            'Samiullah', 'Rafiullah', 'Fazal Ahmad', 'Ajmal', 'Zubair',
            'Feroz', 'Sohail', 'Nasir Ahmad', 'Jawad', 'Matiullah',
            'Obaidullah', 'Ehsanullah', 'Aziz Ahmad', 'Bashir Ahmad', 'Latif',
            'Haroon', 'Saber', 'Shafiq', 'Waheed', 'Zahir',
            'Faisal', 'Mustafa', 'Jamaluddin', 'Shamsuddin', 'Aminullah',
            'Khalid', 'Aref', 'Yasin', 'Ismail', 'Ibrahim',
            'Rashid', 'Nabi', 'Ghulam Nabi', 'Shah Mahmood', 'Nematullah',
        ];

        $family = [
            'Ahmadi', 'Rahimi', 'Safi', 'Azizi', 'Noori', 'Hashimi',
            'Mohammadi', 'Stanikzai', 'Popal', 'Wardak', 'Hotak', 'Shinwari',
            'Kohistani', 'Andar', 'Sultani', 'Rasooli', 'Karimi', 'Nazari',
            'Haidari', 'Akbari', 'Omid', 'Naderi', 'Samadi', 'Jalali',
            'Mansoori', 'Yousufi', 'Arifi', 'Rahmani', 'Siddiqi', 'Hakimi',
        ];

        $firstName = $first[$index % count($first)];
        $secondName = $first[(intdiv($index, count($first)) + 7) % count($first)];
        $familyName = $family[intdiv($index, count($first) * count($first)) % count($family)];

        return $firstName.' '.$secondName.' '.$familyName;
    }

    private function demoPhone(int $index): string
    {
        // Afghanistan-format placeholder generated solely for demo data.
        return sprintf('+93 70 000 %04d', $index % 10000);
    }

    private function storeType(int $index): string
    {
        $types = [
            'General Store',
            'Supermarket',
            'Mini Market',
            'Grocery',
            'Wholesale Shop',
            'Family Market',
            'Trading Store',
            'Retail Shop',
        ];

        return $types[$index % count($types)];
    }

    private function uuid(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'fieldpulse:shahab-demo:'.$key)->toString();
    }
}
