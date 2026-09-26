<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
/**
 * Normalizes Bird webhook batches.
 *
 * @link https://docs.bird.com/api/email-api/webhooks
 *
 * @since 0.1.0
 */
#[Service]
final class BirdWebhookAdapter extends \JooosiMail\Webhook\Adapter\SparkPostWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 331;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'bird';
    }
}
