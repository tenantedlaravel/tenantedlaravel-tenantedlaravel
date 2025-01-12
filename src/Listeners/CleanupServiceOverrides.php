<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Sprout\Events\CurrentTenantChanged;
use Sprout\Managers\ServiceOverrideManager;

/**
 * Clean-up Service Overrides
 *
 * This class is an event listener for {@see \Sprout\Events\CurrentTenantChanged}
 * that cleans up any existing service overrides when the tenancy changes.
 *
 * @package Overrides
 */
final class CleanupServiceOverrides
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
     * Handle event
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Events\CurrentTenantChanged<TenantClass> $event
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\ServiceOverrideException
     */
    public function handle(CurrentTenantChanged $event): void
    {
        // If there's no previous tenant, we aren't interested
        if ($event->previous === null) {
            return;
        }

        $this->overrides->cleanupOverrides($event->tenancy, $event->previous);
    }
}
