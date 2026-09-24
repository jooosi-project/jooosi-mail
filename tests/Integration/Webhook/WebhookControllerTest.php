<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Webhook;

use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use WP_REST_Request;

/**
 * Covers webhook authorization and verified ingestion.
 *
 * @since 0.1.0
 */
final class WebhookControllerTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testWebhookRequestReturnsNotFoundForMissingConnections(): void
    {
        $response = $this->dispatchWebhookRequest(999999, []);

        self::assertSame(404, $response->get_status());
        self::assertSame('jooosi_mail_webhook_connection_not_found', $response->get_data()['code'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testWebhookRequestReturnsNotFoundWhenWebhookIsDisabled(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'null',
            name: 'Disabled webhook connection',
            enabled: true,
            webhookEnabled: false,
        ));

        $response = $this->dispatchWebhookRequest($connection->id, []);

        self::assertSame(404, $response->get_status());
        self::assertSame('jooosi_mail_webhook_disabled', $response->get_data()['code'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testWebhookRequestReturnsForbiddenWhenVerificationIsUnsupported(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'null',
            name: 'Unsupported webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));

        $response = $this->dispatchWebhookRequest($connection->id, []);

        self::assertSame(403, $response->get_status());
        self::assertSame('jooosi_mail_webhook_verification_unsupported', $response->get_data()['code'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testWebhookRequestRejectsInvalidMailgunSignatures(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'mailgun',
            name: 'Mailgun webhook connection',
            enabled: true,
            webhookEnabled: true,
            secrets: [
                'webhook_secret' => 'mailgun-secret',
            ],
        ));

        $payload = [
            'signature' => [
                'timestamp' => '1700000000',
                'token' => 'token-123',
                'signature' => 'invalid-signature',
            ],
            'event-data' => [
                'event' => 'failed',
                'id' => 'provider-1',
                'timestamp' => time(),
            ],
        ];

        $response = $this->dispatchWebhookRequest($connection->id, $payload);

        self::assertSame(401, $response->get_status());
        self::assertSame('jooosi_mail_invalid_webhook_signature', $response->get_data()['code'] ?? null);
        self::assertSame(0, $this->countRows('webhook_events'));
    }

    /**
     * @since 0.1.0
     */
    public function testWebhookRequestRejectsMissingMailgunSignatures(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'mailgun',
            name: 'Mailgun missing signature connection',
            enabled: true,
            webhookEnabled: true,
            secrets: [
                'webhook_secret' => 'mailgun-secret',
            ],
        ));

        $payload = [
            'event-data' => [
                'event' => 'failed',
                'id' => 'provider-unsigned',
                'timestamp' => time(),
            ],
        ];

        $response = $this->dispatchWebhookRequest($connection->id, $payload);

        self::assertSame(401, $response->get_status());
        self::assertSame('jooosi_mail_invalid_webhook_signature', $response->get_data()['code'] ?? null);
        self::assertSame(0, $this->countRows('webhook_events'));
    }

    /**
     * @since 0.1.0
     */
    public function testUnsignedSendGridWebhookWithoutValidationKeyIsAccepted(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'sendgrid',
            name: 'Unsigned SendGrid webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));

        $payload = [[
            'event' => 'delivered',
            'email' => 'recipient@example.com',
            'sg_message_id' => 'sendgrid-provider-1',
            'sg_event_id' => 'sendgrid-event-1',
            'timestamp' => 1700000000,
        ]];

        $response = $this->dispatchWebhookRequest($connection->id, $payload);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertSame('ok', $response->get_data()['status'] ?? null);
        self::assertIsArray($event);
        self::assertSame($connection->id, (int) $event['connection_id']);
        self::assertSame('delivered', $event['event_type']);
        self::assertSame('sendgrid-provider-1', $event['transport_message_id']);
        self::assertSame('sendgrid-event-1', $event['provider_event_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testRepeatedWebhookEventsRemainNonDeduplicating(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'sendgrid',
            name: 'Repeated SendGrid webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));
        $payload = [[
            'event' => 'delivered',
            'email' => 'recipient@example.com',
            'sg_message_id' => 'repeated-provider-1',
            'sg_event_id' => 'repeated-event-1',
            'timestamp' => 1700000000,
        ]];

        $this->dispatchWebhookRequest($connection->id, $payload);
        $this->dispatchWebhookRequest($connection->id, $payload);

        self::assertSame(2, $this->countRows('webhook_events'));
    }

    /**
     * @since 0.1.0
     */
    public function testPostmarkWebhookUsesAllowlistedIps(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'postmark',
            name: 'Postmark webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));

        $payload = [
            'RecordType' => 'SubscriptionChange',
            'MessageID' => 'postmark-provider-1',
            'Recipient' => 'recipient@example.com',
            'ChangedAt' => '2026-03-30T00:00:00Z',
        ];

        $allowedResponse = $this->dispatchWebhookRequest($connection->id, $payload, remoteAddr: '127.0.0.1');
        $allowedEvent = $this->latestRow('webhook_events');

        self::assertSame(200, $allowedResponse->get_status());
        self::assertIsArray($allowedEvent);
        self::assertSame('subscription_change', $allowedEvent['event_type']);
        self::assertSame('postmark-provider-1', $allowedEvent['transport_message_id']);

        $blockedResponse = $this->dispatchWebhookRequest($connection->id, $payload, remoteAddr: '203.0.113.10');

        self::assertSame(401, $blockedResponse->get_status());
        self::assertSame('jooosi_mail_invalid_webhook_signature', $blockedResponse->get_data()['code'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testBrevoWebhookUsesAllowlistedIps(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'brevo',
            name: 'Brevo webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));

        $payload = [
            'event' => 'complaint',
            'email' => 'recipient@example.com',
            'message-id' => 'brevo-provider-1',
            'ts_event' => 1700000700,
        ];

        $allowedResponse = $this->dispatchWebhookRequest($connection->id, $payload, remoteAddr: '1.179.112.5');
        $allowedEvent = $this->latestRow('webhook_events');

        self::assertSame(200, $allowedResponse->get_status());
        self::assertIsArray($allowedEvent);
        self::assertSame('complaint', $allowedEvent['event_type']);
        self::assertSame('brevo-provider-1', $allowedEvent['transport_message_id']);

        $blockedResponse = $this->dispatchWebhookRequest($connection->id, $payload, remoteAddr: '203.0.113.10');

        self::assertSame(401, $blockedResponse->get_status());
        self::assertSame('jooosi_mail_invalid_webhook_signature', $blockedResponse->get_data()['code'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testUnsignedMailtrapWebhookIsAccepted(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'mailtrap',
            name: 'Mailtrap webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));

        $payload = [
            'events' => [[
                'event' => 'delivery',
                'message_id' => 'mailtrap-provider-1',
                'email' => 'recipient@example.com',
                'timestamp' => 1700000800,
            ]],
        ];

        $response = $this->dispatchWebhookRequest($connection->id, $payload);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame('delivery', $event['event_type']);
        self::assertSame('mailtrap-provider-1', $event['transport_message_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testVerifiedMailerSendWebhookPersistsEvent(): void
    {
        $secret = 'mailersend-secret';
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'mailersend',
            name: 'MailerSend webhook connection',
            enabled: true,
            webhookEnabled: true,
            secrets: [
                'webhook_secret' => $secret,
            ],
        ));

        $payload = [
            'type' => 'activity.delivered',
            'created_at' => '2026-03-30T00:00:00.000000Z',
            'data' => [
                'email' => [
                    'message' => ['id' => 'mailersend-provider-1'],
                    'recipient' => ['email' => 'recipient@example.com'],
                    'variables' => ['mail_log_id' => 777],
                ],
            ],
        ];
        $body = wp_json_encode($payload);

        self::assertIsString($body);

        $response = $this->dispatchWebhookRequest($connection->id, $payload, [
            'signature' => hash_hmac('sha256', $body, $secret),
        ]);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame(777, (int) $event['mail_log_id']);
        self::assertSame('activity_delivered', $event['event_type']);
        self::assertSame('mailersend-provider-1', $event['transport_message_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testVerifiedMailomatWebhookPersistsEvent(): void
    {
        $secret = 'mailomat-secret';
        $webhookId = '1d958822-0934-4c6a-abc8-5defec4baa64';
        $eventType = 'failure_perm';
        $timestamp = '1718004211';
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'mailomat',
            name: 'Mailomat webhook connection',
            enabled: true,
            webhookEnabled: true,
            secrets: [
                'webhook_secret' => $secret,
            ],
        ));

        $payload = [
            'id' => $webhookId,
            'eventType' => $eventType,
            'occurredAt' => '2024-06-10T09:23:31+02:00',
            'messageUuid' => '1684f904-596e-4d9f-ba16-ead5c4d27957',
            'messageId' => 'mailomat-provider-1@s.mailomat.swiss',
            'recipient' => 'recipient@example.com',
        ];
        $body = wp_json_encode($payload);

        self::assertIsString($body);

        $response = $this->dispatchWebhookRequest($connection->id, $body, [
            'x-mom-webhook-event' => $eventType,
            'x-mom-webhook-id' => $webhookId,
            'x-mom-webhook-timestamp' => $timestamp,
            'x-mom-webhook-signature' => 'sha256=' . hash_hmac('sha256', $webhookId . '.' . $eventType . '.' . $timestamp, $secret),
        ]);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame('bounce', $event['event_type']);
        self::assertSame('mailomat-provider-1@s.mailomat.swiss', $event['transport_message_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testVerifiedAhaSendWebhookPersistsEvent(): void
    {
        $secret = 'ahasend-secret';
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'ahasend',
            name: 'AhaSend webhook connection',
            enabled: true,
            webhookEnabled: true,
            secrets: [
                'webhook_secret' => $secret,
            ],
        ));

        $payload = [
            'type' => 'message.failed',
            'timestamp' => '2026-03-30T00:00:00.123456789Z',
            'data' => [
                'id' => 'ahasend-provider-1',
                'recipient' => 'recipient@example.com',
                'mail_log_id' => 778,
            ],
        ];
        $body = wp_json_encode($payload);

        self::assertIsString($body);

        $response = $this->dispatchWebhookRequest($connection->id, $payload, [
            'webhook-id' => 'ahasend-webhook-id',
            'webhook-timestamp' => '1711756800',
            'webhook-signature' => 'v1,' . base64_encode(pack('H*', hash_hmac('sha256', 'ahasend-webhook-id.1711756800.' . $body, $secret))),
        ]);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame(778, (int) $event['mail_log_id']);
        self::assertSame('failed', $event['event_type']);
        self::assertSame('ahasend-provider-1', $event['transport_message_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testVerifiedSendLayerWebhookPersistsEvent(): void
    {
        $secret = 'sendlayer-secret';
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'sendlayer',
            name: 'SendLayer webhook connection',
            enabled: true,
            webhookEnabled: true,
            secrets: [
                'webhook_secret' => $secret,
            ],
        ));

        $payload = [
            'Signature' => [
                'Timestamp' => 1711756800,
                'Token' => 'sendlayer-token',
                'Signature' => hash_hmac('sha1', '1711756800sendlayer-token', $secret),
            ],
            'EventData' => [
                'Event' => 'complained',
                'MessageID' => 'sendlayer-provider-1',
                'To' => 'recipient@example.com',
            ],
        ];

        $response = $this->dispatchWebhookRequest($connection->id, $payload);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame('spam_complaint', $event['event_type']);
        self::assertSame('sendlayer-provider-1', $event['transport_message_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testVerifiedZeptoMailWebhookPersistsEvent(): void
    {
        $secret = 'zeptomail-secret';
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'zeptomail',
            name: 'ZeptoMail webhook connection',
            enabled: true,
            webhookEnabled: true,
            secrets: [
                'webhook_secret' => $secret,
            ],
        ));

        $payload = [
            'event_name' => 'link_click',
            'event_message' => [
                'request_id' => 'zepto-provider-1',
                'email_info' => [
                    'client_reference' => '889',
                    'email_reference' => 'zepto-reference-1',
                ],
            ],
            'event_data' => [
                'details' => [
                    'time' => '2026-03-30T00:00:00Z',
                ],
            ],
        ];
        $payloadJson = wp_json_encode($payload);
        $timestamp = (string) round(microtime(true) * 1000);

        self::assertIsString($payloadJson);

        $response = $this->dispatchWebhookRequest(
            $connection->id,
            'data=' . rawurlencode($payloadJson),
            [
                'content-type' => 'application/x-www-form-urlencoded',
                'producer-signature' => sprintf(
                    'ts=%s;s=%s;s-algorithm=HmacSHA256',
                    $timestamp,
                    rawurlencode(base64_encode(hash_hmac('sha256', $payloadJson, $secret, true))),
                ),
            ],
        );
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame(889, (int) $event['mail_log_id']);
        self::assertSame('click', $event['event_type']);
        self::assertSame('zepto-reference-1', $event['transport_message_id']);
        self::assertSame('zepto-provider-1', $event['provider_event_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testUnsignedSmtp2goWebhookPersistsEvent(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'smtp2go',
            name: 'SMTP2GO webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));

        $payload = [
            'event' => 'reject',
            'time' => 1700001200,
            'email_id' => 'smtp2go-provider-1',
            'x_jooosi_mail_mail_log_id' => 890,
        ];

        $response = $this->dispatchWebhookRequest($connection->id, $payload);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame(890, (int) $event['mail_log_id']);
        self::assertSame('rejected', $event['event_type']);
        self::assertSame('smtp2go-provider-1', $event['transport_message_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testUnsignedSparkPostWebhookPersistsEvent(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'sparkpost',
            name: 'SparkPost webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));

        $payload = [[
            'msys' => [
                'message_event' => [
                    'type' => 'delivery',
                    'message_id' => 'sparkpost-provider-1',
                    'timestamp' => 1700001300,
                    'rcpt_meta' => ['mail_log_id' => 891],
                ],
            ],
        ]];

        $response = $this->dispatchWebhookRequest($connection->id, $payload);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame(891, (int) $event['mail_log_id']);
        self::assertSame('delivered', $event['event_type']);
        self::assertSame('sparkpost-provider-1', $event['transport_message_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testUnsignedBirdWebhookPersistsEvent(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'bird',
            name: 'Bird webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));

        $payload = [[
            'msys' => [
                'track_event' => [
                    'type' => 'initial_click',
                    'message_id' => 'bird-provider-1',
                    'timestamp' => 1700001400,
                    'rcpt_meta' => ['mail_log_id' => 892],
                ],
            ],
        ]];

        $response = $this->dispatchWebhookRequest($connection->id, $payload);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame(892, (int) $event['mail_log_id']);
        self::assertSame('click', $event['event_type']);
        self::assertSame('bird-provider-1', $event['transport_message_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testVerifiedSweegoWebhookPersistsEvent(): void
    {
        $rawSecret = 'sweego-shared-secret';
        $encodedSecret = base64_encode($rawSecret);
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'sweego',
            name: 'Sweego webhook connection',
            enabled: true,
            webhookEnabled: true,
            secrets: [
                'webhook_secret' => $encodedSecret,
            ],
        ));

        $payload = [
            'event_type' => 'email_opened',
            'timestamp' => '2026-03-30T00:00:00+00:00',
            'recipient' => 'recipient@example.com',
            'mail_log_id' => 888,
            'headers' => [
                'x-transaction-id' => 'sweego-provider-1',
            ],
        ];
        $body = wp_json_encode($payload);

        self::assertIsString($body);

        $response = $this->dispatchWebhookRequest($connection->id, $payload, [
            'webhook-id' => 'sweego-webhook-id',
            'webhook-timestamp' => '1711756800',
            'webhook-signature' => base64_encode(hash_hmac('sha256', 'sweego-webhook-id.1711756800.' . $body, $rawSecret, true)),
        ]);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame(888, (int) $event['mail_log_id']);
        self::assertSame('email_opened', $event['event_type']);
        self::assertSame('sweego-provider-1', $event['transport_message_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testVerifiedWebhookPersistsTheEventAndUpdatesRoutingHealth(): void
    {
        $secret = 'mailgun-secret';
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'mailgun',
            name: 'Verified Mailgun webhook connection',
            settings: [
                'circuit_breaker' => [
                    'threshold' => 1,
                    'window' => 300,
                    'cooldown' => 300,
                ],
            ],
            secrets: [
                'webhook_secret' => $secret,
            ],
            enabled: true,
            webhookEnabled: true,
        ));

        $mailLogId = $this->createMailLog($this->defaultSinglePlan(), $this->createMailRequest('Webhook delivery'));
        $this->mailLogRepository()->markSent($mailLogId, $connection->id ?? 0, 'provider-1');

        $timestamp = (string) time();
        $token = 'token-verified';
        $signature = hash_hmac('sha256', $timestamp . $token, $secret);
        $payload = [
            'signature' => [
                'timestamp' => $timestamp,
                'token' => $token,
                'signature' => $signature,
            ],
            'event-data' => [
                'event' => 'failed',
                'id' => 'mailgun-event-1',
                'message_id' => 'provider-1',
                'timestamp' => time(),
            ],
        ];

        $response = $this->dispatchWebhookRequest($connection->id, $payload);
        $event = $this->latestRow('webhook_events');
        $status = $this->circuitBreaker()->getStatus($connection);

        self::assertSame(200, $response->get_status());
        self::assertSame('ok', $response->get_data()['status'] ?? null);
        self::assertIsArray($event);
        self::assertSame($connection->id, (int) $event['connection_id']);
        self::assertSame($mailLogId, (int) $event['mail_log_id']);
        self::assertSame('failed', $event['event_type']);
        self::assertSame('provider-1', $event['transport_message_id']);
        self::assertSame('mailgun-event-1', $event['provider_event_id']);
        self::assertSame(1, $status['recent_failures']);
        self::assertIsInt($status['blacklisted_until']);
        self::assertGreaterThan(time(), $status['blacklisted_until']);
    }

    /**
     * @since 0.1.0
     */
    public function testWebhookMatchesProviderMessageIdsWithinTheCurrentConnection(): void
    {
        $firstConnection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'sendgrid',
            name: 'First SendGrid webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));
        $secondConnection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'sendgrid',
            name: 'Second SendGrid webhook connection',
            enabled: true,
            webhookEnabled: true,
        ));

        $sharedProviderMessageId = 'shared-provider-id';
        $firstMailLogId = $this->createMailLog($this->defaultSinglePlan(), $this->createMailRequest('First correlated message'));
        $secondMailLogId = $this->createMailLog($this->defaultSinglePlan(), $this->createMailRequest('Second correlated message'));

        $this->mailAttemptRepository()->record($firstMailLogId, $firstConnection->id ?? 0, 'sent', transportMessageId: $sharedProviderMessageId);
        $this->mailAttemptRepository()->record($secondMailLogId, $secondConnection->id ?? 0, 'sent', transportMessageId: $sharedProviderMessageId);
        $this->mailLogRepository()->markSent($firstMailLogId, $firstConnection->id ?? 0, $sharedProviderMessageId);
        $this->mailLogRepository()->markSent($secondMailLogId, $secondConnection->id ?? 0, $sharedProviderMessageId);

        $response = $this->dispatchWebhookRequest($secondConnection->id, [[
            'event' => 'delivered',
            'email' => 'recipient@example.com',
            'sg_message_id' => $sharedProviderMessageId,
            'sg_event_id' => 'sendgrid-event-shared',
            'timestamp' => 1700000000,
        ]]);
        $event = $this->latestRow('webhook_events');

        self::assertSame(200, $response->get_status());
        self::assertIsArray($event);
        self::assertSame($secondConnection->id, (int) $event['connection_id']);
        self::assertSame($secondMailLogId, (int) $event['mail_log_id']);
        self::assertNotSame($firstMailLogId, (int) $event['mail_log_id']);
        self::assertSame($sharedProviderMessageId, $event['transport_message_id']);
    }

    /**
     * @param array<string, mixed>|string $payload
     *
     * @since 0.1.0
     */
    private function dispatchWebhookRequest(?int $connectionId, array|string $payload, array $headers = [], ?string $remoteAddr = null)
    {
        rest_get_server();

        $request = new WP_REST_Request('POST', '/jooosi-mail/v1/webhook/' . (int) $connectionId);
        $request->set_header('content-type', 'application/json');

        foreach ($headers as $name => $value) {
            $request->set_header($name, (string) $value);
        }

        if (is_string($payload)) {
            $request->set_body($payload);
        } else {
            $body = wp_json_encode($payload);

            if (is_string($body)) {
                $request->set_body($body);
            }
        }

        $originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($remoteAddr !== null) {
            $_SERVER['REMOTE_ADDR'] = $remoteAddr;
        }

        try {
            return rest_do_request($request);
        } finally {
            if ($remoteAddr !== null && is_string($originalRemoteAddr)) {
                $_SERVER['REMOTE_ADDR'] = $originalRemoteAddr;
            }

            if ($remoteAddr !== null && ! is_string($originalRemoteAddr)) {
                unset($_SERVER['REMOTE_ADDR']);
            }
        }
    }
}
