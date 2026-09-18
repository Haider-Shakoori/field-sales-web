<?php

namespace App\Tenancy;

use RuntimeException;

class TenantContextMissingException extends RuntimeException
{
    public function __construct(string $message = 'Tenant context has not been initialized.')
    {
        parent::__construct($message);
    }
}
