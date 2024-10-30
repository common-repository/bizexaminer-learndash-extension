<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

use BizExaminer\LearnDashExtension\Core\LogService;

/**
 * Interface to signal usage of a LogService
 * @see LogServiceAwareTrait
 */
interface LogServiceAwareInterface
{
    /**
     * Sets the LogService instance
     *
     * @param LogService $logService
     * @return void
     */
    public function setLogService(LogService $logService): void;
}
