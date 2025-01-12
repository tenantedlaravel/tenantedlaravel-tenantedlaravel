<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\SessionManager;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Sprout;

final class SproutSessionFileDriverCreator
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
     * Create the tenant-aware session file driver
     *
     * @return \Illuminate\Session\FileSessionHandler
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __invoke(): FileSessionHandler
    {
        /** @var string $originalPath */
        $originalPath = config('session.files');
        $path         = rtrim($originalPath, '/') . DIRECTORY_SEPARATOR;

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

            $path = rtrim($path, DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . $tenant->getTenantResourceKey();
        }

        /** @var int $lifetime */
        $lifetime = config('session.lifetime');

        return new FileSessionHandler(
            $this->app->make('files'),
            $path,
            $lifetime,
        );
    }
}
