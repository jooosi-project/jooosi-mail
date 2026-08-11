<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Query;

use JooosiMailDeps\Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
/** @internal */
final class ForUpdate
{
    public function __construct(private readonly ConflictResolutionMode $conflictResolutionMode)
    {
    }
    public function getConflictResolutionMode(): ConflictResolutionMode
    {
        return $this->conflictResolutionMode;
    }
}
