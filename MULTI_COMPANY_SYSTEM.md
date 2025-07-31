# Multi-Company & User Role System

## 🎯 Overview

This document describes the implementation of a comprehensive multi-company system with role-based access control (RBAC) for the WHMCS Domain Tools application.

## 🏗️ System Architecture

### **Database Schema**

#### **Core Tables:**
1. **`companies`** - Company profiles and information
2. **`users`** - User accounts with role-based access
3. **`permissions`** - Role-based permissions system
4. **`user_sessions`** - Enhanced session management

#### **Updated Tables (with company_id):**
- `domains` - Now includes company_id for multi-company isolation
- `domain_nameservers` - Company-specific nameserver data
- `user_settings` - Company-specific API settings
- `sync_logs` - Company-specific sync history

### **User Roles**

#### **Normal User**
- **Permissions:**
  - View domains
  - Edit domains
  - Add domains
  - Delete domains
  - Sync domains
  - View settings
  - Edit settings

#### **Server Admin**
- **All Normal User permissions PLUS:**
  - Database setup access
  - Create database tables
  - Manage users
  - Manage company profile
  - View system logs

## 🔐 Authentication & Authorization

### **Login System**
- **File:** `login_v2.php`
- **Features:**
  - Company registration with admin user
  - Role selection during registration
  - Password strength validation
  - Email validation
  - Secure session management

### **Role-Based Access Control**
- **File:** `auth_v2.php`
- **Functions:**
  - `hasPermission($permission)` - Check specific permission
  - `requirePermission($permission)` - Require permission or deny access
  - `isServerAdmin()` - Check if user is server admin
  - `canAccessServerSetup()` - Check server setup access
  - `canManageUsers()` - Check user management access
  - `canManageCompany()` - Check company management access

## 🏢 Company Management

### **Company Profile**
- Company name
- Company address
- Contact number
- Contact email
- Logo URL

### **Company Registration Process**
1. **Company Information:**
   - Company name (required)
   - Company address (optional)
   - Contact number (optional)
   - Contact email (required)

2. **Admin User Creation:**
   - First name (required)
   - Last name (required)
   - Email (required)
   - Password (required, with strength validation)
   - Role selection (Server Admin or Normal User)

## 👥 User Management

### **User Roles**
- **Normal User:** Basic domain management capabilities
- **Server Admin:** Full system access including server setup

### **User Features**
- Secure password hashing
- Session management
- Last login tracking
- Account activation/deactivation
- Company-specific data isolation

## 🛡️ Security Features

### **Data Isolation**
- All data is company-specific
- Users can only access their company's data
- Complete separation between companies

### **Permission System**
- Fine-grained permission control
- Role-based access control
- Permission validation on all operations

### **Session Security**
- Secure session management
- Session expiration
- IP address tracking
- User agent tracking

## 📊 Database Schema Details

### **Companies Table**
```sql
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    company_address TEXT,
    contact_number VARCHAR(50),
    contact_email VARCHAR(255),
    logo_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_name (company_name)
);
```

### **Users Table**
```sql
CREATE TABLE users (
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
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### **Permissions Table**
```sql
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('normal_user', 'server_admin') NOT NULL,
    permission_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role, permission_name)
);
```

## 🚀 Implementation Steps

### **Phase 1: Database Setup**
1. Run `database_schema_updates.sql` to create new tables
2. Update existing tables with company_id
3. Insert default permissions

### **Phase 2: Authentication System**
1. Implement new login system (`login_v2.php`)
2. Create role-based authentication (`auth_v2.php`)
3. Update session management

### **Phase 3: Application Updates**
1. Update all database calls to include company_id
2. Implement permission checks throughout the application
3. Update UI to show/hide features based on permissions

### **Phase 4: UI/UX Enhancements**
1. Create company profile management interface
2. Add user management interface
3. Update sidebar menu with role-based visibility

## 🔧 Migration from Single-User System

### **Database Migration**
1. **Backup existing data**
2. **Create new tables**
3. **Migrate existing data:**
   - Create default company for existing users
   - Assign existing data to default company
   - Update all foreign key relationships

### **Application Migration**
1. **Update authentication system**
2. **Modify all database queries**
3. **Implement permission checks**
4. **Update UI components**

## 📋 Permission Matrix

| Feature | Normal User | Server Admin |
|---------|-------------|--------------|
| View Domains | ✅ | ✅ |
| Edit Domains | ✅ | ✅ |
| Add Domains | ✅ | ✅ |
| Delete Domains | ✅ | ✅ |
| Sync Domains | ✅ | ✅ |
| View Settings | ✅ | ✅ |
| Edit Settings | ✅ | ✅ |
| Database Setup | ❌ | ✅ |
| Create Tables | ❌ | ✅ |
| Manage Users | ❌ | ✅ |
| Manage Company | ❌ | ✅ |
| View Logs | ❌ | ✅ |

## 🎯 Benefits

### **For Companies**
- **Complete Data Isolation:** Each company's data is completely separate
- **Multi-User Support:** Multiple users per company
- **Role-Based Access:** Different permission levels for different users
- **Company Branding:** Custom logos and company information

### **For Administrators**
- **User Management:** Add/remove users within company
- **Permission Control:** Assign different roles to users
- **System Administration:** Full access to server setup and configuration
- **Audit Trail:** Track user activities and system logs

### **For Normal Users**
- **Domain Management:** Full domain management capabilities
- **Secure Access:** Role-appropriate permissions
- **Company Context:** All data within company scope

## 🔄 Next Steps

### **Immediate Actions**
1. **Test the new login system**
2. **Verify database schema creation**
3. **Test role-based access control**
4. **Validate data isolation**

### **Future Enhancements**
1. **User invitation system**
2. **Advanced permission management**
3. **Company-specific branding**
4. **Audit logging system**
5. **API rate limiting per company**

## 🛠️ Technical Notes

### **Security Considerations**
- All passwords are hashed using `password_hash()`
- Sessions are managed securely
- SQL injection protection via prepared statements
- XSS protection via input sanitization

### **Performance Considerations**
- Database indexes on frequently queried columns
- Efficient permission checking
- Optimized queries for multi-company data

### **Scalability**
- Designed to support multiple companies
- Efficient data isolation
- Role-based access control
- Session management for multiple users

This multi-company system provides a robust foundation for enterprise-level domain management with proper security, scalability, and user management capabilities. 