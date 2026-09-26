<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Smtp2go\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
final class Smtp2goSmtpTransport extends EsmtpTransport
{
    /**
     * @var mixed[]
     */
    private const REGIONAL_HOSTS = ['global' => 'mail.smtp2go.com', 'us' => 'mail-us.smtp2go.com', 'eu' => 'mail-eu.smtp2go.com', 'eu2' => 'mail-eu2.smtp2go.com', 'au' => 'mail-au.smtp2go.com'];
    public function __construct(
        string $username,
        #[SensitiveParameter]
        string $password,
        ?string $region = null,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        $host = self::REGIONAL_HOSTS[$region] ?? self::REGIONAL_HOSTS['global'];
        $port ??= 587;
        parent::__construct($host, $port, in_array($port, [465, 8465, 443], \true), $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}
