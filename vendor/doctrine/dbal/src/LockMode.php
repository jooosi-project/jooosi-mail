<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL;

/**
 * Contains all supported lock modes.
 */
enum LockMode
{
    case NONE;
    case OPTIMISTIC;
    case PESSIMISTIC_READ;
    case PESSIMISTIC_WRITE;
}
