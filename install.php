<?php
/**
 * Installation script for Energie Label Calculator
 * 
 * This script helps with the initial setup of the plugin
 * Run this script once after uploading the plugin to WordPress
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress, check if we're running from command line
    if (php_sapi_name() !== 'cli') {
        die('Direct access not allowed.');
    }
}

echo "=== Energie Label Calculator Installation ===\n\n";

// Check if config.php exists
if (!file_exists('config.php')) {
    echo "Creating config.php from example...\n";
    
    if (file_exists('config.example.php')) {
        copy('config.example.php', 'config.php');
        echo "✓ config.php created successfully\n";
        echo "⚠️  IMPORTANT: Please edit config.php and add your actual API key!\n\n";
    } else {
        echo "✗ Error: config.example.php not found\n";
        exit(1);
    }
} else {
    echo "✓ config.php already exists\n";
}

// Check file permissions
$files_to_check = [
    'config.php' => 644,
    'energie-label-calculator.php' => 644,
    'assets/css/elc-style.css' => 644,
    'assets/js/elc-script.js' => 644
];

echo "\nChecking file permissions...\n";
foreach ($files_to_check as $file => $expected_perms) {
    if (file_exists($file)) {
        $current_perms = fileperms($file) & 0777;
        if ($current_perms == $expected_perms) {
            echo "✓ $file: correct permissions\n";
        } else {
            echo "⚠️  $file: incorrect permissions ($current_perms, should be $expected_perms)\n";
        }
    } else {
        echo "✗ $file: file not found\n";
    }
}

// Check directory structure
echo "\nChecking directory structure...\n";
$required_dirs = ['assets', 'assets/css', 'assets/js'];
foreach ($required_dirs as $dir) {
    if (is_dir($dir)) {
        echo "✓ $dir: exists\n";
    } else {
        echo "✗ $dir: missing\n";
    }
}

// Check required files
echo "\nChecking required files...\n";
$required_files = [
    'energie-label-calculator.php',
    'assets/css/elc-style.css',
    'assets/js/elc-script.js',
    'config.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✓ $file: exists\n";
    } else {
        echo "✗ $file: missing\n";
    }
}

echo "\n=== Installation Summary ===\n";
echo "1. Upload all files to wp-content/plugins/energie-label-calculator/\n";
echo "2. Edit config.php and add your EP Online API key\n";
echo "3. Activate the plugin in WordPress Admin\n";
echo "4. Use shortcode [energie_label_calculator] on any page\n";
echo "5. For Elementor Pro: Add Shortcode widget with [energie_label_calculator]\n\n";

echo "Plugin is ready for installation!\n"; 