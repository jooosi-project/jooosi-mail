<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\Messenger\Middleware;

use JooosiMailDeps\Symfony\Component\Messenger\Envelope;
use JooosiMailDeps\Symfony\Component\Messenger\Exception\ValidationFailedException;
use JooosiMailDeps\Symfony\Component\Messenger\Stamp\ValidationStamp;
use JooosiMailDeps\Symfony\Component\Validator\Validator\ValidatorInterface;
/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ValidationMiddleware implements MiddlewareInterface
{
    private ValidatorInterface $validator;
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $groups = null;
        /** @var ValidationStamp|null $validationStamp */
        if ($validationStamp = $envelope->last(ValidationStamp::class)) {
            $groups = $validationStamp->getGroups();
        }
        $violations = $this->validator->validate($message, null, $groups);
        if (\count($violations)) {
            throw new ValidationFailedException($message, $violations, $envelope);
        }
        return $stack->next()->handle($envelope, $stack);
    }
}
