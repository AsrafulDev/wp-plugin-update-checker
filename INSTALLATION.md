# Installation Guide

## For Plugin Developers

### Option 1: Install via Composer (Recommended)

1. **Add to your plugin's composer.json:**

```bash
cd your-plugin-directory
composer require asrafuldev/wp-plugin-update-checker
```

2. **Include Composer autoloader in your main plugin file:**

```php
<?php
/**
 * Plugin Name: Your Plugin
 * Version: 1.0.0
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

use WHB\UpdateChecker\PluginUpdateChecker;

// Initialize update checker
$updateChecker = new PluginUpdateChecker(
    'https://your-server.com/api/your-plugin-slug',
    __FILE__,
    'your-plugin-slug',
    12
);
```

3. **Done!** Your plugin now has automatic updates.

### Option 2: Manual Installation

1. **Download or clone this repository**

2. **Copy to your plugin's vendor directory:**
```
your-plugin/
├── vendor/
│   └── plugin-update-checker/
│       ├── src/
│       ├── composer.json
│       ├── plugin-update-checker.php
│       └── README.md
├── your-plugin.php
└── composer.json
```

3. **Include the bootstrap file in your main plugin file:**

```php
<?php
/**
 * Plugin Name: Your Plugin
 * Version: 1.0.0
 */

// Load update checker
require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';

use WHB\UpdateChecker\PluginUpdateChecker;

// Initialize
$updateChecker = new PluginUpdateChecker(
    'https://your-server.com/api/your-plugin-slug',
    __FILE__,
    'your-plugin-slug',
    12
);
```

## For License Server Setup

### Laravel License Server

If you're using the companion Laravel license server, the API endpoint is already configured:

**Endpoint:** `https://your-server.com/api/{plugin-slug}`

**Optional License Parameter:** `?license=YOUR_LICENSE_KEY`

The server will automatically return WordPress-compatible JSON response.

### Custom License Server

If using a custom license server, implement this endpoint:

```php
// GET /api/{plugin-slug}
{
    "name": "Plugin Name",
    "slug": "plugin-slug",
    "version": "1.2.0",
    "author": "Author Name",
    "homepage": "https://plugin-site.com",
    "download_url": "https://server.com/download/secure-token",
    "requires": "5.0",
    "tested": "6.4",
    "requires_php": "7.4",
    "last_updated": "2025-12-13 10:00:00",
    "sections": {
        "description": "Plugin description",
        "changelog": "<h4>1.2.0</h4><p>Changes...</p>"
    }
}
```

## Testing Installation

### 1. Check Class Loaded

```php
if (class_exists('WHB\UpdateChecker\PluginUpdateChecker')) {
    echo "Update checker loaded successfully!";
}
```

### 2. Force Update Check

Visit: `wp-admin/plugins.php` and click "Check for updates" link on your plugin.

### 3. Test API Endpoint

```bash
curl https://your-server.com/api/your-plugin-slug
```

Should return JSON with plugin metadata.

### 4. Debug Mode

Enable WordPress debug mode:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `wp-content/debug.log` for update checker activity.

## Common Issues

### "Class not found" Error

**Solution:** Ensure autoloader is included before instantiating the class.

```php
// Composer
require_once __DIR__ . '/vendor/autoload.php';

// OR Manual
require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
```

### Updates Not Showing

1. ✅ Check API endpoint is accessible
2. ✅ Verify plugin slug matches endpoint
3. ✅ Force update check manually
4. ✅ Check WordPress debug log

### License Key Not Working

1. ✅ Verify license is activated on server
2. ✅ Check license key is passed correctly
3. ✅ Test endpoint with license parameter
4. ✅ Review server logs

## Next Steps

1. **Review Examples:** See `example-integration.php` for complete examples
2. **Customize:** Add license management UI to your plugin
3. **Test:** Use development server to test updates
4. **Deploy:** Configure production license server
5. **Monitor:** Check update logs and user feedback

## Support

- **Documentation:** [README.md](README.md)
- **Examples:** [example-integration.php](example-integration.php)
- **Issues:** [GitHub Issues](https://github.com/AsrafulDev/wp-plugin-update-checker/issues)

## Version Requirements

- PHP >= 7.4
- WordPress >= 5.0
- Composer >= 2.0 (if using Composer installation)
