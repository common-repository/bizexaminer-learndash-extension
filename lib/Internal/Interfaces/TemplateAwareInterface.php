<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

use BizExaminer\LearnDashExtension\Core\TemplateService;

/**
 * Interface to signal usage of a TemplateService in frontend context
 * @see TemplateAwareTrait
 */
interface TemplateAwareInterface
{
    /**
     * Sets the TemplateService instance
     *
     * @param TemplateService $templateService
     * @return void
     */
    public function setTemplateService(TemplateService $templateService): void;
}
