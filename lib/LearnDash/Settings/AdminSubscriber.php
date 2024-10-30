<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Settings;

use BizExaminer\LearnDashExtension\Internal\EventManagement\ActionSubscriberInterface;
use BizExaminer\LearnDashExtension\Internal\EventManagement\FilterSubscriberInterface;

/**
 * Subscriber for settings related hooks in wp-admin
 */
class AdminSubscriber implements ActionSubscriberInterface, FilterSubscriberInterface
{
    /**
     * The SettingsPage instance to use
     *
     * @var SettingsPage
     */
    protected SettingsPage $settingsPage;

    /**
     * The ApiCredentialsSection instance to use
     *
     * @var ApiCredentialsSection
     */
    protected ApiCredentialsSection $apiCredentialsSection;

    /**
     * The OtherSettingsSection instance to use
     *
     * @var OtherSettingsSection
     */
    protected OtherSettingsSection $otherSettingsSection;

    /**
     * The SupportSection instance to use
     *
     * @var SupportSection
     */
    protected SupportSection $supportSection;

    /**
     * The SupportSectionHelper instance to use
     *
     * @var SupportSectionHelper
     */
    protected SupportSectionHelper $supportSectionHelper;

    /**
     * Creates a new SettingsService Instance
     *
     * @param SettingsPage $settingsPage The SettingsPage instance to use
     * @param ApiCredentialsSection $apiCredentialsSection The ApiCredentialsSection instance to use
     * @param OtherSettingsSection $otherSettingsSection The OtherSettingsSection instance to use
     * @param SupportSection $supportSection The SupportSection instance to use
     * @param SupportSectionHelper $supportSectionHelper The SupportSectionHelper instance to use
     */
    public function __construct(
        SettingsPage $settingsPage,
        ApiCredentialsSection $apiCredentialsSection,
        OtherSettingsSection $otherSettingsSection,
        SupportSection $supportSection,
        SupportSectionHelper $supportSectionHelper
    ) {
        $this->settingsPage = $settingsPage;
        $this->apiCredentialsSection = $apiCredentialsSection;
        $this->otherSettingsSection = $otherSettingsSection;
        $this->supportSection = $supportSection;
        $this->supportSectionHelper = $supportSectionHelper;
    }

    public function getSubscribedActions(): array
    {
        /**
         * LearnDashs classes initialize and add hooks in the constructor,
         * Instance creation should happen in the container because of some dependencies (via setter injection)
         * But the initialization should only happen on the hooks defined in here
         */
        return [
            'learndash_settings_pages_init' => ['registerSettingsPages', 100],
            'learndash_settings_sections_init' => 'registerSettingsSections',
        ];
    }

    public function getSubscribedFilters(): array
    {
        return [
            'learndash_support_sections' => 'addSupportScreenData',
            // LearnDash SiteHealth is hooked on 10
            'debug_information' => ['addSiteHealthInfo', 11],
        ];
    }

    /**
     * Registers the settings pages with WordPress/LearnDash
     * @see init()
     *
     * @return void
     */
    public function registerSettingsPages(): void
    {
        $this->settingsPage->register();
    }

    /**
     * Registers the settings sections with WordPress/LearnDash
     * @see init()
     *
     * @return void
     */
    public function registerSettingsSections(): void
    {
        $this->apiCredentialsSection->register();
        $this->otherSettingsSection->register();
        $this->supportSection->register();
    }

    /**
     * Adds Data to to LearnDashs support screen
     *
     * @see LearnDash_Settings_Page_Support::gather_system_details
     *
     * @param array $systemInfo
     * @return array extended systeminfor for support page
     */
    public function addSupportScreenData($systemInfo): array
    {
        $systemInfo['bizexaminer_data'] = $this->supportSectionHelper->getSupportScreenData();
        return $systemInfo;
    }

    /**
     * Add Telemetry info to Site Health.
     *
     * @since 1.1.0
     *
     * @param array $debugInfo Info.
     * @return array Debug info.
     */
    public function addSiteHealthInfo($debugInfo): array
    {
        if (!class_exists('Learndash_Site_Health')) {
            return $debugInfo;
        }
        if (empty($debugInfo['learndash'])) {
            $debugInfo['learndash'] = [
                'label'  => __('LearnDash', 'learndash'),
                'fields' => []
            ];
        }
        if (!isset($debugInfo['learndash']['fields'])) {
            $debugInfo['learndash']['fields'] = [];
        }

        $debugInfo['learndash']['fields'] = array_merge(
            $debugInfo['learndash']['fields'],
            array_map(function ($data) {
                $data['label'] = 'bizExaminer ' . $data['label'];
                return $data;
            }, $this->supportSectionHelper->getData(false, false)),
        );
        return $debugInfo;
    }
}
