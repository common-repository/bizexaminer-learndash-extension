<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\Helper;

/**
 * An abstract message filter class
 * which provides addFilter/removeFilter helper methods
 */
abstract class AbstractMessageFilter implements LearnDashMessageFilterInterface
{
    /**
     * The priority the filter should be appended to
     */
    protected const FILTER_PRIORITY = 10;

    /**
     * Add filter
     *
     * @return void
     */
    public function addFilter(): void
    {
        add_filter(
            'ld_template_args_learndash_quiz_messages',
            [$this, 'filterMessageTemplateArgs'],
            static::FILTER_PRIORITY
        );
    }

    /**
     * Remove filter
     *
     * @return void
     */
    public function removeFilter()
    {
        remove_filter(
            'ld_template_args_learndash_quiz_messages',
            [$this, 'filterMessageTemplateArgs'],
            static::FILTER_PRIORITY
        );
    }
}
