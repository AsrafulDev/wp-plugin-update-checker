<?php
/**
 * Plugin Update Checker - Integration Examples
 * 
 * This file demonstrates various ways to integrate the update checker in your WordPress plugin.
 * Copy the relevant example to your main plugin file.
 * 
 * @package WHB\UpdateChecker
 * @version 1.0.0
 */

// ============================================================================
// INSTALLATION METHOD 1: Using Composer (Recommended)
// ============================================================================

/*
 * 1. Install via Composer in your plugin root:
 *    composer require asrafuldev/wp-plugin-update-checker
 * 
 * 2. Include the Composer autoloader in your main plugin file:
 */

// require_once __DIR__ . '/vendor/autoload.php';

// ============================================================================
// INSTALLATION METHOD 2: Manual Installation
// ============================================================================

/*
 * 1. Copy the plugin-update-checker folder to your plugin's vendor directory
 * 2. Include the bootstrap file:
 */

// require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';

// ============================================================================
// EXAMPLE 1: Basic Usage - Free Plugin (No License)
// ============================================================================

use WHB\UpdateChecker\PluginUpdateChecker;

// Initialize update checker for a free plugin
$updateChecker = new PluginUpdateChecker(
    'https://your-server.com/api/your-plugin-slug', // License server endpoint
    __FILE__,                                        // Main plugin file
    'your-plugin-slug',                              // Plugin slug (optional, auto-detected)
    12                                               // Check interval in hours (default: 12)
);

// ============================================================================
// EXAMPLE 2: Premium Plugin with License Key
// ============================================================================

// Get license key from your plugin settings
$licenseKey = get_option('your_plugin_license_key', '');

$updateChecker = new PluginUpdateChecker(
    'https://your-server.com/api/your-plugin-slug',
    __FILE__,
    'your-plugin-slug',
    12,
    $licenseKey // Pass license key as 5th parameter
);

// ============================================================================
// EXAMPLE 3: Dynamic License Update
// ============================================================================

// Update license key when settings are saved
add_action('update_option_your_plugin_license_key', function($old_value, $value) use ($updateChecker) {
    $updateChecker->setLicenseKey($value);
}, 10, 2);

// ============================================================================
// EXAMPLE 4: Manual Update Check Button
// ============================================================================

// Add "Check for Updates" link to plugin actions
add_filter('plugin_action_links_your-plugin/your-plugin.php', function($links) use ($updateChecker) {
    // Handle force check
    if (isset($_GET['your_plugin_force_check']) && current_user_can('manage_options')) {
        check_admin_referer('your_plugin_force_check');
        $updateChecker->forceCheck();
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Update check completed!</strong></p>';
            echo '</div>';
        });
    }
    
    // Add check link
    $checkUrl = wp_nonce_url(
        add_query_arg('your_plugin_force_check', '1'),
        'your_plugin_force_check'
    );
    
    $links[] = '<a href="' . esc_url($checkUrl) . '">Check for Updates</a>';
    
    return $links;
});

// ============================================================================
// EXAMPLE 5: License Activation Page
// ============================================================================

// Add license settings page
add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',           // Parent menu
        'Plugin License',                // Page title
        'License',                       // Menu title
        'manage_options',                // Capability
        'your-plugin-license',           // Menu slug
        'your_plugin_license_page'       // Callback function
    );
});

function your_plugin_license_page() {
    global $updateChecker;
    
    // Handle form submission
    if (isset($_POST['license_key']) && check_admin_referer('your_plugin_license')) {
        $license = sanitize_text_field($_POST['license_key']);
        update_option('your_plugin_license_key', $license);
        
        // Update checker with new license
        $updateChecker->setLicenseKey($license);
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>License key saved and update check triggered!</strong></p>';
        echo '</div>';
    }
    
    $licenseKey = get_option('your_plugin_license_key', '');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('your_plugin_license'); ?>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="license_key">License Key</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="license_key" 
                                   name="license_key" 
                                   value="<?php echo esc_attr($licenseKey); ?>" 
                                   class="regular-text" 
                                   placeholder="Enter your license key" />
                            <p class="description">
                                Enter your license key to receive automatic updates.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php submit_button('Save License Key'); ?>
        </form>
        
        <?php if (!empty($licenseKey)): ?>
        <div class="card">
            <h2>License Information</h2>
            <p><strong>Status:</strong> <span style="color: green;">Active</span></p>
            <p><strong>License Key:</strong> <?php echo esc_html(substr($licenseKey, 0, 10) . '...'); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// EXAMPLE 6: Complete Integration for WP Host Billing Plugin
// ============================================================================

/*
 * Add this to wp-host-billing.php:
 */

/*
// Load update checker
require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';

use WHB\UpdateChecker\PluginUpdateChecker;

// Initialize update checker
$whb_update_checker = new PluginUpdateChecker(
    'http://127.0.0.1:8000/api/wp-host-billing', // Your license server
    __FILE__,
    'wp-host-billing',
    12
);

// Add license management to existing settings page
add_action('whb_settings_page_content', function() use ($whb_update_checker) {
    $licenseKey = get_option('whb_license_key', '');
    ?>
    <div class="whb-license-section">
        <h2>License & Updates</h2>
        <form method="post">
            <?php wp_nonce_field('whb_license_action', 'whb_license_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>License Key</th>
                    <td>
                        <input type="text" 
                               name="whb_license_key" 
                               value="<?php echo esc_attr($licenseKey); ?>" 
                               class="regular-text" />
                        <p class="description">Enter your license key for automatic updates</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save License'); ?>
        </form>
    </div>
    <?php
});

// Handle license key save
add_action('admin_init', function() use ($whb_update_checker) {
    if (isset($_POST['whb_license_key']) && 
        isset($_POST['whb_license_nonce']) && 
        wp_verify_nonce($_POST['whb_license_nonce'], 'whb_license_action')) {
        
        $licenseKey = sanitize_text_field($_POST['whb_license_key']);
        update_option('whb_license_key', $licenseKey);
        $whb_update_checker->setLicenseKey($licenseKey);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success">';
            echo '<p>License key saved and update check completed!</p>';
            echo '</div>';
        });
    }
});

// Add update check to plugin actions
add_filter('plugin_action_links_wp-host-billing/wp-host-billing.php', function($links) use ($whb_update_checker) {
    if (isset($_GET['whb_force_check'])) {
        $whb_update_checker->forceCheck();
    }
    
    $checkUrl = add_query_arg('whb_force_check', '1');
    $links[] = '<a href="' . esc_url($checkUrl) . '">Check for Updates</a>';
    
    return $links;
});
*/

// ============================================================================
// SERVER API RESPONSE FORMAT
// ============================================================================

/*
 * Your license server should return JSON in this format:
 * 
 * GET /api/your-plugin-slug?license=YOUR_LICENSE_KEY
 * 
 * Response:
 * {
 *     "name": "Your Plugin Name",
 *     "slug": "your-plugin-slug",
 *     "version": "1.2.0",
 *     "author": "Your Name",
 *     "homepage": "https://your-site.com",
 *     "download_url": "https://server.com/download/token",
 *     "requires": "5.0",
 *     "tested": "6.4",
 *     "requires_php": "7.4",
 *     "last_updated": "2025-12-13 10:00:00",
 *     "sections": {
 *         "description": "Plugin description here",
 *         "installation": "Installation instructions",
 *         "changelog": "<h4>1.2.0</h4><ul><li>New feature</li></ul>",
 *         "faq": "<h4>Question?</h4><p>Answer</p>"
 *     },
 *     "banners": {
 *         "low": "https://server.com/banner-772x250.jpg",
 *         "high": "https://server.com/banner-1544x500.jpg"
 *     },
 *     "icons": {
 *         "1x": "https://server.com/icon-128x128.png",
 *         "2x": "https://server.com/icon-256x256.png"
 *     }
 * }
 */
