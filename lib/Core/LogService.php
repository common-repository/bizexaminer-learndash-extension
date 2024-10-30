<?php

namespace BizExaminer\LearnDashExtension\Core;

use WP_Error;

/**
 * A service for logging debug and error messages
 * uses LearnDashs LearnDash_Logger system
 */
class LogService
{
    /**
     * Log level constants
     *
     * @var string
     */
    public const LEVEL_ERROR = 'error';
    public const LEVEL_DEBUG = 'debug';

    /**
     * Maps level string constants to int for comparison with $logLevel
     */
    protected const LEVELS = [
        'none' => 0,
        'error' => 1,
        'debug' => 2
    ];

    /**
     * Logs collected during this request
     *
     * @var array
     *          'message' => (string)
     *          'level' => (string)
     *          'time' => (int)
     */
    protected array $logs;

    /**
     * Log level from which on to write to log
     *
     * @var int
     */
    protected int $logLevel;

    /**
     * The LearnDashLogger instance to use
     *
     * @var LearnDashLogger
     */
    protected LearnDashLogger $learnDashLogger;

    /**
     * Whether the LearnDash Plugin is installed
     *
     * @var boolean
     */
    private bool $isLearnDashAvailable;

    /**
     * Creates a new LogService instance
     *
     * @param int $logLevel which level (@see LEVELS) and downwards to log into file
     */
    public function __construct(int $logLevel, LearnDashLogger $learnDashLogger)
    {
        $this->logs = [];
        $this->logLevel = $logLevel;
        $this->learnDashLogger = $learnDashLogger;

        $this->isLearnDashAvailable = class_exists('LearnDash_Logger');
    }

    /**
     * Sets up the log directory via LearnDash and adds a htaccess to protect log files
     *
     * @global quiz_debug_error_log_file
     *
     * @return void
     */
    public function setupLog(): void
    {
        global $quiz_debug_error_log_file;
        if (empty($quiz_debug_error_log_file)) {
            return;
        }
    }

    /**
     * Gets the LearnDashLogger instance
     *
     * @return LearnDashLogger
     */
    public function getLearnDashLogger(): LearnDashLogger
    {
        return $this->learnDashLogger;
    }

    /**
     * Log a message to the log
     * Will be written to the log file if LEARNDASH_QUIZ_DEBUG is true
     * and will be stored in this instances memory
     *
     * @uses learndash_quiz_debug_log_message
     *
     * @param string $logMessage
     * @param string $level
     * @return void
     */
    public function log(string $logMessage, string $level): void
    {
        $this->logs[] = [
            'message' => $logMessage,
            'level' => $level,
            'time' => time()
        ];

        if (
            $this->isLearnDashAvailable &&
            isset(self::LEVELS[$level]) && self::LEVELS[$level] <= $this->logLevel
        ) {
            // outputs "[ERROR]: $message"
            $message = '[' . strtoupper($level) . ']: ' . $logMessage;
            if ($level === self::LEVEL_ERROR) {
                $this->learnDashLogger->error($message);
            } else {
                $this->learnDashLogger->info($message);
            }
        }
    }

    /**
     * Logs any data (array, object) via var_export to the log
     *
     * @uses log
     * @see log
     *
     * @param string $logMessage
     * @param mixed $data
     * @param string $level
     * @return void
     */
    public function logData(string $logMessage, $data, string $level): void
    {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        $message = $logMessage . ': ' . var_export($data, true);
        $this->log($message, $level);
    }

    /**
     * Logs a WP_Error to the log
     *
     * @uses logData
     * @see logData
     *
     * @param WP_Error $error
     * @param string $level defaults to self::LEVEL_ERROR
     * @return void
     */
    public function logError(WP_Error $error, string $level = self::LEVEL_ERROR): void
    {
        $this->logData(
            $error->get_error_message() . ' (' . $error->get_error_code() . ')',
            $error->get_error_data(),
            $level
        );
    }

    /**
     * Whether logs have been collected
     *
     * @return bool
     */
    public function hasLogs(): bool
    {
        return !empty($this->logs);
    }

    /**
     * Get all collected logs during this request
     *
     * @return array
     *          'message' => (string)
     *          'level' => (string)
     *          'time' => (int)
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Whether logging is enabled on a level greater than none (eg error or debug)
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->logLevel > self::LEVELS['none'];
    }
}
