<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

use BizExaminer\LearnDashExtension\LearnDash\Settings\SettingsService;

/**
 * Interface to signal usage of a SettingsService
 * @see SettingsServiceAwareTrait
 */
interface SettingsServiceAwareInterface
{
    /**
     * Sets the SettingsService instance
     *
     * @param SettingsService $settingsService
     * @return void
     */
    public function setSettingsService(SettingsService $settingsService): void;
}
