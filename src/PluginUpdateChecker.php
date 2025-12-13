<?php
/**
 * Plugin Update Checker
 * 
 * @package WHB\UpdateChecker
 * @version 1.0.0
 * @author Asraful Islam
 * @license GPL-2.0-or-later
 */

namespace WHB\UpdateChecker;

/**
 * WordPress Plugin Update Checker
 * 
 * Lightweight alternative to YahnisElsts/plugin-update-checker
 * Integrates with custom license servers for automatic WordPress plugin updates.
 */
class PluginUpdateChecker {
    /**
     * @var string Plugin file basename
     */
    private $pluginFile;

    /**
     * @var string URL to check for updates
     */
    private $metadataUrl;

    /**
     * @var string Plugin slug
     */
    private $slug;

    /**
     * @var int Hours between update checks
     */
    private $checkPeriod = 12;

    /**
     * @var string Option name for caching
     */
    private $optionName;

    /**
     * @var string License key for premium products
     */
    private $licenseKey;

    /**
     * Initialize the update checker
     * 
     * @param string $metadataUrl URL to check for updates (e.g., http://license-server.com/api/my-plugin)
     * @param string $pluginFile Absolute path to plugin main file
     * @param string $slug Plugin slug (optional, auto-detected from file)
     * @param int $checkPeriod Hours between update checks (default: 12)
     * @param string $licenseKey License key for premium products (optional)
     */
    public function __construct($metadataUrl, $pluginFile, $slug = '', $checkPeriod = 12, $licenseKey = '') {
        $this->metadataUrl = $metadataUrl;
        $this->pluginFile = plugin_basename($pluginFile);
        $this->slug = empty($slug) ? dirname($this->pluginFile) : $slug;
        $this->checkPeriod = $checkPeriod;
        $this->optionName = 'whb_update_check_' . md5($this->slug);
        $this->licenseKey = $licenseKey;

        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdates']);
        add_filter('plugins_api', [$this, 'injectPluginInfo'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'afterInstall'], 10, 3);
    }

    /**
     * Check for plugin updates
     * 
     * @param object $transient Update transient
     * @return object Modified transient
     */
    public function checkForUpdates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Check if we need to run update check
        if (!$this->shouldCheck()) {
            $cached = $this->getCachedUpdate();
            if ($cached) {
                $this->addToTransient($transient, $cached);
            }
            return $transient;
        }

        // Fetch update info
        $updateInfo = $this->requestUpdateInfo();
        
        if ($updateInfo && isset($updateInfo->version)) {
            $this->cacheUpdate($updateInfo);
            $this->addToTransient($transient, $updateInfo);
        }

        return $transient;
    }

    /**
     * Check if we should perform an update check
     * 
     * @return bool
     */
    private function shouldCheck() {
        $lastCheck = get_option($this->optionName . '_time', 0);
        $now = time();
        return ($now - $lastCheck) >= ($this->checkPeriod * 3600);
    }

    /**
     * Get cached update information
     * 
     * @return object|false
     */
    private function getCachedUpdate() {
        return get_option($this->optionName . '_data', false);
    }

    /**
     * Cache update information
     * 
     * @param object $updateInfo
     */
    private function cacheUpdate($updateInfo) {
        update_option($this->optionName . '_time', time());
        update_option($this->optionName . '_data', $updateInfo);
    }

    /**
     * Add update to transient
     * 
     * @param object $transient
     * @param object $updateInfo
     */
    private function addToTransient($transient, $updateInfo) {
        if (!isset($updateInfo->version)) {
            return;
        }

        $currentVersion = $this->getCurrentVersion();
        if (version_compare($updateInfo->version, $currentVersion, '>')) {
            $transient->response[$this->pluginFile] = $updateInfo;
        } else {
            $transient->no_update[$this->pluginFile] = $updateInfo;
        }
    }

    /**
     * Request update information from server
     * 
     * @return object|false
     */
    private function requestUpdateInfo() {
        $url = $this->buildRequestUrl();

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data)) {
            return false;
        }

        return $this->convertToWpFormat($data);
    }

    /**
     * Build request URL with license key
     * 
     * @return string
     */
    private function buildRequestUrl() {
        $url = $this->metadataUrl;
        
        if (!empty($this->licenseKey)) {
            $url = add_query_arg('license', $this->licenseKey, $url);
        }

        return $url;
    }

    /**
     * Convert server response to WordPress update format
     * 
     * @param object $data Server response
     * @return object WordPress update object
     */
    private function convertToWpFormat($data) {
        $update = new \stdClass();
        $update->slug = $this->slug;
        $update->plugin = $this->pluginFile;
        $update->new_version = $data->version ?? '';
        $update->url = $data->homepage ?? '';
        $update->package = $data->download_url ?? $data->download_link ?? '';
        $update->tested = $data->tested ?? '';
        $update->requires_php = $data->requires_php ?? '';
        $update->requires = $data->requires ?? '';
        
        // Additional metadata
        if (isset($data->sections)) {
            $update->sections = (array) $data->sections;
        }
        
        if (isset($data->banners)) {
            $update->banners = (array) $data->banners;
        }
        
        if (isset($data->icons)) {
            $update->icons = (array) $data->icons;
        }

        return $update;
    }

    /**
     * Inject plugin information for plugin details screen
     * 
     * @param false|object $result
     * @param string $action
     * @param object $args
     * @return false|object
     */
    public function injectPluginInfo($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $pluginInfo = $this->fetchPluginInfo();
        
        return $pluginInfo ? $pluginInfo : $result;
    }

    /**
     * Fetch full plugin information
     * 
     * @return object|false
     */
    private function fetchPluginInfo() {
        $url = $this->buildRequestUrl();

        $response = wp_remote_get($url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data)) {
            return false;
        }

        return $this->buildPluginInfoObject($data);
    }

    /**
     * Build plugin info object from server data
     * 
     * @param object $data
     * @return object
     */
    private function buildPluginInfoObject($data) {
        $pluginInfo = new \stdClass();
        $pluginInfo->name = $data->name ?? '';
        $pluginInfo->slug = $this->slug;
        $pluginInfo->version = $data->version ?? '';
        $pluginInfo->author = $data->author ?? '';
        $pluginInfo->homepage = $data->homepage ?? '';
        $pluginInfo->requires = $data->requires ?? '';
        $pluginInfo->tested = $data->tested ?? '';
        $pluginInfo->requires_php = $data->requires_php ?? '';
        $pluginInfo->last_updated = $data->last_updated ?? '';
        $pluginInfo->download_link = $data->download_url ?? $data->download_link ?? '';
        $pluginInfo->sections = isset($data->sections) ? (array) $data->sections : [];
        $pluginInfo->banners = isset($data->banners) ? (array) $data->banners : [];
        $pluginInfo->icons = isset($data->icons) ? (array) $data->icons : [];
        
        // Optional statistics
        if (isset($data->rating)) {
            $pluginInfo->rating = $data->rating;
        }
        
        if (isset($data->num_ratings)) {
            $pluginInfo->num_ratings = $data->num_ratings;
        }
        
        if (isset($data->downloaded)) {
            $pluginInfo->downloaded = $data->downloaded;
        }
        
        if (isset($data->active_installs)) {
            $pluginInfo->active_installs = $data->active_installs;
        }

        return $pluginInfo;
    }

    /**
     * Perform cleanup after plugin update
     * 
     * @param bool $response
     * @param array $hook_extra
     * @param array $result
     * @return bool
     */
    public function afterInstall($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->pluginFile) {
            return $response;
        }

        $this->clearCache();

        return $response;
    }

    /**
     * Clear update cache
     */
    private function clearCache() {
        delete_option($this->optionName . '_time');
        delete_option($this->optionName . '_data');
    }

    /**
     * Get current plugin version
     * 
     * @return string
     */
    private function getCurrentVersion() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->pluginFile);
        return $pluginData['Version'] ?? '0.0.0';
    }

    /**
     * Force check for updates (bypass cache)
     * 
     * @return void
     */
    public function forceCheck() {
        $this->clearCache();
        delete_site_transient('update_plugins');
    }

    /**
     * Set license key
     * 
     * @param string $licenseKey
     * @return void
     */
    public function setLicenseKey($licenseKey) {
        $this->licenseKey = $licenseKey;
        $this->forceCheck();
    }

    /**
     * Get license key
     * 
     * @return string
     */
    public function getLicenseKey() {
        return $this->licenseKey;
    }
}
