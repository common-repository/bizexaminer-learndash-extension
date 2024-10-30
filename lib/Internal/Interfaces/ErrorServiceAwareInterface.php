<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

use BizExaminer\LearnDashExtension\Core\ErrorService;

/**
 * Interface to signal usage of a ErrorService
 * @see TemplateAwareTrait
 */
interface ErrorServiceAwareInterface
{
    public function setErrorService(ErrorService $errorService): void;
}
