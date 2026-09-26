<?php

namespace JooosiMailDeps\AsyncAws\Core\HttpClient;

use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Symfony\Component\HttpClient\HttpClient;
use JooosiMailDeps\Symfony\Component\HttpClient\RetryableHttpClient;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class AwsHttpClientFactory
{
    public static function createRetryableClient(?HttpClientInterface $httpClient = null, ?LoggerInterface $logger = null): HttpClientInterface
    {
        if (null === $httpClient) {
            $httpClient = HttpClient::create();
        }
        if (class_exists(RetryableHttpClient::class)) {
            /** @psalm-suppress MissingDependency */
            $httpClient = new RetryableHttpClient($httpClient, new AwsRetryStrategy(), 3, $logger);
        }
        return $httpClient;
    }
}
