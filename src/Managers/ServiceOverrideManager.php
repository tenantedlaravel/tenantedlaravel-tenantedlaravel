<?php
declare(strict_types=1);

namespace Sprout\Managers;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Events\ServiceOverrideBooted;
use Sprout\Events\ServiceOverrideRegistered;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\ServiceOverrideException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Sprout;
use Sprout\TenancyOptions;

/**
 * Service Override Manager
 *
 * This manager is responsible for managing service overrides, from calling
 * register and booting them, to integrating them into tenancy lifecycle
 * events.
 *
 * @package Overrides
 */
final class ServiceOverrideManager
{
    /**
     * The Laravel application
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected Application $app;

    /**
     * @var array<string, \Sprout\Contracts\ServiceOverride>
     */
    protected array $overrides = [];

    /**
     * @var array<string, class-string<\Sprout\Contracts\ServiceOverride>>
     */
    protected array $overrideClasses = [];

    /**
     * @var list<string>
     */
    protected array $bootableOverrides = [];

    /**
     * @var bool
     */
    protected bool $overridesBooted = false;

    /**
     * @var array<string, array<class-string<\Sprout\Contracts\ServiceOverride>, string>>
     */
    protected array $setupOverrides = [];

    /**
     * Create a new factory instance
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the config for a service
     *
     * @param string $service
     *
     * @return array<string, mixed>|null
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getServiceConfig(string $service): ?array
    {
        /** @var array<string, mixed>|null $config */
        $config = $this->app->make('config')->get('sprout.overrides.' . $service);

        return $config;
    }

    /**
     * Check if a service has an override
     *
     * @param string $service
     *
     * @return bool
     */
    public function hasOverride(string $service): bool
    {
        return isset($this->overrides[$service]);
    }

    /**
     * Check if a services' override has been booted
     *
     * @param string $service
     *
     * @return bool
     */
    public function hasOverrideBooted(string $service): bool
    {
        return $this->haveOverridesBooted() && $this->isOverrideBootable($service);
    }

    /**
     * Check if a service override has been set up for a tenancy
     *
     * @param string $service
     * @param \Sprout\Contracts\Tenancy<*>|null $tenancy
     *
     * @return bool
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\TenancyMissingException
     */
    public function hasOverrideBeenSetUp(string $service, ?Tenancy $tenancy = null): bool
    {
        $tenancy ??= $this->app->make(Sprout::class)->getCurrentTenancy();

        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        return in_array($service, $this->getSetupOverrides($tenancy), true);
    }

    /**
     * Check if a tenancy has been set up
     *
     * @param \Sprout\Contracts\Tenancy<*>|null $tenancy
     *
     * @return bool
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\TenancyMissingException
     */
    public function hasTenancyBeenSetup(?Tenancy $tenancy = null): bool
    {
        $tenancy ??= $this->app->make(Sprout::class)->getCurrentTenancy();

        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        return array_key_exists($tenancy->getName(), $this->setupOverrides);
    }

    /**
     * Check if a services' override is bootable
     *
     * @param string $service
     *
     * @return bool
     */
    public function isOverrideBootable(string $service): bool
    {
        return in_array($service, $this->bootableOverrides, true);
    }

    /**
     * Check if the service override boot stage has passed
     *
     * @return bool
     */
    public function haveOverridesBooted(): bool
    {
        return $this->overridesBooted;
    }

    /**
     * Get the driver class for a service
     *
     * @param string $service
     *
     * @return class-string<\Sprout\Contracts\ServiceOverride>|null
     */
    public function getOverrideClass(string $service): ?string
    {
        return $this->overrideClasses[$service] ?? null;
    }

    /**
     * Get all services whose overrides have been set up for a tenancy
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return array<class-string<\Sprout\Contracts\ServiceOverride>, string>
     */
    public function getSetupOverrides(Tenancy $tenancy): array
    {
        return $this->setupOverrides[$tenancy->getName()] ?? [];
    }

    /**
     * Get an override for a service
     *
     * @param string $service
     *
     * @return \Sprout\Contracts\ServiceOverride|null
     */
    public function get(string $service): ?ServiceOverride
    {
        if ($this->hasOverride($service)) {
            return $this->overrides[$service];
        }

        return null;
    }

    /**
     * Register all the configured service overrides
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\ServiceOverrideException
     */
    public function registerOverrides(): void
    {
        /** @var array<string, array<string, mixed>> $services */
        $services = $this->app->make('config')->get('sprout.overrides', []);

        foreach ($services as $service => $config) {
            $this->register($service);
        }
    }

    /**
     * Boot all the registered service overrides
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\ServiceOverrideException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function bootOverrides(): void
    {
        foreach ($this->bootableOverrides as $service) {
            $this->boot($service); // @codeCoverageIgnore
        }

        $this->overridesBooted = true;
    }

    /**
     * Setup all the registered and enabled service overrides
     *
     * @template TenantClass of Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return void
     */
    public function setupOverrides(Tenancy $tenancy, Tenant $tenant): void
    {
        // Get the overrides enabled for this tenancy
        $enabled    = TenancyOptions::enabledOverrides($tenancy) ?? [];
        $allEnabled = TenancyOptions::shouldEnableAllOverrides($tenancy);

        $this->setupOverrides[$tenancy->getName()] = [];

        // Loop through all registered overrides
        foreach ($this->overrides as $service => $override) {
            // If the override is enabled
            if ($allEnabled || in_array($service, $enabled, true)) {
                // Perform the setup action
                $override->setup($tenancy, $tenant);
                // Keep track of the fact the override was set up
                $this->setupOverrides[$tenancy->getName()][$this->overrideClasses[$service]] = $service;
            }
        }
    }

    /**
     * Clean-up all registered and setup service overrides
     *
     * @template TenantClass of Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\ServiceOverrideException
     */
    public function cleanupOverrides(Tenancy $tenancy, Tenant $tenant): void
    {
        // Get the overrides enabled for this tenancy
        $enabled        = TenancyOptions::enabledOverrides($tenancy) ?? [];
        $allEnabled     = TenancyOptions::shouldEnableAllOverrides($tenancy);
        $setupOverrides = $this->getSetupOverrides($tenancy);

        // Loop through all registered overrides
        foreach ($setupOverrides as $driver => $service) {
            // If the override is enabled
            if ($allEnabled || in_array($service, $enabled, true)) {
                // Perform the setup action
                $this->overrides[$service]->cleanup($tenancy, $tenant);

                unset($this->setupOverrides[$tenancy->getName()][$driver]);
            } else {
                throw ServiceOverrideException::setupButNotEnabled($service, $tenancy->getName()); // @codeCoverageIgnore
            }
        }

        unset($this->setupOverrides[$tenancy->getName()]);
    }

    /**
     * Register a service override
     *
     * @param string $service
     *
     * @return static
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\ServiceOverrideException
     */
    protected function register(string $service): self
    {
        // If the override already exists, we'll error out, because it should,
        // we'd just load the same config again
        if ($this->hasOverride($service)) {
            return $this; // @codeCoverageIgnore
        }

        // Get the config for this service override
        $config = $this->getServiceConfig($service);

        // If there isn't config, there's an issue
        if ($config === null) {
            throw MisconfigurationException::notFound('service override', $service);
        }

        // If there is config, but it's missing a driver, that's also an issue
        if (! isset($config['driver'])) {
            throw MisconfigurationException::missingConfig('driver', 'service override', $service);
        }

        /** @var array{driver:string} $config */

        // If there is a driver, but it doesn't implement the correct interface,
        // that's also an issue
        if (! is_subclass_of($config['driver'], ServiceOverride::class)) {
            throw MisconfigurationException::invalidConfig('driver', 'service override', $service);
        }

        /** @var class-string<\Sprout\Contracts\ServiceOverride> $driver */
        $driver = $config['driver'];

        // Create a new instance of the service override with the service name
        // and config, as we know the constructor signature
        $override = $this->app->make($driver, compact('service', 'config'));

        // Store the override
        $this->overrides[$service] = $override;

        // And map it to its driver, rather than the final class
        $this->overrideClasses[$service] = $driver;

        ServiceOverrideRegistered::dispatch($service, $override);

        // If the service override is bootable, we'll keep track of that too
        if ($override instanceof BootableServiceOverride) {
            $this->bootableOverrides[] = $service;

            // If the overrides have already booted, we'll boot it
            if ($this->haveOverridesBooted()) {
                $this->boot($service);
            }
        }

        return $this;
    }

    /**
     * Boot a service override
     *
     * @param string $service
     *
     * @return static
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\ServiceOverrideException
     */
    protected function boot(string $service): self
    {
        // If the override doesn't exist, that's an issue
        if (! $this->hasOverride($service)) {
            // Realistically, we should never hit this exception, unless something
            // has gone horribly wrong
            throw MisconfigurationException::notFound('service override', $service); // @codeCoverageIgnore
        }

        $override = $this->overrides[$service];

        // If the override exists, but isn't bootable, that's also an issue
        if (! ($override instanceof BootableServiceOverride)) {
            // Again, this should never be reached
            throw ServiceOverrideException::notBootable($service); // @codeCoverageIgnore
        }

        $override->boot($this->app, $this->app->make(Sprout::class));

        ServiceOverrideBooted::dispatch($service, $override);

        return $this;
    }
}
