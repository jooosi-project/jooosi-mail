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
namespace JooosiMail\Mail\Transport\Bridge\Mailomat\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\HttpTransportException;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractApiTransport;
use JooosiMailDeps\Symfony\Component\Mime\Address;
use JooosiMailDeps\Symfony\Component\Mime\Email;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
final class MailomatApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.mailomat.swiss';
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $key,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct($client, $dispatcher, $logger);
    }
    public function __toString(): string
    {
        return \sprintf('mailomat+api://%s', $this->getEndpoint());
    }
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', \sprintf('https://%s/message', $this->getEndpoint()), ['auth_bearer' => $this->key, 'json' => $this->getPayload($email, $envelope), 'headers' => ['Accept' => 'application/json']]);
        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(\false);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Mailomat server.', $response, 0, $e);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException(\sprintf('Unable to send an email: %s (code %d).', $response->getContent(\false), $statusCode), $response, 0, $e);
        }
        if (202 !== $statusCode) {
            $violations = array_map(static fn(array $violation) => ($violation['propertyPath'] ? '(' . $violation['propertyPath'] . ') ' : '') . $violation['message'], $result['violations']);
            throw new HttpTransportException(\sprintf('Unable to send an email: %s (code %d).', implode('; ', $violations), $statusCode), $response);
        }
        if (isset($result['messageUuid'])) {
            $sentMessage->setMessageId($result['messageUuid']);
        }
        return $response;
    }
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port ? ':' . $this->port : '');
    }
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = ['from' => $this->addressToPayload($envelope->getSender()), 'to' => array_map([$this, 'addressToPayload'], $email->getTo()), 'subject' => $email->getSubject(), 'text' => $email->getTextBody(), 'html' => $email->getHtmlBody(), 'attachments' => $this->getAttachments($email)];
        if ($email->getCc()) {
            $payload['cc'] = array_map([$this, 'addressToPayload'], $email->getCc());
        }
        if ($email->getBcc()) {
            $payload['bcc'] = array_map([$this, 'addressToPayload'], $email->getBcc());
        }
        if ($email->getReplyTo()) {
            $payload['replyTo'] = array_map([$this, 'addressToPayload'], $email->getReplyTo());
        }
        return $payload;
    }
    private function addressToPayload(Address $address): array
    {
        $payload = ['email' => $address->getAddress()];
        if ($address->getName()) {
            $payload['name'] = $address->getName();
        }
        return $payload;
    }
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $disposition = $headers->getHeaderBody('Content-Disposition');
            $att = ['filename' => $filename, 'contentBase64' => $attachment->bodyToString(), 'contentType' => $headers->get('Content-Type')->getBody()];
            if ('inline' === $disposition) {
                $att['ContentID'] = 'cid:' . $filename;
            }
            $attachments[] = $att;
        }
        return $attachments;
    }
}
