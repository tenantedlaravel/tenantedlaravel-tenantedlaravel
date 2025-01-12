<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit;

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Listeners\IdentifyTenantOnRouting;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ServiceOverrideManager;
use Sprout\Managers\TenancyManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Sprout;
use Sprout\SproutServiceProvider;
use function Sprout\sprout;

class SproutServiceProviderTest extends UnitTestCase
{
    #[Test]
    public function serviceProviderIsRegistered(): void
    {
        $this->assertTrue(app()->providerIsLoaded(SproutServiceProvider::class));
    }

    #[Test]
    public function serviceProviderIsDiscovered(): void
    {
        $manifest = app(PackageManifest::class);

        $this->assertContains(SproutServiceProvider::class, $manifest->providers());
    }

    #[Test]
    public function sproutIsRegistered(): void
    {
        $this->assertTrue(app()->has(Sprout::class));
        $this->assertTrue(app()->has('sprout'));
        $this->assertTrue(app()->isShared(Sprout::class));
        $this->assertFalse(app()->isShared('sprout'));

        $this->assertSame(app()->make(Sprout::class), app()->make(Sprout::class));
        $this->assertSame(app()->make('sprout'), app()->make('sprout'));
        $this->assertSame(app()->make(Sprout::class), app()->make('sprout'));
        $this->assertSame(app()->make('sprout'), app()->make(Sprout::class));
        $this->assertSame(sprout(), sprout());
        $this->assertSame(app()->make(Sprout::class), sprout());
    }

    #[Test]
    public function providerManagerIsRegistered(): void
    {
        $this->assertTrue(app()->has(TenantProviderManager::class));
        $this->assertTrue(app()->has('sprout.providers'));
        $this->assertTrue(app()->isShared(TenantProviderManager::class));
        $this->assertFalse(app()->isShared('sprout.providers'));

        $this->assertSame(app()->make(TenantProviderManager::class), app()->make(TenantProviderManager::class));
        $this->assertSame(app()->make('sprout.providers'), app()->make('sprout.providers'));
        $this->assertSame(app()->make(TenantProviderManager::class), app()->make('sprout.providers'));
        $this->assertSame(app()->make('sprout.providers'), app()->make(TenantProviderManager::class));
        $this->assertSame(app()->make(Sprout::class)->providers(), app()->make('sprout.providers'));
        $this->assertSame(app()->make(Sprout::class)->providers(), app()->make(TenantProviderManager::class));
        $this->assertSame(sprout()->providers(), sprout()->providers());
        $this->assertSame(app()->make(Sprout::class)->providers(), sprout()->providers());
    }

    #[Test]
    public function identityResolverManagerIsRegistered(): void
    {
        $this->assertTrue(app()->has(IdentityResolverManager::class));
        $this->assertTrue(app()->has('sprout.resolvers'));
        $this->assertTrue(app()->isShared(IdentityResolverManager::class));
        $this->assertFalse(app()->isShared('sprout.resolvers'));

        $this->assertSame(app()->make(IdentityResolverManager::class), app()->make(IdentityResolverManager::class));
        $this->assertSame(app()->make('sprout.resolvers'), app()->make('sprout.resolvers'));
        $this->assertSame(app()->make(IdentityResolverManager::class), app()->make('sprout.resolvers'));
        $this->assertSame(app()->make('sprout.resolvers'), app()->make(IdentityResolverManager::class));
        $this->assertSame(app()->make(Sprout::class)->resolvers(), app()->make('sprout.resolvers'));
        $this->assertSame(app()->make(Sprout::class)->resolvers(), app()->make(IdentityResolverManager::class));
        $this->assertSame(sprout()->resolvers(), sprout()->resolvers());
        $this->assertSame(app()->make(Sprout::class)->resolvers(), sprout()->resolvers());
    }

    #[Test]
    public function tenancyManagerIsRegistered(): void
    {
        $this->assertTrue(app()->has(TenancyManager::class));
        $this->assertTrue(app()->has('sprout.tenancies'));
        $this->assertTrue(app()->isShared(TenancyManager::class));
        $this->assertFalse(app()->isShared('sprout.tenancies'));

        $this->assertSame(app()->make(TenancyManager::class), app()->make(TenancyManager::class));
        $this->assertSame(app()->make('sprout.tenancies'), app()->make('sprout.tenancies'));
        $this->assertSame(app()->make(TenancyManager::class), app()->make('sprout.tenancies'));
        $this->assertSame(app()->make('sprout.tenancies'), app()->make(TenancyManager::class));
        $this->assertSame(app()->make(Sprout::class)->tenancies(), app()->make('sprout.tenancies'));
        $this->assertSame(app()->make(Sprout::class)->tenancies(), app()->make(TenancyManager::class));
        $this->assertSame(sprout()->tenancies(), sprout()->tenancies());
        $this->assertSame(app()->make(Sprout::class)->tenancies(), sprout()->tenancies());
    }

    #[Test]
    public function serviceOverrideManagerIsRegistered(): void
    {
        $this->assertTrue(app()->has(ServiceOverrideManager::class));
        $this->assertTrue(app()->has('sprout.overrides'));
        $this->assertTrue(app()->isShared(ServiceOverrideManager::class));
        $this->assertFalse(app()->isShared('sprout.overrides'));

        $this->assertSame(app()->make(ServiceOverrideManager::class), app()->make(ServiceOverrideManager::class));
        $this->assertSame(app()->make('sprout.overrides'), app()->make('sprout.overrides'));
        $this->assertSame(app()->make(ServiceOverrideManager::class), app()->make('sprout.overrides'));
        $this->assertSame(app()->make('sprout.overrides'), app()->make(ServiceOverrideManager::class));
        $this->assertSame(app()->make(Sprout::class)->overrides(), app()->make('sprout.overrides'));
        $this->assertSame(app()->make(Sprout::class)->overrides(), app()->make(ServiceOverrideManager::class));
        $this->assertSame(sprout()->overrides(), sprout()->overrides());
        $this->assertSame(app()->make(Sprout::class)->overrides(), sprout()->overrides());
    }

    #[Test]
    public function registersTenantRoutesMiddleware(): void
    {
        $router     = $this->app->make(Router::class);
        $middleware = $router->getMiddleware();

        $this->assertTrue(isset($middleware[TenantRoutes::ALIAS]));
        $this->assertSame(TenantRoutes::class, $middleware[TenantRoutes::ALIAS]);
        $this->assertContains(TenantRoutes::class, $middleware);
    }

    #[Test]
    public function registersRouterMixinMethods(): void
    {
        $this->assertTrue(Router::hasMacro('tenanted'));
    }

    #[Test]
    public function publishesConfig(): void
    {
        $paths = ServiceProvider::pathsToPublish(SproutServiceProvider::class, 'config');

        $key = realpath(__DIR__ . '/../../src');

        $this->assertArrayHasKey($key . '/../resources/config/multitenancy.php', $paths);
        $this->assertContains(config_path('multitenancy.php'), $paths);
    }

    #[Test]
    public function coreSproutConfigExists(): void
    {
        $this->assertTrue(app()['config']->has('sprout'));
        $this->assertIsArray(app()['config']->get('sprout'));
        $this->assertTrue(app()['config']->has('sprout.core.hooks'));
    }

    #[Test]
    public function registersServiceOverrides(): void
    {
        $overrides = config('sprout.overrides');

        $manager = $this->app->make(ServiceOverrideManager::class);

        foreach ($overrides as $service => $config) {
            $this->assertTrue($manager->hasOverride($service));
            $this->assertSame($config['driver'], $manager->getOverrideClass($service));
        }
    }

    #[Test]
    public function registersEventHandlers(): void
    {
        $dispatcher = app()->make(Dispatcher::class);

        $this->assertTrue($dispatcher->hasListeners(RouteMatched::class));

        $listeners = $dispatcher->getRawListeners();

        $this->assertContains(IdentifyTenantOnRouting::class, $listeners[RouteMatched::class]);
    }

    #[Test]
    public function registersTenancyBootstrappers(): void
    {
        $bootstrappers = config('sprout.core.bootstrappers');

        $dispatcher = app()->make(Dispatcher::class);

        $this->assertTrue($dispatcher->hasListeners(RouteMatched::class));

        $listeners = $dispatcher->getRawListeners();

        foreach ($bootstrappers as $bootstrapper) {
            $this->assertContains($bootstrapper, $listeners[CurrentTenantChanged::class]);
        }
    }
}
