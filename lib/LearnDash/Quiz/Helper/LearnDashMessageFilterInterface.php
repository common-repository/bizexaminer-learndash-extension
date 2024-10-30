<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\Helper;

/**
 * An Interface for classes which hook into ld_template_args_learndash_quiz_messages
 * to change some filters
 *
 * Reasoning for that:
 * LearnDash outputs spans with empty values in it
 * and replaces them via JavaScript (because the whole quiz happens without any reload)
 *
 * This action will be hooked into the LearnDash function used to get the messages
 * The strings are translated first but the spans get input via sprintf (@see 'show_quiz_result_box.php)
 * The order of spans is fixed, because also LearnDash uses this order in JavaScript (@see wpProQuiz_front.js)
 */
interface LearnDashMessageFilterInterface
{
    /**
     * Filters template args passed to message template
     *
     * @param array $templateArgs
     * @return array $templateArgs modified
     */
    public function filterMessageTemplateArgs(array $templateArgs): array;
}
