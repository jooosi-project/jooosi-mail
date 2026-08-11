<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Platforms;

use JooosiMailDeps\Doctrine\DBAL\Platforms\Keywords\KeywordList;
use JooosiMailDeps\Doctrine\DBAL\Platforms\Keywords\MySQL84Keywords;
/**
 * Provides the behavior, features and SQL dialect of the MySQL 8.4 database platform.
 */
class MySQL84Platform extends MySQL80Platform
{
    protected function createReservedKeywordsList(): KeywordList
    {
        return new MySQL84Keywords();
    }
}
