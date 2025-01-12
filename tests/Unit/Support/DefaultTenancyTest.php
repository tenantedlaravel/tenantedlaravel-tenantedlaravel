<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Events\TenantIdentified;
use Sprout\Events\TenantLoaded;
use Sprout\Providers\EloquentTenantProvider;
use Sprout\Support\DefaultTenancy;
use Sprout\Support\ResolutionHook;
use Sprout\TenancyOptions;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class DefaultTenancyTest extends UnitTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', 'path');
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    #[Test]
    public function hasName(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = sprout()->tenancies()->get();

        $this->assertInstanceOf(DefaultTenancy::class, $tenancy);
        $this->assertSame('tenants', $tenancy->getName());
    }

    #[Test]
    public function hasNoCurrentTenantByDefault(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = sprout()->tenancies()->get();

        $this->assertFalse($tenancy->check());
    }

    #[Test]
    public function storesCurrentTenantForAccess(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = sprout()->tenancies()->get();

        $this->assertFalse($tenancy->check());

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $this->assertTrue($tenancy->check());
        $this->assertSame($tenant, $tenancy->tenant());
        $this->assertSame($tenant->getTenantKey(), $tenancy->key());
        $this->assertSame($tenant->getTenantIdentifier(), $tenancy->identifier());
    }

    #[Test]
    public function identifiesTenant(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = sprout()->tenancies()->get();

        $this->assertFalse($tenancy->check());

        $tenant = TenantModel::factory()->createOne();

        $this->assertFalse($tenancy->identify('non-existent'));

        Event::fake([TenantIdentified::class]);

        $this->assertTrue($tenancy->identify($tenant->getTenantIdentifier()));

        Event::assertDispatched(TenantIdentified::class);
    }

    #[Test]
    public function loadsTenant(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = sprout()->tenancies()->get();

        $this->assertFalse($tenancy->check());

        $tenant = TenantModel::factory()->createOne();

        $this->assertFalse($tenancy->load(-99999));

        Event::fake([TenantLoaded::class]);

        $this->assertTrue($tenancy->load($tenant->getTenantKey()));

        Event::assertDispatched(TenantLoaded::class);
    }

    #[Test]
    public function hasATenantProvider(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = sprout()->tenancies()->get();

        $provider = $tenancy->provider();

        $this->assertNotNull($provider);
        $this->assertInstanceOf(EloquentTenantProvider::class, $provider);
    }

    #[Test]
    public function storesHowAndWhenTheTenantWasResolved(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = sprout()->tenancies()->get();

        $this->assertFalse($tenancy->wasResolved());
        $this->assertNull($tenancy->resolver());
        $this->assertNull($tenancy->hook());

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $this->assertFalse($tenancy->wasResolved());
        $this->assertNull($tenancy->resolver());
        $this->assertNull($tenancy->hook());

        $tenancy->resolvedVia(sprout()->resolvers()->get());
        $tenancy->resolvedAt(ResolutionHook::Booting);

        $this->assertTrue($tenancy->wasResolved());
        $this->assertNotNull($tenancy->resolver());
        $this->assertSame(sprout()->resolvers()->get(), $tenancy->resolver());
        $this->assertNotNull($tenancy->hook());
        $this->assertSame(ResolutionHook::Booting, $tenancy->hook());
    }

    #[Test]
    public function hasOptions(): void
    {
        $this->markTestSkipped('Need to update for changes');

        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = sprout()->tenancies()->get();

        $this->assertSame(config('multitenancy.tenancies.tenants.options'), $tenancy->options());

        $this->assertTrue($tenancy->hasOption(TenancyOptions::hydrateTenantRelation()));
        $this->assertTrue($tenancy->hasOption(TenancyOptions::throwIfNotRelated()));

        $tenancy->removeOption(TenancyOptions::hydrateTenantRelation());

        $this->assertFalse($tenancy->hasOption(TenancyOptions::hydrateTenantRelation()));
        $this->assertTrue($tenancy->hasOption(TenancyOptions::throwIfNotRelated()));

        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        $this->assertFalse($tenancy->hasOption(TenancyOptions::hydrateTenantRelation()));
        $this->assertFalse($tenancy->hasOption(TenancyOptions::throwIfNotRelated()));

        $tenancy->addOption(TenancyOptions::hydrateTenantRelation());

        $this->assertTrue($tenancy->hasOption(TenancyOptions::hydrateTenantRelation()));
        $this->assertFalse($tenancy->hasOption(TenancyOptions::throwIfNotRelated()));
    }
}
