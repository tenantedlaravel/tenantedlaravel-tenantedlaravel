<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Arr;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\Session\SproutSessionDatabaseDriverCreator;
use Sprout\Overrides\Session\SproutSessionFileDriverCreator;
use Sprout\Sprout;
use Sprout\Support\Settings;
use function Sprout\settings;

/**
 * Session Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * session service.
 *
 * @package Overrides
 */
final class SessionOverride extends BaseOverride implements BootableServiceOverride
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
        app()->afterResolving('session', function (SessionManager $manager) use ($app, $sprout) {
            $creator = new SproutSessionFileDriverCreator($app, $manager, $sprout);

            $manager->extend('file', $creator(...));
            $manager->extend('native', $creator(...));

            /** @var bool $overrideDatabase */
            $overrideDatabase = $this->config['database'] ?? true;

            if (settings()->shouldNotOverrideTheDatabase($overrideDatabase) === false) {
                $manager->extend('database', (new SproutSessionDatabaseDriverCreator(
                    $app, $manager, $sprout
                ))(...));
            }
        });
    }

    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
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
    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config   = config();
        $settings = settings();

        if (! $settings->has('original.session')) {
            /** @var array<string, mixed> $original */
            $original = $config->get('session');
            $settings->set(
                'original.session',
                Arr::only($original, ['path', 'domain', 'secure', 'same_site'])
            );
        }

        if ($settings->has(Settings::URL_PATH)) {
            $config->set('session.path', $settings->getUrlPath());
        }

        if ($settings->has(Settings::URL_DOMAIN)) {
            $config->set('session.domain', $settings->getUrlDomain());
        }

        if ($settings->has(Settings::COOKIE_SECURE)) {
            $config->set('session.secure', $settings->shouldCookieBeSecure());
        }

        if ($settings->has(Settings::COOKIE_SAME_SITE)) {
            $config->set('session.same_site', $settings->getCookieSameSite());
        }

        $config->set('session.cookie', $this->getCookieName($tenancy, $tenant));

        // Reset all the drivers
        app(SessionManager::class)->forgetDrivers();
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
        // Reset all the drivers
        app(SessionManager::class)->forgetDrivers();
    }

    /**
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return string
     */
    private function getCookieName(Tenancy $tenancy, Tenant $tenant): string
    {
        return $tenancy->getName() . '_' . $tenant->getTenantIdentifier() . '_session';
    }
}
