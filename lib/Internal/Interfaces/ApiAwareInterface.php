<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

use BizExaminer\LearnDashExtension\Api\ApiService;

/**
 * Interface to signal usage of an ApiService
 * @see ApiAwareTrait
 */
interface ApiAwareInterface
{
    /**
     * Sets the ApiService instance
     *
     * @param ApiService $apiService
     * @return void
     */
    public function setApiService(ApiService $apiService): void;
}
