<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Settings;

use BizExaminer\LearnDashExtension\Internal\AbstractServiceProvider;

/**
 * The service provider for all LearnDash settings related classes/services
 */
class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        'learndash.settings',
        'learndash.settings.subscriber.admin',
        'learndash.settings.support',
        SettingsPage::class,
        ApiCredentialsSection::class,
        OtherSettingsSection::class,
        SupportSection::class
    ];

    public function register(): void
    {
        /**
         * Settings Service
         */
        $this->addShared('learndash.settings', SettingsService::class);

        /**
         * Subscriber
         */
        $this->addShared('learndash.settings.subscriber.admin', AdminSubscriber::class)
            ->addArgument(SettingsPage::class)
            ->addArgument(ApiCredentialsSection::class)
            ->addArgument(OtherSettingsSection::class)
            ->addArgument(SupportSection::class)
            ->addArgument('learndash.settings.support')
            ->addTag('subscriber.admin');

        $this->addShared('learndash.settings.support', SupportSectionHelper::class)
            ->addArgument('learndash.quiz.settings')->addArgument('learndash.quiz.attempts');

        /**
         * Settings Page and Sections
         */
        $this->addShared(SettingsPage::class, function () {
            SettingsPage::add_page_instance();
            /** @var SettingsPage */
            $instance = SettingsPage::get_page_instance(SettingsPage::class);
            return $instance;
        });

        $this->addShared(ApiCredentialsSection::class, function () {
            ApiCredentialsSection::add_section_instance();
            /** @var ApiCredentialsSection */
            $instance = ApiCredentialsSection::get_section_instance(ApiCredentialsSection::class);
            // use setter injection, in case LearnDash ever changes the constructor signature
            $instance->setTemplateService($this->get('templates.admin'));
            $instance->setQuizSettingsService($this->get('learndash.quiz.settings'));
            return $instance;
        });

        $this->addShared(OtherSettingsSection::class, function () {
            OtherSettingsSection::add_section_instance();
            /** @var OtherSettingsSection */
            $instance = OtherSettingsSection::get_section_instance(OtherSettingsSection::class);
            return $instance;
        });

        $this->addShared(SupportSection::class, function () {
            SupportSection::add_section_instance();
            /** @var SupportSection */
            $instance = SupportSection::get_section_instance(SupportSection::class);
            // use setter injection, in case LearnDash ever changes the constructor signature
            $instance->setSectionHelper($this->get('learndash.settings.support'));
            return $instance;
        });
    }
}
