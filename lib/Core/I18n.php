<?php

namespace BizExaminer\LearnDashExtension\Core;

/**
 * Service for loading i18n(internationalization)/l18n(localization) features
 */
class I18n
{
    /**
     * Path of the plugin
     *
     * @var string
     */
    protected string $pluginDir;

    /**
     * Creates a new I18n instance
     *
     * @param string $pluginDir Path of the plugin
     */
    public function __construct(string $pluginDir)
    {
        $this->pluginDir = $pluginDir;
    }

    public function loadTextdomain()
    {
        load_plugin_textdomain('bizexaminer-learndash-extension', false, basename($this->pluginDir) . '/languages');
    }
}
