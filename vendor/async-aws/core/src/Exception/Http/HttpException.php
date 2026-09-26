<?php

declare (strict_types=1);
namespace JooosiMailDeps\AsyncAws\Core\Exception\Http;

use JooosiMailDeps\AsyncAws\Core\Exception\Exception;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
interface HttpException extends Exception
{
    public function getResponse(): ResponseInterface;
    public function getAwsCode(): ?string;
    public function getAwsType(): ?string;
    public function getAwsMessage(): ?string;
    public function getAwsDetail(): ?string;
}
