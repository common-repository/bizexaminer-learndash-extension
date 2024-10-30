<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz;

use BizExaminer\LearnDashExtension\LearnDash\Compat;
use WpProQuiz_Controller_Quiz;
use WpProQuiz_Model_PrerequisiteMapper;
use WpProQuiz_Model_Quiz;
use WpProQuiz_Model_QuizMapper;

/**
 * Class for handling if a user has access to a quiz
 * and is allowed to start a quiz
 */
class QuizAccess
{
    /**
     * QuizAttemptsDataStore instance to use
     *
     * @var QuizAttemptsDataStore
     */
    protected QuizAttemptsDataStore $quizAttempts;

    /**
     * LearnDash Quiz mapper class to get pro quiz
     *
     * @var WpProQuiz_Model_QuizMapper
     */
    protected WpProQuiz_Model_QuizMapper $ldQuizMapper;

    /**
     * Creates a new QuizAccess Instance
     *
     * @param QuizAttemptsDataStore $quizAttempts QuizAttemptsDataStore instance to use
     */
    public function __construct(QuizAttemptsDataStore $quizAttempts)
    {
        $this->quizAttempts = $quizAttempts;

        // always create a new instance, treate it like a data object not like a service
        $this->ldQuizMapper = new WpProQuiz_Model_QuizMapper();
    }

    /**
     * Checks if the user is allowed to start the quiz
     * Settings Taken from WpProQuiz_View_FrontQuiz::script
     * which get's the configuration data and passes it to LearnDashs JavaScript which renders the frontend
     *
     * @see WpProQuiz_View_FrontQuiz::script ($bo uses bitoperators for settings)
     * @watch WpProQuiz_View_FrontQuiz::script
     *
     * handled: autoStart, checkBeforeStart (for prerequisites, registerd useres, only rune once)
     *
     * not handled: randomAnswer, randomQuestion, disabledAnswerMark, preview,
     *  sortCategories, maxShowQuestion, formActivated, hideQuestionPositionOverview,
     *  forcingQuestionSolve, skipButton, reviewQustion, cors, quizSummeryHide,
     *
     * TODO: maybe handle: isAddAutomatic (for toplist)
     *
     * @param int $quizId
     * @param int $userId
     * @param bool $checkRunning Whether disable starting if there is a running quiz. Defaults to true.
     * @return bool
     */
    public function canStartQuiz(int $quizId, int $userId, bool $checkRunning = true): bool
    {
        /**
         * 1. Check if user is logged in and exists
         */
        if (!$userId || !get_userdata($userId)) {
            return false;
        }

        /**
         * 2. Check if there are pending results
         */
        if ($this->quizAttempts->getQuizAttemptWithStatus($quizId, $userId, QuizAttempt::STATUS_PENDING_RESULTS)) {
            return false;
        }

        /**
         * 3. Check if there is a running quiz attempt that is still valid.
         * Gets the latest (=newest) running quiz attempt if multiple
         */
        if ($checkRunning) {
            $runningQuizAttempt = $this->quizAttempts->getQuizAttemptWithStatus(
                $quizId,
                $userId,
                QuizAttempt::STATUS_STARTED,
                false
            );
            // Only take a running quiz attempt as valid if it exists,
            // AND has a valid_until_date that is in the future.
            // If conditionals are true, assume the user should be able to resume it and not start a new one.
            // QuizFrontend::renderQuiz should do the same checks but additonally tries to get a examAccesUrl
            if (
                $runningQuizAttempt &&
                ($runningQuizAttempt->get('be_valid_until') && $runningQuizAttempt->get('be_valid_until') > time())
            ) {
                return false;
            }
        }

        $proQuiz = $this->getProQuiz($quizId);

        /**
         * 4. Check if prerequisites are missting
         */
        $missingPrerequisites = $this->getMissingQuizPrerequisites($proQuiz->getId(), $userId);
        if (!empty($missingPrerequisites)) {
            return false;
        }

        /**
         * 5. Check if retakes are allowed / user reached max. retakes
         */
        if (!$this->isRetakeAllowed($quizId, $proQuiz, $userId)) {
            return false;
        }

        /**
         * 6. Recheck with LearnDashs helper function
         */
        if (!learndash_is_quiz_accessable($userId, get_post($quizId))) {
            return false;
        }

        return true;
    }

    /**
     * Checks if retakes are limited and if user can still take a new attempt
     *
     * Handles running quiz only once for a user
     * Seems LearnDash does not handle that in WpProQuiz_Controller_Quiz::isLockQuiz
     *  therefore use a custom solution to check quiz attempts
     * TODO: when LearnDash handles that in WpProQuiz_Controller_Quiz::isLockQuiz - adapt
     *
     * @see WpProQuiz_Controller_Quiz::isLockQuiz
     * @watch WpProQuiz_Controller_Quiz::isLockQuiz
     *
     * @param WpProQuiz_Model_Quiz $proQuiz
     * @param integer $userId
     * @return boolean
     */
    public function isRetakeAllowed(int $quizId, WpProQuiz_Model_Quiz $proQuiz, int $userId)
    {
        if (!$proQuiz->isQuizRunOnce()) {
            return true;
        }

        $ldQuizSettings = learndash_get_setting($quizId);
        $maxRepeats = 1;
        /**
         * @see learndash_quiz_get_repeats
         * @watch learndash_quiz_get_repeats
         */
        if (
            !empty($ldQuizSettings['retry_restrictions']) && $ldQuizSettings['retry_restrictions'] === 'on' &&
            !empty($ldQuizSettings['repeats'])
        ) {
            $maxRepeats = intval($ldQuizSettings['repeats']);
        }
        $quizAttempts = $this->quizAttempts->findUserQuizAttempts($userId, 'quiz', $quizId);
        // maxRepeats = retakes (original take + retakes) - therefore +1
        if ($quizAttempts && count($quizAttempts) - 1 >= $maxRepeats) {
            return false;
        }

        return true;
    }

    /**
     * Get's the name of missing prerequisite quizes
     * WpProQuiz_Controller_Quiz::isLockQuiz can't be called directly
     *  because it uses the $_POST['quizId'] variable,
     * because normally it's called via ajax
     *
     * copying the code also does not work, because somewhere deeper LearnDash overwrites a quizId
     *  by some shortcode attributes
     *
     * the only (dirty) solution is to copy the code from there
     *
     * Taken from WpProQuiz_Controller_Quiz::isLockQuiz
     * which uses the global $_POST variable
     *
     * @see WpProQuiz_Controller_Quiz::isLockQuiz
     * @watch WpProQuiz_Controller_Quiz::isLockQuiz
     *
     * @param int $quizProId
     * @param int $userId
     * @return string[] empty array if all prerequisites are met or no prerequisites required
     */
    public function getMissingQuizPrerequisites(int $quizProId, int $userId): array
    {
        $missingQuizes = [];

        /**
         * Taken from WpProQuiz_Controller_Quiz::isLockQuiz
         *
         * EDITS:
         * - removed else-case for non-logged-in users, don't need to handle that case
         * - removed commented out code
         */
        $quizMapper         = new WpProQuiz_Model_QuizMapper();
        $prerequisiteMapper = new WpProQuiz_Model_PrerequisiteMapper();
        $quizIds = array();

        if ($userId > 0) {
            $quizIds = $prerequisiteMapper->getNoPrerequisite($quizProId, $userId);
        }

        if (!empty($quizIds)) {
            $post_quiz_ids = array();
            foreach ($quizIds as $pro_quiz_id) {
                // !! use custom version until LearnDash fixes learndash_get_quiz_id_by_pro_quiz_id (see #23)
                $post_quiz_id = Compat::learndash_get_quiz_id_by_pro_quiz_id($pro_quiz_id);
                if (!empty($post_quiz_id)) {
                    $post_quiz_ids[$post_quiz_id] = $pro_quiz_id;
                }
            }
            if (!empty($post_quiz_ids)) {
                $post_quiz_ids = learndash_is_quiz_notcomplete($userId, $post_quiz_ids, true, -1);
                if (!empty($post_quiz_ids)) {
                    // @phpstan-ignore-next-line (Wrong type comes from LearnDash)
                    $quizIds = array_values($post_quiz_ids);
                } else {
                    $quizIds = array();
                }
            }

            if (!empty($quizIds)) {
                $missingQuizes = $quizMapper->fetchCol($quizIds, 'name');
            }
        }

        return $missingQuizes;
    }

    /**
     * Get the WP Pro Quiz Instance by a quiz post ID
     *
     * @param int $quizId The quiz' post ID (NOT quiz pro id)
     * @return WpProQuiz_Model_Quiz
     */
    protected function getProQuiz(int $quizId): WpProQuiz_Model_Quiz
    {
        $quizProid = get_post_meta($quizId, 'quiz_pro_id', true);
        $proQuiz = $this->ldQuizMapper->fetch($quizProid);

        return $proQuiz;
    }
}
