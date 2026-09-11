-- ============================================================
-- TREADLINE - Smart Fleet Tyre Tracking & Management System
-- Database Schema (MySQL 8+)
-- ============================================================

CREATE DATABASE IF NOT EXISTS treadline CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE treadline;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- SETTINGS (configurable thresholds - never hardcode!)
-- ------------------------------------------------------------
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255)
);

INSERT INTO settings (setting_key, setting_value, description) VALUES
('tread_critical_mm', '2.0', 'Tread depth (mm) at/below which tyre is CRITICAL'),
('tread_watch_mm', '5.0', 'Tread depth (mm) below which tyre is WATCH'),
('psi_tolerance_pct', '10', 'Percent deviation from recommended PSI considered abnormal'),
('health_excellent', '90', 'Health score minimum for EXCELLENT'),
('health_good', '75', 'Health score minimum for GOOD'),
('health_watch', '50', 'Health score minimum for WATCH'),
('health_warning', '25', 'Health score minimum for WARNING'),
('inspection_interval_days', '30', 'Default days between scheduled inspections'),
('company_name', 'Treadline Fleet Solutions', 'Company name for reports'),
('currency', 'KES', 'Default currency code');

-- ------------------------------------------------------------
-- USERS & ROLES
-- ------------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255)
);

INSERT INTO roles (name, description) VALUES
('ADMIN','Full system access'),
('FLEET_MANAGER','Vehicles, tyres, inspections, reports, analytics, inventory'),
('TYRE_INSPECTOR','Vehicle inspections, tyre readings, photos, scanner, checklist'),
('SUPERVISOR','Review inspections, approve replacements/movements'),
('MANAGEMENT','Read-only dashboard, analytics, reports');

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    branch_id INT NULL,
    phone VARCHAR(30),
    status ENUM('ACTIVE','SUSPENDED') DEFAULT 'ACTIVE',
    remember_token VARCHAR(255) NULL,
    reset_token VARCHAR(255) NULL,
    reset_expires DATETIME NULL,
    last_login DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- ------------------------------------------------------------
-- BRANCHES / GROUPS
-- ------------------------------------------------------------
CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(30) UNIQUE NOT NULL,
    location VARCHAR(150),
    manager VARCHAR(150),
    contact VARCHAR(60),
    status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users ADD FOREIGN KEY (branch_id) REFERENCES branches(id);

-- ------------------------------------------------------------
-- VEHICLE TYPES & AXLE CONFIGS (defines tyre-map layout)
-- ------------------------------------------------------------
CREATE TABLE vehicle_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    axle_config ENUM('4X2','4X4','6X2','6X4','8X4','TRAILER_2AXLE','TRAILER_3AXLE','BUS') NOT NULL DEFAULT '4X2',
    positions JSON NOT NULL COMMENT 'Flattened array of tyre position codes, derived from layout_rows',
    layout_rows JSON NULL COMMENT 'Generic editable tyre-map layout: [{label, drive(bool), positions:[...]}, ...] - one row per axle/group',
    spare_default INT NOT NULL DEFAULT 1 COMMENT 'Default number of spare slots for a new vehicle of this type (0 = no spare, e.g. forklifts)'
);

INSERT INTO vehicle_types (name, axle_config, positions, layout_rows, spare_default) VALUES
('Pickup/Light Truck 4x2','4X2','["FL", "FR", "RL", "RR"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RL", "RR"]}]',1),
('Pickup/Light Truck 4x4','4X4','["FL", "FR", "RL", "RR"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RL", "RR"]}]',1),
('Trailer 2-axle','TRAILER_2AXLE','["A1L", "A1R", "A2L", "A2R"]','[{"label": "AXLE 1", "drive": false, "positions": ["A1L", "A1R"]}, {"label": "AXLE 2", "drive": false, "positions": ["A2L", "A2R"]}]',1),
('Bus','BUS','["FL", "FR", "RLI", "RLO", "RRI", "RRO"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RLI", "RLO", "RRI", "RRO"]}]',1),
('Forklift','4X2','["FL", "FR", "RL", "RR"]','[{"label": "FRONT", "drive": false, "positions": ["FL", "FR"]}, {"label": "REAR", "drive": false, "positions": ["RL", "RR"]}]',0),
('TukTuk','4X2','["F", "RL", "RR"]','[{"label": "FRONT", "drive": false, "positions": ["F"]}, {"label": "REAR - DRIVEN", "drive": true, "positions": ["RL", "RR"]}]',0),
('Trailer','TRAILER_3AXLE','["P1", "P2", "P4", "P3", "P5", "P6", "P8", "P7", "P9", "P10", "P12", "P11"]','[{"label": "AXLE 1", "drive": false, "positions": ["P1", "P2", "P4", "P3"]}, {"label": "AXLE 2", "drive": false, "positions": ["P5", "P6", "P8", "P7"]}, {"label": "AXLE 3", "drive": false, "positions": ["P9", "P10", "P12", "P11"]}]',1),
('Prime Mover','8X4','["FL", "FR", "RL1-O", "RL1-I", "RR1-I", "RR1-O", "RL2-O", "RL2-I", "RR2-I", "RR2-O"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE 1 - DRIVEN", "drive": true, "positions": ["RL1-O", "RL1-I", "RR1-I", "RR1-O"]}, {"label": "DRIVE 2 - DRIVEN", "drive": true, "positions": ["RL2-O", "RL2-I", "RR2-I", "RR2-O"]}]',1),
('Isuzu FRR','6X2','["FL", "FR", "RLO", "RLI", "RRI", "RRO"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RLO", "RLI", "RRI", "RRO"]}]',1),
('Isuzu NQR','6X2','["FL", "FR", "RLO", "RLI", "RRI", "RRO"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RLO", "RLI", "RRI", "RRO"]}]',1),
('Isuzu NMR','6X2','["FL", "FR", "RLO", "RLI", "RRI", "RRO"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RLO", "RLI", "RRI", "RRO"]}]',1),
('Truck 6x2','6X2','["FL", "FR", "RLO", "RLI", "RRI", "RRO"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RLO", "RLI", "RRI", "RRO"]}]',1),
('Truck 6x4 (dual rear)','6X4','["FL", "FR", "RLO", "RLI", "RRI", "RRO"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RLO", "RLI", "RRI", "RRO"]}]',1),
('Car Saloon','4X2','["FL", "FR", "RL", "RR"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RL", "RR"]}]',1),
('Car Sales Saloon','4X2','["FL", "FR", "RL", "RR"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RL", "RR"]}]',1),
('SUV','4X2','["FL", "FR", "RL", "RR"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RL", "RR"]}]',1),
('Van','4X2','["FL", "FR", "RL", "RR"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RL", "RR"]}]',1),
('Pick Up','4X2','["FL", "FR", "RL", "RR"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RL", "RR"]}]',1),
('Motorcycle','4X2','["F", "R"]','[{"label": "FRONT", "drive": false, "positions": ["F"]}, {"label": "REAR - DRIVEN", "drive": true, "positions": ["R"]}]',0),
('Tata 713','4X2','["FL", "FR", "RL", "RR"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RL", "RR"]}]',1),
('Tata 1116','6X2','["FL", "FR", "RLO", "RLI", "RRI", "RRO"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RLO", "RLI", "RRI", "RRO"]}]',1),
('Truck 10 Ton','6X2','["FL", "FR", "RLO", "RLI", "RRI", "RRO"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE - DRIVEN", "drive": true, "positions": ["RLO", "RLI", "RRI", "RRO"]}]',1),
('Truck 14 Ton','8X4','["FL", "FR", "RL1-O", "RL1-I", "RR1-I", "RR1-O", "RL2-O", "RL2-I", "RR2-I", "RR2-O"]','[{"label": "STEER", "drive": false, "positions": ["FL", "FR"]}, {"label": "DRIVE 1 - DRIVEN", "drive": true, "positions": ["RL1-O", "RL1-I", "RR1-I", "RR1-O"]}, {"label": "DRIVE 2 - DRIVEN", "drive": true, "positions": ["RL2-O", "RL2-I", "RR2-I", "RR2-O"]}]',1);

-- ------------------------------------------------------------
-- VEHICLES
-- ------------------------------------------------------------
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(30) UNIQUE NOT NULL,
    fleet_number VARCHAR(30),
    vehicle_type_id INT NOT NULL,
    make VARCHAR(60),
    model VARCHAR(60),
    year YEAR,
    vin VARCHAR(60),
    branch_id INT NOT NULL,
    department VARCHAR(100),
    driver_name VARCHAR(150),
    current_mileage INT DEFAULT 0,
    mileage_reading_date DATE,
    status ENUM('ACTIVE','MAINTENANCE','OUT_OF_SERVICE','SOLD','ARCHIVED') DEFAULT 'ACTIVE',
    spare_count INT NOT NULL DEFAULT 1,
    towed_by_vehicle_id INT NULL COMMENT 'For trailers: the truck/prime mover that tows this trailer, if any',
    photo VARCHAR(255),
    date_added DATE DEFAULT (CURRENT_DATE),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (towed_by_vehicle_id) REFERENCES vehicles(id)
);

CREATE TABLE mileage_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    mileage INT NOT NULL,
    reading_date DATE NOT NULL,
    recorded_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- SUPPLIERS
-- ------------------------------------------------------------
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150),
    phone VARCHAR(30),
    email VARCHAR(150),
    address VARCHAR(255),
    products VARCHAR(255),
    status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- TYRES (master record - the physical tyre, tracked for life)
-- ------------------------------------------------------------
CREATE TABLE tyres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_number VARCHAR(100) UNIQUE NOT NULL,
    branding_code VARCHAR(100),
    brand VARCHAR(80) NOT NULL,
    model VARCHAR(80),
    size VARCHAR(30) NOT NULL,
    type ENUM('NEW','RETREAD','USED') DEFAULT 'NEW',
    new_tread_mm DECIMAL(4,1) NOT NULL DEFAULT 8.0,
    recommended_psi DECIMAL(5,1) NOT NULL DEFAULT 32.0,
    load_rating VARCHAR(20),
    speed_rating VARCHAR(10),
    purchase_date DATE,
    supplier_id INT,
    purchase_price DECIMAL(12,2) DEFAULT 0,
    warranty_info VARCHAR(150),
    expected_mileage INT DEFAULT 60000,
    expected_lifespan_days INT DEFAULT 730,
    storage_location VARCHAR(100),
    -- lifecycle status
    status ENUM('STOCK','IN_SERVICE','UNDER_REPAIR','RETREAD','REMOVED','SCRAP') DEFAULT 'STOCK',
    -- current placement (denormalized for fast lookups; source of truth is tyre_movements)
    current_vehicle_id INT NULL,
    current_position VARCHAR(10) NULL,
    current_tread_mm DECIMAL(4,1) NULL,
    current_psi DECIMAL(5,1) NULL,
    install_odometer INT NULL,
    total_repairs INT DEFAULT 0,
    total_retreads INT DEFAULT 0,
    date_added DATE DEFAULT (CURRENT_DATE),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (current_vehicle_id) REFERENCES vehicles(id)
);

-- ------------------------------------------------------------
-- TYRE MOVEMENTS (install / rotate / transfer / remove - full history, never deleted)
-- ------------------------------------------------------------
CREATE TABLE tyre_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tyre_id INT NOT NULL,
    movement_type ENUM('INSTALL','ROTATE','TRANSFER','REMOVE','STOCK_IN') NOT NULL,
    from_vehicle_id INT NULL,
    from_position VARCHAR(10) NULL,
    to_vehicle_id INT NULL,
    to_position VARCHAR(10) NULL,
    odometer INT NULL,
    movement_date DATE NOT NULL DEFAULT (CURRENT_DATE),
    reason VARCHAR(150),
    notes TEXT,
    performed_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tyre_id) REFERENCES tyres(id),
    FOREIGN KEY (from_vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (to_vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- INSPECTIONS
-- ------------------------------------------------------------
CREATE TABLE inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    odometer INT NOT NULL,
    inspection_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    inspector_id INT NOT NULL,
    status ENUM('PENDING','APPROVED','FAILED') DEFAULT 'PENDING',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    notes TEXT,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (inspector_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

CREATE TABLE inspection_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    tyre_id INT NOT NULL,
    position VARCHAR(10) NOT NULL,
    tread_inner DECIMAL(4,1),
    tread_center DECIMAL(4,1),
    tread_outer DECIMAL(4,1),
    tread_avg DECIMAL(4,1),
    tread_min DECIMAL(4,1),
    tread_max DECIMAL(4,1),
    psi_actual DECIMAL(5,1),
    psi_unit ENUM('PSI','BAR') DEFAULT 'PSI',
    psi_status ENUM('LOW','NORMAL','HIGH','CRITICAL'),
    sidewall_condition VARCHAR(50) DEFAULT 'Good',
    tread_condition VARCHAR(50) DEFAULT 'Good',
    valve_ok TINYINT(1) DEFAULT 1,
    rim_ok TINYINT(1) DEFAULT 1,
    nuts_ok TINYINT(1) DEFAULT 1,
    foreign_object TINYINT(1) DEFAULT 0,
    comments TEXT,
    health_score INT,
    health_rating VARCHAR(20),
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (tyre_id) REFERENCES tyres(id)
);

CREATE TABLE inspection_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_item_id INT NOT NULL,
    category ENUM('TREAD','SIDEWALL','SERIAL','BRANDING','DAMAGE','PSI','RIM','OTHER') DEFAULT 'OTHER',
    filepath VARCHAR(255) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_item_id) REFERENCES inspection_items(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- REPAIRS
-- ------------------------------------------------------------
CREATE TABLE tyre_repairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tyre_id INT NOT NULL,
    vehicle_id INT NULL,
    repair_date DATE DEFAULT (CURRENT_DATE),
    odometer INT,
    damage VARCHAR(150),
    repair_type VARCHAR(100),
    technician VARCHAR(150),
    cost DECIMAL(12,2) DEFAULT 0,
    comments TEXT,
    created_by INT,
    FOREIGN KEY (tyre_id) REFERENCES tyres(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- RETREADS
-- ------------------------------------------------------------
CREATE TABLE tyre_retreads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tyre_id INT NOT NULL,
    retread_number VARCHAR(50),
    retread_date DATE,
    supplier_id INT,
    cost DECIMAL(12,2) DEFAULT 0,
    tread_after_retread DECIMAL(4,1),
    mileage_before INT,
    mileage_after INT,
    created_by INT,
    FOREIGN KEY (tyre_id) REFERENCES tyres(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- TYRE REMOVAL RECORDS
-- ------------------------------------------------------------
CREATE TABLE tyre_removals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tyre_id INT NOT NULL,
    vehicle_id INT,
    position VARCHAR(10),
    removal_date DATE DEFAULT (CURRENT_DATE),
    removal_odometer INT,
    final_tread DECIMAL(4,1),
    final_psi DECIMAL(5,1),
    reason ENUM('NORMAL_WEAR','DAMAGE','PUNCTURE','SIDEWALL_FAILURE','UNEVEN_WEAR','ACCIDENT','DEFECT','END_OF_LIFE','OTHER'),
    condition_notes TEXT,
    total_mileage INT,
    total_repairs INT,
    cost DECIMAL(12,2),
    removed_by INT,
    FOREIGN KEY (tyre_id) REFERENCES tyres(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (removed_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- ALERTS
-- ------------------------------------------------------------
CREATE TABLE alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NULL,
    tyre_id INT NULL,
    type VARCHAR(60) NOT NULL,
    priority ENUM('CRITICAL','HIGH','MEDIUM','LOW') DEFAULT 'MEDIUM',
    message VARCHAR(255) NOT NULL,
    action_text VARCHAR(150),
    is_read TINYINT(1) DEFAULT 0,
    is_resolved TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (tyre_id) REFERENCES tyres(id)
);

-- ------------------------------------------------------------
-- AUDIT LOG
-- ------------------------------------------------------------
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100),
    entity VARCHAR(60),
    entity_id INT,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO branches (name, code, location, manager, contact) VALUES
('GDL Nakuru','GDL-NKR','Nakuru','J. Mwangi','0722000001'),
('GDL Eldoret','GDL-ELD','Eldoret','P. Kiptoo','0722000002'),
('GDL Kisii','GDL-KSI','Kisii','M. Ombasa','0722000003'),
('GDL Kisumu','GDL-KSM','Kisumu','A. Otieno','0722000004'),
('GDL Malindi','GDL-MLD','Malindi','S. Juma','0722000005'),
('GDL Migori','GDL-MGR','Migori','R. Nyambok','0722000006'),
('GDL Mombasa','GDL-MBA','Mombasa','T. Hassan','0722000007'),
('GDL Nyeri','GDL-NYR','Nyeri','C. Wanjiru','0722000008'),
('GDL Sigona','GDL-SGN','Sigona','B. Karanja','0722000009'),
('GDL Ukunda','GDL-UKD','Ukunda','F. Mwakio','0722000010'),
('GDL Voi','GDL-VOI','Voi','D. Mwangangi','0722000011'),
('GSL Supermarket','GSL-SPM','Nairobi','L. Chebet','0722000012'),
('GDL Unassigned','GDL-UNA','—','—','—');

-- Default admin user, password = "admin123" (bcrypt hash generated at setup via password_hash)
INSERT INTO users (full_name, username, email, password_hash, role_id, branch_id) VALUES
('System Administrator','admin','admin@treadline.local','$2y$10$92IXUNpkjO0rOQ5byMi.YePlLGiHo6JbBZFTQ0RzOOKKQBb2FSUJa',1,NULL);
-- NOTE: hash above is a placeholder pattern; setup.php will RESET this to a real bcrypt hash of admin123 on install.

INSERT INTO suppliers (company, contact_person, phone, email, products) VALUES
('Sailun East Africa','Grace Nduta','0733111222','sales@sailun.co.ke','Sailun R15/R16 tyres'),
('Bridgestone Kenya','Kevin Otieno','0733111333','info@bridgestone.co.ke','Bridgestone truck & light tyres'),
('Retread Solutions Ltd','Ali Mwakio','0733111444','ali@retreadsolutions.co.ke','Retreading services');

-- Vehicle: KBL353M (Isuzu D-Max, 4x2) - GDL Nakuru, matching reference screenshot
INSERT INTO vehicles (plate_number, fleet_number, vehicle_type_id, make, model, year, vin, branch_id, department, driver_name, current_mileage, mileage_reading_date, status) VALUES
('KBL353M','FN-104',1,'Isuzu','D-Max',2022,'JAAXXXX00000001',1,'Distribution','John Kamau',448858,'2026-09-03','ACTIVE'),
('KCP221Y','FN-105',9,'Isuzu','FRR (6x4)',2021,'JAAXXXX00000002',1,'Distribution','Peter Otieno',312400,'2026-08-20','ACTIVE'),
('KDA771B','FN-201',1,'Toyota','Hilux 4x4',2023,'JAAXXXX00000003',2,'Sales','Mary Wanjiku',88250,'2026-08-30','ACTIVE'),
('KCE009F','FN-330',4,'Isuzu','Bus NPR',2019,'JAAXXXX00000004',7,'Transport','Ali Salim',560120,'2026-08-15','MAINTENANCE');

INSERT INTO mileage_log (vehicle_id, mileage, reading_date, recorded_by) VALUES
(1,448858,'2026-09-03',1);

-- Tyres currently fitted on KBL353M (matches reference screenshot readings)
INSERT INTO tyres (serial_number, branding_code, brand, model, size, type, new_tread_mm, recommended_psi, purchase_date, supplier_id, purchase_price, expected_mileage, status, current_vehicle_id, current_position, current_tread_mm, current_psi, install_odometer) VALUES
('SLN-0001-FL','BC-1001','Sailun','R15','R15','NEW',8.0,32,'2025-01-10',1,7500,60000,'IN_SERVICE',1,'FL',2.5,30,420000),
('SLN-0002-FR','BC-1002','Sailun','R15','R15','NEW',8.0,32,'2025-01-10',1,7500,60000,'IN_SERVICE',1,'FR',4.0,32,420000),
('SLN-0003-RL','BC-1003','Sailun','R15','R15','NEW',8.0,34,'2025-01-10',1,7500,60000,'IN_SERVICE',1,'RL',4.5,34,420000),
('SLN-0004-RR','BC-1004','Sailun','R15','R15','NEW',8.0,34,'2025-01-10',1,7500,60000,'IN_SERVICE',1,'RR',3.5,29,420000);

INSERT INTO tyre_movements (tyre_id, movement_type, to_vehicle_id, to_position, odometer, movement_date, reason, performed_by) VALUES
(1,'INSTALL',1,'FL',420000,'2025-01-12','New fitment',1),
(2,'INSTALL',1,'FR',420000,'2025-01-12','New fitment',1),
(3,'INSTALL',1,'RL',420000,'2025-01-12','New fitment',1),
(4,'INSTALL',1,'RR',420000,'2025-01-12','New fitment',1);

-- A couple of stock tyres (unassigned)
INSERT INTO tyres (serial_number, branding_code, brand, model, size, type, new_tread_mm, recommended_psi, purchase_date, supplier_id, purchase_price, expected_mileage, status, storage_location) VALUES
('SLN-0010-STK','BC-2001','Sailun','R15','R15','NEW',8.0,32,'2026-06-01',1,7800,60000,'STOCK','Nakuru Store A'),
('BST-0011-STK','BC-2002','Bridgestone','R16','R16','NEW',9.0,36,'2026-06-15',2,11500,70000,'STOCK','Nakuru Store A');

-- Extra tyres for other vehicles (to populate dashboard numbers)
INSERT INTO tyres (serial_number, brand, size, new_tread_mm, recommended_psi, purchase_date, supplier_id, purchase_price, expected_mileage, status, current_vehicle_id, current_position, current_tread_mm, current_psi, install_odometer) VALUES
('KCP-T01','Bridgestone','R16',9.0,80,'2025-03-01',2,15000,80000,'IN_SERVICE',2,'FL',6.0,78,290000),
('KCP-T02','Bridgestone','R16',9.0,80,'2025-03-01',2,15000,80000,'IN_SERVICE',2,'FR',5.8,80,290000),
('KDA-T01','Bridgestone','265/65R17',8.5,33,'2025-05-01',2,12500,60000,'IN_SERVICE',3,'FL',7.2,33,60000),
('KDA-T02','Bridgestone','265/65R17',8.5,33,'2025-05-01',2,12500,60000,'IN_SERVICE',3,'FR',7.0,32,60000);

COMMIT;
