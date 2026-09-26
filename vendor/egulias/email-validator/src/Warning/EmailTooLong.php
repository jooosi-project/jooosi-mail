<?php

namespace JooosiMailDeps\Egulias\EmailValidator\Warning;

use JooosiMailDeps\Egulias\EmailValidator\EmailParser;
class EmailTooLong extends Warning
{
    public const CODE = 66;
    public function __construct()
    {
        $this->message = 'Email is too long, exceeds ' . EmailParser::EMAIL_MAX_LENGTH;
    }
}
