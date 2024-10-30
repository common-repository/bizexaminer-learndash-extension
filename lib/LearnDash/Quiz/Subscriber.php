<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz;

use BizExaminer\LearnDashExtension\Internal\EventManagement\ActionSubscriberInterface;
use BizExaminer\LearnDashExtension\Internal\EventManagement\FilterSubscriberInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\SettingsServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\SettingsServiceAwareTrait;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings\QuizSettingsService;
use BizExaminer\LearnDashExtension\LearnDash\Settings\SettingsService;

/**
 * Main subscriber for quiz frontend and cron job hooks
 */
class Subscriber implements FilterSubscriberInterface, SettingsServiceAwareInterface
{
    use SettingsServiceAwareTrait;

    /**
     * The QuizService instance to use
     *
     * @var QuizService
     */
    protected QuizService $quizService;

    /**
     * The QuizFrontend instance to use
     *
     * @var QuizFrontend
     */
    protected QuizFrontend $quizFrontend;

    /**
     * The QuizSettingsService instance to use
     *
     * @var QuizSettingsService
     */
    protected QuizSettingsService $quizSettingsService;

    /**
     * Creates a new SettingsService Instance
     *
     * @param QuizService $quizService The QuizService instance to use
     * @param QuizFrontend $quizFrontend The QuizFrontend instance to use
     */
    public function __construct(
        QuizService $quizService,
        QuizFrontend $quizFrontend,
        QuizSettingsService $quizSettingsService
    ) {
        $this->quizService = $quizService;
        $this->quizFrontend = $quizFrontend;
        $this->quizSettingsService = $quizSettingsService;
    }

    public function getSubscribedFilters(): array
    {
        /**
         * LearnDashs classes initialize and add hooks in the constructor,
         * Instance creation should happen in the container because of some dependencies (via setter injection)
         * But the initialization should only happen on the hooks defined in here
         */
        return [
            'learndash_quiz_content' => ['renderQuiz', 11, 2], // after most other filters
            'bizexaminer/checkQuizResults' => ['runCheckQuizResultsJob', 10, 3],
            'learndash_certificate_details_link' => ['customCertificate', 50, 4],
            'learndash_quiz_attempts' => ['filterQuizAttemptsLeft', 11, 4],
        ];
    }

    /**
     * Filters `ld_quiz` shortcode content to show custom bizExaminer Template
     *
     * @param string  $quizContent ld_quiz shortcode content (does not contain content form the quiz page block editor)
     * @param \WP_Post $quizPost    Quiz WP_Post object.
     * @return string
     */
    public function renderQuiz($quizContent, $quizPost): string
    {
        $quizId = $quizPost->ID;
        $shouldShowResults = isset($_GET['be-showResults']) && intval($_GET['be-showResults']) === 1;
        $quizAttemptKey = isset($_GET['be-quizAttempt']) ? sanitize_text_field($_GET['be-quizAttempt']) : null;

        $output = $this->quizFrontend->renderQuiz($quizId, $quizAttemptKey, $shouldShowResults);
        if (!$output) { // bizExaminer is disabled for this quiz
            return $quizContent;
        }
        return $output;
    }

    /**
     * Runs the job to maybe update quiz results for pending quiz attempts
     * maybeUpdateQuizResults -> updateQuizResults will unschedule this job then
     */
    public function runCheckQuizResultsJob(int $quizId, int $userId, string $quizAttemptId): void
    {
        $this->quizService->maybeUpdateQuizResults($quizId, $userId, $quizAttemptId);
    }

    /**
     * Filters the certificate URL of LearnDash to maybe replace it with the link
     * to a custom bizExaminer certificate (if enabled and one is found)
     *
     * @param string $certificateLink
     * @param int $certificateId
     * @param int $quizId
     * @param int $userId
     * @return string
     */
    public function customCertificate(string $certificateLink, int $certificateId, int $quizId, int $userId): string
    {
        // could be for a course, lesson etc
        if (get_post_type($quizId) !== learndash_get_post_type_slug('quiz')) {
            return $certificateLink;
        }

        $customLink = $this->quizFrontend->getBizExaminerCertificateLink($quizId, $userId);
        if ($customLink) {
            return $customLink;
        }

        return $certificateLink;
    }

    /**
     * Filters the quiz attempts left on the results page
     *
     * When loading the quiz view with showResults page
     *  LearnDash may show a message that the user had too many attempts instead of the results
     *  if the just finished attempt was the last allowed one
     * therefore reset the counter in the filter
     *
     * @param int $attemptsLeft
     * @param int $attemptsCount
     * @param int $userId
     * @param int $quizId
     * @return int
     */
    public function filterQuizAttemptsLeft(int $attemptsLeft, int $attemptsCount, int $userId, int $quizId): int
    {
        if ($attemptsLeft === 0) {
            $queriedId = get_queried_object_id();
            if ($queriedId === $quizId && (isset($_GET['be-showResults']) && intval($_GET['be-showResults']) === 1)) {
                return 1;
            }
        }
        return $attemptsLeft;
    }
}
