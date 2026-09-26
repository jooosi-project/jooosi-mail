<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
/**
 * Generic fallback webhook adapter.
 *
 * @since 0.1.0
 */
#[Service]
final class GenericWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return -1000;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return \true;
    }
}
