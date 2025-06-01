# HoverVid

A comprehensive platform that provides sign language video translations for website content. The system consists of a Laravel-based admin panel for managing domains and subscriptions, along with a WordPress plugin that enables real-time text translation features.

## Project Overview

HoverVid makes website content accessible through sign language translation technology. Website owners can install our WordPress plugin, which automatically scans their content and provides video translations for visitors who need sign language interpretation.

The platform includes two main components:
- A Laravel backend application for domain management and user administration
- A WordPress plugin that handles the frontend text scanning and video playback

## Architecture

### Laravel Backend Application
The main application handles user management, domain authorization, subscription management, and provides APIs for the WordPress plugin. Built with Laravel and Vue.js, it includes:

- Domain management system with real-time verification
- User authentication and subscription handling
- API endpoints for plugin communication
- Admin dashboard for monitoring and control
- PostgreSQL database integration

### WordPress Plugin
Located in the `hovervid-plugin` directory, this plugin installs on client websites to provide:

- Automatic text content scanning
- Translation icon injection on hover
- Floating video player for sign language content
- Real-time domain verification
- Graceful error handling for offline scenarios

## Technical Implementation

The system uses a centralized verification approach where the WordPress plugin continuously checks with the database to ensure the domain is authorized. This happens through:

- Domain verification every 30 seconds
- Immediate checks on user interactions
- Graceful degradation when database is unavailable
- Complete functionality blocking for unauthorized domains

### Database Schema

The core functionality relies on a `domains` table with these key fields:
- `domain`: The website domain
- `is_verified`: Main control column (true/false)
- `user_id`: Associated user account
- `subscription_expires_at`: Subscription management

When `is_verified` is true, the plugin works normally. When false, all functionality is disabled with appropriate user messaging.

## Installation

### Backend Setup
1. Clone the repository
2. Install PHP dependencies: `composer install`
3. Install Node dependencies: `npm install`
4. Configure your `.env` file with database credentials
5. Run migrations: `php artisan migrate`
6. Build frontend assets: `npm run build`

### WordPress Plugin
1. Copy the `hovervid-plugin` directory to your WordPress `wp-content/plugins/` folder
2. Update database connection settings in `hovervid-plugin/includes/class-database.php`
3. Activate the plugin through WordPress admin
4. The plugin will automatically check domain authorization

## Configuration

### Database Connection
Update the database credentials in the plugin's database class:

```php
private $host = 'your-database-host';
private $database = 'your-database-name'; 
private $username = 'your-username';
private $password = 'your-password';
```

### Domain Management
Control plugin functionality through the database:

```sql
-- Enable plugin for a domain
UPDATE domains SET is_verified = true WHERE domain = 'example.com';

-- Disable plugin for a domain
UPDATE domains SET is_verified = false WHERE domain = 'example.com';
```

## Usage

### For Website Owners
After installing the WordPress plugin on your site, contact the platform administrator to authorize your domain. Once authorized, visitors will see translation icons when hovering over text content.

### For Administrators
Use the Laravel admin panel to:
- Manage domain authorizations
- Monitor plugin usage across domains
- Handle user subscriptions and payments
- View usage analytics and reports

## Development

### Laravel Application
The backend follows standard Laravel conventions:
- Controllers in `app/Http/Controllers`
- Models in `app/Models`
- Vue.js frontend in `resources/js`
- API routes in `routes/api.php`

### WordPress Plugin
The plugin uses a clean object-oriented structure:
- Main plugin file: `sign-language-video.php`
- Core classes in `includes/` directory
- Frontend assets in `public/` directory
- Centralized domain verification system

### Key Classes
- `SLVP_Domain_Verifier`: Single source of truth for domain verification
- `SLVP_Video_Player`: Main plugin controller and asset management
- `SLVP_Text_Processor`: Text scanning and icon injection
- `SLVP_Api_Handler`: AJAX endpoints for frontend communication

## Error Handling

The system includes comprehensive error handling:

**Database Unavailable**: Plugin enters degraded mode with admin notices but no fatal errors

**Domain Not Authorized**: Clear error messages with instructions for domain authorization

**Network Issues**: Graceful fallbacks with automatic recovery when connectivity returns

## Security

- Domain authorization required for all plugin functionality
- Nonce verification for AJAX requests
- SQL injection protection with prepared statements
- Input sanitization and validation throughout

## Browser Support

The plugin works with modern browsers including Chrome, Firefox, Safari, and Edge. Mobile devices are supported with touch-friendly interactions.

## File Structure

```
├── app/                          # Laravel application
├── hovervid-plugin/              # WordPress plugin
│   ├── sign-language-video.php  # Main plugin file
│   ├── includes/                 # Core classes
│   ├── public/                   # Frontend assets
│   └── assets/                   # Icons and media
├── resources/                    # Laravel frontend
├── database/                     # Migrations and seeds
├── routes/                       # API and web routes
└── config/                       # Laravel configuration
```

## Performance

The system is optimized for performance with:
- Lazy loading of translation icons
- Batched processing for large pages
- Efficient DOM scanning with performance limits
- Cached verification with periodic refresh
- Throttled mutation observers for dynamic content

## Support

For technical support or domain authorization requests, provide:
- Your domain name
- WordPress and plugin versions
- Detailed description of any issues encountered

The platform includes comprehensive logging to help diagnose issues quickly and efficiently.
