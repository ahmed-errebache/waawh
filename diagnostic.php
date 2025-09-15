<?php
/**
 * Diagnostic script for WAAWH application deployment
 * This script helps diagnose common deployment issues on Hostinger
 */

echo "<h1>WAAWH Application Diagnostics</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style>";

// Check PHP version
echo "<h2>PHP Environment</h2>";
echo "PHP Version: " . phpversion();
if (version_compare(phpversion(), '8.0', '>=')) {
    echo " <span class='ok'>✓ OK</span><br>";
} else {
    echo " <span class='error'>✗ REQUIRES PHP 8.0+</span><br>";
}

// Check required extensions
echo "<h2>Required PHP Extensions</h2>";
$required_extensions = ['pdo', 'pdo_sqlite', 'json', 'mbstring'];
foreach ($required_extensions as $ext) {
    echo "Extension {$ext}: ";
    if (extension_loaded($ext)) {
        echo "<span class='ok'>✓ Available</span><br>";
    } else {
        echo "<span class='error'>✗ Missing</span><br>";
    }
}

// Check directories and permissions
echo "<h2>Directory Permissions</h2>";
$directories = [
    __DIR__ . '/data' => 'Data directory',
    __DIR__ . '/uploads' => 'Uploads directory'
];

foreach ($directories as $dir => $name) {
    echo "{$name} ({$dir}): ";
    if (!is_dir($dir)) {
        echo "<span class='error'>✗ Does not exist</span><br>";
        continue;
    }
    
    if (is_writable($dir)) {
        echo "<span class='ok'>✓ Writable</span>";
    } else {
        echo "<span class='error'>✗ Not writable</span>";
    }
    
    $perms = substr(sprintf('%o', fileperms($dir)), -4);
    echo " (permissions: {$perms})<br>";
}

// Check database connection
echo "<h2>Database Connection</h2>";
try {
    require_once __DIR__ . '/config.php';
    $db = connect_db();
    echo "Database connection: <span class='ok'>✓ OK</span><br>";
    
    // Check if tables exist
    $tables = ['users', 'surveys', 'questions', 'sessions', 'participants', 'responses'];
    echo "Database tables:<br>";
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM {$table}");
            $count = $stmt->fetchColumn();
            echo "- {$table}: <span class='ok'>✓ OK ({$count} records)</span><br>";
        } catch (Exception $e) {
            echo "- {$table}: <span class='error'>✗ Error: " . $e->getMessage() . "</span><br>";
        }
    }
    
} catch (Exception $e) {
    echo "Database connection: <span class='error'>✗ Failed: " . $e->getMessage() . "</span><br>";
}

// Check file uploads
echo "<h2>File Upload Configuration</h2>";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post max size: " . ini_get('post_max_size') . "<br>";
echo "Max execution time: " . ini_get('max_execution_time') . " seconds<br>";

// Check if .htaccess is working
echo "<h2>Server Configuration</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $mod_rewrite = in_array('mod_rewrite', $modules);
    echo "Apache mod_rewrite: " . ($mod_rewrite ? "<span class='ok'>✓ Available</span>" : "<span class='error'>✗ Not available</span>") . "<br>";
} else {
    echo "Apache modules: <span class='warning'>? Cannot detect (not Apache or restricted)</span><br>";
}

echo "<h2>Error Logs</h2>";
echo "PHP error log: " . (ini_get('log_errors') ? 'Enabled' : 'Disabled') . "<br>";
echo "Error log location: " . (ini_get('error_log') ?: 'Default system log') . "<br>";

echo "<h2>Next Steps</h2>";
echo "<ul>";
echo "<li>If you see any red errors above, contact your hosting provider</li>";
echo "<li>Ensure data/ and uploads/ directories have write permissions (755 or 775)</li>";
echo "<li>Check your hosting control panel for error logs</li>";
echo "<li>If using shared hosting, some features may be restricted</li>";
echo "</ul>";

echo "<p><strong>If everything shows OK above, try accessing <a href='index.php'>index.php</a> directly.</strong></p>";
?>