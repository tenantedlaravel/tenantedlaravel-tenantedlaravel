<?php
declare(strict_types=1);

namespace Sprout;

use Sprout\Contracts\Tenancy;

/**
 * Tenancy Options
 *
 * This is a helper class for providing and check for tenancy options.
 *
 * @package Core
 */
class TenancyOptions
{
    /**
     * Tenant relations should be automatically hydrated
     *
     * @return string
     */
    public static function hydrateTenantRelation(): string
    {
        return 'tenant-relation.hydrate';
    }

    /**
     * Throw an exception if the model isn't related to the tenant
     *
     * @return string
     */
    public static function throwIfNotRelated(): string
    {
        return 'tenant-relation.strict';
    }

    /**
     * Only enable these overrides for the tenancy
     *
     * @param list<string> $overrides
     *
     * @return array<string, list<string>>
     */
    public static function overrides(array $overrides): array
    {
        return [
            'overrides' => $overrides,
        ];
    }

    /**
     * Enable all overrides for the tenancy
     *
     * @return string
     */
    public static function allOverrides(): string
    {
        return 'overrides.all';
    }

    /**
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return bool
     */
    public static function shouldHydrateTenantRelation(Tenancy $tenancy): bool
    {
        return $tenancy->hasOption(static::hydrateTenantRelation());
    }

    /**
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return bool
     */
    public static function shouldThrowIfNotRelated(Tenancy $tenancy): bool
    {
        return $tenancy->hasOption(static::throwIfNotRelated());
    }

    /**
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return list<string>|null
     */
    public static function enabledOverrides(Tenancy $tenancy): array|null
    {
        return $tenancy->optionConfig('overrides'); // @phpstan-ignore-line
    }

    /**
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return bool
     */
    public static function shouldEnableAllOverrides(Tenancy $tenancy): bool
    {
        return $tenancy->hasOption(static::allOverrides());
    }
}
