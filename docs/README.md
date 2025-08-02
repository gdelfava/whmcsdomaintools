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

3. Set up environment variables for sensitive configuration:
   ```bash
   # Option 1: Use the interactive setup script
   php setup-env.php
   
   # Option 2: Manual setup
   cp env.example .env
   # Edit .env with your actual Firebase credentials
   nano .env
   ```

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

### Environment Variables

For security, sensitive configuration is stored in environment variables. Create a `.env` file in the project root with the following variables:

```bash
# Firebase Configuration
FIREBASE_API_KEY=your_firebase_api_key_here
FIREBASE_AUTH_DOMAIN=your_project.firebaseapp.com
FIREBASE_PROJECT_ID=your_project_id
FIREBASE_STORAGE_BUCKET=your_project.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=your_messaging_sender_id
FIREBASE_APP_ID=your_app_id_here

# Optional: Encryption key for additional security
ENCRYPTION_KEY=your_custom_encryption_key_here
```

**Important**: The `.env` file is automatically ignored by Git to prevent exposing sensitive data.

### Other Configuration

Edit `config.php` to configure:
- Cache settings
- Logging preferences
- Other non-sensitive settings

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