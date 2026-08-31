<?php
// database.php
class Database {
    private $host = null;
    private $db_name = null;
    private $username = null;
    private $password = null;
    private $port = 5432;
    private $driver = 'pgsql';
    private $conn;

    public function __construct() {
        $this->loadConfigFromEnvironment();
    }

    private function loadConfigFromEnvironment() {
        $dbUrl = getenv('DATABASE_URL') ?: getenv('SUPABASE_DB_URL') ?: getenv('SUPABASE_URL') ?: $_ENV['DATABASE_URL'] ?? $_ENV['SUPABASE_DB_URL'] ?? $_ENV['SUPABASE_URL'] ?? null;

        if ($dbUrl) {
            $parsed = parse_url($dbUrl);
            if ($parsed && isset($parsed['scheme'])) {
                $this->driver = in_array($parsed['scheme'], ['pgsql', 'postgres', 'postgresql'], true) ? 'pgsql' : 'mysql';
                $this->host = $parsed['host'] ?? '';
                $this->port = isset($parsed['port']) ? (int) $parsed['port'] : ($this->driver === 'pgsql' ? 5432 : 3306);
                $this->username = urldecode($parsed['user'] ?? '');
                $this->password = urldecode($parsed['pass'] ?? '');
                $this->db_name = ltrim($parsed['path'] ?? '/', '/');
            }
            return;
        }

        $this->driver = getenv('DB_DRIVER') ?: getenv('SUPABASE_DB_DRIVER') ?: ($_ENV['DB_DRIVER'] ?? 'pgsql');
        $this->host = getenv('DB_HOST') ?: getenv('SUPABASE_DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
        $this->port = (int) (getenv('DB_PORT') ?: getenv('SUPABASE_DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($this->driver === 'pgsql' ? 5432 : 3306)));
        $this->db_name = getenv('DB_NAME') ?: getenv('SUPABASE_DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'postgres');
        $this->username = getenv('DB_USERNAME') ?: getenv('SUPABASE_DB_USER') ?: ($_ENV['DB_USERNAME'] ?? $_ENV['SUPABASE_DB_USER'] ?? 'postgres');
        $this->password = getenv('DB_PASSWORD') ?: getenv('SUPABASE_DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? $_ENV['SUPABASE_DB_PASSWORD'] ?? '');
    }

    public function getConnection() {
        $this->conn = null;

        try {
            if ($this->driver === 'pgsql') {
                $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name};sslmode=require";
                $this->conn = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } else {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            }

            $this->createTables();
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    private function createTables() {
        if ($this->driver === 'pgsql') {
            $repairOrdersTable = "CREATE TABLE IF NOT EXISTS repair_orders (
                id BIGSERIAL PRIMARY KEY,
                order_number VARCHAR(20) UNIQUE NOT NULL,
                customer_name VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                email VARCHAR(100),
                device VARCHAR(100) NOT NULL,
                issue TEXT NOT NULL,
                estimated_cost DECIMAL(10,2) DEFAULT 0.00,
                status TEXT DEFAULT 'in-progress' CHECK (status IN ('in-progress', 'completed', 'Pending-Payment', 'cancelled')),
                date_received DATE NOT NULL,
                end_date DATE,
                images TEXT,
                components_changed TEXT,
                updates TEXT,
                created_at TIMESTAMPTZ DEFAULT NOW(),
                updated_at TIMESTAMPTZ DEFAULT NOW()
            )";

            $sparePartsTable = "CREATE TABLE IF NOT EXISTS spare_parts (
                id BIGSERIAL PRIMARY KEY,
                part_number VARCHAR(50) UNIQUE NOT NULL,
                part_name VARCHAR(100) NOT NULL,
                description TEXT,
                quantity INTEGER DEFAULT 0,
                unit_price DECIMAL(10,2) DEFAULT 0.00,
                supplier VARCHAR(100),
                location VARCHAR(50),
                min_quantity INTEGER DEFAULT 5,
                category VARCHAR(50) DEFAULT 'Other',
                created_at TIMESTAMPTZ DEFAULT NOW(),
                updated_at TIMESTAMPTZ DEFAULT NOW()
            )";

            $this->conn->exec($repairOrdersTable);
            $this->conn->exec($sparePartsTable);

            $this->conn->exec("ALTER TABLE repair_orders ADD COLUMN IF NOT EXISTS updates TEXT");
            $this->conn->exec("ALTER TABLE repair_orders ADD COLUMN IF NOT EXISTS images TEXT");
            $this->conn->exec("ALTER TABLE repair_orders ADD COLUMN IF NOT EXISTS components_changed TEXT");
            $this->conn->exec("ALTER TABLE repair_orders ALTER COLUMN updated_at SET DEFAULT NOW()");
            $this->conn->exec("ALTER TABLE spare_parts ALTER COLUMN updated_at SET DEFAULT NOW()");
            return;
        }

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
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }

        try { $this->conn->exec("ALTER TABLE repair_orders ADD COLUMN updates LONGTEXT"); } catch (PDOException $e) {}
        try { $this->conn->exec("ALTER TABLE repair_orders MODIFY COLUMN images LONGTEXT"); } catch (PDOException $e) {}
        try { $this->conn->exec("ALTER TABLE repair_orders MODIFY COLUMN components_changed LONGTEXT"); } catch (PDOException $e) {}
        try { $this->conn->exec("ALTER TABLE repair_orders MODIFY COLUMN updates LONGTEXT"); } catch (PDOException $e) {}
    }
}
?>