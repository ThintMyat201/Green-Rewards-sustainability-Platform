<?php
// Migration Runner for Profile Fields
require_once __DIR__ . '/config/db.php';

echo "=== Profile Fields Migration ===\n\n";

try {
    // Check if columns already exist using information_schema
    $columns = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users' AND TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
    
    $hasStudentId = in_array('student_id', $columns);
    $hasPhone = in_array('phone', $columns);
    $hasAddress = in_array('address', $columns);
    $hasProfilePicture = in_array('profile_picture_path', $columns);
    
    // Run migrations only if columns don't exist
    $migrationCount = 0;
    
    if (!$hasStudentId) {
        echo "Adding student_id column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN student_id VARCHAR(50) DEFAULT NULL UNIQUE");
        $migrationCount++;
    } else {
        echo "✓ student_id column already exists\n";
    }
    
    if (!$hasPhone) {
        echo "Adding phone column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
        $migrationCount++;
    } else {
        echo "✓ phone column already exists\n";
    }
    
    if (!$hasAddress) {
        echo "Adding address column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL");
        $migrationCount++;
    } else {
        echo "✓ address column already exists\n";
    }
    
    if (!$hasProfilePicture) {
        echo "Adding profile_picture_path column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture_path VARCHAR(255) DEFAULT NULL");
        $migrationCount++;
    } else {
        echo "✓ profile_picture_path column already exists\n";
    }
    
    // Create index if it doesn't exist
    echo "Ensuring student_id index exists...\n";
    try {
        $pdo->exec("CREATE INDEX idx_student_id ON users(student_id)");
        echo "Created index idx_student_id\n";
    } catch (Exception $e) {
        echo "✓ Index idx_student_id already exists\n";
    }
    
    echo "\n=== Migration Complete ===\n";
    if ($migrationCount > 0) {
        echo "✓ Successfully added $migrationCount new columns\n";
    } else {
        echo "✓ All columns already exist. No changes needed.\n";
    }
    
    echo "\n✓ Profile fields are ready to use!\n";
    
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
