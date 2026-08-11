<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * Adapted from Symfony Mailer bridge source licensed under the MIT License.
 * See documentation/THIRD_PARTY_NOTICES.md for the original license notice.
 */
namespace JooosiMail\Mail\Transport\Bridge\Resend\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResendSmtpTransport extends EsmtpTransport
{
    public function __construct(
        #[\SensitiveParameter]
        string $password,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct('smtp.resend.com', 465, \true, $dispatcher, $logger);
        $this->setUsername('resend');
        $this->setPassword($password);
    }
}
