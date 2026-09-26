<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\SQL\Builder;

use JooosiMailDeps\Doctrine\DBAL\Exception;
use JooosiMailDeps\Doctrine\DBAL\Query\SelectQuery;
interface SelectSQLBuilder
{
    /** @throws Exception */
    public function buildSQL(SelectQuery $query): string;
}
