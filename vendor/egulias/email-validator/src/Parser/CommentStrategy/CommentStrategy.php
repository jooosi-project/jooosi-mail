<?php

namespace JooosiMailDeps\Egulias\EmailValidator\Parser\CommentStrategy;

use JooosiMailDeps\Egulias\EmailValidator\EmailLexer;
use JooosiMailDeps\Egulias\EmailValidator\Result\Result;
use JooosiMailDeps\Egulias\EmailValidator\Warning\Warning;
interface CommentStrategy
{
    /**
     * Return "true" to continue, "false" to exit
     */
    public function exitCondition(EmailLexer $lexer, int $openedParenthesis): bool;
    public function endOfLoopValidations(EmailLexer $lexer): Result;
    /**
     * @return Warning[]
     */
    public function getWarnings(): array;
}
