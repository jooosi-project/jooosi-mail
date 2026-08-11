<?php

declare(strict_types=1);

use JooosiMail\Rector\Rule\DowngradeAsymmetricVisibilityRector;
use JooosiMail\Rector\Rule\DowngradeCloneWithRector;
use JooosiMail\Rector\Rule\DowngradePropertyHookRector;
use Rector\Config\RectorConfig;

require_once __DIR__ . '/rector/Rule/DowngradeAsymmetricVisibilityRector.php';
require_once __DIR__ . '/rector/Rule/DowngradeCloneWithRector.php';
require_once __DIR__ . '/rector/Rule/DowngradePropertyHookRector.php';

return RectorConfig::configure()
    ->withDowngradeSets(php81: true)
    ->withRules([
        DowngradeAsymmetricVisibilityRector::class,
        DowngradeCloneWithRector::class,
        DowngradePropertyHookRector::class,
    ]);
