<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Settings;

use BizExaminer\LearnDashExtension\Core\I18n;
use BizExaminer\LearnDashExtension\Helper\I18n as HelperI18n;
use BizExaminer\LearnDashExtension\Helper\Scheduler;
use BizExaminer\LearnDashExtension\Internal\Interfaces\CacheAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\LogServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\SettingsServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\CacheAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\EventManagerAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\LogServiceAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\SettingsServiceAwareTrait;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizAttempt;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizAttemptsDataStore;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings\QuizSettingsService;

/**
 * Service for collecting system/bizExaminer-data for support
 */
class SupportSectionHelper implements
    SettingsServiceAwareInterface,
    LogServiceAwareInterface,
    EventManagerAwareInterface,
    CacheAwareInterface
{
    use SettingsServiceAwareTrait;
    use LogServiceAwareTrait;
    use EventManagerAwareTrait;
    use CacheAwareTrait;

    /**
     * QuizSettingsService instance to use
     *
     * @var QuizSettingsService
     */
    protected QuizSettingsService $quizSettingsService;

    /**
     * QuizAttemptsDataStore instance to use
     *
     * @var QuizAttemptsDataStore
     */
    protected QuizAttemptsDataStore $quizAttempts;

    /**
     * Creates a new SupportSectionHelper instance
     *
     * @param QuizSettingsService $quizSettingsService QuizSettingsService instance to use
     * @param QuizAttemptsDataStore $quizAttempts QuizAttemptsDataStore instance to use
     */
    public function __construct(QuizSettingsService $quizSettingsService, QuizAttemptsDataStore $quizAttempts)
    {
        $this->quizSettingsService = $quizSettingsService;
        $this->quizAttempts = $quizAttempts;
    }

    /**
     * Gets the data formatted for display in LearnDash Support Screen
     * @see LearnDash_Settings_Page_Support::show_support_section
     *
     * @return array
     */
    public function getSupportScreenData(): array
    {
        $data = $this->getData();
        $data = array_map(function ($entry) {
            return [
                'label' => $entry['label'],
                'label_html' => $entry['label'],
                'value' => $entry['value'],
            ];
        }, $data);

        return [
            'header' => [
                'html' => esc_html_x(
                    'bizExaminer Data',
                    'support screen data heading',
                    'bizexaminer-learndash-extension'
                ),
                'text' => _x('bizExaminer Data', 'support screen data heading', 'bizexaminer-learndash-extension')
            ],
            'columns' => [
                'label' => [
                    'html'  => esc_html__('Data', 'learndash'),
                    'text'  => 'Setting',
                    'class' => 'learndash-support-settings-left',
                ],
                'value' => [
                    'html'  => esc_html__('Value', 'learndash'),
                    'text'  => 'Value',
                    'class' => 'learndash-support-settings-right',
                ],
            ],
            'settings' => $data
        ];
    }

    /**
     * Get's the raw support data
     *
     * @since 1.1.0 added $includeActions and $includeLogs params
     * @since 1.0.0
     *
     * @param bool $includeActions Wether to include links to actions (eg cache purging)
     * @return array
     */
    public function getData(bool $includeActions = true, bool $includeLogs = true): array
    {
        $data = [];

        $apiCredentials = $this->getSettingsService()->getSetting('api_credentials');
        $data['api_credentials'] = [
            'label' => 'API Credentials',
            'value' => count($apiCredentials)
        ];

        $quizzesWithBizExaminer = $this->quizSettingsService->getQuizCountWithBizExaminer();
        $data['quizzes_be'] = [
            'label' => 'Quizzes with bizExaminer enabled',
            'value' => $quizzesWithBizExaminer
        ];

        $quizzesWithBizExaminerCredentials = 0;
        foreach ($apiCredentials as $id => $apiCredential) {
            $quizzesWithBizExaminerCredentials += $this->quizSettingsService->getQuizCountWithApiCredentials($id);
        }
        $data['quizzes_be_credentials'] = [
            'label' => 'Quizzes with valid bizExaminer credentials',
            'value' => $quizzesWithBizExaminerCredentials
        ];

        $quizzesWithExternalAttemptImport = $this->quizSettingsService->findQuizBySetting(
            QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS,
            true
        );
        $data['quizzes_be_import_external_attempts'] = [
            'label' => 'Quizzes with import external attempts enabled',
            'value' => count($quizzesWithExternalAttemptImport)
        ];

        $completedCrons = Scheduler::getScheduled('', null, ['status' => 'complete']);
        $scheduledCrons = Scheduler::getScheduled('', null, ['status' => ['pending', 'in-progress']]);
        $failedCrons = Scheduler::getScheduled('', null, ['status' => 'failed']);
        $data['crons_scheduled'] = [
            'label' => 'Scheduled Action Schedules',
            'value' => count($scheduledCrons),
        ];
        $data['crons_completed'] = [
            'label' => 'Completed Action Schedules',
            'value' => count($completedCrons)
        ];
        $data['crons_failed'] = [
            'label' => 'Failed Action Schedules',
            'value' => count($failedCrons)
        ];

        // Timezone Error info
        // @see I18n::getTimezone
        $timezone = HelperI18n::getIsoTimezone();
        $data['timezone'] = [
            'label' => 'Timezone',
            'value' => $timezone ?: '<strong style="color:red">' . __(
                'Please set a valid ISO timezone in your WordPress settings',
                'bizexaminer-learndash-extension'
            ) . '</strong>'
        ];

        if ($includeLogs) {
            /**
             * @deprecated 1.1.0
             * uses old logging from LearnDash pre 4.5.0 - will be removed in 1.2.0
             * TODO: remove in 1.2.0
             */
            if (is_callable([$this->logService, 'getLogFiles'])) {
                $logFiles = $this->logService->getLogFiles();
                $logFilesValue = 'None';
                if (!empty($logFiles)) {
                    $logFilesValue = '<ul>';
                    // add links to show log file contents inline
                    // since files can't be accessed because of .htaccess protection
                    foreach ($logFiles as $logFilePath) {
                        $logFile = basename($logFilePath, '.log');
                        if ($includeActions) {
                            $actionUrl = add_query_arg([
                                'be-action' => 'download-ld-log',
                                'be-log' => $logFile,
                                '_wpnonce' => wp_create_nonce('be-support-action')
                            ]);
                            $actionUrl .= '#bizexaminer_data_bizexaminer_data'; // jump to section
                            $logFilesValue .= sprintf(
                                '<li><a href="%1$s" >%2$s</a></li>',
                                esc_url($actionUrl),
                                $logFile
                            );
                        } else {
                            $logFilesValue .= sprintf('<li>%1$s</li>', $logFile);
                        }
                    }
                    $logFilesValue .= '</ul>';
                }
                $data['log_files'] = [
                    'label' => 'Log files',
                    'value' => $logFilesValue,
                ];
            } elseif (is_callable([$this->logService, 'getLearnDashLogger'])) {
                $data['log_files'] = [
                    'label' => 'Log file',
                    'value' => sprintf(
                        '<a href="%1$s">%2$s</a>',
                        esc_url($this->logService->getLearnDashLogger()->get_download_url()),
                        __('Download log file', 'bizexaminer-learndash-extension')
                    ),
                ];
            }
        }

        if ($includeActions) {
            $deleteAttemptsActionUrl = add_query_arg([
                'be-action' => 'delete-quiz-attempts',
                '_wpnonce' => wp_create_nonce('be-support-action')
            ]);
            $data['delete_quizattempts'] = [
                'label' => '<strong>Warning:</strong> Delete all quiz attempts',
                'value' => '<a href="' . esc_url($deleteAttemptsActionUrl) . '">Delete</a>'
            ];

            $deletelRunningAttemptsActionUrl = add_query_arg([
                'be-action' => 'delete-running-quiz-attempts',
                '_wpnonce' => wp_create_nonce('be-support-action')
            ]);
            $data['delete_running_quizattempts'] = [
                'label' => '<strong>Warning:</strong> Delete all running quiz attempts',
                'value' => '<a href="' . esc_url($deletelRunningAttemptsActionUrl) . '">Delete</a>'
            ];

            $deletePendingAttemptsActionUrl = add_query_arg([
                'be-action' => 'delete-pending-quiz-attempts',
                '_wpnonce' => wp_create_nonce('be-support-action')
            ]);
            $data['delete_pending_quizattempts'] = [
                'label' => '<strong>Warning:</strong> Delete all pending quiz attempts',
                'value' => '<a href="' . esc_url($deletePendingAttemptsActionUrl) . '">Delete</a>'
            ];

            $purgeCachesActionUrl = add_query_arg([
                'be-action' => 'purge-caches',
                '_wpnonce' => wp_create_nonce('be-support-action')
            ]);
            $data['purge_caches'] = [
                'label' => '<strong>Warning:</strong> Purge caches',
                'value' => '<a href="' . esc_url($purgeCachesActionUrl) . '">Purge</a>'
            ];
        }

        /**
         * Filters the debug data for support
         *
         * @param array $data The support debug data in format for LearnDash
         */
        $data = $this->eventManager->apply_filters('bizexaminer/support/data', $data);

        return $data;
    }

    /**
     * Handles custom support page actions
     * nonce and capabilities should be checked before calling this function
     *
     * @param string $action
     * @return void
     */
    public function handleAction(string $action, array $queryVars)
    {
        switch ($action) {
            case 'download-ld-log':
                $logFileContents = '';
                /**
                 * @deprecated 1.1.0
                 * uses old logging from LearnDash pre 4.5.0 - will be removed in 1.2.0
                 * TODO: remove in 1.2.0
                 */
                if (is_callable([$this->logService, 'getLogFileContents'])) {
                    if (!isset($queryVars['be-log'])) {
                        return;
                    }
                    // show log file contents inline, since files can't be accessed because of .htaccess protection
                    $logFile = $queryVars['be-log'];
                    $logFileContents = $this->logService->getLogFileContents($logFile);
                } elseif (is_callable([$this->logService, 'getLearnDashLogger'])) {
                    $logFileContents = $this->logService->getLearnDashLogger()->get_content();
                }
                if (empty($logFileContents)) {
                    return;
                }
                printf('<h3>Log File Contents (%s):</h3>', esc_html($logFile ?? ''));
                echo '<pre>';
                echo esc_html($logFileContents);
                echo '</pre>';
                break;
            case 'delete-running-quiz-attempts':
                $users = get_users(['fields' => 'ID']);
                foreach ($users as $userId) {
                    $attempts = $this->quizAttempts->findUserQuizAttempts(
                        $userId,
                        'be_status',
                        QuizAttempt::STATUS_STARTED
                    );
                    foreach ($attempts as $attempt) {
                        $this->quizAttempts->deleteQuizAttempt($userId, $attempt->getId());
                    }
                }
                break;
            case 'delete-pending-quiz-attempts':
                $users = get_users(['fields' => 'ID']);
                foreach ($users as $userId) {
                    $attempts = $this->quizAttempts->findUserQuizAttempts(
                        $userId,
                        'be_status',
                        QuizAttempt::STATUS_PENDING_RESULTS
                    );
                    foreach ($attempts as $attempt) {
                        $this->quizAttempts->deleteQuizAttempt($userId, $attempt->getId());
                    }
                }
                break;
            case 'delete-quiz-attempts':
                $users = get_users(['fields' => 'ID']);
                foreach ($users as $userId) {
                    $attempts = $this->quizAttempts->findUserQuizAttempts(
                        $userId,
                        'bizExaminer',
                        1
                    );
                    foreach ($attempts as $attempt) {
                        $this->quizAttempts->deleteQuizAttempt($userId, $attempt->getId());
                    }
                }
                break;
            case 'purge-caches':
                $this->cacheService->deleteAll();
                break;
        }
    }
}
