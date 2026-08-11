<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use ReflectionClass;
use RuntimeException;
/**
 * Resolves profile identity and optional metadata declared on the discovery attribute.
 *
 * @since 0.1.0
 */
#[Service]
final class ProfileMetadataResolver
{
    /**
     * @since 0.1.0
     */
    public function getKey(\JooosiMail\Mail\Profile\MailProfileInterface $profile): string
    {
        return $this->definition($profile)->key;
    }
    /**
     * @since 0.1.0
     */
    public function getLabel(\JooosiMail\Mail\Profile\MailProfileInterface $profile): string
    {
        return $this->normalizeString($this->definition($profile)->label) ?? $this->getKey($profile);
    }
    /**
     * @since 0.1.0
     */
    public function getDescription(\JooosiMail\Mail\Profile\MailProfileInterface $profile): string
    {
        return $this->normalizeString($this->definition($profile)->description) ?? '';
    }
    /**
     * @return array{website?: string, docsUrl?: string, useCases?: list<string>, extra?: array<string, string|list<string>>}
     *
     * @since 0.1.0
     */
    public function resolve(\JooosiMail\Mail\Profile\MailProfileInterface $profile): array
    {
        $definition = $this->definition($profile);
        $metadata = [];
        $website = $this->normalizeString($definition->website);
        $docsUrl = $this->normalizeString($definition->docsUrl);
        $useCases = $this->normalizeStringList($definition->useCases);
        $extra = $this->normalizeExtraMetadata($definition->extra);
        if ($website !== null) {
            $metadata['website'] = $website;
        }
        if ($docsUrl !== null) {
            $metadata['docsUrl'] = $docsUrl;
        }
        if ($useCases !== []) {
            $metadata['useCases'] = $useCases;
        }
        if ($extra !== []) {
            $metadata['extra'] = $extra;
        }
        return $metadata;
    }
    /**
     * @param array<string, scalar|list<scalar>|null> $metadata
     *
     * @return array<string, string|list<string>>
     *
     * @since 0.1.0
     */
    private function normalizeExtraMetadata(array $metadata): array
    {
        $normalized = [];
        foreach ($metadata as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }
            if (is_array($value)) {
                $normalizedValues = $this->normalizeStringList($value);
                if ($normalizedValues !== []) {
                    $normalized[$normalizedKey] = $normalizedValues;
                }
                continue;
            }
            $normalizedValue = $this->normalizeString($value);
            if ($normalizedValue !== null) {
                $normalized[$normalizedKey] = $normalizedValue;
            }
        }
        return $normalized;
    }
    /**
     * @param list<scalar>|list<string> $values
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function normalizeStringList(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            $normalizedValue = $this->normalizeString($value);
            if ($normalizedValue !== null) {
                $normalized[] = $normalizedValue;
            }
        }
        return array_values(array_unique($normalized));
    }
    /**
     * @since 0.1.0
     */
    private function normalizeString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }
    /**
     * @since 0.1.0
     */
    private function definition(\JooosiMail\Mail\Profile\MailProfileInterface $profile): MailProfile
    {
        $attributes = (new ReflectionClass($profile))->getAttributes(MailProfile::class);
        if ($attributes === []) {
            throw new RuntimeException(sprintf('Profile "%s" is missing the #[MailProfile] attribute.', $profile::class));
        }
        return $attributes[0]->newInstance();
    }
}
