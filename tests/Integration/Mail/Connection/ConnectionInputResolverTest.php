<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\Connection;

use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionConfigurationException;
use JooosiMail\Mail\Connection\ConnectionInputNormalizer;
use JooosiMail\Mail\Connection\ConnectionInputResolver;
use JooosiMail\Mail\Profile\MailProfileInterface;
use JooosiMail\Mail\Profile\ProfileMetadataResolver;
use JooosiMail\Mail\Profile\ProfileRegistry;
use JooosiMail\Mail\Sender\SenderPolicyResolver;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use stdClass;

/**
 * Protects connection input resolution boundaries and compatibility behavior.
 *
 * @since 1.0.9
 */
final class ConnectionInputResolverTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.9
     */
    public function testProfileSwitchResetsProfileStateWhileMergingJsonAndPreservingSharedState(): void
    {
        $existingConnection = new Connection(
            id: 17,
            profileKey: 'smtp',
            name: 'Existing connection',
            dsn: 'smtp://override.example.test:2525',
            settings: [
                'profile' => [
                    'scheme' => 'smtp',
                    'host' => 'old.example.test',
                    'port' => 2525,
                ],
                'rate_limits' => [
                    'minute' => 3,
                ],
                'circuit_breaker' => [
                    'threshold' => 4,
                ],
                'sender' => [
                    'email' => 'existing-sender@example.test',
                ],
                'custom' => [
                    'retained' => true,
                ],
            ],
            secrets: [
                'profile' => [
                    'password' => 'old-password',
                    'username' => 'old-user',
                ],
                'webhook_secret' => 'old-webhook-secret',
            ],
            enabled: false,
            default: true,
            priority: 6,
            weight: 9,
            webhookEnabled: true,
        );
        $sendGridProfile = $this->profile('sendgrid');
        $resolved = $this->resolver()->resolve($existingConnection, $sendGridProfile, [
            'settings_json' => wp_json_encode([
                'profile' => [
                    'region' => 'eu',
                    'api_key' => 'must-not-be-stored-as-a-setting',
                ],
                'rate_limits' => [
                    'day' => 25,
                ],
                'custom' => [
                    'from_json' => true,
                ],
            ]),
            'secrets_json' => wp_json_encode([
                'profile' => [
                    'api_key' => 'json-api-key',
                    'region' => 'must-not-be-stored-as-a-secret',
                ],
                'webhook_secret' => 'json-webhook-secret',
            ]),
            'region' => 'us',
            'rate_limit_hour' => 42,
        ]);

        self::assertSame(17, $resolved->id);
        self::assertSame('sendgrid', $resolved->profileKey);
        self::assertSame('Existing connection', $resolved->name);
        self::assertNull($resolved->dsn);
        self::assertSame(false, $resolved->enabled);
        self::assertTrue($resolved->default);
        self::assertSame(6, $resolved->priority);
        self::assertSame(9, $resolved->weight);
        self::assertTrue($resolved->webhookEnabled);
        self::assertSame([
            'region' => 'us',
        ], $resolved->settings['profile']);
        self::assertSame([
            'minute' => 3,
            'day' => 25,
            'hour' => 42,
        ], $resolved->settings['rate_limits']);
        self::assertSame(['threshold' => 4], $resolved->settings['circuit_breaker']);
        self::assertSame(['email' => 'existing-sender@example.test'], $resolved->settings['sender']);
        self::assertSame([
            'retained' => true,
            'from_json' => true,
        ], $resolved->settings['custom']);
        self::assertSame(['api_key' => 'json-api-key'], $resolved->secrets['profile']);
        self::assertSame('json-webhook-secret', $resolved->secrets['webhook_secret']);
    }

    /**
     * @since 1.0.9
     */
    public function testSecretInputsClearExplicitValuesAndRetainOmittedValues(): void
    {
        $existingConnection = new Connection(
            id: 23,
            profileKey: 'smtp',
            name: 'SMTP connection',
            settings: [
                'profile' => [
                    'host' => 'smtp.example.test',
                    'port' => 587,
                ],
            ],
            secrets: [
                'profile' => [
                    'password' => 'retained-password',
                ],
                'webhook_secret' => 'retained-webhook-secret',
            ],
        );
        $smtpProfile = $this->profile('smtp');
        $resolver = $this->resolver();

        $omittedSecrets = $resolver->resolve($existingConnection, $smtpProfile, [
            'settings_json' => wp_json_encode(['profile' => ['port' => 2525]]),
        ]);

        self::assertSame('retained-password', $omittedSecrets->secrets['profile']['password']);
        self::assertSame('retained-webhook-secret', $omittedSecrets->secrets['webhook_secret']);
        self::assertSame(2525, $omittedSecrets->settings['profile']['port']);

        $clearedSecrets = $resolver->resolve($existingConnection, $smtpProfile, [
            'password' => '',
            'webhook_secret' => '',
        ]);

        self::assertSame([], $clearedSecrets->secrets['profile'] ?? []);
        self::assertArrayNotHasKey('webhook_secret', $clearedSecrets->secrets);
    }

    /**
     * @dataProvider invalidSenderInputProvider
     *
     * @param array<string, mixed> $sender
     *
     * @since 1.0.9
     */
    public function testSenderPolicyInputKeepsValidationMessages(array $sender, string $expectedMessage): void
    {
        $this->expectException(ConnectionConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->resolver()->resolve(null, $this->profile('smtp'), [
            'name' => 'Sender validation connection',
            'sender' => $sender,
        ]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     *
     * @since 1.0.9
     */
    public static function invalidSenderInputProvider(): array
    {
        return [
            'invalid sender email' => [
                ['email' => 'not-an-email'],
                'Sender email must be a valid email address.',
            ],
            'invalid return path email' => [
                ['return_path_email' => 'not-an-email'],
                'Return-Path email must be a valid email address.',
            ],
            'unsupported return path mode' => [
                ['return_path_mode' => 'unsupported'],
                'Return-Path mode is not supported.',
            ],
            'custom return path without email' => [
                ['return_path_mode' => SenderPolicyResolver::RETURN_PATH_MODE_CUSTOM],
                'A custom Return-Path email address is required.',
            ],
        ];
    }

    /**
     * @dataProvider invalidJsonInputProvider
     *
     * @since 1.0.9
     */
    public function testInvalidJsonMessagesRemainStable(string $key): void
    {
        $this->expectException(ConnectionConfigurationException::class);
        $this->expectExceptionMessage(sprintf('Option "%s" must be valid JSON object data.', $key));

        $this->resolver()->resolve(null, $this->profile('null'), [
            'name' => 'Invalid JSON connection',
            $key => '{invalid-json',
        ]);
    }

    /**
     * @return array<string, array{string}>
     *
     * @since 1.0.9
     */
    public static function invalidJsonInputProvider(): array
    {
        return [
            'settings JSON' => ['settings_json'],
            'secrets JSON' => ['secrets_json'],
        ];
    }

    /**
     * @since 1.0.9
     */
    public function testNonScalarInputsRemainSafeAtThePrimitiveBoundary(): void
    {
        $normalizer = $this->container()->get(ConnectionInputNormalizer::class);

        self::assertNull($normalizer->extractScalarString(['value' => []], 'value'));
        self::assertNull($normalizer->extractScalarString(['value' => new stdClass()], 'value'));
        self::assertNull($normalizer->normalizeConfigurationValue(['type' => 'text'], []));
        self::assertNull($normalizer->decodeJsonArray(['value' => []], 'value'));
        self::assertSame(true, $normalizer->resolveBoolean([], 'missing', true));
        self::assertTrue($normalizer->resolveBoolean(['value' => []], 'value', true));
        self::assertFalse($normalizer->resolveBoolean(['value' => new stdClass()], 'value', false));
        self::assertSame(1, $normalizer->resolveInt(['value' => []], 'value', 10, 1));
        self::assertSame(1, $normalizer->resolveInt(['value' => new stdClass()], 'value', 10, 1));
        self::assertSame(0, $normalizer->extractPositiveIntOrZero(['value' => []], 'value'));
        self::assertSame(0, $normalizer->extractPositiveIntOrZero(['value' => new stdClass()], 'value'));
    }

    /**
     * @since 1.0.9
     */
    public function testLegacyOneArgumentConstructorStillUsesExtractedCollaborators(): void
    {
        $resolver = new ConnectionInputResolver($this->container()->get(ProfileMetadataResolver::class));
        $resolved = $resolver->resolve(null, $this->profile('null'), [
            'name' => 'Legacy constructor connection',
        ]);

        self::assertSame('null', $resolved->profileKey);
        self::assertSame('Legacy constructor connection', $resolved->name);
        self::assertNull($resolved->dsn);
    }

    /**
     * @since 1.0.9
     */
    private function resolver(): ConnectionInputResolver
    {
        return $this->container()->get(ConnectionInputResolver::class);
    }

    /**
     * @since 1.0.9
     */
    private function profile(string $key): MailProfileInterface
    {
        $profile = $this->container()->get(ProfileRegistry::class)->get($key);

        self::assertInstanceOf(MailProfileInterface::class, $profile);

        return $profile;
    }
}
