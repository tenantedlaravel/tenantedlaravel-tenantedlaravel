<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\Auth\SproutAuthPasswordBrokerManager;
use Sprout\Sprout;

/**
 * Auth Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * auth service.
 *
 * This override cannot be deferred as it works with several services, and it's
 * possible that one could be loaded without the other.
 * Instead, the code that deals with the various services is wrapped in a
 * condition to only run if the service itself has been loaded.
 *
 * @package Overrides
 */
final class AuthOverride extends BaseOverride implements BootableServiceOverride
{
    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application&\Illuminate\Foundation\Application $app
     * @param \Sprout\Sprout                                                                  $sprout
     *
     * @return void
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        // Although this isn't strictly necessary, this is here to tidy up
        // the list of deferred services, just in case there's some weird gotcha
        // somewhere that causes the provider to be loaded anyway.
        // We'll remove the service we're about to bind against.
        $app->removeDeferredServices(['auth.password']);

        // I'm intentionally not removing the deferred service
        // 'auth.password.broker' as it will proxy to our new 'auth.password'.

        // This is the actual thing we need.
        $app->singleton('auth.password', function ($app) {
            return new SproutAuthPasswordBrokerManager($app);
        });

        // While it's unlikely that the password broker has been resolved,
        // it's possible, and as it's shared, we'll make the container forget it.
        if ($app->resolved('auth.password')) {
            $app->forgetInstance('auth.password');
        }

        // I would ideally also like to mark the password reset service provider
        // as loaded here, but that method is protected.
    }

    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        $this->forgetGuards();
        $this->flushPasswordBrokers();
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
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        $this->forgetGuards();
        $this->flushPasswordBrokers();
    }

    /**
     * Forget all resolved guards
     *
     * @return void
     */
    protected function forgetGuards(): void
    {
        // Since this class isn't deferred because it has to rely on
        // multiple services, we only want to actually run this code if
        // the auth manager has been resolved.
        if (app()->resolved('auth')) {
            /** @var \Illuminate\Auth\AuthManager $authManager */
            $authManager = app(AuthManager::class);

            if ($authManager->hasResolvedGuards()) {
                $authManager->forgetGuards();
            }
        }
    }

    /**
     * Flush all password brokers
     *
     * @return void
     */
    protected function flushPasswordBrokers(): void
    {
        // Same as with 'auth' above, we only want to run this code if the
        // password broker has been resolved already.
        if (app()->resolved('auth.password')) {
            /** @var \Illuminate\Auth\Passwords\PasswordBrokerManager $passwordBroker */
            $passwordBroker = app('auth.password');

            // The flush method only exists on our custom implementation
            if ($passwordBroker instanceof SproutAuthPasswordBrokerManager) {
                $passwordBroker->flush();
            }
        }
    }
}
