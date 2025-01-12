<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Managers;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Managers\ServiceOverrideManager;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\CookieOverride;
use Sprout\TenancyOptions;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class ServiceOverrideManagerTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('sprout.overrides', []);
        });
    }

    #[Test]
    public function isRegisteredWithTheContainerAsSingleton(): void
    {
        $manager = app()->make(ServiceOverrideManager::class);

        $this->assertInstanceOf(ServiceOverrideManager::class, $manager);

        $aliasedManager = app()->make('sprout.overrides');

        $this->assertInstanceOf(ServiceOverrideManager::class, $aliasedManager);
        $this->assertSame($manager, $aliasedManager);

        $sproutManager = sprout()->overrides();

        $this->assertInstanceOf(ServiceOverrideManager::class, $sproutManager);
        $this->assertSame($manager, $sproutManager);
        $this->assertSame($aliasedManager, $sproutManager);
    }

    #[Test]
    public function keepsTrackOfRegisteredOverrides(): void
    {
        $overrides = sprout()->overrides();

        $this->assertFalse($overrides->hasOverride('auth'));

        config()->set('sprout.overrides.auth', [
            'driver' => AuthOverride::class,
        ]);

        $overrides->registerOverrides();

        $this->assertTrue($overrides->hasOverride('auth'));
    }

    #[Test]
    public function keepsTrackOfWhichOverridesAreBootable(): void
    {
        config()->set('sprout.overrides', [
            'auth'   => ['driver' => AuthOverride::class],
            'cookie' => ['driver' => CookieOverride::class],
        ]);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertTrue($overrides->isOverrideBootable('auth'));
        $this->assertFalse($overrides->isOverrideBootable('cookie'));
    }

    #[Test]
    public function bootsBootableOverrides(): void
    {
        config()->set('sprout.overrides', [
            'auth'   => ['driver' => AuthOverride::class],
            'cookie' => ['driver' => CookieOverride::class],
        ]);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertTrue($overrides->isOverrideBootable('auth'));
        $this->assertFalse($overrides->isOverrideBootable('cookie'));
        $this->assertTrue($overrides->haveOverridesBooted());
        $this->assertTrue($overrides->hasOverrideBooted('auth'));
        $this->assertFalse($overrides->hasOverrideBooted('cookie'));
    }

    #[Test]
    public function canReturnServiceOverrides(): void
    {
        config()->set('sprout.overrides', [
            'auth'   => ['driver' => AuthOverride::class],
            'cookie' => ['driver' => CookieOverride::class],
        ]);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertInstanceOf(AuthOverride::class, $overrides->get('auth'));
        $this->assertInstanceOf(CookieOverride::class, $overrides->get('cookie'));
        $this->assertNull($overrides->get('missing'));
    }

    #[Test]
    public function mapsServicesToTheirDriverClass(): void
    {
        config()->set('sprout.overrides', [
            'auth'   => ['driver' => AuthOverride::class],
            'cookie' => ['driver' => CookieOverride::class],
        ]);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertSame(AuthOverride::class, $overrides->getOverrideClass('auth'));
        $this->assertSame(CookieOverride::class, $overrides->getOverrideClass('cookie'));
        $this->assertInstanceOf(AuthOverride::class, $overrides->get('auth'));
        $this->assertInstanceOf(CookieOverride::class, $overrides->get('cookie'));
    }

    #[Test]
    public function errorsWhenCurrentTenancyMissingForSetupCheck(): void
    {
        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        sprout()->overrides()->hasOverrideBeenSetUp('auth');
    }

    #[Test]
    public function keepsTrackOfServiceOverridesThatHaveBeenSetupForEachTenancy(): void
    {
        config()->set('sprout.overrides', [
            'auth'   => ['driver' => AuthOverride::class],
            'cookie' => ['driver' => CookieOverride::class],
        ]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function setsUpOverridesForTenancies(): void
    {
        config()->set('sprout.overrides', [
            'auth'   => ['driver' => AuthOverride::class],
            'cookie' => ['driver' => CookieOverride::class],
        ]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));

        $overrides->setupOverrides($tenancy, $tenant);

        $this->assertTrue($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertTrue($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertContains('auth', $overrides->getSetupOverrides($tenancy));
        $this->assertContains('cookie', $overrides->getSetupOverrides($tenancy));
        $this->assertNotContains('session', $overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function onlySetsUpOverridesConfiguredForTenancy(): void
    {
        config()->set('sprout.overrides', [
            'auth'   => ['driver' => AuthOverride::class],
            'cookie' => ['driver' => CookieOverride::class],
        ]);

        config()->set('multitenancy.tenancies.tenants.options', [TenancyOptions::overrides(['cookie'])]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));

        $overrides->setupOverrides($tenancy, $tenant);

        $this->assertFalse($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertTrue($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertNotContains('auth', $overrides->getSetupOverrides($tenancy));
        $this->assertContains('cookie', $overrides->getSetupOverrides($tenancy));
        $this->assertNotContains('session', $overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function setsUpAllOverridesIfConfiguredTo(): void
    {
        config()->set('sprout.overrides', [
            'auth'   => ['driver' => AuthOverride::class],
            'cookie' => ['driver' => CookieOverride::class],
        ]);

        config()->set('multitenancy.tenancies.tenants.options', [TenancyOptions::allOverrides()]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));

        $overrides->setupOverrides($tenancy, $tenant);

        $this->assertTrue($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertTrue($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertContains('auth', $overrides->getSetupOverrides($tenancy));
        $this->assertContains('cookie', $overrides->getSetupOverrides($tenancy));
        $this->assertNotContains('session', $overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function onlyCleansUpOverridesThatHaveAlreadyBeenSetUp(): void
    {
        config()->set('sprout.overrides', [
            'auth'   => ['driver' => AuthOverride::class],
            'cookie' => ['driver' => CookieOverride::class],
        ]);

        config()->set('multitenancy.tenancies.tenants.options', [TenancyOptions::overrides(['cookie'])]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));

        $overrides->setupOverrides($tenancy, $tenant);

        $this->assertFalse($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertTrue($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertNotContains('auth', $overrides->getSetupOverrides($tenancy));
        $this->assertContains('cookie', $overrides->getSetupOverrides($tenancy));
        $this->assertNotContains('session', $overrides->getSetupOverrides($tenancy));

        $overrides->cleanupOverrides($tenancy, $tenant);

        $this->assertFalse($overrides->hasOverrideBeenSetUp('auth'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function errorsWhenRegisteringOverrideWithoutConfig(): void
    {
        config()->set('sprout.overrides', ['auth' => null]);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The service override for [auth] could not be found');

        sprout()->overrides()->registerOverrides();
    }

    #[Test]
    public function errorsWhenRegisteringOverrideWithoutDriver(): void
    {
        config()->set('sprout.overrides', ['auth' => []]);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The service override [auth] is missing a required value for \'driver\'');

        sprout()->overrides()->registerOverrides();
    }

    #[Test]
    public function errorsWhenRegisteringOverrideWithInvalidDriver(): void
    {
        config()->set('sprout.overrides', ['auth' => ['driver' => \stdClass::class]]);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provided value for \'driver\' is not valid for service override [auth]');

        sprout()->overrides()->registerOverrides();
    }
}
