<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\ZohoMail\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * Zoho Mail SMTP transport.
 *
 * @link https://www.zoho.com/mail/help/zoho-smtp.html
 *
 * @since 0.1.0
 */
final class ZohoMailSmtpTransport extends EsmtpTransport
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        string $username,
        #[SensitiveParameter]
        string $password,
        ?string $accountType = null,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        $port ??= 587;
        $host = in_array($accountType, ['business', 'pro'], \true) ? 'smtppro.zoho.com' : 'smtp.zoho.com';
        parent::__construct($host, $port, $port === 465, $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}
