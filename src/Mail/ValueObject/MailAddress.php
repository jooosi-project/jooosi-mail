<?php

declare(strict_types=1);

namespace JooosiMail\Mail\ValueObject;

use Symfony\Component\Mime\Address;

/**
 * Immutable mail address value object.
 *
 * @since 0.1.0
 */
final class MailAddress
{
    public function __construct(
        public readonly string $address,
        public readonly ?string $name = null,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public static function fromArray(array $data): self
    {
        return new self(
            address: (string) ($data['address'] ?? ''),
            name: isset($data['name']) ? (string) $data['name'] : null,
        );
    }

    /**
     * @return array{address: string, name: string|null}
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'name' => $this->name,
        ];
    }

    /**
     * @since 0.1.0
     */
    public function toSymfony(): Address
    {
        return new Address($this->address, $this->name ?? '');
    }
}
