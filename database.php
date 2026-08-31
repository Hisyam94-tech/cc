<?php
// database.php - Updated for InfinityFree MySQL with spare parts table
class Database {
    private $host = "sql100.infinityfree.com";
    private $db_name = "if0_40188936_ccdatabase";
    private $username = "if0_40188936"; // Your InfinityFree username
    private $password = "jPLDC1Iny1nGd"; // Your InfinityFree MySQL password
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
            
            // Create tables if they don't exist
            $this->createTables();
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    private function createTables() {
        // Create repair_orders table if it doesn't exist
        $repairOrdersTable = "CREATE TABLE IF NOT EXISTS repair_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(20) UNIQUE NOT NULL,
            customer_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100),
            device VARCHAR(100) NOT NULL,
            issue TEXT NOT NULL,
            estimated_cost DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('in-progress', 'completed', 'Pending-Payment', 'cancelled') DEFAULT 'in-progress',
            date_received DATE NOT NULL,
            end_date DATE,
            images LONGTEXT,
            components_changed LONGTEXT,
            updates LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        // Create spare_parts table if it doesn't exist
        $sparePartsTable = "CREATE TABLE IF NOT EXISTS spare_parts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_number VARCHAR(50) UNIQUE NOT NULL,
            part_name VARCHAR(100) NOT NULL,
            description TEXT,
            quantity INT DEFAULT 0,
            unit_price DECIMAL(10,2) DEFAULT 0.00,
            supplier VARCHAR(100),
            location VARCHAR(50),
            min_quantity INT DEFAULT 5,
            category VARCHAR(50) DEFAULT 'Other',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        try {
            $this->conn->exec($repairOrdersTable);
            $this->conn->exec($sparePartsTable);
        } catch(PDOException $e) {
            // Table creation errors are acceptable if tables already exist
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }

        // Migration: add 'updates' column if it doesn't exist
        try { $this->conn->exec("ALTER TABLE repair_orders ADD COLUMN updates LONGTEXT"); } catch(PDOException $e) {}

        // Migration: upgrade images/components_changed/updates to LONGTEXT to support large base64 images
        try { $this->conn->exec("ALTER TABLE repair_orders MODIFY COLUMN images LONGTEXT"); } catch(PDOException $e) {}
        try { $this->conn->exec("ALTER TABLE repair_orders MODIFY COLUMN components_changed LONGTEXT"); } catch(PDOException $e) {}
        try { $this->conn->exec("ALTER TABLE repair_orders MODIFY COLUMN updates LONGTEXT"); } catch(PDOException $e) {}
    }
}
?>