<?php
declare(strict_types=1);

namespace Sprout\Overrides\Auth;

use Illuminate\Auth\Passwords\CacheTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use SensitiveParameter;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use function Sprout\sprout;

class SproutAuthCacheTokenRepository extends CacheTokenRepository
{
    /**
     * @return string
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function getPrefix(): string
    {
        $prefix = $this->getTenantedPrefix();

        if (! empty($this->prefix)) {
            $prefix = $this->prefix . '.' . $prefix;
        }

        return $prefix;
    }

    /**
     * @return string
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    protected function getTenantedPrefix(): string
    {
        if (! sprout()->withinContext()) {
            return '';
        }

        $tenancy = sprout()->getCurrentTenancy();

        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        if (! $tenancy->check()) {
            throw TenantMissingException::make($tenancy->getName());
        }

        /** @var \Sprout\Contracts\Tenant $tenant */
        $tenant = $tenancy->tenant();

        return $tenancy->getName() . '.' . ($tenant instanceof TenantHasResources ? $tenant->getTenantResourceKey() : $tenant->getTenantKey()) . '.';
    }

    /**
     * Create a new token.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     *
     * @return string
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function create(CanResetPasswordContract $user): string
    {
        $this->delete($user);

        $token = hash_hmac('sha256', Str::random(40), $this->hashKey);

        $this->cache->put(
            $this->getPrefix() . $user->getEmailForPasswordReset(),
            [$token, Carbon::now()->format($this->format)],
            $this->expires,
        );

        return $token;
    }

    /**
     * Determine if a token record exists and is valid.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     * @param string                                      $token
     *
     * @return bool
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function exists(CanResetPasswordContract $user, #[SensitiveParameter] $token): bool
    {
        /** @var null|array{string, string} $result */
        $result = $this->cache->get($this->getPrefix() . $user->getEmailForPasswordReset());

        if ($result === null) {
            return false;
        }

        [$record, $createdAt] = $result;

        return $record
               && ! $this->tokenExpired($createdAt)
               && $this->hasher->check($token, $record);
    }

    /**
     * Determine if the given user recently created a password reset token.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     *
     * @return bool
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function recentlyCreatedToken(CanResetPasswordContract $user): bool
    {
        /** @var null|array{string, string} $result */
        $result = $this->cache->get($this->getPrefix() . $user->getEmailForPasswordReset());

        if ($result === null) {
            return false;
        }

        [$record, $createdAt] = $result;

        return $record && $this->tokenRecentlyCreated($createdAt);
    }

    /**
     * Delete a token record.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function delete(CanResetPasswordContract $user): void
    {
        $this->cache->forget($this->getPrefix() . $user->getEmailForPasswordReset());
    }
}
