<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings;

use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\EventManagerAwareTrait;

/**
 * Service for quiz settings
 */
class QuizSettingsService implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * Setting key for enabled status - inside the LearnDash quiz settings array
     *
     * @var string
     */
    public const SETTING_KEY_ENABLED = 'bizExaminerEnabled';

    /**
     * Setting key for credentials - inside the LearnDash quiz settings array
     *
     * @var string
     */
    public const SETTING_KEY_CREDENTIALS = 'bizExaminerApiCredentials';

    /**
     * Setting key for exam module id - inside the LearnDash quiz settings array
     *
     * @var string
     */
    public const SETTING_KEY_EXAM_MODULE = 'bizExaminerExamModule';

    /**
     * Setting key for exam module id - inside the LearnDash quiz settings array
     *
     * @var string
     */
    public const SETTING_KEY_REMOTE_PROCTOR = 'bizExaminerRemoteProctoring';

    /**
     * Setting key for exam module id - inside the LearnDash quiz settings array
     *
     * @var string
     */
    public const SETTING_KEY_CERTIFICATE = 'bizExaminerCertificate';

    /**
     * Setting key for import external attempts status - inside the LearnDash quiz settings array
     *
     * @var string
     */
    public const SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS = 'bizExaminerImportExternalAttempts';

    /**
     * Setting key for disabling starting the Quiz from LearnDash when import external attempts is enabled.
     *
     * @var string
     */
    public const SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS_DISABLE_START = 'bizExaminerImportExternalAttemptsDisableStart';

    /**
     * Get's the quiz configured bizExaminer settings
     *
     * @param int $quizId The post type id (not the pro quiz id)
     *                   use learndash_get_quiz_id_by_pro_quiz_id to map them
     * @return array|false False if bizExaminer is not enabled for this quiz or array:
     *             'credentials' => (string) ID of the saved API credentials,
     *             'examModule' => (string) ID of exam module & contentRevision ({$productPartsId}_{$contentRevisionId})
     */
    public function getQuizSettings(int $quizId)
    {
        $settings = learndash_get_setting($quizId);

        if (
            !isset($settings[self::SETTING_KEY_ENABLED]) ||
            !$settings[self::SETTING_KEY_ENABLED]
        ) {
            return false;
        }

        if (empty($settings[self::SETTING_KEY_CREDENTIALS])) {
            return false;
        }

        // does not check if api credentials still exist / work correctly

        $returnSettings = [
            'credentials' => $settings[self::SETTING_KEY_CREDENTIALS],
            'examModule' => $settings[self::SETTING_KEY_EXAM_MODULE],
            'remoteProctor' => null, // default, overwrite below
            'remoteProctorSettings' => [], // default, overwrite below
            'useBeCertificate' => $settings[self::SETTING_KEY_CERTIFICATE],
            'importExternalAttempts' => $settings[self::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS],
            'importExternalAttemptsDisableStart' => $settings[self::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS_DISABLE_START],
        ];

        $remoteProctorString = $settings[self::SETTING_KEY_REMOTE_PROCTOR];

        if (!empty($remoteProctorString) && $remoteProctorString !== '-1') {
            /**
             * frontend stores it with {$proctorType}_-_{$proctorAccountName} for frontend JS
             * @see MetaBox
             */
            $remoteProctorAccountParts = explode('_-_', $remoteProctorString);
            if (count($remoteProctorAccountParts) === 2) {
                $remoteProctorAccountType = $remoteProctorAccountParts[0];
                $remoteProctorAccount = $remoteProctorAccountParts[1];
                $returnSettings['remoteProctor'] = $remoteProctorAccount;

                /**
                 * @see MetaBoxHelper::buildRemoteProctorFieldName
                 */
                $proctorFieldPrefix = self::SETTING_KEY_REMOTE_PROCTOR . "_{$remoteProctorAccountType}_";

                foreach ($settings as $key => $value) {
                    if (!str_starts_with($key, $proctorFieldPrefix)) {
                        continue;
                    }
                    $singleKey = substr($key, strlen($proctorFieldPrefix));
                    $returnSettings['remoteProctorSettings'][$singleKey] = $value;
                }
            }
        }

        /**
         * Filters the bizExaminer related settings of a quiz
         *
         * @param array $quizSettings The bizExaminer quiz settings
         * @param int $quizId The quiz post type id (not the pro quiz id)
         */
        $this->eventManager->apply_filters('bizexaminer/quizSettings', $returnSettings, $quizId);

        return $returnSettings;
    }

    /**
     * Get's all LearnDash quiz settings
     *
     * @param int $quizId The post type id (not the pro quiz id)
     *                   use learndash_get_quiz_id_by_pro_quiz_id to map them
     * @return array
     */
    public function getAllQuizSettings(int $quizId)
    {
        $settings = learndash_get_setting($quizId);
        return $settings;
    }

    /**
     * Counts quizes which have the bizExaminer enabled
     *
     * @return int
     */
    public function getQuizCountWithBizExaminer(): int
    {
        $ids = $this->findQuizBySetting(self::SETTING_KEY_ENABLED, true);
        return count($ids);
    }

    /**
     * Counts quizes which have the credentials ID configured for bizExaminer
     *
     * @param string $apiCredentialsId
     * @return int
     */
    public function getQuizCountWithApiCredentials($apiCredentialsId): int
    {
        $ids = $this->findQuizBySetting(self::SETTING_KEY_CREDENTIALS, $apiCredentialsId);
        return count($ids);
    }

    /**
     * Finds a quiz by searching for a specific value of a setting key.
     * Can be used to search through LearnDash/bizExaminer quiz settings.
     * This uses REGEX, because LearnDash stores all quiz settings in a serialized array.
     *
     * The regex is copied form Learndash_Admin_Filter_Meta::get_sql_where_clause
     * and Learndash_Site_Health::map_meta_value_from_setting
     *
     * Results can be filtered before and after seraching in database.
     *
     * Returns an array of quiz ids (=post type ids)
     *
     * @see Learndash_Admin_Filter_Meta::get_sql_where_clause
     * @see Learndash_Site_Health::map_meta_value_from_setting
     *
     * @watch Learndash_Admin_Filter_Meta::get_sql_where_clause
     * @watch Learndash_Site_Health::map_meta_value_from_setting
     *
     * @param string $settingKey
     * @param mixed $value Can be a string value or a bool
     * @return int[]
     */
    public function findQuizBySetting(string $settingKey, $value = ''): array
    {
        global $wpdb;

        /**
         * Allows short-circuiting the findQuiz query, by returning a non-null value
         * So you can do your own query (if you know exactly what you're searching for)
         *
         * @since 1.4.0
         *
         * @param int[] $ids The post ids, return a non-null value to short-circuit
         * @param string $settingKey The setting key to search for
         * @param string $value The value to compare the setting key against
         * @param QuizService $quizService The quizService instance this is running in
         */
        $preResults = $this->eventManager->apply_filters('bizexaminer/findQuiz/pre', null, $settingKey, $value, $this);
        if ($preResults !== null) {
            return array_map('absint', (array) $preResults);
        }

        if (is_bool($value)) {
            // For bool values check the type of the serialized value
            // true values will be b:1, false values will be a b:0 or s:0 (when empty value from form)
            if ($value === true) {
                $serializedRegex = '";b:1;';
            } else {
                $serializedRegex = '";(s:0:""|b:0)';
            }
        } else {
            $value = (string) $value;
            /**
             * Regex design by LearnDash core, see
             * Learndash_Admin_Filter_Meta::get_sql_where_clause for explanation
             *
             * @see Learndash_Admin_Filter_Meta::get_sql_where_clause
             */
            $serializedRegex = '' !== $value
                ? '";.:[^;]*:?"?' . $wpdb->esc_like($value) . '"?;'
                : '";.:[^;]*:?"";';
        }


        $quizPostTypeSlug = learndash_get_post_type_slug('quiz');

        // key in '_sfwd-quiz' is 'sfwd-quiz_$setting'
        $settingNestedKey = "${quizPostTypeSlug}_${settingKey}";
        // eg "sfwd-quiz_bizExaminerApiCredentials";.:[^;]*:?"?63e36d17c60ee"?;
        $like = '"' . $settingNestedKey . $serializedRegex;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.id FROM {$wpdb->posts} AS p
            JOIN {$wpdb->postmeta} AS pm on p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key = %s
            AND pm.meta_VALUE RLIKE %s",
            $quizPostTypeSlug,
            "_$quizPostTypeSlug",
            $like
        ), 0);

        $ids = array_map('absint', (array) $ids);

        /**
         * Allows filtering the post ids the findQuiz query
         *
         * @since 1.4.0
         *
         * @param int[] $ids The post ids
         * @param string $settingKey The setting key to search for
         * @param string $value The value to compare the setting key against
         * @param QuizService $quizService The quizService instance this is running in
         */
        $ids = $this->eventManager->apply_filters('bizexaminer/findQuiz', $ids, $settingKey, $value, $this);

        return array_map('absint', (array) $ids);
    }
}
