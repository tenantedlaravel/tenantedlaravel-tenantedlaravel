<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

final class ServiceOverrideException extends SproutException
{
    /**
     * Create an exception for when attempting to boot a non-bootable service override
     *
     * @param string $service
     *
     * @return self
     */
    public static function notBootable(string $service): self
    {
        return new self('The service override [' . $service . '] is not bootable'); // @codeCoverageIgnore
    }

    /**
     * Create an exception for when a service override has been set up, but isn't
     * enabled for the tenancy
     *
     * @param string $service
     * @param string $tenancy
     *
     * @return self
     */
    public static function setupButNotEnabled(string $service, string $tenancy): self
    {
        return new self('The service override [' . $service . '] has been set up for the tenancy [' . $tenancy . '] but it is not enabled for that tenancy'); // @codeCoverageIgnore
    }
}
