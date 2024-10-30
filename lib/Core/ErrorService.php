<?php

namespace BizExaminer\LearnDashExtension\Core;

use WP_Error;

/**
 * A service to collect errors
 */
class ErrorService
{
    /**
     * All collected errors separated by context
     *
     * @var array
     */
    protected array $errors;

    /**
     * Creates a new ErrorService instance
     */
    public function __construct()
    {
        $this->errors = [];
    }

    /**
     * Add an error to the collection
     *
     * @param \WP_Error $error
     * @param string $context
     * @return void
     */
    public function addError(\WP_Error $error, string $context): void
    {
        if (!isset($this->errors[$context])) {
            $this->errors[$context] = [];
        }
        $this->errors[$context][$error->get_error_code()] = $error;
    }

    /**
     * Whether the collection has errors for a specific context
     *
     * @param string|null $context
     * @return bool
     */
    public function hasErrors(?string $context = null): bool
    {
        if ($context) {
            return isset($this->errors[$context]) && !empty($this->errors[$context]);
        }
        return !empty($this->errors);
    }

    /**
     * Whether the collection has errors for a specific error code
     * optionally checks only a specific context
     *
     * @param string $errorCode
     * @param string|null $context
     * @return bool
     */
    public function hasErrorCode(string $errorCode, ?string $context = null): bool
    {
        if ($context) {
            return isset($this->errors[$context][$errorCode]) && !empty($this->errors[$context][$errorCode]);
        } else {
            foreach ($this->errors as $contextErrors) {
                foreach ($contextErrors as $ec => $error) {
                    if ($ec === $errorCode) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Get all erors for a context
     *
     * @param string $context
     * @return WP_Error[]
     */
    public function getErrors(string $context): array
    {
        if (isset($this->errors[$context])) {
            return $this->errors[$context];
        }
        return [];
    }

    /**
     * Get all errors for all contexts
     *
     * @return array indexed by context => WP_Error[]
     */
    public function getAllErors()
    {
        return $this->errors;
    }
}
