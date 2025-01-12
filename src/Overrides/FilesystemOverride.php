<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\Cache\SproutCacheDriverCreator;
use Sprout\Overrides\Filesystem\SproutFilesystemDriverCreator;
use Sprout\Overrides\Filesystem\SproutFilesystemManager;
use Sprout\Sprout;

final class FilesystemOverride extends BaseOverride implements BootableServiceOverride
{
    /**
     * @var list<string>
     */
    protected array $drivers = [];

    /**
     * Should the manager be overridden?
     *
     * @return bool
     */
    protected function shouldOverrideManager(): bool
    {
        return $this->config['manager'] ?? true; // @phpstan-ignore-line
    }

    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Sprout                               $sprout
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        // If we're overriding the filesystem manager
        if ($this->shouldOverrideManager()) {
            $original = null;

            // If the filesystem has already been resolved
            if ($app->resolved('filesystem')) {
                // We'll grab the manager
                $original = $app->make('filesystem');
                // and then tell the container to forget it
                $app->forgetInstance('filesystem');
            }

            // Bind a replacement filesystem manager to enable Sprout features
            $app->singleton('filesystem', fn ($app) => new SproutFilesystemManager($app, $original));
        }

        $tracker = fn(string $store) => $this->drivers[] = $store;

        // If the filesystem manager has been resolved, we can add the driver
        if ($app->resolved('filesystem')) {
            $this->addDriver($app->make('filesystem'), $sprout, $tracker);
        } else {
            // But if it hasn't, we'll add it once it is
            $app->afterResolving('filesystem', function (FilesystemManager $manager) use ($sprout, $tracker) {
                $this->addDriver($manager, $sprout, $tracker);
            });
        }
    }

    protected function addDriver(FilesystemManager $manager, Sprout $sprout, Closure $tracker): void
    {
        $manager->extend('sprout', function (Application $app, array $config) use ($manager, $sprout, $tracker): Filesystem {
            // If the config contains the disk name
            if (isset($config['name'])) {
                // Track it
                $tracker($config['name']);
            }

            return (new SproutFilesystemDriverCreator($app, $manager, $config, $sprout))();
        });
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
        /** @var array<string, array<string, mixed>> $diskConfig */
        $diskConfig = config('filesystems.disks', []);

        /** @var \Illuminate\Filesystem\FilesystemManager $filesystemManager */
        $filesystemManager = app(FilesystemManager::class);

        // If it's our custom filesystem manager, we know that we have the names
        // of the created disks
        if ($filesystemManager instanceof SproutFilesystemManager) {
            if (! empty($this->drivers)) {
                $filesystemManager->forgetDisk($this->drivers);
            }
        } else {
            // But if we don't, we have to cycle through the config and pick out
            // any that have the 'sprout' driver
            foreach ($diskConfig as $disk => $config) {
                if (($config['driver'] ?? null) === 'sprout') {
                    $filesystemManager->forgetDisk($disk);
                }
            }
        }
    }
}
