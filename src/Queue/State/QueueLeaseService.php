<?php

declare(strict_types=1);

namespace JooosiMail\Queue\State;

use Closure;
use JooosiMail\Discovery\Attribute\Service;

/**
 * Coordinates owner-token-aware leases stored in WordPress options.
 *
 * Legacy scalar expiry values remain readable so existing queue installations
 * can transition to owner-token-aware leases without a migration.
 *
 * @since 1.0.9
 */
#[Service]
final class QueueLeaseService
{
    /**
     * @var Closure(string): void|null
     */
    private ?Closure $beforeConditionalDeleteHook = null;

    private readonly QueueLeaseRepository $leaseRepository;

    /**
     * @since 1.0.9
     */
    public function __construct(?QueueLeaseRepository $leaseRepository = null)
    {
        $this->leaseRepository = $leaseRepository ?? new QueueLeaseRepository();
    }

    /**
     * @since 1.0.9
     */
    public function acquire(string $optionName, int $ttl): ?QueueLease
    {
        $lease = new QueueLease(
            optionName: $optionName,
            ownerToken: wp_generate_uuid4(),
            expiresAt: time() + $ttl,
        );

        if ($this->leaseRepository->add($lease)) {
            return $lease;
        }

        $current = $this->leaseRepository->find($optionName);

        if ($current === null) {
            return $this->leaseRepository->add($lease) ? $lease : null;
        }

        if ($current['lease'] instanceof QueueLease && $current['lease']->expiresAt >= time()) {
            return null;
        }

        if (! $this->deleteIfMatches($optionName, $current['serialized_value'])) {
            return null;
        }

        return $this->leaseRepository->add($lease) ? $lease : null;
    }

    /**
     * Releases a lease only when its owner token still matches the option value.
     *
     * @since 1.0.9
     */
    public function release(QueueLease $lease): bool
    {
        $current = $this->leaseRepository->find($lease->optionName);

        if ($current === null
            || ! $current['lease'] instanceof QueueLease
            || $current['lease']->ownerToken !== $lease->ownerToken
        ) {
            return false;
        }

        return $this->deleteIfMatches($lease->optionName, $current['serialized_value']);
    }

    /**
     * Installs a one-shot interleaving callback for deterministic ownership-race tests.
     *
     * @internal
     *
     * @since 1.0.9
     */
    public function setBeforeConditionalDeleteHook(?Closure $hook): void
    {
        $this->beforeConditionalDeleteHook = $hook;
    }

    /**
     * Deletes an option only when its stored value has not changed since it was read.
     *
     * @since 1.0.9
     */
    private function deleteIfMatches(string $optionName, string $serializedValue): bool
    {
        $hook = $this->beforeConditionalDeleteHook;
        $this->beforeConditionalDeleteHook = null;

        if ($hook instanceof Closure) {
            $hook($optionName);
        }

        return $this->leaseRepository->deleteIfMatches($optionName, $serializedValue);
    }
}
