# Plugin Update Checker Library

Lightweight WordPress plugin update checker for integrating with your license server. This library enables automatic updates for your WordPress plugins without relying on third-party services like Appsero.

## Features

- ✅ Automatic update checks
- ✅ WordPress.org compatible API format
- ✅ License key support for premium plugins
- ✅ Manual update check button
- ✅ Cached requests to reduce server load
- ✅ Plugin information popup
- ✅ No external dependencies

## Installation

1. Copy the `plugin-update-checker` folder to your plugin's `vendor/` directory
2. Include the library in your main plugin file

```php
require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
```

## Basic Usage

### Free Plugin (No License Required)

```php
use WHB\UpdateChecker\PluginUpdateChecker;

$updateChecker = new PluginUpdateChecker(
    'http://your-license-server.com/api/your-plugin-slug',
    __FILE__,
    'your-plugin-slug',
    12 // Check every 12 hours
);
```

### Premium Plugin (License Required)

```php
use WHB\UpdateChecker\PluginUpdateChecker;

// Get license key from your settings
$licenseKey = get_option('your_plugin_license_key', '');

$updateChecker = new PluginUpdateChecker(
    'http://your-license-server.com/api/your-plugin-slug',
    __FILE__,
    'your-plugin-slug',
    12, // Check every 12 hours
    $licenseKey
);
```

## Integration with WP Host Billing

Add this to `wp-host-billing.php`:

```php
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

// If you have a license system, add license key
add_action('init', function() use ($whb_update_checker) {
    $licenseKey = get_option('whb_license_key', '');
    if (!empty($licenseKey)) {
        $whb_update_checker->setLicenseKey($licenseKey);
    }
});
```

## License Management (Optional)

For premium plugins, you can add a license activation page:

```php
// Add license settings page
add_action('admin_menu', function() {
    add_submenu_page(
        'whb-dashboard',
        'License',
        'License',
        'manage_options',
        'whb-license',
        'whb_render_license_page'
    );
});

function whb_render_license_page() {
    $licenseKey = get_option('whb_license_key', '');
    ?>
    <div class="wrap">
        <h1>License Settings</h1>
        <form method="post">
            <?php wp_nonce_field('whb_license_action', 'whb_license_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>License Key</th>
                    <td>
                        <input type="text" name="license_key" value="<?php echo esc_attr($licenseKey); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button('Activate License'); ?>
        </form>
    </div>
    <?php
}
```

## Manual Update Check

Add a manual check button to plugin actions:

```php
add_filter('plugin_action_links_wp-host-billing/wp-host-billing.php', function($links) use ($whb_update_checker) {
    $checkUrl = add_query_arg(['whb_force_check' => '1']);
    $links[] = '<a href="' . esc_url($checkUrl) . '">Check for Updates</a>';
    return $links;
});

// Handle manual check
add_action('admin_init', function() use ($whb_update_checker) {
    if (isset($_GET['whb_force_check'])) {
        $whb_update_checker->forceCheck();
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Update check completed!</p></div>';
        });
    }
});
```

## API Methods

### Constructor

```php
new PluginUpdateChecker($metadataUrl, $pluginFile, $slug, $checkPeriod, $licenseKey)
```

- `$metadataUrl` - URL to your license server API endpoint
- `$pluginFile` - Main plugin file path (`__FILE__`)
- `$slug` - Plugin slug (optional, auto-detected)
- `$checkPeriod` - Hours between update checks (default: 12)
- `$licenseKey` - License key for premium plugins (optional)

### Methods

- `setLicenseKey($licenseKey)` - Update license key
- `getLicenseKey()` - Get current license key
- `forceCheck()` - Force immediate update check (bypasses cache)

## Server Requirements

Your license server must provide a WordPress-compatible API endpoint:

```
GET /api/{plugin-slug}?license={license-key}
```

Response format:
```json
{
    "name": "Plugin Name",
    "slug": "plugin-slug",
    "version": "1.2.0",
    "download_url": "https://server.com/download/token",
    "requires": "5.0",
    "tested": "6.4",
    "requires_php": "7.4",
    "sections": {
        "description": "Plugin description",
        "changelog": "<h4>1.2.0</h4><p>New features...</p>"
    }
}
```

## How It Works

1. WordPress checks for plugin updates every 12 hours
2. The library sends a request to your license server
3. Server returns plugin metadata (version, download URL, changelog)
4. If a newer version is available, WordPress shows update notification
5. User clicks "Update Now" to download and install

## Cache System

The library caches update checks to reduce server load:

- Default cache period: 12 hours
- Cache stored in WordPress options table
- Force check bypasses cache
- Cache cleared after successful update

## Troubleshooting

### Updates Not Showing

1. Check plugin slug matches server endpoint
2. Verify license server is accessible
3. Force update check manually
4. Check WordPress debug log for errors

### Download Fails

1. Verify download URL is accessible
2. Check license key is valid
3. Ensure server returns proper ZIP file
4. Check file permissions on wp-content/uploads

### License Issues

1. Verify license key activation on server
2. Check site URL matches activation
3. Test API endpoint directly in browser
4. Review server logs for errors

## Security

- All HTTP requests use WordPress core functions
- License keys transmitted via HTTPS (recommended)
- Download URLs use time-limited tokens
- Server validates all license activations

## Support

For issues or questions:
- Check `example-integration.php` for complete examples
- Review server API documentation
- Test endpoints with curl/Postman
- Enable WordPress debug mode for detailed errors

## License

This library is part of WP Host Billing plugin and follows the same license terms.
