<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Platforms\MySQL;

/** @internal */
interface CollationMetadataProvider
{
    public function getCollationCharset(string $collation): ?string;
}
