<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\Messenger\EventListener;

use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Symfony\Component\Console\Command\SignalableCommandInterface;
trigger_deprecation('symfony/messenger', '6.3', '"%s" is deprecated, use the "%s" instead.', StopWorkerOnSigtermSignalListener::class, SignalableCommandInterface::class);
/**
 * @author Tobias Schultze <http://tobion.de>
 *
 * @deprecated since Symfony 6.3, use the {@see SignalableCommandInterface} instead
 */
class StopWorkerOnSigtermSignalListener extends StopWorkerOnSignalsListener
{
    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct(\extension_loaded('pcntl') ? [\SIGTERM] : [], $logger);
    }
}
