create table if not exists repair_orders (
  id bigserial primary key,
  order_number varchar(20) unique not null,
  customer_name varchar(100) not null,
  phone varchar(20) not null,
  email varchar(100),
  device varchar(100) not null,
  issue text not null,
  estimated_cost decimal(10,2) default 0.00,
  status text default 'in-progress' check (status in ('in-progress', 'completed', 'Pending-Payment', 'cancelled')),
  date_received date not null,
  end_date date,
  images text,
  components_changed text,
  updates text,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create table if not exists spare_parts (
  id bigserial primary key,
  part_number varchar(50) unique not null,
  part_name varchar(100) not null,
  description text,
  quantity integer default 0,
  unit_price decimal(10,2) default 0.00,
  supplier varchar(100),
  location varchar(50),
  min_quantity integer default 5,
  category varchar(50) default 'Other',
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists idx_repair_orders_customer_name on repair_orders (customer_name);
create index if not exists idx_repair_orders_status on repair_orders (status);
create index if not exists idx_spare_parts_part_number on spare_parts (part_number);
create index if not exists idx_spare_parts_category on spare_parts (category);
