<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\Mailer\Bridge\Postmark\Webhook;

use JooosiMailDeps\Symfony\Component\HttpFoundation\ChainRequestMatcher;
use JooosiMailDeps\Symfony\Component\HttpFoundation\Request;
use JooosiMailDeps\Symfony\Component\HttpFoundation\RequestMatcher\IpsRequestMatcher;
use JooosiMailDeps\Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use JooosiMailDeps\Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use JooosiMailDeps\Symfony\Component\HttpFoundation\RequestMatcherInterface;
use JooosiMailDeps\Symfony\Component\Mailer\Bridge\Postmark\RemoteEvent\PostmarkPayloadConverter;
use JooosiMailDeps\Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use JooosiMailDeps\Symfony\Component\RemoteEvent\Exception\ParseException;
use JooosiMailDeps\Symfony\Component\Webhook\Client\AbstractRequestParser;
use JooosiMailDeps\Symfony\Component\Webhook\Exception\RejectWebhookException;
final class PostmarkRequestParser extends AbstractRequestParser
{
    // https://postmarkapp.com/support/article/800-ips-for-firewalls#webhooks
    public const PROVIDER_IPS = ['3.134.147.250', '50.31.156.6', '50.31.156.77', '18.217.206.57'];
    public function __construct(private readonly PostmarkPayloadConverter $converter, private readonly array $allowedIPs = self::PROVIDER_IPS)
    {
    }
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([new MethodRequestMatcher('POST'), new IpsRequestMatcher($this->allowedIPs), new IsJsonRequestMatcher()]);
    }
    protected function doParse(
        Request $request,
        #[\SensitiveParameter]
        string $secret
    ): ?AbstractMailerEvent
    {
        $payload = $request->toArray();
        if (!isset($payload['RecordType']) || !isset($payload['MessageID']) || !(isset($payload['Recipient']) || isset($payload['Email']))) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }
        try {
            return $this->converter->convert($payload);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }
}
