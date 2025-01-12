<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Events\ServiceOverrideBooted;
use Sprout\Events\ServiceOverrideProcessed;
use Sprout\Events\ServiceOverrideProcessing;
use Sprout\Events\ServiceOverrideRegistered;
use Sprout\Listeners\SetCurrentTenantForJob;
use Sprout\Overrides\Auth\SproutAuthPasswordBrokerManager;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\CookieOverride;
use Sprout\Overrides\JobOverride;
use Sprout\Overrides\StorageOverride;
use Sprout\Support\Services;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class CookieOverrideTest extends UnitTestCase
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
        $this->assertFalse(is_subclass_of(CookieOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'cookie' => [
                'driver' => CookieOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('cookie'));
        $this->assertSame(CookieOverride::class, $sprout->overrides()->getOverrideClass('cookie'));
        $this->assertFalse($sprout->overrides()->isOverrideBootable('cookie'));
        $this->assertFalse($sprout->overrides()->hasOverrideBooted('cookie'));
    }

    #[Test]
    public function isDeferredCorrectly(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        Event::fake();

        $sprout->registerOverride(Services::COOKIE, CookieOverride::class);

        Event::assertDispatched(ServiceOverrideRegistered::class);
        Event::assertNotDispatched(ServiceOverrideProcessing::class);
        Event::assertNotDispatched(ServiceOverrideProcessed::class);
        Event::assertNotDispatched(ServiceOverrideBooted::class);

        $this->assertTrue($sprout->hasRegisteredOverride(CookieOverride::class));
        $this->assertFalse($sprout->hasOverride(CookieOverride::class));
        $this->assertTrue($sprout->isServiceBeingOverridden(Services::COOKIE));
        $this->assertFalse($sprout->isBootableOverride(CookieOverride::class));
        $this->assertTrue($sprout->isDeferrableOverride(CookieOverride::class));
        $this->assertFalse($sprout->hasBootedOverride(CookieOverride::class));
        $this->assertFalse($sprout->hasOverrideBeenSetup(CookieOverride::class));

        $overrides = $sprout->getOverrides();

        $this->assertEmpty($overrides);

        $overrides = $sprout->getRegisteredOverrides();

        $this->assertCount(1, $overrides);
        $this->assertContains(CookieOverride::class, $overrides);

        app()->make('cookie');

        $overrides = $sprout->getOverrides();

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(CookieOverride::class, $overrides[CookieOverride::class]);

        $this->assertFalse($sprout->isBootableOverride(CookieOverride::class));
        $this->assertFalse($sprout->hasBootedOverride(CookieOverride::class));

        Event::assertDispatched(ServiceOverrideProcessing::class);
        Event::assertDispatched(ServiceOverrideProcessed::class);
        Event::assertNotDispatched(ServiceOverrideBooted::class);
    }

    #[Test]
    public function performsSetup(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        $sprout->registerOverride(Services::COOKIE, CookieOverride::class);

        app()->make('cookie');

        $override = $sprout->getOverrides()[CookieOverride::class];

        $this->assertInstanceOf(CookieOverride::class, $override);

        $this->assertNull(sprout()->settings()->getUrlPath());
        $this->assertNull(sprout()->settings()->getUrlDomain());
        $this->assertNull(sprout()->settings()->shouldCookieBeSecure());
        $this->assertNull(sprout()->settings()->getCookieSameSite());

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $this->instance('cookie', $this->spy(CookieJar::class, function (MockInterface $mock) {
            $mock->shouldReceive('setDefaultPathAndDomain')->once();
        }));

        $override->setup($tenancy, $tenant);
    }
}
