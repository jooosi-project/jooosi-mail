<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Settings;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Routing\DeliveryMode;
use JooosiMail\Mail\Routing\RoutingStrategy;
use JooosiMail\Mail\Sender\SenderPolicyResolver;
/**
 * Defines the supported admin settings choices.
 *
 * @since 1.0.9
 */
#[Service]
final class SettingsOptions
{
    /**
     * @return list<array{value: string, label: string}>
     *
     * @since 1.0.9
     */
    public function deliveryModes(): array
    {
        return [['value' => DeliveryMode::Async->value, 'label' => 'Async queue'], ['value' => DeliveryMode::Sync->value, 'label' => 'Sync']];
    }
    /**
     * @return list<array{value: string, label: string}>
     *
     * @since 1.0.9
     */
    public function routingStrategies(): array
    {
        return [['value' => RoutingStrategy::WeightedRandom->value, 'label' => 'Weighted random'], ['value' => RoutingStrategy::RoundRobin->value, 'label' => 'Round robin'], ['value' => RoutingStrategy::Failover->value, 'label' => 'Failover'], ['value' => RoutingStrategy::Single->value, 'label' => 'Single connection']];
    }
    /**
     * @return list<array{value: string, label: string}>
     *
     * @since 1.0.9
     */
    public function returnPathModes(): array
    {
        return [['value' => SenderPolicyResolver::RETURN_PATH_MODE_PROVIDER_DEFAULT, 'label' => 'Provider default'], ['value' => SenderPolicyResolver::RETURN_PATH_MODE_MATCH_FROM, 'label' => 'Match From Email'], ['value' => SenderPolicyResolver::RETURN_PATH_MODE_CUSTOM, 'label' => 'Custom address']];
    }
    /**
     * @param list<array{value: string, label: string}> $options
     *
     * @since 1.0.9
     */
    public function contains(array $options, string $value): bool
    {
        return in_array($value, array_column($options, 'value'), \true);
    }
}
