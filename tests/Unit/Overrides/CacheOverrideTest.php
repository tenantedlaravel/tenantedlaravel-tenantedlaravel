<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Overrides\CacheOverride;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;
use function Sprout\tenancy;

class CacheOverrideTest extends UnitTestCase
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
        $this->assertTrue(is_subclass_of(CacheOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'cache' => [
                'driver' => CacheOverride::class,
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('cache'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('cache'));
        $this->assertSame(CacheOverride::class, $sprout->overrides()->getOverrideClass('cache'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('cache'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('cache'));
    }

    #[Test]
    public function addsSproutDriverToCacheManager(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'cache' => [
                'driver' => CacheOverride::class,
            ],
        ]);

        config()->set('cache.stores.null', [
            'driver' => 'null',
        ]);

        $sprout->overrides()->registerOverrides();

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = tenancy();

        $tenancy->setTenant($tenant);
        sprout()->setCurrentTenancy($tenancy);

        $manager = $this->app->make('cache');

        $disk = $manager->build([
            'driver'   => 'sprout',
            'override' => 'null',
        ]);

        $this->assertInstanceOf(\Illuminate\Contracts\Cache\Repository::class, $disk);
    }

    #[Test]
    public function performsCleanup(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'cache' => [
                'driver' => CacheOverride::class,
            ],
        ]);

        config()->set('cache.stores.null', [
            'driver' => 'null',
        ]);

        config()->set('cache.stores.sprout', [
            'driver'   => 'sprout',
            'override' => 'null',
        ]);

        $this->app->forgetInstance('cache');

        $sprout->overrides()->registerOverrides();

        $override = $sprout->overrides()->get('cache');

        $this->assertInstanceOf(CacheOverride::class, $override);

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = tenancy();

        $tenancy->setTenant($tenant);
        sprout()->setCurrentTenancy($tenancy);

        $this->app->make('cache')->store('sprout');

        $this->instance('cache', $this->spy(CacheManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('forgetDriver')->once()->withArgs([['sprout']]);
        }));

        $override->cleanup($tenancy, $tenant);
    }
}
