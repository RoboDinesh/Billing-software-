-- Multi-Tenant Migration Script
-- Adds company_id to all tables and sets up isolation

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- 1. Update Settings Table
ALTER TABLE `settings` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;
ALTER TABLE `settings` ADD UNIQUE KEY `company_id` (`company_id`);

-- 2. Update Users Table
ALTER TABLE `users` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;

-- 3. Update Customers Table
ALTER TABLE `customers` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;

-- 4. Update Products Table
ALTER TABLE `products` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;

-- 5. Update Invoices Table
ALTER TABLE `invoices` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;

-- 6. Update Quotations Table
ALTER TABLE `quotations` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;

-- 7. Update Challans Table
ALTER TABLE `challans` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;

-- 8. Update Labors Table
ALTER TABLE `labors` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;

-- 9. Update Labor Ledger Table
ALTER TABLE `labor_ledger` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;

-- 10. Update Materials Table
ALTER TABLE `materials` ADD COLUMN `company_id` varchar(50) DEFAULT 'C001' AFTER `id`;

-- 11. Create 4 Initial Companies
-- Note: 'C001' already has the existing data.
INSERT IGNORE INTO `users` (`username`, `password`, `name`, `role`, `company_id`) VALUES 
('comp1_admin', 'pass123', 'Company 1 Admin', 'admin', 'C001'),
('comp2_admin', 'pass123', 'Company 2 Admin', 'admin', 'C002'),
('comp3_admin', 'pass123', 'Company 3 Admin', 'admin', 'C003'),
('comp4_admin', 'pass123', 'Company 4 Admin', 'admin', 'C004');

-- Ensure each company has its own settings row
INSERT IGNORE INTO `settings` (`company_id`, `company_name`) VALUES 
('C002', 'Company Two'),
('C003', 'Company Three'),
('C004', 'Company Four');

COMMIT;
