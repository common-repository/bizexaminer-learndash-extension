<?php

namespace BizExaminer\LearnDashExtension\Internal\Traits;

use BizExaminer\LearnDashExtension\Core\CacheService;
use Exception;

/**
 * Adds setter and (protected) getter for the setter-injection of the CacheService
 * @see CacheAwareInterface
 */
trait CacheAwareTrait
{
    /**
     * The injected CacheService instance to use
     *
     * @var CacheService
     */
    protected CacheService $cacheService;

    public function setCacheService(CacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Gets the CacheService instance
     *
     * @return CacheService
     */
    protected function getCacheService(): CacheService
    {
        if (isset($this->cacheService) && $this->cacheService instanceof CacheService) {
            return $this->cacheService;
        }

        throw new Exception('No cache service set.');
    }
}
