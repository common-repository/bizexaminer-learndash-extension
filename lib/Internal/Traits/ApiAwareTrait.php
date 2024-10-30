<?php

namespace BizExaminer\LearnDashExtension\Internal\Traits;

use BizExaminer\LearnDashExtension\Api\ApiClient;
use BizExaminer\LearnDashExtension\Api\ApiCredentials;
use BizExaminer\LearnDashExtension\Api\ApiService;
use Exception;

/**
 * Adds setter and (protected) getter for the setter-injection of the ApiService
 * @see ApiAwareInterface
 */
trait ApiAwareTrait
{
    /**
     * The injected ApiService instance to use
     *
     * @var ApiService
     */
    protected ApiService $apiService;

    public function setApiService(ApiService $apiService): void
    {
        $this->apiService = $apiService;
    }

    /**
     * Gets the ApiService instance
     *
     * @return ApiService
     */
    protected function getApiService(): ApiService
    {
        if (isset($this->apiService) && $this->apiService instanceof ApiService) {
            return $this->apiService;
        }

        throw new Exception('No ApiService set.');
    }

    /**
     * Helper function to call makeApi on the ApiService
     *
     * @uses ApiService::makeApi
     *
     * @param ApiCredentials $apiCredentials
     * @return ApiClient
     */
    protected function makeApi(ApiCredentials $apiCredentials): ApiClient
    {
        $apiService = $this->getApiService();
        return $apiService->makeApi($apiCredentials);
    }
}
