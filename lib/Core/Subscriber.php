<?php

namespace BizExaminer\LearnDashExtension\Core;

use BizExaminer\LearnDashExtension\Internal\EventManagement\ActionSubscriberInterface;
use BizExaminer\LearnDashExtension\Internal\EventManagement\FilterSubscriberInterface;
use Learndash_Logger;

/**
 * Subscriber for core related hooks
 */
class Subscriber implements ActionSubscriberInterface, FilterSubscriberInterface
{
    /**
     * I18n instance
     *
     * @var I18n
     */
    protected I18n $i18nService;

    /**
     * LogService instance
     *
     * @var LogService
     */
    protected LogService $logService;

    /**
     * Creates a new Subscriber instance
     *
     * @param I18n $i18nService
     * @param LogService $logService
     */
    public function __construct(I18n $i18nService, LogService $logService)
    {
        $this->i18nService = $i18nService;
        $this->logService = $logService;
    }

    public function getSubscribedActions(): array
    {
        return [
            'init' => 'initI18n',
            // learndash_quiz_debug_log_init uses LEARNDASH_TEMPLATES_DIR which only gets set in after_setup_theme
            'after_setup_theme' => ['setupLogger', 51],
        ];
    }

    public function getSubscribedFilters(): array
    {
        return [
            'learndash_loggers' => ['setupLearnDashLogger'],
            'option_learndash_logs' => ['filterEnabledLearnDashLoggers'],
        ];
    }

    /**
     * Load i18n (internationalization)
     *
     * @hooked on 'init'
     *
     * @return void
     */
    public function initI18n(): void
    {
        $this->i18nService->loadTextdomain();
    }

    /**
     * Setup logger
     *
     * @hooked on 'after_setup_theme' (because of used LearnDash function)
     *
     * @return void
     */
    public function setupLogger(): void
    {
        $this->logService->setupLog();
    }

    /**
     * Registers the LearnDashLogger instance
     *
     * @since 1.1.0
     * @hooked on 'learndash_loggers'
     *
     * @param Learndash_Logger[] $loggers List of logger instances.
     * @return Learndash_Logger[]
     */
    public function setupLearnDashLogger(array $loggers): array
    {
        $loggers[] = $this->logService->getLearnDashLogger();
        return $loggers;
    }

    /**
     * LearnDash has a setting to enable/disable loggers.
     * Since the bizExaminer logger is enabled by a wp-config.php constant
     * use that to overwrite this option.
     * So admins do not have to enable the constant in wp-config.php AND in LearnDash settings.
     *
     * @hooked on 'option_learndash_logs'
     *
     * @param string|array|mixed $enabledLoggers
     * @return array
     */
    public function filterEnabledLearnDashLoggers($enabledLoggers): array
    {
        if (empty($enabledLoggers) || !is_array($enabledLoggers)) {
            $enabledLoggers = [];
        }

        $learndashLogger = $this->logService->getLearnDashLogger();

        if ($this->logService->isEnabled()) {
            $enabledLoggers[$learndashLogger->get_name()] = 'yes';
        } else {
            unset($enabledLoggers[$learndashLogger->get_name()]);
        }

        return $enabledLoggers;
    }
}
