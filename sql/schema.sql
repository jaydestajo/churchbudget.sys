-- =========================================================
-- Budgeting and Expense Management System - Database Schema
-- =========================================================
CREATE DATABASE IF NOT EXISTS budget_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE budget_system;

-- ---------------------------------------------------------
-- Roles
-- ---------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    level INT DEFAULT 0 -- used for approver ordering (1-4), 0 for non-approver roles
) ENGINE=InnoDB;

INSERT INTO roles (name, level) VALUES
('super_admin', 0),
('treasurer', 0),
('approver_1', 1),
('approver_2', 2),
('approver_3', 3),
('approver_4', 4);

-- ---------------------------------------------------------
-- Users
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- Default admin (password: admin123) and treasurer (password: treasurer123)
-- Hashes generated with PHP password_hash() using bcrypt
INSERT INTO users (name, username, email, password_hash, role_id, status) VALUES
('System Administrator', 'admin', 'admin@church.org', '$2y$10$wH1kQ0m2N3z8Y4jGZbR9OeVh0Z6q0m1s2v0T3fR4d5G6h7J8k9L0O', 1, 'Active'),
('Church Treasurer', 'treasurer', 'treasurer@church.org', '$2y$10$wH1kQ0m2N3z8Y4jGZbR9OeVh0Z6q0m1s2v0T3fR4d5G6h7J8k9L0O', 2, 'Active');
-- NOTE: These are PLACEHOLDER hashes and will NOT work as-is.
-- Immediately after importing this schema, run: php tools/setup_passwords.php
-- (this sets working passwords: admin/admin123 and treasurer/treasurer123)

-- ---------------------------------------------------------
-- Budget Allocation Settings
-- ---------------------------------------------------------
CREATE TABLE budget_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO budget_allocations (item_name, percentage) VALUES
('Ministry Expenses', 30.00),
('Administration', 20.00),
('Utilities', 15.00),
('Missions', 10.00),
('Building Maintenance', 15.00),
('Savings', 10.00);

-- ---------------------------------------------------------
-- Approvers (levels 1-4)
-- ---------------------------------------------------------
CREATE TABLE approvers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level INT NOT NULL UNIQUE, -- 1,2,3,4
    name VARCHAR(150) NOT NULL,
    position VARCHAR(150),
    email VARCHAR(150),
    contact_number VARCHAR(50),
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO approvers (level, name, position, email, contact_number, status) VALUES
(1, 'Approver One', 'Finance Committee Member', 'approver1@church.org', '', 'Active'),
(2, 'Approver Two', 'Finance Committee Member', 'approver2@church.org', '', 'Active'),
(3, 'Approver Three', 'Board Member', 'approver3@church.org', '', 'Active'),
(4, 'Approver Four', 'Senior Pastor', 'approver4@church.org', '', 'Active');

-- ---------------------------------------------------------
-- Treasurer profile (contact info, separate from user login)
-- ---------------------------------------------------------
CREATE TABLE treasurer_profile (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    position VARCHAR(150),
    contact_number VARCHAR(50),
    email VARCHAR(150)
) ENGINE=InnoDB;

INSERT INTO treasurer_profile (name, position, contact_number, email) VALUES
('Church Treasurer', 'Treasurer', '', 'treasurer@church.org');

-- ---------------------------------------------------------
-- Denominations (bill master + running quantities)
-- ---------------------------------------------------------
CREATE TABLE denomination_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_value DECIMAL(10,2) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO denomination_master (bill_value) VALUES (1000),(500),(200),(100),(50),(20),(10),(5),(1);

CREATE TABLE denomination_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    bill_value DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    total DECIMAL(12,2) GENERATED ALWAYS AS (bill_value * quantity) STORED,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Income
-- ---------------------------------------------------------
CREATE TABLE income (
    id INT AUTO_INCREMENT PRIMARY KEY,
    income_date DATE NOT NULL,
    source VARCHAR(100) NOT NULL, -- Tithes, Offering, Donation, Fund Raising, Rental, Other Income
    amount DECIMAL(12,2) NOT NULL,
    reference_number VARCHAR(100),
    remarks TEXT,
    entered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entered_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Expenses (with 4-level approval workflow)
-- ---------------------------------------------------------
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(12,2) NOT NULL,
    receipt_no VARCHAR(100),
    payment_method VARCHAR(50), -- Cash, Bank Transfer, Check, GCash, Others
    fund_source VARCHAR(100),   -- links loosely to budget_allocations.item_name
    entered_by INT,
    remarks TEXT,
    status ENUM('Pending','Approved by L1','Approved by L2','Approved by L3','Approved','Rejected') DEFAULT 'Pending',
    approver1_id INT DEFAULT NULL, approver1_action ENUM('Approved','Rejected') DEFAULT NULL, approver1_date DATETIME DEFAULT NULL,
    approver2_id INT DEFAULT NULL, approver2_action ENUM('Approved','Rejected') DEFAULT NULL, approver2_date DATETIME DEFAULT NULL,
    approver3_id INT DEFAULT NULL, approver3_action ENUM('Approved','Rejected') DEFAULT NULL, approver3_date DATETIME DEFAULT NULL,
    approver4_id INT DEFAULT NULL, approver4_action ENUM('Approved','Rejected') DEFAULT NULL, approver4_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entered_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Assets
-- ---------------------------------------------------------
CREATE TABLE assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL, -- Land, Building, Vehicle, Furniture, Equipment, Computers, Appliances, Others
    description TEXT,
    purchase_date DATE,
    cost DECIMAL(12,2) DEFAULT 0,
    current_value DECIMAL(12,2) DEFAULT 0,
    location VARCHAR(150),
    condition_status VARCHAR(50), -- New, Good, Fair, Poor, Disposed
    serial_number VARCHAR(100),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Manual ledger entries for Balance Sheet (bank balance, loans, payables)
-- ---------------------------------------------------------
CREATE TABLE balance_sheet_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('Bank','Loan','Payable') NOT NULL,
    label VARCHAR(150) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    as_of_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Audit Logs
-- ---------------------------------------------------------
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
