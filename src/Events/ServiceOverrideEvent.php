<?php
declare(strict_types=1);

namespace Sprout\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sprout\Contracts\ServiceOverride;

/**
 * Service Override Event
 *
 * This is a base event class for the service override events.
 *
 * @template OverrideClass of \Sprout\Contracts\ServiceOverride
 *
 * @package Overrides
 *
 * @method static self dispatch(string $service, ServiceOverride $override)
 * @method static self dispatchIf(bool $boolean, string $service, ServiceOverride $override)
 * @method static self dispatchUnless(bool $boolean, string $service, ServiceOverride $override)
 *
 * @package Overrides
 */
abstract class ServiceOverrideEvent
{
    use Dispatchable;

    /**
     * @var string
     */
    public readonly string $service;

    /**
     * @var \Sprout\Contracts\ServiceOverride
     *
     * @phpstam-var OverrideClass
     */
    public readonly ServiceOverride $override;

    /**
     * @param string                            $service
     * @param \Sprout\Contracts\ServiceOverride $override
     *
     * @phpstan-param OverrideClass             $override
     */
    public function __construct(string $service, ServiceOverride $override)
    {
        $this->service  = $service;
        $this->override = $override;
    }
}
