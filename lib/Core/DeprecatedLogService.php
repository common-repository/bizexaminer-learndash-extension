<?php

namespace BizExaminer\LearnDashExtension\Core;

use WP_Error;

/**
 * A service for logging debug and error messages
 *
 * @deprecated 1.1.0 replaced by LogService using LearnDashLogger
 */
class DeprecatedLogService extends LogService
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
    public function __construct(int $logLevel)
    {
        $this->logs = [];
        $this->logLevel = $logLevel;

        $this->isLearnDashAvailable = function_exists('learndash_quiz_debug_log_init');
    }

    /**
     * Sets up the log directory via LearnDash and adds a htaccess to protect log files
     *
     * @uses learndash_quiz_debug_log_init
     * @global quiz_debug_error_log_file
     *
     * @return void
     */
    public function setupLog(): void
    {
        if (!function_exists('learndash_quiz_debug_log_init')) {
            return;
        }
        learndash_quiz_debug_log_init(); // let LearnDash setup the log file
        global $quiz_debug_error_log_file;
        if (empty($quiz_debug_error_log_file)) {
            return;
        }
        /**
         * Add a .htaccess to hide logs - LearnDash does this not atm - bug is reported and open
         * Recommendation from support was to add .htaccess ourselves
         * TODO: When LearnDash adds code to add htaccess in core, remove this
         */
        $debugPath = dirname($quiz_debug_error_log_file);
        $htaccessPath = trailingslashit($debugPath) . '.htaccess';
        if (!file_exists($htaccessPath)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions
            file_put_contents(
                $htaccessPath,
                "Order Deny,Allow \nDeny from all"
            );
        }
    }

    public function getLearnDashLogger(): LearnDashLogger
    {
        throw new \Exception(
            'DeprecatedLogService is for LearnDash versions pre 4.5.0 and does not support LearnDash_Logger'
        );
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
            learndash_quiz_debug_log_message($message);
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
     * Get's all of LearnDashs log files (filenames)
     *
     * @return string[]|false array of log file names (absolute path)
     */
    public function getLogFiles()
    {
        $logDir = $this->getLogFilesDir();
        if (!$logDir) {
            return false;
        }
        $logFiles = glob($logDir . '/*.log', GLOB_NOSORT);

        return $logFiles;
    }

    /**
     * Gets the content of a single log file
     *
     * @param string $logFile logFile name without path and extension
     * @return string|false false on failure of if LD logging is not set up, otherwise file contents
     */
    public function getLogFileContents($logFile)
    {
        $logDir = $this->getLogFilesDir();
        if (!$logDir) {
            return false;
        }
        $filePath = trailingslashit($logDir) . $logFile . '.log';
        if (!file_exists($filePath)) {
            return false;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions
        return file_get_contents($filePath);
    }

    /**
     * Get the path to the log files directory from LearnDash
     *
     * @return string|false false if LD logging is not set up, otherwise absolute path
     */
    protected function getLogFilesDir()
    {
        global $quiz_debug_error_log_file;
        if (!$quiz_debug_error_log_file) {
            return false;
        }
        $logDir = dirname($quiz_debug_error_log_file);
        return $logDir;
    }
}
