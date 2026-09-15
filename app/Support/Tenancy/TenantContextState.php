<?php

namespace App\Support\Tenancy;

enum TenantContextState: string
{
    case Uninitialized = 'uninitialized';
    case Tenant = 'tenant';
    case Platform = 'platform';
}
