<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Device-binding domain errors with stable machine-readable codes.
 *
 * Mobile clients map `error.code` (DEVICE_REQUIRED, DEVICE_NOT_FOUND,
 * DEVICE_MISMATCH, DEVICE_REVOKED) instead of matching English message text.
 */
class DeviceException extends HttpException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $statusCode = 403,
    ) {
        parent::__construct($statusCode, $message);
    }

    public static function required(): self
    {
        return new self('DEVICE_REQUIRED', 'Device identification is required.', 403);
    }

    public static function notFound(): self
    {
        return new self('DEVICE_NOT_FOUND', 'Device not found.', 404);
    }

    public static function mismatch(): self
    {
        return new self('DEVICE_MISMATCH', 'Device cannot be validated.', 403);
    }

    public static function revoked(string $message = 'This device has been revoked. Please reinstall the app.'): self
    {
        return new self('DEVICE_REVOKED', $message, 403);
    }
}
