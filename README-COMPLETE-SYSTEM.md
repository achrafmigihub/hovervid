# HoverVid Complete System Documentation

## Overview

The HoverVid system is a comprehensive WordPress plugin with domain protection that consists of two main components:

1. **HoverVid WordPress Plugin** (`hovervid-plugin/`) - A domain-protected WordPress plugin
2. **HoverVid Backend API** (`hovervid-api/`) - A standalone PHP API for domain validation

## System Architecture

```
WordPress Site → HoverVid Plugin → HoverVid API → PostgreSQL Database
```

### Key Features

- **Domain Protection**: Plugin only works on authorized domains
- **Real-time Validation**: Checks domain authorization during activation
- **Auto-deactivation**: Automatically deactivates if domain is removed from database
- **Flexible Configuration**: Supports multiple environments (local, staging, production)
- **Direct Database Mode**: Can connect directly to database for local development
- **Comprehensive Logging**: Debug logging for troubleshooting
- **Rate Limiting**: Built-in API rate limiting
- **Admin Interface**: WordPress admin panel for monitoring

## Quick Start

### 1. Database Setup

```bash
# Navigate to the API directory
cd hovervid-api

# Run the setup script to initialize the database
php setup.php
```

This creates the required tables and sample data:
- `users` - System users
- `domains` - Authorized domains
- `api_requests` - API request logs
- `plugin_logs` - Plugin activity logs

### 2. Start the API Server

```bash
# For local development
cd hovervid-api
php -S localhost:8000 app.php
```

### 3. Install the WordPress Plugin

1. Copy the `hovervid-plugin` folder to your WordPress `wp-content/plugins/` directory
2. Activate the plugin through WordPress admin
3. The plugin will check if your domain is authorized

## Configuration

### Plugin Configuration (`hovervid-plugin/config.php`)

```php
'backend_api' => [
    'production' => 'https://api.hovervid.com',
    'staging' => 'https://staging-api.hovervid.com', 
    'local' => 'http://localhost:8000',
    'development' => 'http://127.0.0.1:8000',
],

'settings' => [
    'environment' => 'auto', // or 'local', 'staging', 'production'
    'use_direct_database' => true, // For local development
    'enable_debug_logging' => true,
    'auto_deactivate_on_unauthorized' => true,
]
```

### API Configuration (`hovervid-api/config.php`)

```php
'database' => [
    'host' => '127.0.0.1',
    'port' => '5432',
    'dbname' => 'hovervid_db',
    'username' => 'postgres',
    'password' => 'postgres_hovervid',
],
```

## Plugin Behavior

### During Activation

1. Plugin detects the current domain
2. Makes API call to validate domain authorization
3. If authorized: Plugin activates and sets status to 'active'
4. If not authorized: Plugin fails to activate with error message

### During Operation

1. Checks domain authorization every hour (configurable)
2. If domain is removed from database: Auto-deactivates plugin
3. Shows admin notices for any authorization issues
4. Logs all activity for debugging

### Admin Interface

Access the plugin settings at: **WordPress Admin → Settings → HoverVid**

Features:
- View current domain and authorization status
- Manual domain re-checking
- Plugin information and diagnostics
- Troubleshooting guide

## API Endpoints

### POST `/validate-domain`
Validates if a domain is authorized to use the plugin.

```json
{
    "domain": "example.com",
    "action": "validate"
}
```

Response:
```json
{
    "success": true,
    "authorized": true,
    "domain_exists": true,
    "is_active": true,
    "plugin_status": "active",
    "message": "Domain is authorized"
}
```

### POST `/update-status`
Updates the plugin status for a domain.

```json
{
    "domain": "example.com",
    "status": "active",
    "action": "update_status"
}
```

### POST `/get-status`
Gets the current plugin status for a domain.

```json
{
    "domain": "example.com",
    "action": "get_status"
}
```

### GET `/health`
API health check endpoint.

## Database Schema

### `domains` Table
```sql
- id (bigint, primary key)
- user_id (bigint, foreign key)
- domain (text, unique)
- platform (text, e.g., 'wordpress')
- plugin_status (text, 'active'|'inactive'|'error'|'suspended')
- status (varchar, 'active'|'inactive')
- is_active (boolean)
- is_verified (boolean)
- created_at (timestamp)
- updated_at (timestamp)
```

### Sample Domains (Created by Setup)
- `localhost`
- `test123.com`
- `example.com`
- `demo.local`
- `mysite.test`
- `test-website.com`

## Environment Configuration

### Local Development
```php
'environment' => 'local',
'use_direct_database' => true,
'enable_debug_logging' => true,
'auto_deactivate_on_unauthorized' => false,
```

### Production
```php
'environment' => 'production',
'use_direct_database' => false,
'enable_debug_logging' => false,
'auto_deactivate_on_unauthorized' => true,
```

## Security Features

### Domain Protection
- Plugin validates domain on every activation
- Regular background checks (hourly by default)
- Auto-deactivation when domain is unauthorized
- Domain cleaning and normalization

### Rate Limiting
- Maximum 100 API calls per hour per IP (configurable)
- Prevents API abuse and DoS attacks

### Data Validation
- All API inputs are sanitized and validated
- SQL injection prevention with prepared statements
- XSS protection in WordPress admin interface

## Troubleshooting

### Common Issues

1. **Plugin won't activate**
   - Check if domain exists in `domains` table
   - Verify API server is running
   - Check network connectivity

2. **Plugin deactivated automatically**
   - Domain was removed from database
   - API server is down
   - Network connection issues

3. **API connection errors**
   - Verify API server is running on correct port
   - Check firewall settings
   - Review error logs

### Debug Logging

Enable debug logging in config:
```php
'enable_debug_logging' => true
```

Logs are written to:
- WordPress: WordPress error log
- API: `hovervid-api/logs/` directory

### Manual Domain Check

Use WordPress admin panel:
1. Go to **Settings → HoverVid**
2. Click **"Check Domain Status"**
3. Review authorization status

## Adding New Domains

### Via Database
```sql
INSERT INTO domains (user_id, domain, platform, plugin_status, status, is_active, is_verified) 
VALUES (1, 'newdomain.com', 'wordpress', 'inactive', 'active', true, true);
```

### Via API (Future Enhancement)
A domain management API endpoint can be added for programmatic domain management.

## File Structure

```
hovervid-plugin/
├── config.php                 # Main configuration
├── sign-language-video.php    # Main plugin file
├── includes/
│   ├── class-domain-validator.php  # Domain validation logic
│   ├── class-api-client.php        # API communication
│   ├── class-admin.php             # WordPress admin interface
│   └── class-public.php            # Frontend functionality
└── backend-api.php            # Standalone API (legacy)

hovervid-api/
├── app.php                    # API application entry point
├── config.php                 # API configuration
├── setup.php                  # Database setup script
└── logs/                      # API logs directory
```

## Development Workflow

### Setting Up Development Environment

1. **Database**: Install PostgreSQL and create `hovervid_db`
2. **API Setup**: Run `php setup.php` to initialize database
3. **API Server**: Start with `php -S localhost:8000 app.php`
4. **Plugin**: Install in WordPress and configure for local environment

### Testing

1. Add test domains to database
2. Test plugin activation on authorized domains
3. Test plugin rejection on unauthorized domains
4. Test auto-deactivation by removing domain from database

### Deployment

1. **Production Database**: Set up PostgreSQL with production credentials
2. **API Server**: Deploy API to production server with SSL
3. **Plugin**: Update config for production environment
4. **Domain Management**: Add production domains to database

## Support and Maintenance

### Monitoring
- Check API logs for errors
- Monitor plugin activation failures
- Review database for unauthorized access attempts

### Updates
- Plugin updates maintain domain protection
- API updates preserve backward compatibility
- Database schema migrations are handled carefully

### Backup
- Regular database backups recommended
- Plugin configuration should be version controlled
- API logs can be rotated and archived

## Security Considerations

1. **HTTPS**: Use HTTPS for all API communications in production
2. **Database Security**: Secure PostgreSQL with proper authentication
3. **API Keys**: Consider implementing API key authentication for production
4. **Rate Limiting**: Monitor and adjust rate limits based on usage
5. **Input Validation**: All inputs are validated and sanitized
6. **Error Handling**: Detailed errors only shown in debug mode

## License

This system is proprietary software. Unauthorized use, distribution, or modification is prohibited.

## Support

For technical support or questions about this system, please contact the development team with:
- Domain name
- Plugin version
- WordPress version
- Error logs (if applicable)
- Steps to reproduce the issue 
