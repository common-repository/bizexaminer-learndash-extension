<?php

namespace BizExaminer\LearnDashExtension\Internal\Traits;

use BizExaminer\LearnDashExtension\Core\TemplateService;
use Exception;

/**
 * Adds setter and (protected) getter for the setter-injection of the TemplateService
 * @see TemplateAwareInterface
 */
trait TemplateAwareTrait
{
    /**
     * The injected TemplateService instance to use
     *
     * @var TemplateService
     */
    protected TemplateService $templateService;

    public function setTemplateService(TemplateService $templateService): void
    {
        $this->templateService = $templateService;
    }

    /**
     * Gets the TemplateService instance
     *
     * @return TemplateService
     */
    protected function getTemplateService(): TemplateService
    {
        if (isset($this->templateService) && $this->templateService instanceof TemplateService) {
            return $this->templateService;
        }

        throw new Exception('No template service set.');
    }
}
