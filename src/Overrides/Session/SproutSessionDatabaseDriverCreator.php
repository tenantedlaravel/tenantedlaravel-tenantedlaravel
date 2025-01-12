<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\DatabaseSessionHandler as OriginalDatabaseSessionHandler;
use Illuminate\Session\SessionManager;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Sprout;

final class SproutSessionDatabaseDriverCreator
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private Application $app;

    /**
     * @var \Illuminate\Session\SessionManager
     */
    private SessionManager $manager; // @phpstan-ignore-line

    /**
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    /**
     * Create a new instance
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Illuminate\Session\SessionManager           $manager
     * @param \Sprout\Sprout                               $sprout
     */
    public function __construct(Application $app, SessionManager $manager, Sprout $sprout)
    {
        $this->app     = $app;
        $this->manager = $manager;
        $this->sprout  = $sprout;
    }

    /**
     * Create the tenant-aware session database driver
     *
     * @return \Illuminate\Session\DatabaseSessionHandler
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function __invoke(): DatabaseSessionHandler
    {
        $table      = config('session.table');
        $lifetime   = config('session.lifetime');
        $connection = config('session.connection');

        /**
         * @var string|null $connection
         * @var string      $table
         * @var int         $lifetime
         */

        // This driver is unlike many of the others, where if we aren't in
        // multitenanted context, we don't do anything
        if ($this->sprout->withinContext()) {
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

            $tenant = $tenancy->tenant();

            // If the tenant isn't configured for resources, this is another issue
            if (! ($tenant instanceof TenantHasResources)) {
                throw MisconfigurationException::misconfigured('tenant', $tenant::class, 'resources');
            }

            return new SproutDatabaseSessionHandler(
                $this->app->make('db')->connection($connection),
                $table,
                $lifetime,
                $this->app
            );
        }

        return new OriginalDatabaseSessionHandler(
            $this->app->make('db')->connection($connection),
            $table,
            $lifetime,
            $this->app
        );
    }
}
