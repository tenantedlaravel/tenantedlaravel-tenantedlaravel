<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobProcessing;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Listeners\SetCurrentTenantForJob;
use Sprout\Sprout;

/**
 * Job Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * queue/job service.
 *
 * @package Overrides
 */
final class JobOverride extends BaseOverride implements BootableServiceOverride
{
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
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $events */
        $events = app(Dispatcher::class);

        // This override simply adds a listener to make sure that tenancies
        // and their tenants are accessible to jobs
        $events->listen(JobProcessing::class, SetCurrentTenantForJob::class);
    }
}
