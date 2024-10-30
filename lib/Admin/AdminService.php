<?php

namespace BizExaminer\LearnDashExtension\Admin;

use BizExaminer\LearnDashExtension\Core\ErrorService;

/**
 * General wp-admin service
 */
class AdminService
{
    /**
     * The ErrorService instance
     *
     * @var ErrorService
     */
    protected ErrorService $errorService;

    /**
     * Creates a new AdminService instance
     *
     * @param ErrorService $errorService
     */
    public function __construct(ErrorService $errorService)
    {
        $this->errorService = $errorService;
    }

    /**
     * Shows all errors/infos as admin notices during an admin request
     */
    public function showErrors(): void
    {
        $allErrors = $this->errorService->getAllErors();

        foreach ($allErrors as $context => $errors) {
            /** @var \WP_Error[] $errors */
            foreach ($errors as $code => $error) {
                printf(
                    '<div class="notice notice-error"><p>%1$s</p></div>',
                    wp_kses_post($error->get_error_message())
                );
            }
        }
    }
}
