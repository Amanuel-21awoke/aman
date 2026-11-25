<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'injibara_training_hub';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Auto-ensure categories table exists for existing installations
$conn->query("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("INSERT IGNORE INTO categories (name, description) VALUES
    ('ICT & Data Analytics', 'Practical computing, data literacy, and digital tools'),
    ('Pedagogy & Digital Teaching', 'Modern classroom & digital assessment methodologies'),
    ('Entrepreneurship & Agribusiness', 'Enterprise creation, cooperatives, and value chains'),
    ('Green Economy & Environment', 'Sustainable resource management and climate resilience')");

// Auto-ensure training_programs has fee_etb and category columns for compatibility
$col_chk = $conn->query("SHOW COLUMNS FROM training_programs LIKE 'fee_etb'");
if ($col_chk && $col_chk->num_rows === 0) {
    $birr_chk = $conn->query("SHOW COLUMNS FROM training_programs LIKE 'fee_birr'");
    if ($birr_chk && $birr_chk->num_rows > 0) {
        $conn->query("ALTER TABLE training_programs ADD COLUMN fee_etb DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        $conn->query("UPDATE training_programs SET fee_etb = COALESCE(fee_birr, 0.00)");
    } else {
        $conn->query("ALTER TABLE training_programs ADD COLUMN fee_etb DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
}
$cat_col_chk = $conn->query("SHOW COLUMNS FROM training_programs LIKE 'category'");
if ($cat_col_chk && $cat_col_chk->num_rows === 0) {
    $conn->query("ALTER TABLE training_programs ADD COLUMN category VARCHAR(120) DEFAULT 'ICT & Data Analytics'");
}

?>
