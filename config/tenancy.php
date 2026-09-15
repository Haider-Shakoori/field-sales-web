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
