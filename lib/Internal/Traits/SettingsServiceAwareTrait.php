<?php

namespace BizExaminer\LearnDashExtension\Internal\Traits;

use BizExaminer\LearnDashExtension\LearnDash\Settings\SettingsService;

/**
 * Adds setter and (protected) getter for the setter-injection of the SettingsService
 * @see SettingsServiceAwareInterface
 */
trait SettingsServiceAwareTrait
{
    /**
     * The injected SettingsService instance to use
     *
     * @var SettingsService
     */
    protected SettingsService $settingsService;

    public function setSettingsService(SettingsService $settingsService): void
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Gets the SettingsService instance
     *
     * @return SettingsService
     */
    protected function getSettingsService(): SettingsService
    {
        if (isset($this->settingsService) && $this->settingsService instanceof SettingsService) {
            return $this->settingsService;
        }

        throw new \Exception('No Settings service set or it is not an instance of ' . SettingsService::class . '.');
    }
}
