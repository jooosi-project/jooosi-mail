<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Platforms;

use JooosiMailDeps\Doctrine\DBAL\Platforms\Keywords\KeywordList;
use JooosiMailDeps\Doctrine\DBAL\Platforms\Keywords\MySQL80Keywords;
use JooosiMailDeps\Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
/**
 * Provides the behavior, features and SQL dialect of the MySQL 8.0 database platform.
 *
 * @deprecated This class will be removed once support for MySQL 5.7 is dropped.
 */
class MySQL80Platform extends MySQLPlatform
{
    protected function createReservedKeywordsList(): KeywordList
    {
        return new MySQL80Keywords();
    }
    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return AbstractPlatform::createSelectSQLBuilder();
    }
}
