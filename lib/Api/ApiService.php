<?php

namespace BizExaminer\LearnDashExtension\Api;

use BizExaminer\LearnDashExtension\Internal\Interfaces\LogServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\SettingsServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\LogServiceAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\SettingsServiceAwareTrait;

/**
 * Service for getting API credentials and factory-creating a ApiClient from them
 */
class ApiService implements SettingsServiceAwareInterface, LogServiceAwareInterface
{
    use SettingsServiceAwareTrait;
    use LogServiceAwareTrait;

    /**
     * In-instance cache for settings-credentials
     *
     * @var array
     */
    protected $configuredCredentials = [];

    /**
     * Get all configured Api Credentials
     *
     * @return ApiCredentials[]
     */
    public function getApiCredentials(): array
    {
        $configuredCredentials = $this->getConfiguredCredentials();

        $credentialSets = [];
        foreach ($configuredCredentials as $id => $credentials) {
            $credentialSets[$id] = $this->makeApiCredentials(
                array_merge(['id' => $id], $credentials)
            );
        }

        return $credentialSets;
    }

    /**
     * Get a specific set of Api Credentials by ID
     *
     * @param string $id
     * @return ApiCredentials|false
     */
    public function getApiCredentialsById(string $id)
    {
        $configuredCredentials = $this->getConfiguredCredentials();
        if (isset($configuredCredentials[$id])) {
            return $this->makeApiCredentials(array_merge(['id' => $id], $configuredCredentials[$id]));
        }
        return false;
    }

    /**
     * Whether API credentials with this id exist
     *
     * @param string $id
     * @return bool
     */
    public function hasApiCredentials(string $id): bool
    {
        return $this->getApiCredentialsById($id) !== false;
    }

    /**
     * Build a new ApiClient (factory method)
     *
     * @param ApiCredentials $apiCredentials
     * @return ApiClient
     */
    public function makeApi(ApiCredentials $apiCredentials): ApiClient
    {
        $apiClient = new ApiClient($apiCredentials);
        $apiClient->setLogService($this->logService);
        return $apiClient;
    }

    /**
     * Build a new ApiCredentials instance from array
     *
     * @param array $credentialValues
     *              'id' => (string)
     *              'api_key_name' => (string),
     *              'api_key_instance' => (string),
     *              'api_key_owner' => (string)
     *              'api_key_organisation' => (string)
     * @return ApiCredentials
     */
    public function makeApiCredentials(array $credentialValues): ApiCredentials
    {
        $credentials = new ApiCredentials(
            $credentialValues['id'],
            $credentialValues['api_key_name'],
            $credentialValues['api_key_instance'],
            $credentialValues['api_key_owner'],
            $credentialValues['api_key_organisation']
        );
        return $credentials;
    }

    /**
     * Get's the configured API credentials form settings
     *
     * @return array
     */
    protected function getConfiguredCredentials(): array
    {
        if (empty($this->configuredCredentials)) {
            $this->configuredCredentials = $this->getSettingsService()->getSetting('api_credentials');
        }

        if (empty($this->configuredCredentials) || !is_array($this->configuredCredentials)) {
            $this->configuredCredentials = [];
        }

        return $this->configuredCredentials;
    }
}
