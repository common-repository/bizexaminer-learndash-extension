<?php

namespace BizExaminer\LearnDashExtension\Api;

use stdClass;

/**
 * Data object for results from the API
 */
class ApiResult
{
    public const STATUS_OK = 200;
    public const STATUS_UNAUTHORIZED = 401;
    public const STATUS_NOT_FOUND = 404;
    public const STATUS_BAD_REQUEST = 400;
    public const STATUS_ERROR = 500;

    /**
     * The requested function
     *
     * @var string
     */
    protected string $requestFunction;

    /**
     * The HTTP response code as an int
     *
     * @var int
     */
    protected int $responseCode;

    /**
     * The complete body parsed via json_decode
     *
     * @var stdClass
     */
    protected ?stdClass $body;

    /**
     * Whether the API returned successfully or not
     *
     * @var bool
     */
    protected bool $success;

    /**
     * The actual response data as parsed from JSON body
     *
     * @var mixed|null
     */
    protected $response;

    /**
     * The error code from the API
     *
     * @var string|null
     */
    protected ?string $errorCode;

    /**
     * The error message from the API
     *
     * @var string|null
     */
    protected ?string $errorMessage;

    /**
     * The error details with infos to specific fields
     *
     * @var mixed|stdClass
     */
    protected $errorDetails;

    /**
     * Array of headers
     * Indexed by the header attribute name
     *
     * @var string[]
     */
    protected array $headers;

    /**
     * Creates a new ApiResult instance
     *
     * @param string $requestFunction The requested function
     * @param int $responseCode The HTTP response code as an int
     * @param string $body The body of the response
     * @param string[] $headers Array of headers
     */
    public function __construct(string $requestFunction, int $responseCode, ?string $body = '', array $headers = [])
    {
        $this->requestFunction = $requestFunction;
        $this->responseCode = $responseCode;
        $this->headers = $headers;

        $this->body = json_decode($body, false);
        if ($this->body === null) {
            $this->body = new stdClass();
            $this->body->success = false;
            $this->body->errorCode = 'json-parsing-error';
            $this->body->errorMessage = __('Error parsing JSON response.', 'bizexaminer-learndash-extension');
        }

        $this->success = $this->body->success ?? false;
        if ($this->success) {
            $this->errorCode = null;
            $this->errorMessage = null;
            $this->response = $this->body->response ?? null;
        } else {
            $this->errorCode = $this->body->errorCode ?? '';
            $this->errorMessage = $this->body->errorMessage ?? '';
            $this->errorDetails = $this->body->errorDetails ?? new stdClass();
            $this->response = null;
        }
    }

    /**
     * Get the requested function
     *
     * @return string
     */
    public function getFunction(): string
    {
        return $this->requestFunction;
    }

    /**
     * Get the HTTP response code as an int
     *
     * @return int
     */
    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    /**
     * Get the body as stdClass object parsed via json-decode
     *
     * @return stdClass
     */
    public function getBody(): stdClass
    {
        return $this->body;
    }

    /**
     * Whether the API returned successfully or not
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Gets the actual response data as parsed from JSON body
     *
     * @return mixed|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Gets the error code from the API
     *
     * @return string|null errorCode from API as string or null if it was successful/given
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Gets the error message from the API
     *
     * @return string|null errorMessage from API as string or null if it was successful/given
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Gets the error details from the API
     *
     * @return mixed|stdClass errorDetails from API with details per field on what is wrong
     */
    public function getErrorDetails()
    {
        return $this->errorDetails;
    }

    /**
     * Get all HTTP headers
     * Indexed by the header attribute name
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific HTTP header
     *
     * @param string $header
     * @return mixed|null
     */
    public function getHeader($header)
    {
        if (isset($this->headers[$header])) {
            return $this->headers[$header];
        }
        return null;
    }
}
