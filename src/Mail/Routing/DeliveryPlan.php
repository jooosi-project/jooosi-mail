<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing;

/**
 * Normalized delivery routing decision.
 *
 * Stored plans remain useful as an enqueue-time snapshot, but queued delivery
 * may still resolve current routing defaults from the original mail request.
 *
 * @since 0.1.0
 */
final class DeliveryPlan
{
    public function __construct(public readonly \JooosiMail\Mail\Routing\DeliveryMode $mode, public readonly \JooosiMail\Mail\Routing\RoutingStrategy $strategy, public readonly int $priority, public readonly int $delaySeconds, public readonly ?int $preferredConnectionId = null)
    {
    }
    /**
     * @since 0.1.0
     */
    public static function fromArray(array $data): self
    {
        return new self(mode: \JooosiMail\Mail\Routing\DeliveryMode::fromMixed($data['mode'] ?? null), strategy: \JooosiMail\Mail\Routing\RoutingStrategy::fromMixed($data['strategy'] ?? \JooosiMail\Mail\Routing\RoutingStrategy::WeightedRandom->value), priority: (int) ($data['priority'] ?? 10), delaySeconds: (int) ($data['delaySeconds'] ?? 0), preferredConnectionId: isset($data['preferredConnectionId']) ? (int) $data['preferredConnectionId'] : null);
    }
    /**
     * @return array<string, scalar|null>
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return ['mode' => $this->mode->value, 'strategy' => $this->strategy->value, 'priority' => $this->priority, 'delaySeconds' => $this->delaySeconds, 'preferredConnectionId' => $this->preferredConnectionId];
    }
}
