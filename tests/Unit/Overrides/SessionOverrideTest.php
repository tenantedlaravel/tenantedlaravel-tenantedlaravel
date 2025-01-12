<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Config\Repository;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Events\ServiceOverrideBooted;
use Sprout\Events\ServiceOverrideRegistered;
use Sprout\Overrides\SessionOverride;
use Sprout\Support\Services;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\settings;
use function Sprout\sprout;

class SessionOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(SessionOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'session' => [
                'driver' => SessionOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('session'));
        $this->assertSame(SessionOverride::class, $sprout->overrides()->getOverrideClass('session'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('session'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('session'));
    }

    #[Test]
    public function performsSetup(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'session' => [
                'driver' => SessionOverride::class,
            ],
        ]);

        $this->assertFalse(sprout()->settings()->has('original.session'));

        $this->instance(SessionManager::class, Mockery::mock(SessionManager::class, function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldReceive('forgetDrivers')->once();
        }));

        $this->assertNull(sprout()->settings()->getUrlPath());
        $this->assertNull(sprout()->settings()->getUrlDomain());
        $this->assertNull(sprout()->settings()->shouldCookieBeSecure());
        $this->assertNull(sprout()->settings()->getCookieSameSite());

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $sprout->overrides()->registerOverrides();

        app()->make('session');

        $override = $sprout->overrides()->get('session');

        $this->assertInstanceOf(SessionOverride::class, $override);

        $override->setup($tenancy, $tenant);
    }

    #[Test]
    public function performsCleanup(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'session' => [
                'driver' => SessionOverride::class,
            ],
        ]);

        $this->assertFalse(sprout()->settings()->has('original.session'));

        $this->instance(SessionManager::class, Mockery::mock(SessionManager::class, function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldReceive('forgetDrivers')->once();
        }));

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $sprout->overrides()->registerOverrides();

        app()->make('session');

        $override = $sprout->overrides()->get('session');

        $this->assertInstanceOf(SessionOverride::class, $override);

        $override->cleanup($tenancy, $tenant);
    }

    #[Test]
    public function setSessionConfigFromSproutSettings(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'session' => [
                'driver' => SessionOverride::class,
            ],
        ]);

        config()->set('session.path', '/test-path');
        config()->set('session.domain', 'test-domain.localhost');
        config()->set('session.secure', false);
        config()->set('session.same_site', 'lax');

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $sprout->overrides()->registerOverrides();

        $this->assertSame('/test-path', config('session.path'));
        $this->assertSame('test-domain.localhost', config('session.domain'));
        $this->assertFalse(config('session.secure'));
        $this->assertSame('lax', config('session.same_site'));

        $override = $sprout->overrides()->get('session');

        settings()->setUrlPath('/test-path2');
        settings()->setUrlDomain('test-domain2.localhost');
        settings()->setCookieSecure(true);
        settings()->setCookieSameSite('strict');

        $override->setup($tenancy, $tenant);

        $this->assertSame('/test-path2', config('session.path'));
        $this->assertSame('test-domain2.localhost', config('session.domain'));
        $this->assertTrue(config('session.secure'));
        $this->assertSame('strict', config('session.same_site'));
    }
}
