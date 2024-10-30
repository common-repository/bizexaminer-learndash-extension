<?php

namespace BizExaminer\LearnDashExtension\Helper;

use ActionScheduler;

/**
 * An abstraction class over ActionScheduler
 * which is shipped with LearnDash
 *
 * @link https://actionscheduler.org
 */
class Scheduler
{
    /**
     * Group used for ActionScheduler
     *
     * @var string
     */
    protected const SCHEDULER_GROUP = 'bizexaminer';

    /**
     * Check whether the ActionScheduler is available and has been initialized.
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        if (!class_exists('ActionScheduler')) {
            return false;
        }

        if (!function_exists('as_schedule_recurring_action')) {
            return false;
        }

        return ActionScheduler::is_initialized();
    }

    /**
     * Schedule a recurring action
     *
     * @uses as_schedule_recurring_action
     *
     * @param int $intervalSeconds How long to wait between runs.
     * @param string $hook The hook to trigger.
     * @param array $args Arguments to pass when the hook triggers (default: empty array).
     * @return int|false The action ID, false if ActionScheduler is not available
     */
    public static function scheduleRecurring(int $intervalSeconds, string $hook, array $args = [])
    {
        if (!self::isAvailable()) {
            return false;
        }

        return as_schedule_recurring_action(time(), $intervalSeconds, $hook, $args, self::SCHEDULER_GROUP);
    }

    /**
     * Check if there is a scheduled action in the queue with the exact same args.
     *
     * @uses as_has_scheduled_action
     *
     * @param string $hook  The hook of the action.
     * @param array|null  $args  Args that have been passed to the action. Null will matches any args.
     * @return bool True if a matching action is pending or in-progress,
     *              false otherwise (or if ActionScheduler is not available)
     */
    public static function hasScheduled(string $hook, ?array $args = null)
    {
        if (!self::isAvailable()) {
            return false;
        }

        return as_has_scheduled_action($hook, $args, self::SCHEDULER_GROUP);
    }

    /**
     * Queries the scheduled actions
     *
     * @uses as_get_scheduled_actions
     *
     * @param string $hook  The hook of the action.
     * @param array|null  $hookArgs  Args that have been passed to the action. Null will matches any args.
     * @param array $queryArgs args for querying the action schedule store (eg status)
     * @return array|false false if ActionScheduler is not available
     */
    public static function getScheduled(string $hook = '', ?array $hookArgs = null, array $queryArgs = [])
    {
        if (!self::isAvailable()) {
            return false;
        }

        $queryArgs = wp_parse_args($queryArgs, [
            'hook' => $hook,
            'args' => $hookArgs,
            'group' => self::SCHEDULER_GROUP,
            'per_page' => -1
        ]);

        return as_get_scheduled_actions($queryArgs);
    }

    /**
     * Cancel the next (and all) occurrence(s) of a scheduled action.
     *
     * @uses as_unschedule_action
     *
     * @param string $hook The hook that the job will trigger.
     * @param array $args Args that would have been passed to the job.
     * @return int|null|false The scheduled action ID if a scheduled action was found,
     *                        or null if no matching action found, or false if ActionScheduler is not available
     */
    public static function unschedule(string $hook, array $args = [])
    {
        if (!self::isAvailable()) {
            return false;
        }

        return as_unschedule_action($hook, $args, self::SCHEDULER_GROUP);
    }
}
