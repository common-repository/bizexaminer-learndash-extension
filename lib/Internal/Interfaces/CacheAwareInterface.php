<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

use BizExaminer\LearnDashExtension\Core\CacheService;

/**
 * Interface to signal usage of a CacheService
 * @see CacheAwareTrait
 */
interface CacheAwareInterface
{
    /**
     * Sets the CacheService instance
     *
     * @param CacheService $cacheService
     * @return void
     */
    public function setCacheService(CacheService $cacheService): void;
}
