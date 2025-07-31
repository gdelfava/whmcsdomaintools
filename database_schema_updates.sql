-- Multi-Company & User Role System Database Schema Updates
-- This file contains all the necessary database changes for the new system

-- 1. Create companies table
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    company_address TEXT,
    contact_number VARCHAR(50),
    contact_email VARCHAR(255),
    logo_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_name (company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('normal_user', 'server_admin') DEFAULT 'normal_user',
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    UNIQUE KEY unique_email (email),
    INDEX idx_company_id (company_id),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Update existing tables to include company_id
-- Add company_id to domains table
ALTER TABLE domains ADD COLUMN company_id INT NOT NULL AFTER user_email;
ALTER TABLE domains ADD INDEX idx_company_id (company_id);
ALTER TABLE domains ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

-- Add company_id to domain_nameservers table
ALTER TABLE domain_nameservers ADD COLUMN company_id INT NOT NULL AFTER user_email;
ALTER TABLE domain_nameservers ADD INDEX idx_company_id (company_id);
ALTER TABLE domain_nameservers ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

-- Add company_id to user_settings table
ALTER TABLE user_settings ADD COLUMN company_id INT NOT NULL AFTER user_email;
ALTER TABLE user_settings ADD INDEX idx_company_id (company_id);
ALTER TABLE user_settings ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

-- Add company_id to sync_logs table
ALTER TABLE sync_logs ADD COLUMN company_id INT NOT NULL AFTER user_email;
ALTER TABLE sync_logs ADD INDEX idx_company_id (company_id);
ALTER TABLE sync_logs ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

-- 4. Update unique constraints to include company_id
-- Update domains unique constraint
ALTER TABLE domains DROP INDEX unique_user_domain;
ALTER TABLE domains ADD UNIQUE KEY unique_company_user_domain (company_id, user_email, domain_id);

-- Update domain_nameservers unique constraint
ALTER TABLE domain_nameservers DROP INDEX unique_user_domain;
ALTER TABLE domain_nameservers ADD UNIQUE KEY unique_company_user_domain (company_id, user_email, domain_id);

-- Update user_settings unique constraint
ALTER TABLE user_settings DROP INDEX unique_user_email;
ALTER TABLE user_settings ADD UNIQUE KEY unique_company_user_email (company_id, user_email);

-- 5. Create permissions table for fine-grained access control
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('normal_user', 'server_admin') NOT NULL,
    permission_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role, permission_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Insert default permissions
INSERT INTO permissions (role, permission_name, description) VALUES
('normal_user', 'view_domains', 'Can view domains'),
('normal_user', 'edit_domains', 'Can edit domains'),
('normal_user', 'add_domains', 'Can add domains'),
('normal_user', 'delete_domains', 'Can delete domains'),
('normal_user', 'sync_domains', 'Can sync domains'),
('normal_user', 'view_settings', 'Can view settings'),
('normal_user', 'edit_settings', 'Can edit settings'),
('server_admin', 'view_domains', 'Can view domains'),
('server_admin', 'edit_domains', 'Can edit domains'),
('server_admin', 'add_domains', 'Can add domains'),
('server_admin', 'delete_domains', 'Can delete domains'),
('server_admin', 'sync_domains', 'Can sync domains'),
('server_admin', 'view_settings', 'Can view settings'),
('server_admin', 'edit_settings', 'Can edit settings'),
('server_admin', 'database_setup', 'Can access database setup'),
('server_admin', 'create_tables', 'Can create database tables'),
('server_admin', 'manage_users', 'Can manage users'),
('server_admin', 'manage_company', 'Can manage company profile'),
('server_admin', 'view_logs', 'Can view system logs');

-- 7. Create user sessions table for better session management
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 