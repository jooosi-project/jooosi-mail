<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\SQL\Builder;

use JooosiMailDeps\Doctrine\DBAL\Platforms\AbstractPlatform;
use JooosiMailDeps\Doctrine\DBAL\Query\UnionQuery;
use JooosiMailDeps\Doctrine\DBAL\Query\UnionType;
use function count;
use function implode;
final class DefaultUnionSQLBuilder implements UnionSQLBuilder
{
    public function __construct(private readonly AbstractPlatform $platform)
    {
    }
    public function buildSQL(UnionQuery $query): string
    {
        $parts = [];
        foreach ($query->getUnionParts() as $union) {
            if ($union->type !== null) {
                $parts[] = $union->type === UnionType::ALL ? $this->platform->getUnionAllSQL() : $this->platform->getUnionDistinctSQL();
            }
            $parts[] = $this->platform->getUnionSelectPartSQL((string) $union->query);
        }
        $orderBy = $query->getOrderBy();
        if (count($orderBy) > 0) {
            $parts[] = 'ORDER BY ' . implode(', ', $orderBy);
        }
        $sql = implode(' ', $parts);
        $limit = $query->getLimit();
        if ($limit->isDefined()) {
            $sql = $this->platform->modifyLimitQuery($sql, $limit->getMaxResults(), $limit->getFirstResult());
        }
        return $sql;
    }
}
