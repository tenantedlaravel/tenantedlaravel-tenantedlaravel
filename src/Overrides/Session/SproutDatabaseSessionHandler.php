<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Database\Query\Builder;
use Illuminate\Session\DatabaseSessionHandler;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use function Sprout\sprout;

/**
 * Sprout Database Session Handler
 *
 * This is a database session driver that wraps the default
 * {@see \Illuminate\Session\DatabaseSessionHandler} and adds a where clause
 * to the query to ensure sessions are tenanted.
 *
 * @package Overrides
 */
class SproutDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * Get a fresh query builder instance for the table.
     *
     * @return \Illuminate\Database\Query\Builder
     *
     * @throws \Sprout\Exceptions\TenantMissingException
     * @throws \Sprout\Exceptions\TenancyMissingException
     */
    protected function getQuery(): Builder
    {
        $tenancy = sprout()->getCurrentTenancy();

        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        if ($tenancy->check() === false) {
            throw TenantMissingException::make($tenancy->getName());
        }

        return parent::getQuery()
                     ->where('tenancy', '=', $tenancy->getName())
                     ->where('tenant_id', '=', $tenancy->key());
    }

    /**
     * Perform an insert operation on the session ID.
     *
     * @param string               $sessionId
     * @param array<string, mixed> $payload
     *
     * @return bool|null
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    protected function performInsert($sessionId, $payload): ?bool
    {
        $tenancy = sprout()->getCurrentTenancy();

        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        if ($tenancy->check() === false) {
            throw TenantMissingException::make($tenancy->getName());
        }

        $payload['tenancy']   = $tenancy->getName();
        $payload['tenant_id'] = $tenancy->key();

        return parent::performInsert($sessionId, $payload);
    }
}
