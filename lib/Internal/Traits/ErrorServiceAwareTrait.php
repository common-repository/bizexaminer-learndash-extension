<?php

namespace BizExaminer\LearnDashExtension\Internal\Traits;

use BizExaminer\LearnDashExtension\Core\ErrorService;

/**
 * Adds setter and (protected) getter for the setter-injection of the ErrorService
 * @see ErrorServiceAwareInterface
 */
trait ErrorServiceAwareTrait
{
    /**
     * The injected ErrorService instance to use
     *
     * @var ErrorService
     */
    protected ErrorService $errorService;

    public function setErrorService(ErrorService $errorService): void
    {
        $this->errorService = $errorService;
    }

    /**
     * Gets the ErrorService instance
     *
     * @return ErrorService
     */
    protected function getErrorService(): ErrorService
    {
        if (isset($this->errorService) && $this->errorService instanceof ErrorService) {
            return $this->errorService;
        }

        throw new \Exception('No ErrorService set.');
    }
}
