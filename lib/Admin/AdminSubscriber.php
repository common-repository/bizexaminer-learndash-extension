<?php

namespace BizExaminer\LearnDashExtension\Admin;

use BizExaminer\LearnDashExtension\Internal\EventManagement\ActionSubscriberInterface;

/**
 * Subscriber for wp-admin related hooks
 */
class AdminSubscriber implements ActionSubscriberInterface
{
    /**
     * AdminService instance
     *
     * @var AdminService
     */
    protected AdminService $adminService;

    /**
     * Creates a new AdminSubscriber instance
     *
     * @param AdminService $adminService
     */
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function getSubscribedActions(): array
    {
        return [
            'admin_notices' => 'showErrors'
        ];
    }

    /**
     * Shows errors on admin_notices
     *
     * @hooked on 'admin_notices'
     *
     * @return void
     */
    public function showErrors(): void
    {
        // TODO: check for capabilities - so only real admins are shown errors?
        $this->adminService->showErrors();
    }
}
