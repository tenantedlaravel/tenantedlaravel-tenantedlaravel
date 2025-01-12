<?php

namespace Sprout\Contracts;

use Sprout\Support\ResolutionHook;

/**
 * Tenancy Contract
 *
 * This contract represents a tenancy, an object responsible for managing the
 * state of the current tenancy, i.e. the current {@see \Sprout\Contracts\Tenant}.
 *
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @package Core
 */
interface Tenancy
{
    /**
     * Get the registered name of the tenancy
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if there's a current tenant
     *
     * Checks to see if a current tenant is set.
     * Implementations should not attempt to load a tenant if one is not
     * present, but should perform a simple check for the present of a
     * tenant.
     *
     * @return bool
     *
     * @phpstan-assert-if-true \Sprout\Contracts\Tenant $this->tenant()
     * @phpstan-assert-if-true string $this->identifier()
     * @phpstan-assert-if-true string|int $this->key()
     * @phpstan-assert-if-false null $this->tenant()
     * @phpstan-assert-if-false null $this->identifier()
     * @phpstan-assert-if-false null $this->key()
     */
    public function check(): bool;

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
    public function tenant(): ?Tenant;

    /**
     * Get the tenants key
     *
     * Get the tenant key for the current tenant if there is one.
     *
     * @return int|string|null
     * @see \Sprout\Contracts\Tenant::getTenantKey()
     *
     */
    public function key(): int|string|null;

    /**
     * Get the tenants' identifier
     *
     * Get the tenant identifier for the current tenant if there is one.
     *
     * @return string|null
     * @see \Sprout\Contracts\Tenant::getTenantIdentifier()
     *
     */
    public function identifier(): ?string;

    /**
     * Identity a tenant
     *
     * Retrieve and set the current tenant based on an identifier.
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function identify(string $identifier): bool;

    /**
     * Load a tenant
     *
     * Retrieve and set the current tenant based on a key.
     *
     * @param int|string $key
     *
     * @return bool
     */
    public function load(int|string $key): bool;

    /**
     * Get the tenant provider
     *
     * Get the tenant provider used by this tenancy.
     *
     * @return \Sprout\Contracts\TenantProvider<TenantClass>
     */
    public function provider(): TenantProvider;

    /**
     * Set the identity resolved used
     *
     * @param \Sprout\Contracts\IdentityResolver $resolver
     *
     * @return static
     */
    public function resolvedVia(IdentityResolver $resolver): static;

    /**
     * Set the hook where the tenant was resolved
     *
     * @param \Sprout\Support\ResolutionHook $hook
     *
     * @return $this
     */
    public function resolvedAt(ResolutionHook $hook): static;

    /**
     * Get the used identity resolver
     *
     * @return \Sprout\Contracts\IdentityResolver|null
     */
    public function resolver(): ?IdentityResolver;

    /**
     * Get the hook where the tenant was resolved
     *
     * @return \Sprout\Support\ResolutionHook|null
     */
    public function hook(): ?ResolutionHook;

    /**
     * Check if the current tenant was resolved
     *
     * @return bool
     *
     * @phpstan-assert-if-true \Sprout\Contracts\IdentityResolver $this->resolver()
     * @phpstan-assert-if-false null $this->resolver()
     */
    public function wasResolved(): bool;

    /**
     * Set the current tenant
     *
     * @param \Sprout\Contracts\Tenant|null $tenant
     *
     * @phpstan-param TenantClass|null      $tenant
     *
     * @return static
     */
    public function setTenant(?Tenant $tenant): static;

    /**
     * Get all tenant options
     *
     * @return list<string>
     */
    public function options(): array;

    /**
     * Check if a tenancy has an option
     *
     * @param string $option
     *
     * @return bool
     */
    public function hasOption(string $option): bool;

    /**
     * Check if a tenancy has an option with config
     *
     * @param string $option
     *
     * @return bool
     */
    public function hasOptionConfig(string $option): bool;

    /**
     * Add an option to the tenancy
     *
     * @param string $option
     *
     * @return static
     */
    public function addOption(string $option): static;

    /**
     * Remove an option from the tenancy
     *
     * @param string $option
     *
     * @return static
     */
    public function removeOption(string $option): static;

    /**
     * Get a tenancy options config
     *
     * @param string $option
     *
     * @return array<array-key, mixed>|null
     */
    public function optionConfig(string $option): ?array;
}
