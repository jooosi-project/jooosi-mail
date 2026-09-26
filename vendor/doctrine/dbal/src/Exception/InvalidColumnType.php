<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Exception;

use JooosiMailDeps\Doctrine\DBAL\Exception;
use LogicException;
abstract class InvalidColumnType extends LogicException implements Exception
{
}
