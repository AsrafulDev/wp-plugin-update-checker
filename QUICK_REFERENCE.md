# Quick Reference

## Installation

### Composer
```bash
composer require asrafuldev/wp-plugin-update-checker
```

### Manual
Copy folder to `your-plugin/vendor/plugin-update-checker/`

## Basic Usage

```php
use WHB\UpdateChecker\PluginUpdateChecker;

$checker = new PluginUpdateChecker(
    'https://server.com/api/plugin-slug', // API URL
    __FILE__,                              // Plugin file
    'plugin-slug',                         // Slug (optional)
    12,                                    // Check interval (hours)
    'license-key'                          // License (optional)
);
```

## Methods

| Method | Description |
|--------|-------------|
| `setLicenseKey($key)` | Update license key |
| `getLicenseKey()` | Get current license |
| `forceCheck()` | Force update check (bypass cache) |

## API Response Format

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
        "description": "...",
        "changelog": "..."
    }
}
```

## Debug

```php
// Enable debug
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check log
// wp-content/debug.log
```

## Common Patterns

### Force Update Check
```php
add_filter('plugin_action_links_plugin/plugin.php', function($links) use ($checker) {
    if (isset($_GET['force_check'])) {
        $checker->forceCheck();
    }
    $links[] = '<a href="?force_check=1">Check Updates</a>';
    return $links;
});
```

### Dynamic License
```php
add_action('update_option_license_key', function($old, $new) use ($checker) {
    $checker->setLicenseKey($new);
}, 10, 2);
```

## Requirements

- PHP >= 7.4
- WordPress >= 5.0

## Files

- `src/PluginUpdateChecker.php` - Main class
- `plugin-update-checker.php` - Bootstrap
- `example-integration.php` - Examples
- `README.md` - Full documentation
- `INSTALLATION.md` - Setup guide
- `composer.json` - Composer config

## Support

- [Full Documentation](README.md)
- [Installation Guide](INSTALLATION.md)
- [Examples](example-integration.php)
- [GitHub Issues](https://github.com/AsrafulDev/wp-plugin-update-checker/issues)
