<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Http\RouterMethods;
use Sprout\Listeners\IdentifyTenantOnRouting;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Managers\TenancyManager;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;

/**
 * Sprout Service Provider
 *
 * @package Core
 */
class SproutServiceProvider extends ServiceProvider
{
    private Sprout $sprout;

    public function register(): void
    {
        $this->registerSprout();
        $this->registerManagers();
        $this->registerMiddleware();
        $this->registerRouteMixin();
        $this->registerServiceOverrideBooting();
    }

    private function registerSprout(): void
    {
        $this->sprout = new Sprout($this->app, new SettingsRepository());

        $this->app->singleton(Sprout::class, fn () => $this->sprout);
        $this->app->alias(Sprout::class, 'sprout');

        // Bind the settings repository too
        $this->app->bind(SettingsRepository::class, fn () => $this->sprout->settings());
    }

    private function registerManagers(): void
    {
        // Register the tenant provider manager
        $this->app->singleton(TenantProviderManager::class, function ($app) {
            return new TenantProviderManager($app);
        });

        // Register the identity resolver manager
        $this->app->singleton(IdentityResolverManager::class, function ($app) {
            return new IdentityResolverManager($app);
        });

        // Register the tenancy manager
        $this->app->singleton(TenancyManager::class, function ($app) {
            return new TenancyManager($app, $app->make(TenantProviderManager::class));
        });

        // Alias the managers with simple names
        $this->app->alias(TenantProviderManager::class, 'sprout.providers');
        $this->app->alias(IdentityResolverManager::class, 'sprout.resolvers');
        $this->app->alias(TenancyManager::class, 'sprout.tenancies');
    }

    private function registerMiddleware(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make(Router::class);

        // Alias the basic tenant middleware
        $router->aliasMiddleware(TenantRoutes::ALIAS, TenantRoutes::class);
    }

    protected function registerRouteMixin(): void
    {
        Router::mixin(new RouterMethods());
    }

    protected function registerServiceOverrideBooting(): void
    {
        $this->app->booted($this->sprout->bootOverrides(...));
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->registerServiceOverrides();
        $this->registerEventListeners();
        $this->registerTenancyBootstrappers();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/config/sprout.php'       => config_path('sprout.php'),
            __DIR__ . '/../resources/config/multitenancy.php' => config_path('multitenancy.php'),
        ], ['config', 'sprout-config']);
    }

    private function registerServiceOverrides(): void
    {
        /** @var array<string, class-string<\Sprout\Contracts\ServiceOverride>> $overrides */
        $overrides = config('sprout.services', []);

        foreach ($overrides as $service => $overrideClass) {
            if (! is_string($service)) {
                throw new RuntimeException('Service overrides must be registered against a "service"'); // @codeCoverageIgnore
            }

            $this->sprout->registerOverride($service, $overrideClass);
        }
    }

    private function registerEventListeners(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        // If we should be listening for routing
        if ($this->sprout->supportsHook(ResolutionHook::Routing)) {
            $events->listen(RouteMatched::class, IdentifyTenantOnRouting::class);
        }
    }

    private function registerTenancyBootstrappers(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        /** @var array<class-string> $bootstrappers */
        $bootstrappers = config('sprout.bootstrappers', []);

        foreach ($bootstrappers as $bootstrapper) {
            $events->listen(CurrentTenantChanged::class, $bootstrapper);
        }
    }
}
