<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing;

/**
 * Provides routing health penalties from external delivery signals.
 *
 * @since 0.1.0
 */
interface ConnectionHealthPenaltyProviderInterface
{
    /**
     * @param list<int> $connectionIds
     * @return array<int, int>
     *
     * @since 0.1.0
     */
    public function getNegativeHealthPenalties(array $connectionIds): array;
}
