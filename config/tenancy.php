<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | Locales a tenant may select when configuring their company profile.
    |
    */
    'locales' => [
        'en' => 'English',
        'ps' => 'Pashto',
        'fa' => 'Dari',
    ],

    /*
    |--------------------------------------------------------------------------
    | Occupied Subscription Statuses
    |--------------------------------------------------------------------------
    |
    | Subscription statuses that grant access to a tenant's data.
    |
    */
    'active_subscription_statuses' => [
        'active',
        'trial',
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform-Only Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions that may only be assigned by a platform super admin. Company
    | custom roles cannot grant these, and they are hidden from the permission
    | matrix shown to company users.
    |
    */
    'platform_permissions' => [
        'tenants:manage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Permissions
    |--------------------------------------------------------------------------
    |
    | Permission toggles that require an explicit confirmation in the UI
    | before they are saved, due to their security impact.
    |
    */
    'sensitive_permissions' => [
        'users:manage',
        'roles:manage',
        'settings:manage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Roles
    |--------------------------------------------------------------------------
    |
    | The catalog of roles available for assignment to users. The first
    | matching "name" is used as the pivot key for model_has_roles.
    |
    */
    'roles' => [
        'owner' => 'Owner',
        'company_admin' => 'Company Admin',
        'sales_manager' => 'Sales Manager',
        'supervisor' => 'Supervisor',
        'salesman' => 'Salesman',
        'accountant' => 'Accountant',
        'warehouse_user' => 'Warehouse User',
        'auditor' => 'Auditor',
    ],

];
