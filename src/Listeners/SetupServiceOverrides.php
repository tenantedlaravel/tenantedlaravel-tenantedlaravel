<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Sprout\Events\CurrentTenantChanged;
use Sprout\Managers\ServiceOverrideManager;

/**
 * Setup Service Overrides
 *
 * This class is an event listener for {@see \Sprout\Events\CurrentTenantChanged}
 * that sets up any service overrides using their setup action hook.
 *
 * @package Override
 */
final class SetupServiceOverrides
{
    /**
     * @var \Sprout\Managers\ServiceOverrideManager
     */
    private ServiceOverrideManager $overrides;

    /**
     * Create a new instance
     *
     * @param \Sprout\Managers\ServiceOverrideManager $overrides
     */
    public function __construct(ServiceOverrideManager $overrides)
    {
        $this->overrides = $overrides;
    }

    /**
     * Handle the event
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Events\CurrentTenantChanged<TenantClass> $event
     *
     * @return void
     */
    public function handle(CurrentTenantChanged $event): void
    {
        // If there's no current tenant, we aren't interested
        if ($event->current === null) {
            return;
        }

        $this->overrides->setupOverrides($event->tenancy, $event->current);
    }
}
