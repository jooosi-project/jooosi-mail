<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver;

use JooosiMailDeps\Doctrine\DBAL\Driver;
interface Middleware
{
    public function wrap(Driver $driver): Driver;
}
