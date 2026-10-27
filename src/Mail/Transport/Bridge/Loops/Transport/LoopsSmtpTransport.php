<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Loops\Transport;

use Exception;
use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\InvalidArgumentException;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\RuntimeException;
use JooosiMailDeps\Symfony\Component\Mailer\Header\MetadataHeader;
use JooosiMailDeps\Symfony\Component\Mailer\Header\TagHeader;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use JooosiMailDeps\Symfony\Component\Mime\Message;
use JooosiMailDeps\Symfony\Component\Mime\MessageConverter;
use JooosiMailDeps\Symfony\Component\Mime\RawMessage;
/**
 * Loops SMTP transport for transactional template payloads.
 *
 * @since 1.0.13
 */
final class LoopsSmtpTransport extends EsmtpTransport
{
    /** @var list<string> */
    private const RESERVED_HEADERS = ['bcc', 'cc', 'content-disposition', 'content-id', 'content-transfer-encoding', 'content-type', 'date', 'dkim-signature', 'from', 'in-reply-to', 'message-id', 'mime-version', 'received', 'reply-to', 'return-path', 'sender', 'subject', 'to'];
    /** @since 1.0.13 */
    public function __construct(
        #[SensitiveParameter]
        string $password,
        private readonly string $transactionalId,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct('smtp.loops.so', $port ?? 587, \false, $eventDispatcher, $logger);
        $this->setUsername('loops');
        $this->setPassword($password);
    }
    /** @since 1.0.13 */
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (!$message instanceof Message) {
            throw new RuntimeException('Loops SMTP requires a MIME message.');
        }
        try {
            $email = MessageConverter::toEmail($message);
        } catch (Exception $exception) {
            throw new RuntimeException('Loops SMTP could not convert the message to an email.', 0, $exception);
        }
        if ($email->getCc() !== [] || $email->getBcc() !== []) {
            throw new InvalidArgumentException('Loops SMTP does not support CC or BCC recipients.');
        }
        if ($email->getReplyTo() !== []) {
            throw new InvalidArgumentException('Loops SMTP does not support a Reply-To address.');
        }
        if ($email->getAttachments() !== []) {
            throw new InvalidArgumentException('Loops SMTP does not support attachments.');
        }
        $envelope ??= Envelope::create($email);
        $recipients = $envelope->getRecipients();
        if (count($recipients) !== 1) {
            throw new InvalidArgumentException('Loops SMTP supports one recipient per message.');
        }
        if ($this->transactionalId === '') {
            throw new InvalidArgumentException('Loops SMTP requires a transactional email ID.');
        }
        $dataVariables = ['subject' => $email->getSubject(), 'from' => $envelope->getSender()->toString()];
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $dataVariables['html'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $dataVariables['text'] = $email->getTextBody();
        }
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $dataVariables[$header->getKey()] = $header->getValue();
                continue;
            }
            if ($header instanceof TagHeader) {
                throw new InvalidArgumentException('Loops SMTP does not support tags.');
            }
            if (!in_array(strtolower($header->getName()), self::RESERVED_HEADERS, \true)) {
                throw new InvalidArgumentException(sprintf('Loops SMTP does not support the custom email header "%s".', $header->getName()));
            }
        }
        $payload = ['transactionalId' => $this->transactionalId, 'email' => $recipients[0]->getAddress(), 'dataVariables' => $dataVariables];
        $body = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $email = clone $email;
        $email->html(null);
        $email->text($body);
        return parent::send($email, $envelope);
    }
}
