# Domain Tools Management Suite

A comprehensive domain management application for WHMCS integration with multi-user and multi-company support.

## 📁 Project Structure

### Core Application Files
- `index.php` - Main entry point
- `main_page.php` - Main application interface
- `auth_v2.php` - Authentication system
- `config.php` - Application configuration
- `database_v2.php` - Database operations
- `api.php` - WHMCS API integration
- `user_settings_db.php` - User settings management
- `cache.php` - Caching system

### Authentication & User Management
- `login.php` - User login page
- `logout.php` - Logout functionality
- `registration.php` - User registration
- `user_management.php` - User management interface
- `company_profile.php` - Company profile management

### Domain Management
- `domains_db.php` - Domain database operations
- `domain_sync.php` - Domain synchronization
- `sync_interface.php` - Sync interface
- `add_domain.php` - Add domain functionality
- `update_domain.php` - Update domain functionality
- `delete_domain.php` - Delete domain functionality
- `update_nameservers.php` - Nameserver updates

### Settings & Configuration
- `settings.php` - Application settings interface
- `user_settings.php` - User settings interface
- `create_tables.php` - Database table creation
- `setup_database.php` - Database setup
- `setup-env.php` - Environment setup

### Export & Utilities
- `export_domains.php` - Domain export functionality
- `export_progress.php` - Export progress tracking
- `list_csv_files.php` - CSV file listing
- `clear-cache.php` - Cache clearing
- `performance.php` - Performance monitoring

### AJAX Handlers
- `ajax-clear-cache.php` - Cache clearing AJAX
- `ajax-servers.php` - Server AJAX operations

### Directories
- `cache/` - Application cache files
- `css/` - Stylesheets
- `js/` - JavaScript files
- `logs/` - Application logs
- `user_settings/` - User settings files

## 📚 Documentation (`docs/`)
- All `.md` documentation files
- SQL schema files
- Migration guides
- Troubleshooting documentation

## 🧪 Tests (`tests/`)
- All test files (`test_*.php`)
- Debug files (`debug_*.php`)
- Development and debugging utilities

## 📊 Exports (`exports/`)
- CSV export files
- Batch processing results
- Export data files

## 🔄 Migrations (`migrations/`)
- Database migration scripts
- Schema update files
- Multi-user migration files

## 🚀 Quick Start

1. **Upload files** to your web server
2. **Configure environment** - Copy `.env.example` to `.env` and update settings
3. **Set up database** - Run `setup_database.php` to create tables
4. **Configure settings** - Access the application and configure WHMCS API settings
5. **Start managing domains** - Use the dashboard to manage your domains

## 🔧 Configuration

### Environment Variables
- Copy `.env.example` to `.env`
- Update database connection details
- Configure WHMCS API credentials

### Database Setup
- Ensure MySQL/MariaDB is running
- Create a database for the application
- Run `create_tables.php` to set up the schema

### WHMCS API Setup
- Create API credentials in WHMCS admin panel
- Configure API URL in application settings
- Test API connection in debug tools

## 🛠️ Development

### File Organization
- Core application files remain in root directory
- Documentation moved to `docs/`
- Test files moved to `tests/`
- Export files moved to `exports/`
- Migration files moved to `migrations/`

### Adding New Features
- Follow existing code patterns
- Update documentation in `docs/`
- Add tests to `tests/` directory
- Maintain backward compatibility

## 🔍 Debug Tools

Access debug tools through the application interface (admin only):
- System diagnostics
- Database connection testing
- API connectivity checks
- Settings validation

## 📞 Support

For support and questions, contact: guilio@kaldera.co.za

## 📝 License

This application is proprietary software. All rights reserved. 