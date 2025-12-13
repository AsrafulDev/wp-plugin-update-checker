<?php
/**
 * Plugin Update Checker - Bootstrap File
 * 
 * This file maintains backward compatibility with direct inclusion.
 * For new projects, use Composer autoloader instead.
 * 
 * @package WHB\UpdateChecker
 * @version 1.0.0
 * @author Asraful Islam
 * @license MIT
 */

// Check if Composer autoloader exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Manual autoload for backward compatibility
    require_once __DIR__ . '/src/PluginUpdateChecker.php';
}

// Verify class is loaded
if (!class_exists('WHB\UpdateChecker\PluginUpdateChecker')) {
    throw new \Exception('PluginUpdateChecker class not found. Please check installation.');
}
