<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL;

interface ServerVersionProvider
{
    /**
     * Returns the database server version
     */
    public function getServerVersion(): string;
}
