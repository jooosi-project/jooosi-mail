<?php

namespace JooosiMailDeps\Egulias\EmailValidator\Validation\Extra;

use Spoofchecker;
use JooosiMailDeps\Egulias\EmailValidator\EmailLexer;
use JooosiMailDeps\Egulias\EmailValidator\Result\SpoofEmail;
use JooosiMailDeps\Egulias\EmailValidator\Result\InvalidEmail;
use JooosiMailDeps\Egulias\EmailValidator\Validation\EmailValidation;
class SpoofCheckValidation implements EmailValidation
{
    /**
     * @var InvalidEmail|null
     */
    private $error;
    public function __construct()
    {
        if (!extension_loaded('intl')) {
            throw new \LogicException(sprintf('The %s class requires the Intl extension.', __CLASS__));
        }
    }
    public function isValid(string $email, EmailLexer $emailLexer): bool
    {
        $checker = new Spoofchecker();
        $checker->setChecks(Spoofchecker::SINGLE_SCRIPT);
        if ($checker->isSuspicious($email)) {
            $this->error = new SpoofEmail();
        }
        return $this->error === null;
    }
    public function getError(): ?InvalidEmail
    {
        return $this->error;
    }
    public function getWarnings(): array
    {
        return [];
    }
}
