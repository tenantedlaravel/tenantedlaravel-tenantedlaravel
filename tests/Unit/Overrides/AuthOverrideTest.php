<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Overrides\Auth\SproutAuthCacheTokenRepository;
use Sprout\Overrides\Auth\SproutAuthDatabaseTokenRepository;
use Sprout\Overrides\Auth\SproutAuthPasswordBrokerManager;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\CookieOverride;
use Sprout\Support\Services;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use Workbench\App\Models\User;
use function Sprout\sprout;
use function Sprout\tenancy;

class AuthOverrideTest extends UnitTestCase
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
        $this->assertTrue(is_subclass_of(AuthOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('auth'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('auth'));
        $this->assertSame(AuthOverride::class, $sprout->overrides()->getOverrideClass('auth'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('auth'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('auth'));
    }

    #[Test]
    public function rebindsAuthPassword(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        app()->rebinding('auth.password', function ($app, $passwordBrokerManager) {
            $this->assertInstanceOf(SproutAuthPasswordBrokerManager::class, $passwordBrokerManager);
        });

        $sprout->overrides()->registerOverrides();
    }

    #[Test]
    public function forgetsAuthPasswordInstance(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        $this->assertFalse(app()->resolved('auth.password'));
        $this->assertNotInstanceOf(SproutAuthPasswordBrokerManager::class, app()->make('auth.password'));
        $this->assertTrue(app()->resolved('auth.password'));

        $sprout->overrides()->registerOverrides();

        $this->assertInstanceOf(SproutAuthPasswordBrokerManager::class, app()->make('auth.password'));
    }

    #[Test]
    public function replacesTheDatabaseTokenRepositoryDriver(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        config()->set('auth.passwords.users.driver', 'database');
        config()->set('auth.passwords.users.table', 'password_reset_tokens');
        config()->set('multitenancy.providers.eloquent.model', TenantModel::class);

        $sprout->overrides()->registerOverrides();

        $broker = app()->make('auth.password.broker');

        $this->assertInstanceOf(SproutAuthDatabaseTokenRepository::class, $broker->getRepository());

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = tenancy();

        $tenancy->setTenant($tenant);
        sprout()->setCurrentTenancy($tenancy);

        $user     = User::factory()->createOne();
        $token    = $broker->createToken($user);
        $database = app()->make('db.connection');

        $dbEntry = $database->table('password_reset_tokens')
                            ->where('email', '=', $user->getAttribute('email'))
                            ->where('tenancy', '=', $tenancy->getName())
                            ->where('tenant_id', '=', $tenant->getTenantKey())
                            ->first();

        $this->assertNotNull($dbEntry);
        $this->assertTrue(app()->make('hash')->check($token, $dbEntry->token));
    }

    #[Test]
    public function replacesTheCacheTokenRepositoryDriver(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        config()->set('auth.passwords.users.driver', 'cache');
        config()->set('auth.passwords.users.store', 'array');
        config()->set('multitenancy.providers.eloquent.model', TenantModel::class);

        $sprout->overrides()->registerOverrides();

        $broker = app()->make('auth.password.broker');

        $this->assertInstanceOf(SproutAuthCacheTokenRepository::class, $broker->getRepository());

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = tenancy();

        $tenancy->setTenant($tenant);
        sprout()->setCurrentTenancy($tenancy);

        $user  = User::factory()->createOne();
        $token = $broker->createToken($user);
        $cache = app()->make('cache')->store('array');

        $cacheKey = $tenancy->getName() . '.' . $tenant->getTenantResourceKey() . '.' . $user->getAttribute('email');

        $this->assertTrue($cache->has($cacheKey));
        $this->assertSame($token, $cache->get($cacheKey)[0]);
    }

    #[Test]
    public function canFlushBrokers(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        config()->set('auth.passwords.users.driver', 'database');

        $sprout->overrides()->registerOverrides();

        /** @var SproutAuthPasswordBrokerManager $manager */
        $manager = app()->make('auth.password');

        $this->assertFalse($manager->isResolved());

        $manager->broker();

        $this->assertTrue($manager->isResolved());

        $manager->flush();

        $this->assertFalse($manager->isResolved());
    }

    #[Test]
    public function performsSetup(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        $override = $sprout->overrides()->get('auth');

        $this->assertInstanceOf(AuthOverride::class, $override);

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $this->instance('auth', $this->spy(AuthManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasResolvedGuards')->once()->andReturn(true);
            $mock->shouldReceive('forgetGuards')->once();
        }));

        $this->instance('auth.password', $this->spy(SproutAuthPasswordBrokerManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('flush')->once();
        }));

        $override->setup($tenancy, $tenant);
    }

    #[Test]
    public function performsCleanup(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        $override = $sprout->overrides()->get('auth');

        $this->assertInstanceOf(AuthOverride::class, $override);

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $this->instance('auth', $this->spy(AuthManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasResolvedGuards')->once()->andReturn(true);
            $mock->shouldReceive('forgetGuards')->once();
        }));

        $this->instance('auth.password', $this->spy(SproutAuthPasswordBrokerManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('flush')->once();
        }));

        $override->cleanup($tenancy, $tenant);
    }
}
