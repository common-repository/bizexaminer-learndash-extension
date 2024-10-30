<?php

namespace BizExaminer\LearnDashExtension\Core;

use Learndash_Logger;

/**
 * A logger to use LearnDashs new logging from 4.5.0
 * @since 1.1.0
 */
class LearnDashLogger extends Learndash_Logger
{
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- extended method from parent
    public function get_label(): string
    {
        return esc_html_x('bizExaminer', 'logger instance name', 'bizexaminer-learndash-extension');
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- extended method from parent
    public function get_name(): string
    {
        return 'bizexaminer';
    }
}
