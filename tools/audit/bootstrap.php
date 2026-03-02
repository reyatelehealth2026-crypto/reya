<?php
/**
 * Bootstrap file for audit tool tests
 * Manually loads all required classes since composer autoloader needs regeneration
 */

// Load composer autoloader first
require_once __DIR__ . '/../../vendor/autoload.php';

// Manually register Tools namespace autoloader
spl_autoload_register(function ($class) {
    // Only handle Tools namespace
    if (strpos($class, 'Tools\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $classPath = str_replace('Tools' . DIRECTORY_SEPARATOR, '', $classPath);
    
    $file = __DIR__ . '/../../tools/' . $classPath . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});
