<?php
declare(strict_types=1);

namespace Sprout\Overrides\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Sprout;

final class SproutCacheDriverCreator
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private Application $app; // @phpstan-ignore-line

    /**
     * @var \Illuminate\Cache\CacheManager
     */
    private CacheManager $manager;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    /**
     * Create a new instance
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Illuminate\Cache\CacheManager               $manager
     * @param array<string, mixed>                         $config
     * @param \Sprout\Sprout                               $sprout
     */
    public function __construct(Application $app, CacheManager $manager, array $config, Sprout $sprout)
    {
        $this->app     = $app;
        $this->config  = $config;
        $this->manager = $manager;
        $this->sprout  = $sprout;
    }

    /**
     * Create the Sprout cache driver
     *
     * @return \Illuminate\Contracts\Cache\Repository
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function __invoke(): Repository
    {
        // If we're not within a multitenanted context, we need to error
        // out, as this driver shouldn't be hit without one
        if (! $this->sprout->withinContext()) {
            // TODO: Create a better exception
            throw TenancyMissingException::make();
        }

        // Get the current active tenancy
        $tenancy = $this->sprout->getCurrentTenancy();

        // If there isn't one, that's an issue as we need a tenancy
        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        // If there is a tenancy, but it doesn't have a tenant, that's also
        // an issue
        if ($tenancy->check() === false) {
            throw TenantMissingException::make($tenancy->getName());
        }

        /** @var \Sprout\Contracts\Tenant $tenant */
        $tenant = $tenancy->tenant();

        // We need to know which store we're overriding to make tenanted
        if (! isset($this->config['override'])) {
            throw MisconfigurationException::missingConfig('override', self::class, 'service override');
        }

        // We need to get the config for that store
        /** @var array<string, mixed> $storeConfig */
        $storeConfig = config('cache.stores.' . $this->config['override']);

        if (empty($storeConfig)) {
            throw new InvalidArgumentException('Cache store [' . $this->config['override'] . '] is not defined.');
        }

        // Get the prefix for the tenanted store based on the store config,
        // the tenancy and its current tenant
        $storeConfig['prefix'] = $this->getStorePrefix($storeConfig, $tenancy, $tenant);

        return $this->manager->build($storeConfig);
    }

    /**
     * Get the prefix for the store
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param array<string, mixed>                   $config
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return string
     */
    protected function getStorePrefix(array $config, Tenancy $tenancy, Tenant $tenant): string
    {
        return (isset($config['prefix']) ? $config['prefix'] . '_' : '')
               . $tenancy->getName()
               . '_'
               . $tenant->getTenantKey();
    }
}
