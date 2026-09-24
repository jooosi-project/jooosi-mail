<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\Logging;

use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use JooosiMail\Webhook\Event\WebhookEvent;

/**
 * Verifies busy connections cannot hide quieter connections from health samples.
 *
 * @since 1.0.9
 */
final class PerConnectionHealthSampleTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.9
     */
    public function testMailAttemptSamplesAreBoundedPerConnection(): void
    {
        $busyConnection = $this->createNullConnection(['name' => 'Busy connection']);
        $quietConnection = $this->createNullConnection(['name' => 'Quiet connection', 'default' => false]);
        $mailLogId = $this->createMailLog($this->defaultSinglePlan());

        self::assertNotNull($busyConnection->id);
        self::assertNotNull($quietConnection->id);

        $this->mailAttemptRepository()->record($mailLogId, $quietConnection->id, 'failed');
        $this->mailAttemptRepository()->record($mailLogId, $quietConnection->id, 'failed');

        for ($index = 0; $index < 60; $index++) {
            $this->mailAttemptRepository()->record($mailLogId, $busyConnection->id, 'sent');
        }

        $scores = $this->mailAttemptRepository()->getHealthScores([
            $busyConnection->id,
            $quietConnection->id,
        ], 2);

        self::assertSame(95, $scores[$busyConnection->id]);
        self::assertSame(11, $scores[$quietConnection->id]);
    }

    /**
     * @since 1.0.9
     */
    public function testWebhookEventSamplesAreBoundedPerConnection(): void
    {
        $busyConnection = $this->createNullConnection(['name' => 'Busy webhook connection']);
        $quietConnection = $this->createNullConnection(['name' => 'Quiet webhook connection', 'default' => false]);
        $occurredAt = gmdate('Y-m-d H:i:s');

        self::assertNotNull($busyConnection->id);
        self::assertNotNull($quietConnection->id);

        $this->saveWebhookEvent($quietConnection->id, 'failed', $occurredAt);
        $this->saveWebhookEvent($quietConnection->id, 'rejected', $occurredAt);

        for ($index = 0; $index < 60; $index++) {
            $this->saveWebhookEvent($busyConnection->id, 'delivered', $occurredAt);
        }

        $events = $this->webhookEventRepository()->listRecentEventTypesByConnection([
            $busyConnection->id,
            $quietConnection->id,
        ], 24, 2);

        self::assertSame(['delivered', 'delivered'], $events[$busyConnection->id]);
        self::assertSame(['rejected', 'failed'], $events[$quietConnection->id]);
    }

    /**
     * @since 1.0.9
     */
    private function saveWebhookEvent(int $connectionId, string $eventType, string $occurredAt): void
    {
        $this->webhookEventRepository()->save(new WebhookEvent(
            connectionId: $connectionId,
            mailLogId: null,
            eventType: $eventType,
            transportMessageId: null,
            providerEventId: null,
            payload: [],
            occurredAt: $occurredAt,
        ));
    }
}
