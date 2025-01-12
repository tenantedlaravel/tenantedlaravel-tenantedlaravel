<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\CompatibilityException;
use Sprout\Http\Resolvers\SessionIdentityResolver;
use Sprout\Overrides\CookieOverride;
use Sprout\Overrides\SessionOverride;
use Sprout\Support\ResolutionHook;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\resolver;
use function Sprout\sprout;
use function Sprout\tenancy;

class SessionIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.defaults.resolver', 'session');
        });
    }

    protected function defineRoutes($router): void
    {
        $router->tenanted(function () {
            Route::get('/tenant', function () {
            })->name('tenant-route');
        }, 'session');
    }

    protected function withCustomSessionName(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.session.session', 'Custom-Session-Name');
        });
    }

    protected function withCustomSessionNamePattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.session.session', '{Tenancy}-{tenancy}-{Resolver}-{resolver}');
        });
    }

    protected function withSessionServiceOverride(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('sprout.overrides.session', ['driver' => SessionOverride::class]);
        });
    }

    #[Test]
    public function isRegisteredAndCanBeAccessed(): void
    {
        $resolver = resolver('session');

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertSame('multitenancy.{tenancy}', $resolver->getSessionName());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test]
    public function replacesPlaceholdersInSessionName(): void
    {
        $resolver = resolver('session');
        $tenancy  = tenancy();

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertSame('multitenancy.{tenancy}', $resolver->getSessionName());
        $this->assertSame('multitenancy.' . $tenancy->getName(), $resolver->getRequestSessionName($tenancy));
    }

    #[Test, DefineEnvironment('withCustomSessionName')]
    public function acceptsCustomSessionName(): void
    {
        $resolver = resolver('session');

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertNotSame('multitenancy.{tenancy}', $resolver->getSessionName());
        $this->assertSame('Custom-Session-Name', $resolver->getSessionName());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomSessionName')]
    public function replacesAllPlaceholders(): void
    {
        $resolver = resolver('session');

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertNotSame('multitenancy.{tenancy}', $resolver->getSessionName());
        $this->assertSame('Custom-Session-Name', $resolver->getSessionName());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomSessionNamePattern')]
    public function replacesAllPlaceholdersInSessionName(): void
    {
        $resolver = resolver('session');
        $tenancy  = tenancy();

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertSame('{Tenancy}-{tenancy}-{Resolver}-{resolver}', $resolver->getSessionName());
        $this->assertSame(
            ucfirst($tenancy->getName()) . '-' . $tenancy->getName() . '-' . ucfirst($resolver->getName()) . '-' . $resolver->getName(),
            $resolver->getRequestSessionName($tenancy)
        );
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test]
    public function canGenerateRoutesForATenant(): void
    {
        $resolver = resolver('session');
        $tenancy  = tenancy();
        $tenant   = TenantModel::factory()->createOne();

        $this->assertSame('http://localhost/tenant', $resolver->route('tenant-route', $tenancy, $tenant));
        $this->assertSame('/tenant', $resolver->route('tenant-route', $tenancy, $tenant, absolute: false));
        $this->assertSame('http://localhost/tenant', sprout()->route('tenant-route', $tenant, $resolver->getName(), $tenancy->getName()));
        $this->assertSame('http://localhost/tenant', sprout()->route('tenant-route', $tenant));
    }

    #[Test, DefineEnvironment('withSessionServiceOverride')]
    public function errorsOutIfTheCookieServiceHasAnOverride(): void
    {
        /** @var \Illuminate\Http\Request $request */
        $request = app()->make(Request::class);

        $tenancy  = tenancy();
        $resolver = resolver('session');

        $this->expectException(CompatibilityException::class);
        $this->expectExceptionMessage('Cannot use resolver [session] with service override [session]');

        $resolver->resolveFromRequest($request, $tenancy);
    }
}
