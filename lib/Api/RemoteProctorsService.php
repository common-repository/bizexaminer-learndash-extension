<?php

namespace BizExaminer\LearnDashExtension\Api;

use BizExaminer\LearnDashExtension\Internal\Interfaces\ApiAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\CacheAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\ApiAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\CacheAwareTrait;

/**
 * Service for getting remote proctors
 */
class RemoteProctorsService implements ApiAwareInterface, CacheAwareInterface
{
    use ApiAwareTrait;
    use CacheAwareTrait;

    /**
     * Get remote proctor environments from the Api, uses a cache via transients
     *
     * @param ApiCredentials $apiCredentials
     * @return array $remoteProctors (array):
     *               'name' => (string)
     *               'description' => (string)
     *               'type' => (string)
     */
    public function getRemoteProctors(ApiCredentials $apiCredentials): array
    {
        $cacheKey = "remote-proctors_{$apiCredentials->getId()}";

        $returnProctors = $this->getCacheService()->get($cacheKey);

        if (!$returnProctors) {
            $returnProctors = [];
            $apiClient = $this->makeApi($apiCredentials);
            $proctors = $apiClient->getRemoteProctoringEnvironments();

            if (!$proctors || is_wp_error($proctors)) {
                return $returnProctors;
            }

            foreach ($proctors as $proctor) {
                $id = trim($proctor->name); // Trim to make comparing from wp-admin selection work.
                $returnProctors[$id] = [
                    'name' => $proctor->name,
                    'description' => $proctor->description ?? '',
                    'type' => $proctor->type,
                ];
            }

            /**
             * save with a relative short amount of expiration
             * this is mostly cached so when viewing settings page, saving, validating (mulitple times within minutes)
             * it gets the same values from local
             * but it needs to be short, so new exam modules created in bizExaminer show here soon
             */
            $this->getCacheService()->set($cacheKey, $returnProctors, MINUTE_IN_SECONDS * 5);
        }
        return $returnProctors;
    }

    /**
     * Returns a readable name for a proctor type
     *
     * @param string $proctorType
     * @return string
     */
    public function mapProctorTypeLabel($proctorType)
    {
        switch ($proctorType) {
            case 'proctorio':
                return 'Proctorio';
            case 'examity':
            case 'examity_v5':
                return 'Examity';
            case 'examus':
                return 'Constructor';
            case 'proctorexam':
                return 'ProctorExam';
            case 'meazure':
                return 'Meazure Learning';
        }
        return '';
    }

    /**
     * Checks if a remote proctor exists for a set of api credentials
     *
     * @param string $name name (unique) of remote proctor connection
     * @param ApiCredentials $apiCredentials
     * @return bool
     */
    public function hasRemoteProctor(string $name, ApiCredentials $apiCredentials): bool
    {
        $allRemoteProctors = $this->getRemoteProctors($apiCredentials);
        if (!isset($allRemoteProctors[$name])) {
            return false;
        }

        return true;
    }

    /**
     * Gets a remote proctory by the name/id.
     *
     * @param string $name name (unique) of remote proctor connection
     * @param ApiCredentials $apiCredentials
     * @return array|false
     */
    public function getRemoteProctor(string $name, ApiCredentials $apiCredentials)
    {
        $allRemoteProctors = $this->getRemoteProctors($apiCredentials);
        if (!isset($allRemoteProctors[$name])) {
            return false;
        }

        return $allRemoteProctors[$name];
    }

    /**
     * Extracts the type and name of combined value

     * The remote proctor may be saved as a combined string from type + name of connection
     * to allow JS in wp-admin to show/hide fields depending on proctor type.
     *
     * @param string $fullValue full value of saved remote proctor connection ({$type}_-_{$nameq})
     * @return array|false
     *              'type' => (string) remote proctor type
     *              'name' => (string) remote proctor connection name/id
     */
    public function explodeRemoteProctorParts(string $fullValue)
    {
        if (!str_contains($fullValue, '_-_')) {
            return false;
        }
        $parts = explode('_-_', trim($fullValue));

        return [
            'type' => $parts[0],
            'name' => $parts[1],
        ];
    }

    /**
     * Do some reformatting of options
     * because of differences in storing the settings and how the API expects them.
     *
     * @param string|null $proctor
     * @param array|null $proctorSettings
     * @param ApiCredentials $apiCredentials
     * @return array
     */
    public function formatOptionsForApi($proctor, $proctorSettings, ApiCredentials $apiCredentials): array
    {
        if (!$proctor || empty($proctorSettings)) {
            return [];
        }

        $proctorDetails = $this->getRemoteProctor($proctor, $apiCredentials);

        if ($proctorDetails['type'] === 'meazure') {
            $proctorSettings['allowedUrls'] = array_map(function ($url) {
                return [
                    'url' => $url,
                    'open_on_start' => false, // TODO: Not supported in learndash atm
                ];
            }, $proctorSettings['allowedUrls'] ?? []);

            if (empty($proctorSettings['allowedResources'])) {
                $proctorSettings['allowedResources'] = [];
            }
        }

        return $proctorSettings;
    }
}
