# Domain Updater

A PHP-based web application for managing and updating domain nameservers. This tool provides a user-friendly interface for bulk domain management operations.

## Features

- **User Authentication**: Secure login system with session management
- **Domain Management**: Add, edit, and delete domain entries
- **Nameserver Updates**: Bulk update nameservers for multiple domains
- **Server Management**: Configure and manage multiple DNS servers
- **Export Functionality**: Export domain lists in various formats
- **Performance Monitoring**: Built-in performance tracking and optimization
- **Responsive UI**: Modern, mobile-friendly interface

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/gdelfava/whmcsdomaintools.git
   cd whmcsdomaintools
   ```

2. Configure your web server to point to the project directory

3. Set up the database configuration in `config.php`

4. Ensure PHP 7.4+ is installed with required extensions

## Usage

1. Start the development server:
   ```bash
   php -S localhost:8001
   ```

2. Navigate to `http://localhost:8001` in your browser

3. Log in with your credentials

4. Use the dashboard to manage your domains and nameservers

## File Structure

```
domain-updater/
├── api.php                 # API endpoints
├── auth.php               # Authentication logic
├── config.php             # Configuration settings
├── css/                   # Stylesheets
├── js/                    # JavaScript files
├── cache/                 # Cache directory (auto-generated)
├── logs/                  # Log files (auto-generated)
├── user_settings/         # User preferences (auto-generated)
└── README.md             # This file
```

## Configuration

Edit `config.php` to configure:
- Database settings
- API credentials
- Cache settings
- Logging preferences

## Security

- All user inputs are sanitized
- Session-based authentication
- CSRF protection
- Secure password handling

## Performance

The application includes several performance optimizations:
- Caching system for API responses
- Optimized CSS and JavaScript
- Efficient database queries
- Background processing for bulk operations

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is licensed under the MIT License.

## Support

For support and questions, please open an issue on GitHub. 