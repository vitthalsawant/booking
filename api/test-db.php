<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    
    // Test basic connection
    $pdo->query('SELECT 1');
    
    // Check if tables exist
    $tables = ['space_types', 'locations', 'spaces', 'space_availability', 'bookings'];
    $existingTables = [];
    $missingTables = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $existingTables[] = $table;
        } else {
            $missingTables[] = $table;
        }
    }
    
    // Count records
    $counts = [];
    foreach ($existingTables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
        $counts[$table] = (int) $stmt->fetch()['cnt'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'existing_tables' => $existingTables,
        'missing_tables' => $missingTables,
        'record_counts' => $counts,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $exception->getMessage(),
    ]);
}

