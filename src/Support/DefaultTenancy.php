<?php
/** @noinspection PhpUnnecessaryStaticReferenceInspection */
declare(strict_types=1);

namespace Sprout\Support;

use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantProvider;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Events\TenantIdentified;
use Sprout\Events\TenantLoaded;

/**
 * Default Tenancy
 *
 * This is a default implementation of the {@see \Sprout\Contracts\Tenancy}
 * interface.
 *
 * @package Core
 *
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @implements \Sprout\Contracts\Tenancy<TenantClass>
 */
final class DefaultTenancy implements Tenancy
{
    private string $name;

    /**
     * @var \Sprout\Contracts\TenantProvider<TenantClass>
     */
    private TenantProvider $provider;

    /**
     * @var \Sprout\Contracts\IdentityResolver|null
     */
    private ?IdentityResolver $resolver = null;

    /**
     * @var \Sprout\Contracts\Tenant|null
     *
     * @phpstan-var TenantClass|null
     */
    private ?Tenant $tenant = null;

    /**
     * @var list<string>
     */
    private array $options = [];

    /**
     * @var array<string, array<array-key, mixed>>
     */
    private array $optionConfig = [];

    /**
     * @var \Sprout\Support\ResolutionHook|null
     */
    private ?ResolutionHook $hook = null;

    /**
     * Create a new instance
     *
     * @param string                                              $name
     * @param \Sprout\Contracts\TenantProvider<TenantClass>       $provider
     * @param list<string|array<string, array<array-key, mixed>>> $options
     */
    public function __construct(string $name, TenantProvider $provider, array $options)
    {
        $this->name     = $name;
        $this->provider = $provider;

        /** @var string|array<string, array<array-key, mixed>> $value */
        foreach ($options as $value) {
            if (is_array($value)) {
                $option                      = array_keys($value)[0];
                $this->options[]             = $option;
                $this->optionConfig[$option] = $value[$option];
            } else if (is_string($value)) {
                $this->options[] = $value;
            }
        }
    }

    /**
     * Get the registered name of the tenancy
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if there's a current tenant
     *
     * Checks to see if a current tenant is set.
     * Implementations should not attempt to load a tenant if one is not
     * present, but should perform a simple check for the present of a
     * tenant.
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Get the current tenant
     *
     * Gets the current set tenant if one is present.
     * Implementations may attempt to load a tenant if one isn't present, though
     * this is not required.
     *
     * @return \Sprout\Contracts\Tenant|null
     *
     * @phpstan-return TenantClass|null
     */
    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the tenants key
     *
     * Get the tenant key for the current tenant if there is one.
     *
     * @return int|string|null
     *
     * @see \Sprout\Contracts\Tenant::getTenantKey()
     */
    public function key(): int|string|null
    {
        return $this->tenant()?->getTenantKey();
    }

    /**
     * Get the tenants' identifier
     *
     * Get the tenant identifier for the current tenant if there is one.
     *
     * @return string|null
     *
     * @see \Sprout\Contracts\Tenant::getTenantIdentifier()
     */
    public function identifier(): ?string
    {
        return $this->tenant()?->getTenantIdentifier();
    }

    /**
     * Identity a tenant
     *
     * Retrieve and set the current tenant based on an identifier.
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function identify(string $identifier): bool
    {
        $tenant = $this->provider()->retrieveByIdentifier($identifier);

        if ($tenant === null) {
            $this->resolver = null;
            $this->hook     = null;

            return false;
        }

        $this->setTenant($tenant);

        event(new TenantIdentified($tenant, $this));

        return true;
    }

    /**
     * Load a tenant
     *
     * Retrieve and set the current tenant based on a key.
     *
     * @param int|string $key
     *
     * @return bool
     */
    public function load(int|string $key): bool
    {
        $tenant = $this->provider()->retrieveByKey($key);

        if ($tenant === null) {
            $this->resolver = null;
            $this->hook     = null;

            return false;
        }

        $this->setTenant($tenant);

        event(new TenantLoaded($tenant, $this));

        return true;
    }

    /**
     * Get the tenant provider
     *
     * Get the tenant provider used by this tenancy.
     *
     * @return \Sprout\Contracts\TenantProvider<TenantClass>
     */
    public function provider(): TenantProvider
    {
        return $this->provider;
    }

    /**
     * Set the identity resolved used
     *
     * @param \Sprout\Contracts\IdentityResolver $resolver
     *
     * @return static
     */
    public function resolvedVia(IdentityResolver $resolver): static
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Get the used identity resolver
     *
     * @return \Sprout\Contracts\IdentityResolver|null
     */
    public function resolver(): ?IdentityResolver
    {
        return $this->resolver;
    }

    /**
     * Check if the current tenant was resolved
     *
     * @return bool
     */
    public function wasResolved(): bool
    {
        return $this->check() && $this->resolver() !== null;
    }

    /**
     * Set the current tenant
     *
     * @param \Sprout\Contracts\Tenant|null $tenant
     *
     * @phpstan-param TenantClass|null      $tenant
     *
     * @return static
     */
    public function setTenant(?Tenant $tenant): static
    {
        $previousTenant = $this->tenant();

        if ($previousTenant !== $tenant) {
            $this->tenant = $tenant;

            event(new CurrentTenantChanged($this, $previousTenant, $tenant));
        }

        if ($tenant === null) {
            $this->resolver = null;
            $this->hook     = null;
        }

        return $this;
    }

    /**
     * Get all tenant options
     *
     * @return list<string>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Get a tenant option
     *
     * @param string $option
     *
     * @return bool
     */
    public function hasOption(string $option): bool
    {
        return in_array($option, $this->options(), true);
    }

    /**
     * Check if a tenancy has an option with config
     *
     * @param string $option
     *
     * @return bool
     */
    public function hasOptionConfig(string $option): bool
    {
        return isset($this->optionConfig[$option]);
    }

    /**
     * Add an option to the tenancy
     *
     * @param string $option
     *
     * @return static
     */
    public function addOption(string $option): static
    {
        if (! $this->hasOption($option)) {
            $this->options[] = $option;
        }

        return $this;
    }

    /**
     * Remove an option from the tenancy
     *
     * @param string $option
     *
     * @return static
     */
    public function removeOption(string $option): static
    {
        if ($this->hasOption($option)) {
            unset($this->options[array_search($option, $this->options(), true)]);
        }

        return $this;
    }

    /**
     * Get a tenancy options config
     *
     * @param string $option
     *
     * @return array<array-key, mixed>|null
     */
    public function optionConfig(string $option): ?array
    {
        if (! $this->hasOptionConfig($option)) {
            return null;
        }

        return $this->optionConfig[$option];
    }

    /**
     * Set the hook where the tenant was resolved
     *
     * @param \Sprout\Support\ResolutionHook $hook
     *
     * @return $this
     */
    public function resolvedAt(ResolutionHook $hook): static
    {
        $this->hook = $hook;

        return $this;
    }

    /**
     * Get the hook where the tenant was resolved
     *
     * @return \Sprout\Support\ResolutionHook|null
     */
    public function hook(): ?ResolutionHook
    {
        return $this->hook;
    }
}
