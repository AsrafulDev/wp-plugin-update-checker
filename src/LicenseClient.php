<?php
/**
 * License Client - Complete License Management UI and Support System
 * 
 * This class provides a complete license management solution including:
 * - License activation/deactivation UI
 * - Update checking and notifications
 * - Support ticket system integration
 * - One-line integration for WordPress plugins
 * 
 * @package WHB\UpdateChecker
 */

namespace WHB\UpdateChecker;

if (!defined('ABSPATH')) { exit; }

class LicenseClient {
    
    /**
     * @var PluginUpdateChecker Update checker instance
     */
    private $updateChecker;
    
    /**
     * @var string Plugin slug
     */
    private $slug;
    
    /**
     * @var string Plugin name
     */
    private $pluginName;
    
    /**
     * @var string License server URL
     */
    private $licenseServerUrl;
    
    /**
     * @var string Plugin version
     */
    private $version;
    
    /**
     * @var string Plugin main file path
     */
    private $pluginFile;
    
    /**
     * @var array Configuration
     */
    private $config;
    
    /**
     * Initialize License Client
     * 
     * ONE-LINE INTEGRATION:
     * $license_client = new LicenseClient([
     *     'slug' => 'my-plugin',
     *     'plugin_name' => 'My Awesome Plugin',
     *     'plugin_file' => __FILE__,
     *     'license_server_url' => 'http://license-server.com',
     *     'requires_license' => true, // true for paid, false for free
     * ]);
     * 
     * @param array $config Configuration array
     */
    public function __construct($config = []) {
        $defaults = [
            'slug' => '',
            'plugin_name' => '',
            'plugin_file' => '',
            'license_server_url' => '',
            'requires_license' => false,
            'update_check_period' => 12, // hours
            'license_check_period' => 1, // hours
            'parent_menu' => '', // Parent menu slug for submenu
            'support_enabled' => true,
            'support_url' => '', // Auto-generated if empty
        ];
        
        $this->config = wp_parse_args($config, $defaults);
        $this->slug = $this->config['slug'];
        $this->pluginName = $this->config['plugin_name'];
        $this->pluginFile = $this->config['plugin_file'];
        $this->licenseServerUrl = rtrim($this->config['license_server_url'], '/');
        
        // Get plugin version
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data($this->pluginFile);
        $this->version = $plugin_data['Version'] ?? '1.0.0';
        
        // Auto-generate support URL if not provided
        if (empty($this->config['support_url']) && !empty($this->licenseServerUrl)) {
            $this->config['support_url'] = $this->licenseServerUrl . '/support';
        }
        
        // Initialize update checker
        $this->initUpdateChecker();
        
        // Register WordPress hooks
        $this->registerHooks();
    }
    
    /**
     * Initialize update checker
     */
    private function initUpdateChecker() {
        $this->updateChecker = new PluginUpdateChecker(
            $this->licenseServerUrl . '/api/v1/updates/check',
            $this->pluginFile,
            $this->slug,
            $this->config['update_check_period'],
            '', // License key will be retrieved dynamically
            $this->config['requires_license']
        );
        
        // Set callback to get license key dynamically
        $this->updateChecker->setLicenseKeyCallback(function() {
            return get_option($this->getOptionName('license_key'), '');
        });
    }
    
    /**
     * Register WordPress hooks
     */
    private function registerHooks() {
        add_action('admin_menu', [$this, 'registerMenu'], 100);
        add_action('admin_init', [$this, 'handleLicenseForm']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // Register AJAX handlers for support system
        if ($this->config['support_enabled']) {
            add_action('wp_ajax_' . $this->slug . '_submit_support_ticket', [$this, 'handleSupportTicket']);
        }
        
        // Show admin notices
        add_action('admin_notices', [$this, 'showAdminNotices']);
    }
    
    /**
     * Get option name with prefix
     */
    private function getOptionName($key) {
        return $this->slug . '_' . $key;
    }
    
    /**
     * Register admin menu
     */
    public function registerMenu() {
        if (empty($this->config['parent_menu'])) {
            // Create top-level menu
            add_menu_page(
                $this->pluginName . ' License',
                $this->pluginName,
                'manage_options',
                $this->slug . '-license',
                [$this, 'renderLicensePage'],
                'dashicons-admin-network',
                65
            );
        } else {
            // Create submenu
            add_submenu_page(
                $this->config['parent_menu'],
                __('License', 'wp-host-billing'),
                __('License', 'wp-host-billing'),
                'manage_options',
                $this->slug . '-license',
                [$this, 'renderLicensePage']
            );
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAssets($hook) {
        $menu_hook = $this->config['parent_menu'] ? 
            str_replace('-', '_', $this->config['parent_menu']) . '_page_' . $this->slug . '-license' :
            'toplevel_page_' . $this->slug . '-license';
            
        if ($hook !== $menu_hook) {
            return;
        }
        
        // Enqueue WordPress core styles
        wp_enqueue_style('common');
        wp_enqueue_style('forms');
        wp_enqueue_style('dashboard');
        
        // Minimal custom styling
        $custom_css = "
            .whb-license-key { font-family: 'Courier New', Consolas, Monaco, monospace; font-size: 13px; background: #f0f0f1; padding: 8px 12px; border-radius: 3px; display: inline-block; border: 1px solid #c3c4c7; }
            .nav-tab-wrapper { margin-bottom: 0; }
            .whb-update-badge { background: #2271b1; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 8px; }
            .postbox .inside table { margin-bottom: 0; }
            .postbox .inside textarea { width: 100%; min-height: 180px; }
        ";
        wp_add_inline_style('dashboard', $custom_css);
        
        // Enqueue jQuery
        wp_enqueue_script('jquery');
    }
    
    /**
     * Handle license form submission
     */
    public function handleLicenseForm() {
        if (!isset($_POST[$this->slug . '_license_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST[$this->slug . '_license_nonce'], $this->slug . '_license_action')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $action = $_POST[$this->slug . '_license_action'] ?? '';
        
        switch ($action) {
            case 'activate':
                $this->activateLicense($_POST[$this->slug . '_license_key'] ?? '');
                break;
                
            case 'deactivate':
                $this->deactivateLicense();
                break;
                
            case 'check_update':
                $this->forceUpdateCheck();
                break;
        }
    }
    
    /**
     * Activate license key
     */
    private function activateLicense($license_key) {
        $license_key = sanitize_text_field($license_key);
        
        if (empty($license_key)) {
            $this->addNotice('error', __('Please enter a license key', 'wp-host-billing'));
            return;
        }
        
        // Activate with server
        $result = $this->activateLicenseWithServer($license_key);
        
        if ($result['success']) {
            update_option($this->getOptionName('license_key'), $license_key);
            update_option($this->getOptionName('license_status'), 'active');
            update_option($this->getOptionName('license_data'), $result['data']);
            
            // Update checker
            $this->updateChecker->setLicenseKey($license_key);
            $this->forceUpdateCheck();
            
            $this->addNotice('success', __('License activated successfully!', 'wp-host-billing'));
        } else {
            $this->addNotice('error', $result['message']);
        }
    }
    
    /**
     * Deactivate license
     */
    private function deactivateLicense() {
        $license_key = get_option($this->getOptionName('license_key'), '');
        
        if (!empty($license_key)) {
            $this->deactivateLicenseWithServer($license_key);
        }
        
        delete_option($this->getOptionName('license_key'));
        delete_option($this->getOptionName('license_status'));
        delete_option($this->getOptionName('license_data'));
        
        $this->addNotice('success', __('License deactivated', 'wp-host-billing'));
    }
    
    /**
     * Activate license with server
     */
    private function activateLicenseWithServer($license_key) {
        $api_url = $this->licenseServerUrl . '/api/v1/licenses/activate';
        
        $response = wp_remote_post($api_url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'key' => $license_key,
                'site_url' => get_site_url(),
                'product_slug' => $this->slug,
            ]),
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => __('Could not connect to license server: ', 'wp-host-billing') . $response->get_error_message(),
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code !== 200 || !isset($data['success']) || !$data['success']) {
            return [
                'success' => false,
                'message' => $data['message'] ?? __('License activation failed', 'wp-host-billing'),
            ];
        }
        
        return [
            'success' => true,
            'data' => $data['data'] ?? [],
        ];
    }
    
    /**
     * Deactivate license with server
     */
    private function deactivateLicenseWithServer($license_key) {
        $api_url = $this->licenseServerUrl . '/api/v1/licenses/deactivate';
        
        wp_remote_post($api_url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'key' => $license_key,
                'site_url' => get_site_url(),
            ]),
        ]);
    }
    
    /**
     * Force update check from custom license server
     */
    private function forceUpdateCheck() {
        // Use custom update checker to check against license server
        $this->updateChecker->forceUpdateCheck();
        $this->addNotice('success', __('Update check completed', 'wp-host-billing'));
    }
    
    /**
     * Add admin notice
     */
    private function addNotice($type, $message) {
        add_settings_error($this->slug . '_license', $type, $message, $type === 'success' ? 'success' : 'error');
    }
    
    /**
     * Show admin notices
     */
    public function showAdminNotices() {
        // Only show on license page
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, $this->slug . '-license') === false) {
            return;
        }
        
        settings_errors($this->slug . '_license');
    }
    
    /**
     * Handle support ticket submission
     */
    public function handleSupportTicket() {
        check_ajax_referer($this->slug . '_support_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'wp-host-billing')]);
        }
        
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $license_key = get_option($this->getOptionName('license_key'), '');
        
        if (empty($subject) || empty($message)) {
            wp_send_json_error(['message' => __('Please fill in all fields', 'wp-host-billing')]);
        }
        
        // Submit to support API
        $api_url = $this->licenseServerUrl . '/api/v1/support/tickets';
        
        $response = wp_remote_post($api_url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'license_key' => $license_key,
                'subject' => $subject,
                'message' => $message,
                'site_url' => get_site_url(),
                'product_slug' => $this->slug,
                'plugin_version' => $this->version,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
            ]),
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('Could not connect to support server', 'wp-host-billing')]);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 200 && isset($data['success']) && $data['success']) {
            wp_send_json_success([
                'message' => __('Support ticket submitted successfully! Ticket ID: ', 'wp-host-billing') . ($data['data']['ticket_id'] ?? ''),
            ]);
        } else {
            wp_send_json_error(['message' => $data['message'] ?? __('Failed to submit support ticket', 'wp-host-billing')]);
        }
    }
    
    /**
     * Render license page with tabs
     */
    public function renderLicensePage() {
        $active_tab = $_GET['tab'] ?? 'license';
        $license_info = $this->getLicenseInfo();
        $update_info = $this->getUpdateInfo();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($this->pluginName); ?></h1>
            <hr class="wp-header-end">
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr($this->slug . '-license'); ?>&tab=license" 
                   class="nav-tab <?php echo $active_tab === 'license' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-network" style="vertical-align: text-top;"></span>
                    <?php _e('License', 'wp-host-billing'); ?>
                </a>
                <?php if ($this->config['support_enabled']): ?>
                <a href="?page=<?php echo esc_attr($this->slug . '-license'); ?>&tab=support" 
                   class="nav-tab <?php echo $active_tab === 'support' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-sos" style="vertical-align: text-top;"></span>
                    <?php _e('Support', 'wp-host-billing'); ?>
                </a>
                <?php endif; ?>
                <a href="?page=<?php echo esc_attr($this->slug . '-license'); ?>&tab=about" 
                   class="nav-tab <?php echo $active_tab === 'about' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-info" style="vertical-align: text-top;"></span>
                    <?php _e('About', 'wp-host-billing'); ?>
                </a>
            </h2>
            
            <?php
            switch ($active_tab) {
                case 'support':
                    $this->renderSupportTab($license_info);
                    break;
                case 'about':
                    $this->renderAboutTab();
                    break;
                default:
                    $this->renderLicenseTab($license_info, $update_info);
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Render license tab
     */
    private function renderLicenseTab($license_info, $update_info) {
        if ($license_info['is_active']): ?>
            <!-- Active License -->
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h2 style="padding-left:5px;padding-right:5px;">
                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a; margin-right: 5px;"></span>
                        <?php _e('License Information', 'wp-host-billing'); ?>
                    </h2>
                </div>
                <div class="inside">
                    <div class="notice notice-success inline" style="margin: 0 0 15px 0;">
                        <p><strong><?php _e('✓ Your license is active and all features are enabled.', 'wp-host-billing'); ?></strong></p>
                    </div>
                    
                    <table class="form-table" role="presentation">
                    <tr>
                        <th><?php _e('License Key:', 'wp-host-billing'); ?></th>
                        <td><span class="whb-license-key"><?php echo esc_html($this->maskLicenseKey($license_info['key'])); ?></span></td>
                    </tr>
                    <?php if (!empty($license_info['data']['product'])): 
                        $product = $license_info['data']['product'];
                    ?>
                    <tr>
                        <th><?php _e('Product:', 'wp-host-billing'); ?></th>
                        <td><?php echo esc_html($product['name'] ?? $this->pluginName); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php _e('Status:', 'wp-host-billing'); ?></th>
                        <td><span style="text-transform: capitalize;"><?php echo esc_html($license_info['data']['status'] ?? 'active'); ?></span></td>
                    </tr>
                    <?php if (!empty($license_info['data']['expires_at'])): ?>
                    <tr>
                        <th><?php _e('Expires:', 'wp-host-billing'); ?></th>
                        <td><?php echo esc_html(date('F j, Y', strtotime($license_info['data']['expires_at']))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><span class="dashicons dashicons-update"></span> <?php _e('Version', 'wp-host-billing'); ?></th>
                        <td>
                            <strong><?php echo esc_html($update_info['current_version']); ?></strong>
                            <?php if ($update_info['has_update']): ?>
                                <span class="whb-license-badge update" style="margin-left: 10px;">
                                    <span class="dashicons dashicons-arrow-up-alt" style="vertical-align: middle; font-size: 14px;"></span>
                                    <?php printf(__('Update to %s Available', 'wp-host-billing'), esc_html($update_info['new_version'])); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #00a32a; margin-left: 10px;">
                                    <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                    <?php _e('Up to date', 'wp-host-billing'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <p style="margin-top: 20px;">
                    <?php if ($update_info['has_update']): ?>
                        <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary button-large">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            <?php _e('Install Update Now', 'wp-host-billing'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <button type="button" class="button button-large" onclick="document.getElementById('check-update-form').submit();">
                        <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                        <?php _e('Check for Updates', 'wp-host-billing'); ?>
                    </button>
                    
                    <button type="button" class="button button-large button-link-delete" onclick="if(confirm('<?php esc_attr_e('Are you sure you want to deactivate your license? This will disable all premium features.', 'wp-host-billing'); ?>')) { document.getElementById('deactivate-form').submit(); }">
                        <span class="dashicons dashicons-dismiss" style="vertical-align: middle;"></span>
                        <?php _e('Deactivate License', 'wp-host-billing'); ?>
                    </button>
                </p>
                
                <!-- Hidden forms -->
                <form id="check-update-form" method="post" action="" style="display: none;">
                    <?php wp_nonce_field($this->slug . '_license_action', $this->slug . '_license_nonce'); ?>
                    <input type="hidden" name="<?php echo esc_attr($this->slug . '_license_action'); ?>" value="check_update">
                </form>
                
                <form id="deactivate-form" method="post" action="" style="display: none;">
                    <?php wp_nonce_field($this->slug . '_license_action', $this->slug . '_license_nonce'); ?>
                    <input type="hidden" name="<?php echo esc_attr($this->slug . '_license_action'); ?>" value="deactivate">
                </form>
            </div>
            
        <?php else: ?>
            <!-- Inactive License -->
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h2 style="padding-left:5px;padding-right:5px;">
                        <span class="dashicons dashicons-lock" style="color: #d63638; margin-right: 5px;"></span>
                        <?php _e('Activate Your License', 'wp-host-billing'); ?>
                    </h2>
                </div>
                <div class="inside">
                    <div class="notice notice-warning inline" style="margin: 0 0 15px 0;">
                        <p><strong><?php _e('⚠ License Not Activated', 'wp-host-billing'); ?></strong></p>
                        <p><?php _e('Please enter your license key to unlock all features, receive automatic updates, and access premium support.', 'wp-host-billing'); ?></p>
                    </div>
                
                <form method="post" action="">
                    <?php wp_nonce_field($this->slug . '_license_action', $this->slug . '_license_nonce'); ?>
                    <input type="hidden" name="<?php echo esc_attr($this->slug . '_license_action'); ?>" value="activate">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($this->slug . '_license_key'); ?>">
                                    <?php _e('License Key', 'wp-host-billing'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="<?php echo esc_attr($this->slug . '_license_key'); ?>" 
                                       name="<?php echo esc_attr($this->slug . '_license_key'); ?>" 
                                       class="regular-text"
                                       placeholder="XXXX-XXXX-XXXX-XXXX"
                                       style="font-family: monospace; font-size: 14px;"
                                       required>
                                <p class="description">
                                    <?php _e('Enter your license key from your purchase confirmation.', 'wp-host-billing'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Activate License', 'wp-host-billing'), 'primary large'); ?>
                </div>
            </div>
        <?php endif;
    }
    
    /**
     * Render support tab
     */
    private function renderSupportTab($license_info) {
        ?>
        <div class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2 style="padding-left:5px;padding-right:5px;">
                    <span class="dashicons dashicons-sos" style="margin-right: 5px;"></span>
                    <?php _e('Submit Support Ticket', 'wp-host-billing'); ?>
                </h2>
            </div>
            <div class="inside">
            
            <?php if (!$license_info['is_active']): ?>
                <div class="notice notice-warning inline">
                    <p><?php _e('Please activate your license to submit support tickets.', 'wp-host-billing'); ?></p>
                </div>
            <?php else: ?>
                
                <div id="support-message"></div>
                
                <form id="support-ticket-form">
                    <?php wp_nonce_field($this->slug . '_support_nonce', 'nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="support_subject"><?php _e('Subject', 'wp-host-billing'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="support_subject" name="subject" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="support_message"><?php _e('Message', 'wp-host-billing'); ?></label>
                            </th>
                            <td>
                                <textarea id="support_message" name="message" class="regular-text" required></textarea>
                                <p class="description">
                                    <?php _e('Describe your issue in detail. Include any error messages or steps to reproduce.', 'wp-host-billing'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('System Info', 'wp-host-billing'); ?></th>
                            <td>
                                <p class="description">
                                    <strong><?php _e('Plugin Version:', 'wp-host-billing'); ?></strong> <?php echo esc_html($this->version); ?><br>
                                    <strong><?php _e('WordPress:', 'wp-host-billing'); ?></strong> <?php echo esc_html(get_bloginfo('version')); ?><br>
                                    <strong><?php _e('PHP:', 'wp-host-billing'); ?></strong> <?php echo esc_html(PHP_VERSION); ?>
                                </p>
                                <p class="description"><?php _e('This information will be included with your ticket.', 'wp-host-billing'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php _e('Submit Ticket', 'wp-host-billing'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin-left: 10px;"></span>
                    </p>
                </form>
                
                <script>
                jQuery(document).ready(function($) {
                    $('#support-ticket-form').on('submit', function(e) {
                        e.preventDefault();
                        
                        var $form = $(this);
                        var $button = $form.find('button[type="submit"]');
                        var $spinner = $form.find('.spinner');
                        var $message = $('#support-message');
                        
                        $button.prop('disabled', true);
                        $spinner.addClass('is-active');
                        $message.empty();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: '<?php echo esc_js($this->slug . '_submit_support_ticket'); ?>',
                                nonce: $form.find('input[name="nonce"]').val(),
                                subject: $form.find('input[name="subject"]').val(),
                                message: $form.find('textarea[name="message"]').val()
                            },
                            success: function(response) {
                                if (response.success) {
                                    $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                                    $form[0].reset();
                                } else {
                                    $message.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                                }
                            },
                            error: function() {
                                $message.html('<div class="notice notice-error"><p><?php esc_html_e('Connection error. Please try again.', 'wp-host-billing'); ?></p></div>');
                            },
                            complete: function() {
                                $button.prop('disabled', false);
                                $spinner.removeClass('is-active');
                            }
                        });
                    });
                });
                </script>
                
            <?php endif; ?>
            
            <hr style="margin: 30px 0;">
            
            <h3><?php _e('Alternative Support Options', 'wp-host-billing'); ?></h3>
            <p>
                <a href="<?php echo esc_url($this->config['support_url']); ?>" target="_blank" class="button">
                    <span class="dashicons dashicons-external" style="vertical-align: middle;"></span>
                    <?php _e('Visit Support Portal', 'wp-host-billing'); ?>
                </a>
            </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render about tab
     */
    private function renderAboutTab() {
        ?>
        <div class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2 style="padding-left:5px;padding-right:5px;">
                    <span class="dashicons dashicons-info-outline" style="margin-right: 5px;"></span>
                    <?php _e('Plugin Information', 'wp-host-billing'); ?>
                </h2>
            </div>
            <div class="inside">
            <table class="form-table" role="presentation">
                <tr>
                    <th><?php _e('Plugin Name:', 'wp-host-billing'); ?></th>
                    <td><?php echo esc_html($this->pluginName); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Version:', 'wp-host-billing'); ?></th>
                    <td><?php echo esc_html($this->version); ?></td>
                </tr>
                <tr>
                    <th><?php _e('License Server:', 'wp-host-billing'); ?></th>
                    <td><code style="background: #f0f0f1; padding: 3px 6px; border-radius: 3px;"><?php echo esc_html($this->licenseServerUrl); ?></code></td>
                </tr>
                <tr>
                    <th><?php _e('Update Check:', 'wp-host-billing'); ?></th>
                    <td><?php printf(__('Every %d hours', 'wp-host-billing'), $this->config['update_check_period']); ?></td>
                </tr>
                <?php if ($this->config['requires_license']): ?>
                <tr>
                    <th><?php _e('License Check:', 'wp-host-billing'); ?></th>
                    <td><?php printf(__('Every %d hour(s)', 'wp-host-billing'), $this->config['license_check_period']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            </div>
        </div>
        
        <div class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2 style="padding-left:5px;padding-right:5px;">
                    <span class="dashicons dashicons-wordpress" style="margin-right: 5px;"></span>
                    <?php _e('WordPress Environment', 'wp-host-billing'); ?>
                </h2>
            </div>
            <div class="inside">
            <table class="form-table" role="presentation">
                <tr>
                    <th><?php _e('WordPress Version:', 'wp-host-billing'); ?></th>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>
                <tr>
                    <th><?php _e('PHP Version:', 'wp-host-billing'); ?></th>
                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Site URL:', 'wp-host-billing'); ?></th>
                    <td><?php echo esc_html(get_site_url()); ?></td>
                </tr>
            </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get license information
     */
    private function getLicenseInfo() {
        return [
            'key' => get_option($this->getOptionName('license_key'), ''),
            'status' => get_option($this->getOptionName('license_status'), ''),
            'data' => get_option($this->getOptionName('license_data'), []),
            'is_active' => $this->updateChecker->isLicenseValid(),
        ];
    }
    
    /**
     * Get update information
     */
    private function getUpdateInfo() {
        $update_plugins = get_site_transient('update_plugins');
        $plugin_basename = plugin_basename($this->pluginFile);
        
        $has_update = isset($update_plugins->response[$plugin_basename]);
        $new_version = $has_update ? $update_plugins->response[$plugin_basename]->new_version : $this->version;
        
        return [
            'current_version' => $this->version,
            'new_version' => $new_version,
            'has_update' => $has_update,
        ];
    }
    
    /**
     * Mask license key for display
     */
    private function maskLicenseKey($key) {
        if (strlen($key) <= 8) {
            return $key;
        }
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }
    
    /**
     * Check if license is valid (public method for plugin use)
     */
    public function isLicenseValid() {
        return $this->updateChecker->isLicenseValid();
    }
}
