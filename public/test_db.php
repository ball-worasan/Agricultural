<?php
require_once __DIR__ . '/../app/bootstrap/bootstrap.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test</title></head><body>";
echo "<h1>Database Connection Test</h1>";

echo "<p>ROLE_ADMIN defined: " . (defined('ROLE_ADMIN') ? 'YES (' . ROLE_ADMIN . ')' : 'NO') . "</p>";
echo "<p>Database class exists: " . (class_exists('Database') ? 'YES' : 'NO') . "</p>";

try {
    $users = Database::fetchAll('SELECT user_id, username, full_name, role FROM users ORDER BY created_at DESC');
    echo "<p><strong>Total users: " . count($users) . "</strong></p>";
    
    if (count($users) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th></tr>";
        foreach ($users as $u) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars((string)$u['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$u['username']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$u['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$u['role']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>No users found!</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
