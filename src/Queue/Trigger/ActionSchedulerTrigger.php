<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Trigger;

use JooosiMail\Discovery\Attribute\Hook;
use JooosiMail\Discovery\Attribute\Service;
/**
 * Uses Action Scheduler as a queue runner trigger and fallback.
 *
 * @since 0.1.0
 */
#[Service]
final class ActionSchedulerTrigger
{
    /**
     * @var string
     */
    public const RUN_HOOK = 'jooosi_mail_queue_process_async';
    /**
     * @var string
     */
    public const RECURRING_HOOK = 'jooosi_mail_queue_process_fallback';
    /**
     * @var string
     */
    public const ASYNC_RUNNER_ACTION = 'as_async_request_queue_runner';
    /**
     * @var string
     */
    public const GROUP = 'jooosi-mail';
    /**
     * @var string
     */
    public const SCHEDULE_LOCK_OPTION = 'jooosi_mail_queue_schedule_lock';
    /**
     * @var int
     */
    private const SCHEDULE_LOCK_TTL = 15;
    /**
     * @since 0.1.0
     */
    public function trigger(): void
    {
        if (!function_exists('as_enqueue_async_action') || !function_exists('as_get_scheduled_actions')) {
            return;
        }
        if (!$this->acquireScheduleLock()) {
            return;
        }
        try {
            if ($this->hasPendingWakeup()) {
                return;
            }
            as_enqueue_async_action(self::RUN_HOOK, [], self::GROUP, \false);
            $this->wakeActionSchedulerAsyncRunner();
        } finally {
            $this->releaseScheduleLock();
        }
    }
    /**
     * @since 0.1.0
     */
    #[Hook(name: 'init', kind: 'action', priority: 20, acceptedArgs: 0)]
    public function ensureRecurringScheduled(): void
    {
        $this->scheduleRecurring();
    }
    /**
     * @since 0.1.0
     */
    public function scheduleRecurring(): void
    {
        if (!function_exists('as_schedule_recurring_action') || !function_exists('as_next_scheduled_action')) {
            return;
        }
        if (as_next_scheduled_action(self::RECURRING_HOOK, [], self::GROUP) !== \false) {
            return;
        }
        as_schedule_recurring_action(time() + \MINUTE_IN_SECONDS, \MINUTE_IN_SECONDS, self::RECURRING_HOOK, [], self::GROUP, \true);
    }
    /**
     * @since 0.1.0
     */
    public function unscheduleAll(): void
    {
        if (!function_exists('as_unschedule_all_actions')) {
            return;
        }
        as_unschedule_all_actions(self::RUN_HOOK, [], self::GROUP);
        as_unschedule_all_actions(self::RECURRING_HOOK, [], self::GROUP);
    }
    /**
     * @since 0.1.0
     */
    private function hasPendingWakeup(): bool
    {
        return as_get_scheduled_actions(['hook' => self::RUN_HOOK, 'group' => self::GROUP, 'status' => 'pending', 'per_page' => 1], 'ids') !== [];
    }
    /**
     * @since 0.1.0
     */
    private function acquireScheduleLock(): bool
    {
        return $this->acquireLock(self::SCHEDULE_LOCK_OPTION, self::SCHEDULE_LOCK_TTL);
    }
    /**
     * @since 0.1.0
     */
    private function releaseScheduleLock(): void
    {
        delete_option(self::SCHEDULE_LOCK_OPTION);
    }
    /**
     * Nudges Action Scheduler's internal async runner so due actions can start
     * without waiting for another site visit.
     *
     * @since 0.1.0
     */
    private function wakeActionSchedulerAsyncRunner(): void
    {
        if (!$this->shouldWakeActionSchedulerAsyncRunner()) {
            return;
        }
        wp_remote_post(add_query_arg(['action' => self::ASYNC_RUNNER_ACTION, 'nonce' => wp_create_nonce(self::ASYNC_RUNNER_ACTION)], admin_url('admin-ajax.php')), [
            'timeout' => 0.01,
            'blocking' => \false,
            'cookies' => $_COOKIE,
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter, not a custom hook.
            'sslverify' => apply_filters('https_local_ssl_verify', \false),
        ]);
    }
    /**
     * @since 0.1.0
     */
    private function shouldWakeActionSchedulerAsyncRunner(): bool
    {
        if (defined('JooosiMailDeps\WP_CLI') && WP_CLI) {
            return \false;
        }
        if (wp_doing_cron() || doing_action('action_scheduler_run_queue')) {
            return \false;
        }
        return function_exists('admin_url');
    }
    /**
     * @since 0.1.0
     */
    private function acquireLock(string $optionName, int $ttl): bool
    {
        $expiresAt = time() + $ttl;
        if (add_option($optionName, (string) $expiresAt, '', \false)) {
            return \true;
        }
        $existingExpiresAt = (int) get_option($optionName, '0');
        if ($existingExpiresAt >= time()) {
            return \false;
        }
        delete_option($optionName);
        return add_option($optionName, (string) $expiresAt, '', \false);
    }
}
