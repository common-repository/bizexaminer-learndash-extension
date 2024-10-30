<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz;

use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizAttempt;

/**
 * Data store for accessing quiz attempts
 * LearnDash stores quiz attempts as an array in the users meta
 */
class QuizAttemptsDataStore
{
    /**
     * Gets data for a quiz attempt
     *
     * @param int $userId
     * @param int $quizId The quiz' post ID (NOT quiz pro id)
     * @param string $quizAttemptId
     * @return QuizAttempt|false
     */
    public function getQuizAttempt(int $userId, int $quizId, string $quizAttemptId)
    {
        $userQuizAttempts = $this->getUserQuizAttemptsData($userId);
        // do not use array indices, because they may change
        $attemptIndex = $this->findFirstQuizAttempt('be_id', $quizAttemptId, $userQuizAttempts);
        if ($attemptIndex === false) {
            return false;
        }

        if ($userQuizAttempts[$attemptIndex]['quiz'] !== $quizId) {
            return false;
        }

        return new QuizAttempt($quizId, $userId, $userQuizAttempts[$attemptIndex]);
    }

    /**
     * Checks whether the user has a quiz attempt with the passed status
     *
     * @param int $quizId The quiz' post ID (NOT quiz pro id)
     * @param int $userId
     * @param string $status One of
     *                              QuizAttempt::STATUS_STARTED, QuizAttempt::STATUS_PENDING_RESULTS,
     *                              QuizAttempt::STATUS_COMPLETED, QuizAttempt::STATUS_CANCELED
     * @param bool $first whether to return the first (earliest) or last (latest) found result
     * @return QuizAttempt|false
     */
    public function getQuizAttemptWithStatus(int $quizId, int $userId, string $status, bool $first = true)
    {
        $userQuizAttempts = $this->getUserQuizAttemptsData($userId);
        $quizAttempts = $this->filterQuizAttempts('quiz', $quizId, $userQuizAttempts);
        if (empty($quizAttempts)) {
            return false;
        }

        if (
            !in_array(
                $status,
                [
                    QuizAttempt::STATUS_STARTED, QuizAttempt::STATUS_PENDING_RESULTS,
                    QuizAttempt::STATUS_COMPLETED, QuizAttempt::STATUS_CANCELED
                ]
            )
        ) {
            return false;
        }

        $runningAttempts = $this->filterQuizAttempts('be_status', $status, $quizAttempts);

        if (empty($runningAttempts)) {
            return false;
        }

        $foundAttempt = $first ?
            $runningAttempts[array_key_first($runningAttempts)] : $runningAttempts[array_key_last($runningAttempts)];

        return new QuizAttempt($quizId, $userId, $foundAttempt);
    }

    /**
     * Creates and stores a new quiz attempt in the users meta
     *
     * @since 1.4.0: add $save parameter
     *
     * @param int $userId
     * @param int $quizId The quiz' post ID (NOT quiz pro id)
     * @param array $examModule
     *              'contentRevision' => (string) content revision id

     * @param bool $start whether the quiz attempt should be set to start right now
     * @param bool $save Whether the attempt should be saved right now (default: true)
     * @return QuizAttempt|false quiz attempt or false on error
     */
    public function createQuizAttempt(
        int $userId,
        int $quizId,
        array $examModule,
        bool $start = true,
        bool $save = true
    ) {
        /**
         * Taken from WpProQuiz_View_FrontQuiz::script
         * which generates the data passed to the client which will then be saved in the quiz attempt
         *
         * @see WpProQuiz_View_FrontQuiz::script
         * @watch WpProQuiz_View_FrontQuiz::script
         */
        $quizProId = get_post_meta($quizId, 'quiz_pro_id', true);
        $courseId = learndash_get_course_id($quizId);
        if (empty($courseId)) {
            $sharedSteps = \LearnDash_Settings_Section::get_section_setting(
                'LearnDash_Settings_Courses_Builder',
                'shared_steps'
            );
            if ($sharedSteps !== 'yes') {
                $courseId = learndash_get_setting($quizId, 'course');
                $courseId = absint($courseId);
            }
        }
        // Lesson ID
        $lessonId = 0;
        if (!empty($courseId)) {
            $lessonId = learndash_course_get_single_parent_step($courseId, $quizId, 'sfwd-lessons');
        }
        // Topic ID
        $topicId = 0;
        if (!empty($courseId)) {
            $topicId = learndash_course_get_single_parent_step($courseId, $quizId, 'sfwd-topic');
        }

        $quizAttempt = new QuizAttempt($quizId, $userId, [
            'time' => time(),
            'started' => $start ? time() : 0,
            'be_contentRevision'  => $examModule['contentRevision'],
            'course'              => !empty($courseId) ? $courseId : 0,
            'lesson'              => !empty($lessonId) ? $lessonId : 0,
            'topic'               => !empty($topicId) ? $topicId : 0,
            'pro_quizid'          => $quizProId,
        ]);

        if (!$save) {
            return $quizAttempt;
        }

        $success = $this->addQuizAttempt($quizAttempt);

        if ($success) {
            return $quizAttempt;
        }
        return false;
    }

    /**
     * Saves a new QuizAttempt instance to the database
     *
     * @since 1.4.0
     *
     * @param QuizAttempt $quizAttempt
     * @return bool
     */
    public function addQuizAttempt(QuizAttempt $quizAttempt): bool
    {
        $attemptData = $quizAttempt->getData();

        $userQuizAttempts = $this->getUserQuizAttemptsData($quizAttempt->getUserId());
        $userQuizAttempts[] = $attemptData;
        return $this->updateUserQuizAttemptsData($quizAttempt->getUserId(), $userQuizAttempts);
    }

    /**
     * Saves an existing QuizAttempt instance to the database
     *
     * @param QuizAttempt $quizAttempt
     * @return bool
     */
    public function saveQuizAttempt(QuizAttempt $quizAttempt): bool
    {
        $userQuizAttempts = $this->getUserQuizAttemptsData($quizAttempt->getUserId());
        $attemptIndex = $this->findFirstQuizAttempt('be_id', $quizAttempt->getId(), $userQuizAttempts);
        if ($attemptIndex === false) {
            return false;
        }

        $attemptData = $userQuizAttempts[$attemptIndex];

        if (!isset($attemptData['bizExaminer']) || $attemptData['bizExaminer'] !== 1) {
            return false;
        }

        if (!isset($attemptData['quiz']) || $attemptData['quiz'] !== $quizAttempt->getQuizId()) {
            return false;
        }

        $userQuizAttempts[$attemptIndex] = $quizAttempt->getData();
        $success = $this->updateUserQuizAttemptsData($quizAttempt->getUserId(), $userQuizAttempts);
        return $success;
    }

    /**
     * Deletes a quiz attempt from the user
     *
     * @param int $userId
     * @param string $quizAttemptId
     * @return bool
     */
    public function deleteQuizAttempt(int $userId, string $quizAttemptId)
    {
        $userQuizAttempts = $this->getUserQuizAttemptsData($userId);
        $attemptIndex = $this->findFirstQuizAttempt('be_id', $quizAttemptId, $userQuizAttempts);
        if ($attemptIndex === false) {
            return false;
        }

        unset($userQuizAttempts[$attemptIndex]); // delete quiz attempt item
        $userQuizAttempts = array_values($userQuizAttempts); // reindex array

        $success = $this->updateUserQuizAttemptsData($userId, $userQuizAttempts);
        return $success;
    }

    /**
     * Finds IDs of quiz attempts by a specific key => value
     *
     * @param int $userId
     * @param string $key
     * @param string|int $value
     * @return QuizAttempt[] Found quiz attempts
     */
    public function findUserQuizAttempts(int $userId, string $key, $value): array
    {
        $userQuizAttempts = $this->getUserQuizAttemptsData($userId);
        $foundAttempts = $this->filterQuizAttempts($key, $value, $userQuizAttempts);
        return array_map(function ($attempt) use ($userId) {
            return new QuizAttempt($attempt['quiz'], $userId, $attempt);
        }, $foundAttempts);
    }

    /**
     * Get all quiz attempts of a user
     *
     * @param int $userId
     * @return array
     */
    protected function getUserQuizAttemptsData(int $userId): array
    {
        $quizAttemptsData = [];
        $quizAttemptsData = get_user_meta($userId, '_sfwd-quizzes', true);
        $quizAttemptsData = maybe_unserialize($quizAttemptsData);

        if (!is_array($quizAttemptsData)) {
            $quizAttemptsData = [];
        }

        return $quizAttemptsData;
    }

    /**
     * Updates a users quiz attempts
     *
     * @param int $userId
     * @param array $quizAttemptsData
     * @return bool
     */
    protected function updateUserQuizAttemptsData(int $userId, array $quizAttemptsData): bool
    {
        $result = update_user_meta($userId, '_sfwd-quizzes', $quizAttemptsData);
        if ($result) { // meta id if new or true on successful update
            return true;
        } else {
            // update_user_meta returns false if it's the same data - check for that and call it a success
            $existingData = $this->getUserQuizAttemptsData($userId);
            if ($existingData == $quizAttemptsData) {
                return true;
            }
            return false;
        }
    }

    /**
     * Finds the first quiz attempt of a user having a specific key => value
     *
     * @param string $key
     * @param string|int|float|bool|mixed $value
     * @param array $quizAttempts
     * @return int|false The index of the (first) found element or false if not found
     */
    protected function findFirstQuizAttempt(string $key, $value, array $quizAttempts)
    {
        foreach ($quizAttempts as $i => $attempt) {
            // do not compare strict type (because of string|int)
            if (isset($attempt[$key]) && $attempt[$key] == $value) {
                return $i;
            }
        }
        return false;
    }

    /**
     * Finds quiz attempts by a specific key => value
     *
     * @param string $key
     * @param string|int|float|bool|mixed $value
     * @param array $quizAttempts
     * @return array the filtered quiz attemptes
     */
    protected function filterQuizAttempts(string $key, $value, array $quizAttempts)
    {
        $filtered = [];

        foreach ($quizAttempts as $i => $attempt) {
            // do not compare strict type (because of string|int)
            if (isset($attempt[$key]) && $attempt[$key] == $value) {
                $filtered[$i] = $attempt;
            }
        }
        return $filtered;
    }
}
