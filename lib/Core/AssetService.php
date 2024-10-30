<?php

namespace BizExaminer\LearnDashExtension\Core;

/**
 * Service for registering and enqueueing scripts and styles
 */
class AssetService
{
    /**
     * Path of the plugin file, used for path/url generation
     *
     * @var string
     */
    protected string $pluginFile;

    /**
     * Plugin version, default for assets version
     *
     * @var string
     */
    protected string $pluginVersion;

    /**
     * Keeps a log of all scripts registerd via this service
     * so only those get enqueued
     *
     * @see enqueueScript
     *
     * @var array
     */
    protected array $registeredScripts;

    /**
     * Keeps a log of all styles registerd via this service
     * so only those get enqueued
     *
     * @see enqueueStyle
     *
     * @var array
     */
    protected array $registeredStyles;

    /**
     * Prefix for handles
     *
     * @var string
     */
    protected const HANDLE_PREFIX = 'bizexaminer-';

    /**
     * Creates a new AssetService instance
     *
     * @param string $pluginFile Path of the plugin file, used for path/url generation
     * @param string $pluginVersion Plugin version, default for assets version
     */
    public function __construct(string $pluginFile, string $pluginVersion)
    {
        $this->pluginFile = $pluginFile;
        $this->pluginVersion = $pluginVersion;
        $this->registeredScripts = [];
        $this->registeredStyles = [];
    }

    /**
     * Enqueue a script by handle, has to be registered via registerScript
     *
     * @param string $handle
     * @return bool true if enqueued, false if not registered
     */
    public function enqueueScript(string $handle): bool
    {
        if (!isset($this->registeredScripts[$handle])) {
            return false;
        }

        $fullHandle = self::HANDLE_PREFIX . $handle;
        wp_enqueue_script($fullHandle);
        return true;
    }

    /**
     * Enqueue a style by handle, has to be registered via registerStyle
     *
     * @param string $handle
     * @return bool true if enqueued, false if not registered
     */
    public function enqueueStyle(string $handle): bool
    {
        if (!isset($this->registeredStyles[$handle])) {
            return false;
        }

        $fullHandle = self::HANDLE_PREFIX . $handle;
        wp_enqueue_style($fullHandle);
        return true;
    }

    /**
     * Adds data to the server so JavaScript can use it
     * Must be called after the script is registered
     *
     * @uses wp_add_inline_script
     * @see wp_localize_script
     *
     * @param string $handle
     * @param string $objectName the name of the JavaScript (global) variable/object
     * @param mixed $data json encodable data
     * @param string $position 'before' or 'after' (defaults to 'before' so the data is instantly available)
     * @return bool
     */
    public function addScriptData(string $handle, string $objectName, $data, string $position = 'before'): bool
    {
        if (!isset($this->registeredScripts[$handle])) {
            return false;
        }

        $script = "var $objectName = " . wp_json_encode($data) . ';';
        $fullHandle = self::HANDLE_PREFIX . $handle;
        return wp_add_inline_script($fullHandle, $script, $position === 'before' ? 'before' : 'after');
    }

    /**
     * Register a script which has an asset manifest built via @wordpress/scripts
     *
     * @param string $handle The handle to use for the script
     * @param string $file Filename / relative path inside "build" directory - WITHOUT file extension
     * @param bool $footer Whether to load script in the footer (@see wp_register_script)
     * @return string The full handle (="bizexaminer-" + $handle)
     */
    public function registerScript($handle, $file, $footer = false): string
    {
        $assetFile = plugin_dir_path($this->pluginFile) . "build/$file.asset.php";

        $dependencies = [];
        $version = $this->pluginVersion;

        if (file_exists($assetFile)) {
            $assetData = require $assetFile;
            $dependencies = $assetData['dependencies'] ?? [];
            $version = $assetData['version'] ?? $this->pluginVersion;
        }

        $fullHandle = self::HANDLE_PREFIX . $handle;

        wp_register_script(
            $fullHandle,
            plugins_url("build/$file.js", $this->pluginFile),
            $dependencies,
            $version,
            $footer
        );

        $this->registeredScripts[$handle] = $file;

        return $fullHandle;
    }

    /**
     * Register a style which has an asset manifest built via @wordpress/scripts
     *
     * @param string $handle The handle to use for the style
     * @param string $file Filename / relative path inside "build" directory - WITHOUT file extension
     * @return string The full handle (="bizexaminer-" + $handle)
     */
    public function registerStyle($handle, $file): string
    {
        $assetFile = plugin_dir_path($this->pluginFile) . "build/$file.asset.php";

        $dependencies = [];
        $version = $this->pluginVersion;

        if (file_exists($assetFile)) {
            $assetData = require $assetFile;
            // $dependencies = $assetData['dependencies'] ?? []; // dependencies are for JS deps
            $version = $assetData['version'] ?? $this->pluginVersion;
        }

        $fullHandle = self::HANDLE_PREFIX . $handle;

        wp_register_style(
            $fullHandle,
            plugins_url("build/$file.css", $this->pluginFile),
            $dependencies,
            $version,
        );

        $this->registeredStyles[$handle] = $file;

        return $fullHandle;
    }
}
