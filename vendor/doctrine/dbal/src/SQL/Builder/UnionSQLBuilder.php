<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\SQL\Builder;

use JooosiMailDeps\Doctrine\DBAL\Exception;
use JooosiMailDeps\Doctrine\DBAL\Query\UnionQuery;
interface UnionSQLBuilder
{
    /** @throws Exception */
    public function buildSQL(UnionQuery $query): string;
}
