<?php

namespace BizExaminer\LearnDashExtension\Internal\Traits;

use BizExaminer\LearnDashExtension\Core\LogService;

/**
 * Adds setter and (protected) getter for the setter-injection of the Logservice
 * @see LogServiceAwareInterface
 */
trait LogServiceAwareTrait
{
    /**
     * The injected Logservice instance to use
     *
     * @var LogService
     */
    protected LogService $logService;

    public function setLogService(LogService $logService): void
    {
        $this->logService = $logService;
    }

    /**
     * Gets the LogService instance
     *
     * @return LogService
     */
    protected function getLogService(): LogService
    {
        if (isset($this->logService) && $this->logService instanceof LogService) {
            return $this->logService;
        }

        throw new \Exception('No LogService set.');
    }
}
