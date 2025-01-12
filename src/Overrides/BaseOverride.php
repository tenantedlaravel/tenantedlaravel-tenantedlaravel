<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

abstract class BaseOverride implements ServiceOverride
{
    public readonly string $service;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Create a new instance of the service override
     *
     * @param string               $service
     * @param array<string, mixed> $config
     */
    public function __construct(string $service, array $config)
    {
        $this->config  = $config;
        $this->service = $service;
    }

    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        // I'm intentionally empty
    }

    /**
     * Clean up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when the current tenant is unset, either to be replaced
     * by another tenant, or none.
     *
     * It will be called before {@see self::setup()}, but only if the previous
     * tenant was not null.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        // I'm intentionally empty
    }

}
