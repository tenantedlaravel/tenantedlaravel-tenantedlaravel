<?php
declare(strict_types=1);

namespace Sprout\Events;

use Sprout\Contracts\ServiceOverride as ServiceOverrideClass;

/**
 * Service Override Booted Event
 *
 * This event is dispatched after a service override has been booted.
 *
 * @template OverrideClass of \Sprout\Contracts\ServiceOverride
 *
 * @extends \Sprout\Events\ServiceOverrideEvent<OverrideClass>
 *
 * @package Overrides
 */
final class ServiceOverrideBooted extends ServiceOverrideEvent
{
}
