<?php

namespace BizExaminer\LearnDashExtension\Api;

use BizExaminer\LearnDashExtension\Helper\I18n;
use BizExaminer\LearnDashExtension\Internal\Interfaces\LogServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\LogServiceAwareTrait;
use DateTime;
use WP_Error;

/**
 * A base HTTP implementation of an API Client
 * Works with wp_remote_request
 * @link https://docs.google.com/document/d/1UvvQJOhBZ6d2SpPSz5U5SL6dULwgdW1dnVSXwrDaODQ
 */
class ApiClient implements LogServiceAwareInterface
{
    use LogServiceAwareTrait;

    /**
     * Date format to use for date fields
     *
     * @var string
     */
    public const DATE_FORMAT = 'Y-m-d\TH:i:sP';

    /**
     * Path to the API on a domain/instance
     *
     * @var string
     */
    protected const API_PATH = '/api/exmservice';

    /**
     * ApiCredentials to use to connect to API
     *
     * @var ApiCredentials
     */
    protected ApiCredentials $apiCredentials;

    /**
     * Creates a new ApiClient instance
     *
     * @param ApiCredentials $apiCredentials
     */
    public function __construct(ApiCredentials $apiCredentials)
    {
        $this->apiCredentials = $apiCredentials;
    }

    /**
     * Gets the API credentials used by this api client
     *
     * @return ApiCredentials
     */
    public function getApiCredentials()
    {
        return $this->apiCredentials;
    }

    /**
     * Calls getProductParts to get all exam modules and content revisions
     *
     * @return array|WP_Error Array of productParts (see documentation) or WP_Error on error
     */
    public function getExamModules()
    {
        $result = $this->makeCall(
            'getProductParts',
            [
                'includeDemos' => 0,
                'onlyActive' => 1,
            ]
        );
        $error = $this->handleApiResultErrors($result);
        if (!$error && $result->getResponseCode() === ApiResult::STATUS_OK) {
            if ($result->isSuccess()) {
                /**
                 *  @link https://docs.google.com/document/d/1UvvQJOhBZ6d2SpPSz5U5SL6dULwgdW1dnVSXwrDaODQ/edit#heading=h.fmqg4i974eru
                 */
                $examModules = $result->getResponse();
                return $examModules;
            } else {
                return [];
            }
        }
        return $error;
    }

    /**
     * Calls getRemoteProctoringEnvironments to get all remote proctors
     *
     * @return array|WP_Error Array of remote proctors (see documentation) or WP_Error on error
     */
    public function getRemoteProctoringEnvironments()
    {
        $result = $this->makeCall('getRemoteProctoringEnvironments');
        $error = $this->handleApiResultErrors($result);
        if (!$error && $result->getResponseCode() === ApiResult::STATUS_OK) {
            if ($result->isSuccess()) {
                /**
                 *  @link https://docs.google.com/document/d/1UvvQJOhBZ6d2SpPSz5U5SL6dULwgdW1dnVSXwrDaODQ/edit#heading=h.7xc5t8p17ib8
                 */
                $resultData = $result->getResponse();
                if (isset($resultData->environments)) {
                    return $resultData->environments;
                }
                return [];
            } else {
                return [];
            }
        }
        return $error;
    }


    /**
     * Calls createBooking to book an exam
     *
     * @param string $examModule
     * @param string $contentRevisionId
     * @param string $participantId
     * @param string $returnUrl
     * @param string $callbackUrl
     * @param string|null $remoteProctor
     * @param string|null $uiLanguage
     * @param array $remoteProctorSettings default: []
     * @param DateTime|null $startDate
     * @param DateTime|null $endDate
     * @return array|false|WP_Error bookingData (array):
     *                        'bookingId' => (int) the exam booking id
     *                        'url' => (string) the url to access the exam
     */
    public function bookExam(
        string $examModule,
        string $contentRevisionId,
        string $participantId,
        string $returnUrl,
        string $callbackUrl,
        ?string $remoteProctor = null,
        array $remoteProctorSettings = [],
        ?string $uiLanguage = null,
        ?DateTime $startDate = null,
        ?DateTime $endDate = null
    ) {
        if (!$startDate) {
            $startDate = new DateTime('now');
        }
        if (!$endDate) {
            $endDate = new DateTime();
            $endDate->setTimestamp(($startDate->getTimestamp()));
            $endDate->modify('+24 hours'); // default to 24 hours future
        }

        if (!$uiLanguage) {
            $uiLanguage = I18n::getLanguage();
        }

        /**
         * Generate random username + password for each booking
         * since the directAccessLoginUrl/directAccessExamUrl will be used to log the user in
         */
        $username = uniqid('beld-');
        $password = wp_generate_password();

        $result = $this->makeCall('createBooking', [
            'productPartsId' => $examModule,
            'participantID' => $participantId,
            'redirectAfterFinishUrl' => $returnUrl,
            'callBackUrl' => $callbackUrl,
            'contentsRevisionsId' => $contentRevisionId,
            'validFrom' => $startDate->format(self::DATE_FORMAT),
            'validTo' => $endDate->format(self::DATE_FORMAT),
            'timezone' => I18n::getIsoTimezone(),
            'attendanceCount' => 1,
            'username' => $username,
            'password' => $password,
            'returnWithAccessUrls' => 1,
            'uiLanguage' => $uiLanguage,
            'remoteProctoringEnvironment' => $remoteProctor,
            'remoteProctoringOptions' => $remoteProctorSettings

        ]);
        $error = $this->handleApiResultErrors($result);
        if (!$error && $result->getResponseCode() === ApiResult::STATUS_OK) {
            if ($result->isSuccess()) {
                $response = $result->getResponse();
                return [
                    'bookingId' => $response->exmBookingsId,
                    'url' => $response->directAccessExamUrl,
                ];
            } else {
                return false;
            }
        }
        return $error;
    }

    /**
     * Gets the direct exam access url for a booking.
     *
     * @param int $bookingId
     * @param string|null $uiLanguage
     * @return string|false|WP_Error directAccessExamUrl on success, false if not found, WP_Error on error
     */
    public function getExaminationAccessUrl(int $bookingId, ?string $uiLanguage = null)
    {
        if (!$uiLanguage) {
            $uiLanguage = I18n::getLanguage();
        }

        $result = $this->makeCall('getExaminationAccessUrl', [
            'bookingsId' => $bookingId,
            'directExamAccess' => 1,
            'uiLanguage' => $uiLanguage,
        ]);
        $error = $this->handleApiResultErrors($result);
        if (!$error && $result->getResponseCode() === ApiResult::STATUS_OK) {
            if ($result->isSuccess()) {
                $response = $result->getResponse();
                return $response->url;
            } else {
                return false;
            }
        }
        return $error;
    }

    /**
     * Cralls createParticipant to create a new participant
     *
     * For available fields see API Docs
     * https://docs.google.com/document/d/1UvvQJOhBZ6d2SpPSz5U5SL6dULwgdW1dnVSXwrDaODQ/edit#heading=h.xul5vxc38hmz
     *
     * @param array $participantData must have firstName, lastName
     * @return string|false|WP_Error participantId if created, false if not found, WP_Error on error
     */
    public function createParticipant(array $participantData)
    {
        if (!isset($participantData['firstName'], $participantData['lastName'])) {
            return new WP_Error(
                'bizexaminer-api-error',
                'You must set firstName and lastName fields for participants',
                ['participantData' => $participantData]
            );
        }

        $result = $this->makeCall(
            'createParticipant',
            $participantData
        );
        $error = $this->handleApiResultErrors($result);
        if (!$error && $result->getResponseCode() === ApiResult::STATUS_OK) {
            if ($result->isSuccess()) {
                $participantId = $result->getResponse()->participantID;
                return $participantId;
            } else {
                return false;
            }
        }
        return $error;
    }

    /**
     * Calls checkParticipant to check for an existing participant based on the search data
     *
     * @param array $searchData Search for a participant with the following data
     *              'id'|'participantID' => (string)
     *              'email' => (string)
     *              'firstName' => (string)
     *              'lastName' => (string)
     * @return string|false|WP_Error participantId if found, false if not found, WP_Error on error
     */
    public function checkParticipant(array $searchData)
    {
        $allowedSearchData = array_intersect_key(
            $searchData,
            array_flip(['id', 'participantID', 'email', 'firstName', 'lastName'])
        );

        if (isset($allowedSearchData['id'])) {
            $allowedSearchData['participantID'] = $allowedSearchData['id'];
            unset($allowedSearchData['id']);
        }

        $result = $this->makeCall(
            'checkParticipant',
            $allowedSearchData
        );
        $error = $this->handleApiResultErrors($result);
        if (!$error && $result->getResponseCode() === ApiResult::STATUS_OK) {
            if ($result->isSuccess()) {
                $participants = $result->getResponse();
                if (count($participants) > 0) {
                    return $participants[0]->participantID;
                }
            }
            return false;
        }
        return $error;
    }

    /**
     * Calls getParticipantOverview to get the results for a participant in a booking
     *
     * @param string $participantId
     * @param string $bookingId
     * @return array|WP_Error array of results (see docs)
     */
    public function getParticipantOverview(string $participantId, string $bookingId)
    {
        $result = $this->makeCall('getParticipantOverview', [
            'participantID' => $participantId,
            'exmBookingsId' => $bookingId
        ]);
        $error = $this->handleApiResultErrors($result);
        if (!$error && $result->getResponseCode() === ApiResult::STATUS_OK) {
            if ($result->isSuccess()) {
                /**
                 *  @link https://docs.google.com/document/d/1UvvQJOhBZ6d2SpPSz5U5SL6dULwgdW1dnVSXwrDaODQ/edit#heading=h.fmqg4i974eru
                 */
                $results = $result->getResponse();
                return $results;
            } else {
                return [];
            }
        }
        return $error;
    }

    /**
     * Calls getParticipantOverviewWithDetailsAndContent to get the results including content details
     *
     * @param string $participantId
     * @param string|null $bookingId Can be null
     * @return array|WP_Error array of results (see docs)
     */
    public function getParticipantOverviewWithDetails(string $participantId, ?string $bookingId = null)
    {
        $result = $this->makeCall('getParticipantOverviewWithDetailsAndContent', [
            'participantID' => $participantId,
            'exmBookingsId' => $bookingId
        ]);
        $error = $this->handleApiResultErrors($result);
        if (!$error && $result->getResponseCode() === ApiResult::STATUS_OK) {
            if ($result->isSuccess()) {
                /**
                 *  @link https://docs.google.com/document/d/1UvvQJOhBZ6d2SpPSz5U5SL6dULwgdW1dnVSXwrDaODQ/edit#heading=h.fmqg4i974eru
                 */
                $results = $result->getResponse();
                return $results;
            } else {
                return [];
            }
        }
        return $error;
    }

    /**
     * Test if credentials are valid by calling a simple function on the API
     *
     * @return bool
     */
    public function testCredentials(): bool
    {
        $result = $this->makeCall('getProductParts');
        $error = $this->handleApiResultErrors($result);
        if (!$error && $result->getResponseCode() === ApiResult::STATUS_OK) {
            if ($result->isSuccess()) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Checks for common (400, 401, 404, 500) HTTP Errors
     * and error codes from API
     *
     * @param ApiResult|WP_Error|false $apiResult
     * @return false|WP_Error false if no error was matched, a WP_Error if a matching error was found
     */
    protected function handleApiResultErrors($apiResult)
    {
        if (!$apiResult) {
            return new WP_Error(
                'bizexaminer-api-error',
                'Api returned another error',
                ['result' => $apiResult]
            );
        }

        if (is_wp_error($apiResult)) {
            $this->logService->logError($apiResult);
            return $apiResult;
        }

        if (
            $apiResult->getResponseCode() === ApiResult::STATUS_OK &&
            (isset($apiResult->getBody()->success) && $apiResult->getBody()->success)
        ) {
            return false;
        }

        $error = null;

        if (!empty($apiResult->getErrorCode())) {
            if (!empty($apiResult->getErrorMessage())) {
                $error = new WP_Error(
                    'bizexaminer-api-error',
                    $apiResult->getErrorMessage(),
                    ['result' => $apiResult]
                );
            } else {
                switch ($apiResult->getErrorCode()) {
                    case 'keys_error':
                        $error = new WP_Error(
                            'bizexaminer-api-not-allowed',
                            __('The API keys for bizExaminer are invalid.', 'bizexaminer-learndash-extension'),
                            ['result' => $apiResult]
                        );
                        break;
                    case 'inputdata_error':
                        $error = new WP_Error(
                            'bizexaminer-api-error',
                            __(
                                'The data sent to bizExaminer was invalid and the exam could not be started.',
                                'bizexaminer-learndash-extension'
                            ),
                            ['result' => $apiResult]
                        );
                        break;
                    case 'json-parsing-error':
                        $error = new WP_Error(
                            'bizexaminer-api-error',
                            __('bizExaminer returned an invalid value.', 'bizexaminer-learndash-extension'),
                            ['result' => $apiResult]
                        );
                        break;
                }
            }
        }

        if (!$error) {
            switch ($apiResult->getResponseCode()) {
                case ApiResult::STATUS_UNAUTHORIZED:
                    $error = new WP_Error(
                        'bizexaminer-api-not-allowed',
                        __('The API keys for bizExaminer are invalid.', 'bizexaminer-learndash-extension'),
                        ['result' => $apiResult]
                    );
                    break;
                case ApiResult::STATUS_NOT_FOUND:
                    $error = new WP_Error(
                        'bizexaminer-api-not-found',
                        __(
                            'The bizExaminer could not be found at the specified URL.',
                            'bizexaminer-learndash-extension'
                        ),
                        ['result' => $apiResult]
                    );
                    break;
                case ApiResult::STATUS_BAD_REQUEST:
                case ApiResult::STATUS_ERROR:
                default:
                    $error = new WP_Error(
                        'bizexaminer-api-error',
                        __('bizExaminer could not handle the request.', 'bizexaminer-learndash-extension'),
                        ['result' => $apiResult]
                    );
                    break;
            }
        }

        $this->logService->logError($error);
        return $error;
    }

    /**
     * Sends an HTTP(S) request to the API
     *
     * @param string $function  The function to call
     * @param array $data Data to send as body with request
     * @return ApiResult|WP_Error
     */
    public function makeCall($function, array $data = [])
    {
        $body = array_merge($data, [ // overwrite fixed values
            'function' => $function,
            'key_owner' => $this->apiCredentials->getOwnerKey(),
            'key_organisation' => $this->apiCredentials->getOrganisationKey(),
        ]);

        $url = 'https://' . trim($this->apiCredentials->getInstance(), '/') . self::API_PATH;

        $result = wp_safe_remote_request($url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/vnd.bizexaminer.exmservice-v1+json',
                'User-Agent' => 'WordPress/LearnDash-Extension'
            ],
            'method' => 'POST',
            'body' => $body
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        $apiResult = $this->makeApiResult($function, $result);

        return $apiResult;
    }

    /**
     * Map wp_remote_request result to ApiResult
     *
     * @param string $function the requested function
     * @param array $result results from wp_remote_request @see wp_remote_request()
     * @return ApiResult
     */
    protected function makeApiResult(string $function, array $result): ApiResult
    {
        $headers = wp_remote_retrieve_headers($result);
        // pre WP 6.2 and post WP 6.2 class names (Requests Library 2.0)
        // TODO: remove when WordPress version <6.2 are not supported anymore
        $headers = is_a($headers, 'Requests_Utility_CaseInsensitiveDictionary')
            || is_a($headers, '\WpOrg\Requests\Utility\CaseInsensitiveDictionary') ? $headers->getAll() : $headers;

        $apiResult = new ApiResult(
            $function,
            wp_remote_retrieve_response_code($result),
            wp_remote_retrieve_body($result),
            $headers,
        );
        return $apiResult;
    }
}
