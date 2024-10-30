<?php

namespace BizExaminer\LearnDashExtension\LearnDash;

use BizExaminer\LearnDashExtension\Internal\Interfaces\TemplateAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\TemplateAwareTrait;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\CallbackApi\CallbackApiService;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizAccess;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizService;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings\QuizSettingsService;

/**
 * Sservice for rendering shortcodes
 */
class Shortcodes implements TemplateAwareInterface
{
    use TemplateAwareTrait;

    /**
     * QuizService instance to use
     *
     * @var QuizService
     */
    protected QuizService $quizService;

    /**
     * QuizSettingsService instance to use
     *
     * @var QuizSettingsService
     */
    protected QuizSettingsService $quizSettingsService;

    /**
     * CallbackApiService instance to use
     *
     * @var CallbackApiService
     */
    protected CallbackApiService $callbackApiService;

    /**
     * QuizAccess instance to use
     *
     * @var QuizAccess
     */
    protected QuizAccess $quizAccess;

    /**
     * Creates a new Shortcodes Instance
     *
     * @param QuizService $quizService QuizService instance to use
     * @param QuizSettingsService $quizSettingsService QuizSettingsService instance to use
     * @param CallbackApiService $callbackApiService CallbackApiService instance to use
     * @param QuizAccess $quizAccess QuizAccess instance to use
     */
    public function __construct(
        QuizService $quizService,
        QuizSettingsService $quizSettingsService,
        CallbackApiService $callbackApiService,
        QuizAccess $quizAccess
    ) {
        $this->quizSettingsService = $quizSettingsService;
        $this->quizService = $quizService;
        $this->callbackApiService = $callbackApiService;
        $this->quizAccess = $quizAccess;
    }

    /**
     * Renders the [be_import_attempts_button] Shortcode
     * A button allowing a user to import attempts from bizExaminer
     *
     * @param array $args
     *              'quiz_id' => (int) The quiz id, defaults to current id
     *              'button_label' => (string) Label for import button
     *              'show_completed' => (bool|"true",1|"1") Whether to show the button if the quiz is completed
     * @return string|null Rendered HTMl, null on "error"
     */
    public function renderImportAttemptsButton($args): ?string
    {
        $args = wp_parse_args((array)$args, [
            'quiz_id' => get_the_ID(),
            // Must to be escaped in template
            'button_label' => _x('Import', 'import quiz attempts button label', 'bizexaminer-learndash-extension'),
            'show_completed' => false,
        ]);

        // parse "true" | "1" strings
        $args['show_completed'] = $args['show_completed'] === true
            || $args['show_completed'] === "true" || absint($args['show_completed']) === 1;

        $args['quiz_id'] = absint($args['quiz_id']);
        if (empty($args['quiz_id'])) {
            // Try to guess quiz id from context
            $currentId = get_the_ID();
            if (get_post_type($currentId) === 'sfwd-quiz') {
                $args['quiz_id'] = $currentId;
            } else {
                return null;
            }
        }

        if (get_post_type($args['quiz_id']) !== 'sfwd-quiz') {
            return null;
        }

        $quizSettings = $this->quizSettingsService->getQuizSettings($args['quiz_id']);
        // If quiz does not have bizExaminer or importing attempts enabled
        if (!$quizSettings || !$quizSettings['importExternalAttempts']) {
            return null;
        }

        $userId = get_current_user_id();

        // Check if user can access this quiz. Ignore running quizzes, user might have finished it already.
        if (!$this->quizAccess->canStartQuiz($args['quiz_id'], $userId, false)) {
            return null;
        }

        $completed = !learndash_is_quiz_notcomplete($userId, [$args['quiz_id'] => 1], false, -1);

        if ($completed && !$args['show_completed']) {
            return null;
        }

        return $this->templateService->get('learndash/shortcodes/import_attempts_button', [
            'buttonLabel' => $args['button_label'],
            'link' => $this->callbackApiService->buildCallbackApiUrl(
                'import-attempts',
                [
                    'be-quizId' => absint($args['quiz_id']),
                ],
                learndash_get_step_permalink($args['quiz_id']),
                true
            ),
        ]);
    }

    /**
     * Renders the [be_import_attempts_table] shortcode
     * A table showing all quizes which the user can import attempts for with a button
     *
     * @param array $args
     *              'button_label' => (string) Label for import button
     *              'empty_message' => (string) Message to show when no quizzes found
     *              'show_completed' => (bool|"true"|1|"1") Whether to show completed quizzes
     *              'course_id' => (int) The id of the course for which to show quizzes
     * @return string|null Rendered HTMl, null on "error"
     */
    public function renderImportableQuizAttemptsTable($args): ?string
    {
        // 1. Parse shortcode args

        $args = wp_parse_args((array)$args, [
            // Must to be escaped in template
            'button_label' => _x('Import', 'import quiz attempts button label', 'bizexaminer-learndash-extension'),
            'empty_message' => _x(
                'Nothing to import',
                'import quiz attempts no found',
                'bizexaminer-learndash-extension'
            ),
            'show_completed' => false,
            'course_id' => 0,
        ]);

        $args['course_id'] = absint($args['course_id']);
        // parse "true" | "1" strings
        $args['show_completed'] = $args['show_completed'] === true
            || $args['show_completed'] === "true" || absint($args['show_completed']) === 1;

        $userId = get_current_user_id();

        if (empty($args['course_id'])) {
            // Try to guess course id from context
            $currentId = get_the_ID();
            $currentPostType = get_post_type($currentId);
            if ($currentPostType === 'sfwd-courses') {
                $args['course_id'] = $currentId;
            } elseif (in_array($currentPostType, ['sfwd-lessons', 'sfwd-topic', 'sfwd-quiz'])) {
                $relatedCourseId = learndash_get_course_id($currentId);
                if (!$relatedCourseId) {
                    return null;
                }
                $args['course_id'] = $relatedCourseId;
            } else {
                return null;
            }
        }

        // 2. Get Quizzes

        // Get all quizzes in this course, filtered by whether the user can access them
        $quizzes = $this->quizService->getQuizzes($args['course_id'], true, get_current_user_id());

        // Filter quizzes by whether importing attempts is enabled and completed/notcompleted status
        $filteredQuizzes = [];

        foreach ($quizzes as $quizPost) {
            $quizSettings = $this->quizSettingsService->getQuizSettings($quizPost->ID);
            // If quiz does not have bizExaminer or importing attempts enabled
            if (!$quizSettings || !$quizSettings['importExternalAttempts']) {
                continue;
            }

            $completed = !learndash_is_quiz_notcomplete($userId, [$quizPost->ID => 1], false, $args['course_id']);

            if ($completed && !$args['show_completed']) {
                continue;
            }

            $quizItem = [
                'id' => $quizPost->ID,
                'status' => $completed ? 'completed' : 'notcompleted',
                'link' => learndash_get_step_permalink($quizPost->ID, $args['course_id']),
            ];

            $quizItem['importAttemptUrl'] = $this->callbackApiService->buildCallbackApiUrl(
                'import-attempts',
                [
                    'be-quizId' => absint($quizPost->ID),
                ],
                $quizItem['link'],
                true
            );

            $filteredQuizzes[] = $quizItem;
        }

        // 3. Render
        return $this->templateService->get('learndash/shortcodes/import_attempts_table', [
            'courseId' => $args['course_id'],
            'buttonLabel' => $args['button_label'],
            'emptyMessage' => $args['empty_message'],
            'quizzes' => $filteredQuizzes,
        ]);
    }
}
