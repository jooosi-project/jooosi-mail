<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\Mysqli;

use JooosiMailDeps\Doctrine\DBAL\Driver\Exception;
use mysqli;
interface Initializer
{
    /** @throws Exception */
    public function initialize(mysqli $connection): void;
}
