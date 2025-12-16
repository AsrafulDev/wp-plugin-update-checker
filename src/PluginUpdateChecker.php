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
     * @var callable License key callback for dynamic retrieval
     */
    private $licenseKeyCallback;
    
    /**
     * @var bool Whether this is a paid plugin requiring license
     */
    private $requiresLicense = false;
    
    /**
     * @var string License server URL for validation
     */
    private $licenseServerUrl = '';
    
    /**
     * @var int Hours between license validation checks (default: 1 hour)
     */
    private $licenseCheckPeriod = 1;

    /**
     * Initialize the update checker
     * 
     * @param string $metadataUrl URL to check for updates (e.g., http://license-server.com/api/my-plugin)
     * @param string $pluginFile Absolute path to plugin main file
     * @param string $slug Plugin slug (optional, auto-detected from file)
     * @param int $checkPeriod Hours between update checks (default: 12)
     * @param string $licenseKey License key for premium products (optional)
     * @param bool $requiresLicense Whether this plugin requires a valid license (default: false for free plugins)
     */
    public function __construct($metadataUrl, $pluginFile, $slug = '', $checkPeriod = 12, $licenseKey = '', $requiresLicense = false) {
        $this->metadataUrl = $metadataUrl;
        $this->pluginFile = plugin_basename($pluginFile);
        $this->slug = empty($slug) ? dirname($this->pluginFile) : $slug;
        $this->checkPeriod = $checkPeriod;
        $this->optionName = 'whb_update_check_' . md5($this->slug);
        $this->licenseKey = $licenseKey;
        $this->requiresLicense = $requiresLicense;
        
        // Extract license server URL from metadata URL
        if (strpos($metadataUrl, '/api/v1/updates/check') !== false) {
            $this->licenseServerUrl = str_replace('/api/v1/updates/check', '', $metadataUrl);
        }

        $this->registerHooks();
        
        // Register hourly license validation check
        if ($this->requiresLicense && !empty($this->licenseServerUrl)) {
            add_action('init', [$this, 'scheduleLicenseValidation']);
            add_action('whb_hourly_license_check_' . $this->slug, [$this, 'validateLicenseWithServer']);
        }
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
        // If URL is the new format (license server with /api/v1/updates/check)
        if (strpos($this->metadataUrl, '/api/v1/updates/check') !== false || strpos($this->metadataUrl, '/api/updates/check') !== false) {
            return $this->requestLicenseServerUpdate();
        }
        
        // Otherwise use the simple API format (GET /api/{slug})
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
     * Request update from license server (POST /api/v1/updates/check)
     * 
     * @return object|false
     */
    private function requestLicenseServerUpdate() {
        $currentVersion = $this->getCurrentVersion();
        $siteUrl = get_site_url();
        $licenseKey = $this->getLicenseKey(); // Get fresh license key
        
        $body = [
            'product_slug' => $this->slug,
            'current_version' => $currentVersion,
            'site_url' => $siteUrl,
        ];
        
        // Add license key if available
        if (!empty($licenseKey)) {
            $body['key'] = $licenseKey;
        }
        
        $response = wp_remote_post($this->metadataUrl, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
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

        // Handle API response format
        if (isset($data->success) && !$data->success) {
            return false;
        }

        // If update_available is explicitly false, no update
        if (isset($data->update_available) && $data->update_available === false) {
            return false;
        }

        // If new_version exists and is greater than current, there's an update
        if (isset($data->new_version)) {
            return $this->convertLicenseServerFormat($data);
        }

        return false;
    }

    /**
     * Convert license server response to WordPress format
     * 
     * @param object $data License server response
     * @return object WordPress update object
     */
    private function convertLicenseServerFormat($data) {
        $update = new \stdClass();
        $update->slug = $this->slug;
        $update->plugin = $this->pluginFile;
        $update->new_version = $data->new_version ?? '';
        $update->version = $data->new_version ?? ''; // For compatibility
        $update->url = ''; // Will be fetched from plugin info
        $update->package = $data->download_url ?? '';
        $update->tested = $data->tested_up_to ?? '';
        $update->requires_php = $data->requires_php ?? '';
        $update->requires = $data->requires_wp ?? $data->requires ?? '';
        
        return $update;
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
        // If callback is set, use it to get fresh license key
        if (is_callable($this->licenseKeyCallback)) {
            $this->licenseKey = call_user_func($this->licenseKeyCallback);
        }
        return $this->licenseKey;
    }
    
    /**
     * Set license key callback for dynamic retrieval
     * 
     * @param callable $callback
     */
    public function setLicenseKeyCallback($callback) {
        $this->licenseKeyCallback = $callback;
    }
    
    /**
     * Force update check by clearing cache
     * This triggers a fresh check against the custom license server
     */
    public function forceUpdateCheck() {
        // Clear cached update data
        delete_option($this->optionName . '_time');
        delete_option($this->optionName . '_data');
        
        // Clear WordPress transient to force recheck
        delete_site_transient('update_plugins');
        
        // Trigger WordPress update check which will call our custom checker via filter
        wp_update_plugins();
    }
    
    /**
     * Schedule hourly license validation
     */
    public function scheduleLicenseValidation() {
        $hook = 'whb_hourly_license_check_' . $this->slug;
        
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), 'hourly', $hook);
        }
    }
    
    /**
     * Validate license with remote server and update local DB
     */
    public function validateLicenseWithServer() {
        // Check if we should validate (hourly check)
        $lastValidation = get_option('whb_license_validation_time', 0);
        $now = time();
        
        if (($now - $lastValidation) < ($this->licenseCheckPeriod * 3600)) {
            return; // Already validated recently
        }
        
        // Get current license key from DB
        $licenseKey = get_option('whb_license_key', '');
        
        if (empty($licenseKey)) {
            // No license key, mark as inactive
            update_option('whb_license_status', 'inactive');
            update_option('whb_license_validation_time', $now);
            return;
        }
        
        // Call license server to validate
        $apiUrl = $this->licenseServerUrl . '/api/v1/licenses/' . $licenseKey . '/status';
        
        $response = wp_remote_get($apiUrl, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        
        if (is_wp_error($response)) {
            // Network error, don't change status
            error_log('License validation failed: ' . $response->get_error_message());
            return;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Update validation time
        update_option('whb_license_validation_time', $now);
        
        if ($code === 200 && isset($data['success']) && $data['success']) {
            $licenseData = $data['data'] ?? [];
            
            // Check if license is active
            if (isset($licenseData['is_active']) && $licenseData['is_active']) {
                update_option('whb_license_status', 'active');
                update_option('whb_license_data', [
                    'license_key' => $licenseData['license_key'] ?? $licenseKey,
                    'status' => $licenseData['status'] ?? 'active',
                    'product_name' => $licenseData['product']['name'] ?? '',
                    'product_slug' => $licenseData['product']['slug'] ?? '',
                    'current_version' => $licenseData['product']['current_version'] ?? '',
                    'expires_at' => $licenseData['expires_at'] ?? null,
                    'activation_limit' => $licenseData['activation_limit'] ?? -1,
                    'active_activations' => $licenseData['active_activations'] ?? 0,
                ]);
            } else {
                // License is not active
                update_option('whb_license_status', 'inactive');
            }
        } else {
            // Invalid response or license not found
            update_option('whb_license_status', 'inactive');
        }
    }
    
    /**
     * Check if license is valid from local DB
     * 
     * @return bool
     */
    public function isLicenseValid() {
        // For free plugins, always return true
        if (!$this->requiresLicense) {
            return true;
        }
        
        // Check local DB status
        $status = get_option('whb_license_status', '');
        return in_array($status, ['valid', 'active']);
    }
    
    /**
     * Set whether this plugin requires a license
     * 
     * @param bool $requires
     */
    public function setRequiresLicense($requires) {
        $this->requiresLicense = $requires;
    }
}
