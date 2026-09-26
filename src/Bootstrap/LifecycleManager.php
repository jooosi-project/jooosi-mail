<?php

declare (strict_types=1);
namespace JooosiMail\Bootstrap;

use JooosiMail\Database\Migration\MigrationRunner;
use JooosiMail\Infrastructure\WordPress\CommandRegistrar;
use JooosiMail\Infrastructure\WordPress\HookRegistrar;
use JooosiMail\Infrastructure\WordPress\RestRouteRegistrar;
use JooosiMail\Mail\Logging\MailLogRetentionScheduler;
use JooosiMail\Queue\Trigger\ActionSchedulerTrigger;
/**
 * Registers runtime integrations with WordPress.
 *
 * @since 0.1.0
 */
final class LifecycleManager
{
    public function __construct(private readonly HookRegistrar $hookRegistrar, private readonly RestRouteRegistrar $restRouteRegistrar, private readonly CommandRegistrar $commandRegistrar, private readonly MigrationRunner $migrationRunner, private readonly ActionSchedulerTrigger $actionSchedulerTrigger, private readonly MailLogRetentionScheduler $mailLogRetentionScheduler)
    {
    }
    /**
     * Register all runtime bridges.
     *
     * @since 0.1.0
     */
    public function boot(): void
    {
        $this->hookRegistrar->register();
        $this->restRouteRegistrar->register();
        $this->commandRegistrar->register();
    }
    /**
     * Run activation tasks.
     *
     * @since 0.1.0
     */
    public function activate(): void
    {
        $this->migrationRunner->run();
        $this->actionSchedulerTrigger->scheduleRecurring();
        $this->mailLogRetentionScheduler->scheduleRecurring();
    }
    /**
     * Run deactivation tasks.
     *
     * @since 0.1.0
     */
    public function deactivate(): void
    {
        $this->actionSchedulerTrigger->unscheduleAll();
        $this->mailLogRetentionScheduler->unscheduleAll();
    }
}
