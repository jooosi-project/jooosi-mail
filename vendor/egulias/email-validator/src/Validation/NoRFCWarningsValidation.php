<?php

namespace JooosiMailDeps\Egulias\EmailValidator\Validation;

use JooosiMailDeps\Egulias\EmailValidator\EmailLexer;
use JooosiMailDeps\Egulias\EmailValidator\Result\InvalidEmail;
use JooosiMailDeps\Egulias\EmailValidator\Result\Reason\RFCWarnings;
class NoRFCWarningsValidation extends RFCValidation
{
    /**
     * @var InvalidEmail|null
     */
    private $error;
    /**
     * {@inheritdoc}
     */
    public function isValid(string $email, EmailLexer $emailLexer): bool
    {
        if (!parent::isValid($email, $emailLexer)) {
            return \false;
        }
        if (empty($this->getWarnings())) {
            return \true;
        }
        $this->error = new InvalidEmail(new RFCWarnings(), '');
        return \false;
    }
    /**
     * {@inheritdoc}
     */
    public function getError(): ?InvalidEmail
    {
        return $this->error ?: parent::getError();
    }
}
