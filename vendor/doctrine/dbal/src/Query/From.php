<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Query;

/** @internal */
final class From
{
    public function __construct(public readonly string $table, public readonly ?string $alias = null)
    {
    }
}
