# WordPress Plugin Update Checker & License Client

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/AsrafulDev/wp-plugin-update-checker/releases)
[![Stability](https://img.shields.io/badge/stability-stable-green.svg)](https://github.com/AsrafulDev/wp-plugin-update-checker)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D7.4-blue.svg)](https://php.net)

**Complete License Management & Update System for WordPress Plugins**

A comprehensive solution for WordPress plugin licensing, updates, and support - **integrated in just one line of code**.

## ✨ Features

- 🚀 **One-Line Integration** - Complete setup in single line
- 🔒 **License Management** - Full activation/deactivation UI
- 🔄 **Automatic Updates** - WordPress native update integration
- 🎫 **Support System** - Built-in support ticket form
- ⏰ **Hourly Validation** - Automatic remote license checking
- 💰 **Free & Paid Support** - Works for both business models
- 🎨 **Beautiful UI** - WordPress-native admin interface
- 📊 **Usage Tracking** - Track activations and updates

## 🚀 Quick Start

### Installation

```bash
composer require asrafuldev/wp-plugin-update-checker
```

### ONE-LINE Integration

```php
<?php
// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// ONE LINE - Complete license system! 🎉
$license = new \WHB\UpdateChecker\LicenseClient([
    'slug' => 'my-plugin',
    'plugin_name' => 'My Awesome Plugin',
    'plugin_file' => __FILE__,
    'license_server_url' => 'https://license-server.com',
    'requires_license' => true, // true = paid, false = free
]);

// Optional: Check license before enabling features
if ($license->isLicenseValid()) {
    // Initialize premium features
}
```

That's it! You now have:
- ✅ Complete license management page
- ✅ Support ticket system
- ✅ Automatic update checking
- ✅ Hourly license validation
- ✅ WordPress native update UI

## 📖 Complete Documentation

See [ONE_LINE_INTEGRATION_GUIDE.md](../../ONE_LINE_INTEGRATION_GUIDE.md) for full documentation.

## 🎯 Quick Examples

### Free Plugin
```php
$license = new \WHB\UpdateChecker\LicenseClient([
    'slug' => 'free-plugin',
    'plugin_name' => 'Free Plugin',
    'plugin_file' => __FILE__,
    'license_server_url' => 'https://server.com',
    'requires_license' => false, // FREE
]);
```

### Paid Plugin
```php
$license = new \WHB\UpdateChecker\LicenseClient([
    'slug' => 'paid-plugin',
    'plugin_name' => 'Premium Plugin',
    'plugin_file' => __FILE__,
    'license_server_url' => 'https://server.com',
    'requires_license' => true, // PAID
]);
```

## 🔌 Laravel API Required

Your license server needs these endpoints:
- `POST /api/v1/licenses/activate` - Activate license
- `POST /api/v1/licenses/deactivate` - Deactivate license
- `GET /api/v1/licenses/{key}/status` - Check status
- `POST /api/v1/updates/check` - Check for updates
- `POST /api/v1/support/tickets` - Submit support ticket (optional)

## 📦 What You Get

### Admin UI
- **License Tab** - Activation/status
- **Support Tab** - Submit tickets
- **About Tab** - System information

### Automation
- Hourly license validation
- Automatic update checks
- WordPress native updates
- Local database caching

### Developer-Friendly
- One-line integration
- Feature gating support
- Free/paid plugin modes
- Extensible architecture

## 🎨 Configuration

```php
$license = new \WHB\UpdateChecker\LicenseClient([
    // Required
    'slug' => 'my-plugin',
    'plugin_name' => 'My Plugin',
    'plugin_file' => __FILE__,
    'license_server_url' => 'https://server.com',
    
    // Optional
    'requires_license' => true,        // true = paid, false = free
    'update_check_period' => 12,       // Hours between update checks
    'license_check_period' => 1,       // Hours between license checks
    'parent_menu' => 'my-menu',        // Parent menu slug
    'support_enabled' => true,         // Enable support form
]);
```

## 🚀 Changelog

### Version 2.0.0 (Current)
- ✅ **LicenseClient** - One-line integration
- ✅ Complete admin UI with tabs
- ✅ Support ticket system
- ✅ Free/paid plugin support
- ✅ Hourly remote validation
- ✅ Beautiful WordPress interface

### Version 1.1.0
- Added PluginUpdateChecker
- Added hourly validation
- Added local DB caching

## 📄 License

MIT License

## 🤝 Author

**Asraful Islam**

---

**🎉 Complete license management in just one line of code!**
