<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Profile\MailProfileInterface;
use JooosiMail\Mail\Profile\ProfileMetadataResolver;

/**
 * Resolves profile and webhook secrets without exposing them in settings.
 *
 * @since 1.0.9
 */
#[Service]
final class ConnectionProfileSecretsResolver
{
    public function __construct(
        private readonly ProfileMetadataResolver $profileMetadataResolver,
        private readonly ConnectionInputNormalizer $inputNormalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function resolve(MailProfileInterface $profile, array $input, ?Connection $existingConnection): array
    {
        $secrets = $existingConnection?->secrets ?? [];

        if ($existingConnection instanceof Connection && $existingConnection->profileKey !== $this->profileMetadataResolver->getKey($profile)) {
            unset($secrets['profile']);
        }

        $jsonSecrets = $this->inputNormalizer->decodeJsonArray($input, 'secrets_json');

        if ($jsonSecrets !== null) {
            $secrets = array_replace_recursive($secrets, $jsonSecrets);
        }

        $profileSecrets = is_array($secrets['profile'] ?? null) ? $secrets['profile'] : [];

        foreach ($profile->getConfigurationFields() as $fieldName => $field) {
            if (! $this->inputNormalizer->isSecretField($field)) {
                unset($profileSecrets[$fieldName]);
            }
        }

        foreach ($profile->getConfigurationFields() as $fieldName => $field) {
            if (! $this->inputNormalizer->isSecretField($field) || ! array_key_exists($fieldName, $input)) {
                continue;
            }

            $value = $this->inputNormalizer->normalizeConfigurationValue($field, $input[$fieldName]);

            if ($value === null) {
                unset($profileSecrets[$fieldName]);
                continue;
            }

            $profileSecrets[$fieldName] = (string) $value;
        }

        if ($profileSecrets === []) {
            unset($secrets['profile']);
        } else {
            $secrets['profile'] = $profileSecrets;
        }

        if (array_key_exists('webhook_secret', $input)) {
            $webhookSecret = $this->inputNormalizer->extractScalarString($input, 'webhook_secret');

            if ($webhookSecret === null || $webhookSecret === '') {
                unset($secrets['webhook_secret']);
            } else {
                $secrets['webhook_secret'] = $webhookSecret;
            }
        }

        return $secrets;
    }
}
