<?php
declare(strict_types=1);

namespace Sprout\Overrides\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;

/**
 * Sprout Auth Password Broker Manager
 *
 * This is an override of the default password broker manager to make it
 * create a tenant-aware {@see \Illuminate\Auth\Passwords\TokenRepositoryInterface}.
 *
 * This is an unfortunate necessity as there's no other way to control the
 * token repository that is created.
 *
 * @package Overrides
 */
class SproutAuthPasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * Create a token repository instance based on the current configuration.
     *
     * @param array<string, mixed> $config
     *
     * @return \Illuminate\Auth\Passwords\TokenRepositoryInterface
     */
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        // @phpstan-ignore-next-line
        $key = $this->app['config']['app.key'];

        // @codeCoverageIgnoreStart
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        // @codeCoverageIgnoreEnd

        if (isset($config['driver']) && $config['driver'] === 'cache') {
            return new SproutAuthCacheTokenRepository(
                $this->app['cache']->store($config['store'] ?? null), // @phpstan-ignore-line
                $this->app['hash'],                                   // @phpstan-ignore-line
                $key,
                ($config['expire'] ?? 60) * 60,
                $config['throttle'] ?? 0, // @phpstan-ignore-line
                $config['prefix'] ?? '', // @phpstan-ignore-line
            );
        }

        $connection = $config['connection'] ?? null;

        return new SproutAuthDatabaseTokenRepository(
            $this->app['db']->connection($connection), // @phpstan-ignore-line
            $this->app['hash'],                        // @phpstan-ignore-line
            $config['table'],                          // @phpstan-ignore-line
            $key,
            $config['expire'],// @phpstan-ignore-line
            $config['throttle'] ?? 0// @phpstan-ignore-line
        );
    }

    /**
     * Check if a broker has been resolved
     *
     * @param string|null $name
     *
     * @return bool
     */
    public function isResolved(?string $name = null): bool
    {
        return isset($this->brokers[$name ?? $this->getDefaultDriver()]);
    }

    /**
     * Flush the resolved brokers
     *
     * @return $this
     */
    public function flush(): self
    {
        $this->brokers = [];

        return $this;
    }
}
