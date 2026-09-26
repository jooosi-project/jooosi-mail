<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Query;

enum UnionType
{
    case ALL;
    case DISTINCT;
}
