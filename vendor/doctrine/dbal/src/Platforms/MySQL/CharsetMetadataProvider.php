<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Platforms\MySQL;

/** @internal */
interface CharsetMetadataProvider
{
    public function getDefaultCharsetCollation(string $charset): ?string;
}
