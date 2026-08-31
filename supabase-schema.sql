CREATE TABLE IF NOT EXISTS repair_orders (
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
);

CREATE TABLE IF NOT EXISTS spare_parts (
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
);

CREATE INDEX IF NOT EXISTS idx_repair_orders_customer_name ON repair_orders (customer_name);
CREATE INDEX IF NOT EXISTS idx_repair_orders_status ON repair_orders (status);
CREATE INDEX IF NOT EXISTS idx_spare_parts_part_number ON spare_parts (part_number);
CREATE INDEX IF NOT EXISTS idx_spare_parts_category ON spare_parts (category);
