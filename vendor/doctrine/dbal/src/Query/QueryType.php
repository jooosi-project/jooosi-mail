<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Query;

/** @internal */
enum QueryType
{
    case SELECT;
    case DELETE;
    case UPDATE;
    case INSERT;
    case UNION;
}
