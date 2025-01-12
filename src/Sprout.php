<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ServiceOverrideManager;
use Sprout\Managers\TenancyManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;

/**
 * Sprout
 *
 * This is the core Sprout class.
 *
 * @package Core
 */
final class Sprout
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private Application $app;

    /**
     * @var array<int, \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant>>
     */
    private array $tenancies = [];

    /**
     * @var bool
     */
    private bool $withinContext = false;

    /**
     * @var \Sprout\Support\SettingsRepository
     */
    private SettingsRepository $settings;

    /**
     * Create a new instance
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Support\SettingsRepository           $settings
     */
    public function __construct(Application $app, SettingsRepository $settings)
    {
        $this->app      = $app;
        $this->settings = $settings;
    }

    /**
     * Get a config item from the sprout config
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->app->make('config')->get('sprout.' . $key, $default);
    }

    /**
     * Get a config item from the sprout config
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }

    /**
     * Get the Sprout settings repository
     *
     * @return \Sprout\Support\SettingsRepository
     */
    public function settings(): SettingsRepository
    {
        return $this->settings;
    }

    /**
     * Set the current tenancy
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return void
     */
    public function setCurrentTenancy(Tenancy $tenancy): void
    {
        if ($this->getCurrentTenancy() !== $tenancy) {
            $this->tenancies[] = $tenancy;
        }

        $this->markAsInContext();
    }

    /**
     * Check if there is a current tenancy
     *
     * @return bool
     */
    public function hasCurrentTenancy(): bool
    {
        return count($this->tenancies) > 0;
    }

    /**
     * Get the current tenancy
     *
     * @return \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant>|null
     */
    public function getCurrentTenancy(): ?Tenancy
    {
        if ($this->hasCurrentTenancy()) {
            return $this->tenancies[count($this->tenancies) - 1];
        }

        return null;
    }

    /**
     * Get all the current tenancies
     *
     * @return \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant>[]
     */
    public function getAllCurrentTenancies(): array
    {
        return $this->tenancies;
    }

    /**
     * Get the identity resolver manager
     *
     * @return \Sprout\Managers\IdentityResolverManager
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function resolvers(): IdentityResolverManager
    {
        return $this->app->make(IdentityResolverManager::class);
    }

    /**
     * Get the tenant providers manager
     *
     * @return \Sprout\Managers\TenantProviderManager
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function providers(): TenantProviderManager
    {
        return $this->app->make(TenantProviderManager::class);
    }

    /**
     * Get the tenancy manager
     *
     * @return \Sprout\Managers\TenancyManager
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function tenancies(): TenancyManager
    {
        return $this->app->make(TenancyManager::class);
    }

    /**
     * Get the service override manager
     *
     * @return \Sprout\Managers\ServiceOverrideManager
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function overrides(): ServiceOverrideManager
    {
        return $this->app->make(ServiceOverrideManager::class);
    }

    /**
     * Check if a resolution hook is enabled
     *
     * @param \Sprout\Support\ResolutionHook $hook
     *
     * @return bool
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function supportsHook(ResolutionHook $hook): bool
    {
        /** @var array<ResolutionHook> $enabledHooks */
        $enabledHooks = $this->config('core.hooks', []);

        return in_array($hook, $enabledHooks, true);
    }

    /**
     * Flag the current request as being within multitenanted context
     *
     * @return static
     */
    public function markAsInContext(): self
    {
        $this->withinContext = true;

        return $this;
    }

    /**
     * Flag the current request as being outside multitenanted context
     *
     * @return static
     */
    public function markAsOutsideContext(): self
    {
        $this->withinContext = false;

        return $this;
    }

    /**
     * Check if the current request is within multitenanted context
     *
     * @return bool
     */
    public function withinContext(): bool
    {
        return $this->withinContext;
    }

    /**
     * Generate a route for a tenant
     *
     * This method will proxy a call to {@see \Sprout\Contracts\IdentityResolver::route()}
     * to generate a URL for a tenanted route.
     *
     * If no tenancy name is provided, this method will use the current tenancy
     * or the default one.
     *
     * If no resolver name is provided, this method will use the resolver
     * currently linked with the tenancy, or the default one.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param string                   $name
     * @param \Sprout\Contracts\Tenant $tenant
     * @param string|null              $resolver
     * @param string|null              $tenancy
     * @param array<string, mixed>     $parameters
     * @param bool                     $absolute
     *
     * @phpstan-param TenantClass      $tenant
     *
     * @return string
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function route(string $name, Tenant $tenant, ?string $resolver = null, ?string $tenancy = null, array $parameters = [], bool $absolute = true): string
    {
        if ($tenancy === null) {
            $tenancyInstance = $this->getCurrentTenancy() ?? $this->tenancies()->get();
        } else {
            $tenancyInstance = $this->tenancies()->get($tenancy);
        }

        /** @var \Sprout\Contracts\Tenancy<TenantClass> $tenancyInstance */

        if ($resolver === null) {
            $resolverInstance = $tenancyInstance->resolver() ?? $this->resolvers()->get();
        } else {
            $resolverInstance = $this->resolvers()->get($resolver);
        }

        /** @var \Sprout\Contracts\IdentityResolver $resolverInstance */

        return $resolverInstance->route($name, $tenancyInstance, $tenant, $parameters, $absolute);
    }
}
