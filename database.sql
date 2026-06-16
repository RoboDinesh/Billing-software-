-- Billing System - Database Schema (Multi-Tenant)
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- Table: settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` varchar(50) NOT NULL,
  `company_name` varchar(255) DEFAULT 'Your Company Name',
  `gstin` varchar(50) DEFAULT '',
  `address` text,
  `city` varchar(100) DEFAULT 'Shivamogga',
  `state_name` varchar(100) DEFAULT 'Karnataka',
  `state_code` varchar(10) DEFAULT '29',
  `phone` varchar(50) DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `bank_name` varchar(255) DEFAULT '',
  `bank_account` varchar(100) DEFAULT '',
  `bank_ifsc` varchar(50) DEFAULT '',
  `bank_branch` varchar(100) DEFAULT '',
  `terms_conditions` text,
  `logo_url` text,
  `signature_url` text,
  `bank_reptid` varchar(100) DEFAULT '',
  `show_logo` tinyint(1) DEFAULT 1,
  `show_bank` tinyint(1) DEFAULT 1,
  `show_signature` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'admin',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` varchar(50) NOT NULL,
  `company_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `gstin` varchar(50) DEFAULT '',
  `address` text,
  `phone` varchar(20) DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `state_code` varchar(10) DEFAULT '29',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: products
CREATE TABLE IF NOT EXISTS `products` (
  `id` varchar(50) NOT NULL,
  `company_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `hsn` varchar(50) DEFAULT '',
  `unit` varchar(20) DEFAULT 'Nos',
  `price` decimal(10,2) DEFAULT '0.00',
  `gst_rate` decimal(5,2) DEFAULT '18.00',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: invoices
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` varchar(50) NOT NULL,
  `company_id` varchar(50) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `customer_id` varchar(50) NOT NULL,
  `customer_json` text,
  `items_json` text,
  `subtotal` decimal(12,2) DEFAULT '0.00',
  `cgst` decimal(12,2) DEFAULT '0.00',
  `sgst` decimal(12,2) DEFAULT '0.00',
  `igst` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) DEFAULT '0.00',
  `notes` text,
  `payment_status` varchar(50) DEFAULT 'pending',
  `paid_amount` decimal(12,2) DEFAULT '0.00',
  `terms_conditions` text,
  `gst_type` varchar(20) DEFAULT 'intra',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: quotations
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` varchar(50) NOT NULL,
  `company_id` varchar(50) NOT NULL,
  `quotation_no` varchar(50) NOT NULL,
  `quotation_date` date NOT NULL,
  `customer_id` varchar(50) NOT NULL,
  `customer_json` text,
  `items_json` text,
  `total` decimal(12,2) DEFAULT '0.00',
  `subtotal` decimal(12,2) DEFAULT '0.00',
  `cgst` decimal(12,2) DEFAULT '0.00',
  `sgst` decimal(12,2) DEFAULT '0.00',
  `igst` decimal(12,2) DEFAULT '0.00',
  `valid_until` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `gst_type` varchar(20) DEFAULT 'intra',
  `terms_conditions` text,
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_no` (`quotation_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: challans
CREATE TABLE IF NOT EXISTS `challans` (
  `id` varchar(50) NOT NULL,
  `company_id` varchar(50) NOT NULL,
  `challan_no` varchar(50) NOT NULL,
  `challan_date` date NOT NULL,
  `customer_id` varchar(50) NOT NULL,
  `customer_json` text,
  `items_json` text,
  `vehicle_no` varchar(50) DEFAULT '',
  `status` varchar(50) DEFAULT 'active',
  `terms_conditions` text,
  `notes` text,
  `reference_no` varchar(100) DEFAULT '',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `challan_no` (`challan_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: labors
CREATE TABLE IF NOT EXISTS `labors` (
  `id` varchar(50) NOT NULL,
  `company_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT '',
  `address` text,
  `joined_date` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: labor_ledger
CREATE TABLE IF NOT EXISTS `labor_ledger` (
  `id` varchar(50) NOT NULL,
  `company_id` varchar(50) NOT NULL,
  `labor_id` varchar(50) NOT NULL,
  `entry_date` date NOT NULL,
  `type` enum('payment','work') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text,
  `reference_no` varchar(100) DEFAULT '',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: materials
CREATE TABLE IF NOT EXISTS `materials` (
  `id` varchar(50) NOT NULL,
  `company_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `rate` decimal(15,2) NOT NULL,
  `gst_rate` decimal(5,2) DEFAULT 0,
  `cgst` decimal(15,2) DEFAULT 0,
  `sgst` decimal(15,2) DEFAULT 0,
  `total_amount` decimal(15,2) NOT NULL,
  `date` date NOT NULL,
  `supplier` varchar(255) DEFAULT '',
  `bill_url` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

