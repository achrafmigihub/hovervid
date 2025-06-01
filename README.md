# HoverVid - Sign Language Video Translation Platform

HoverVid is a comprehensive platform that provides sign language video translations for website content. The system consists of a Laravel-based management application and a WordPress plugin that enables website owners to make their content accessible through sign language translations.

## Project Overview

This platform enables website owners to add sign language video translations to their websites. Visitors can click on translation icons that appear on text content to view corresponding sign language videos. The system includes domain-based licensing, real-time verification, and a complete management interface.

## Project Structure

The repository contains two main components:

### Laravel Application (Root Directory)
The main Laravel application serves as the backend management system and API. It handles domain authorization, user management, subscription tracking, and content management.

**Key directories:**
- `app/` - Laravel application logic, models, controllers
- `database/` - Database migrations, seeders, and schema
- `resources/` - Frontend views, Vue.js components, and assets
- `routes/` - API and web route definitions
- `config/` - Laravel configuration files
- `storage/` - File storage and application logs
- `public/` - Web-accessible files and compiled assets

### WordPress Plugin (`hovervid-plugin/`)
The WordPress plugin that gets installed on client websites to provide the sign language translation functionality.

**Plugin structure:**
- `sign-language-video.php` - Main plugin file with WordPress hooks
- `includes/` - Core plugin classes and functionality
  - `class-domain-verifier.php` - Centralized domain verification system
  - `class-video-player.php` - Main plugin controller
  - `class-text-processor.php` - Text scanning and processing
  - `class-api-handler.php` - AJAX endpoints and API communication
  - `class-database.php` - Database connection and queries
- `public/` - Frontend assets (CSS, JavaScript)
  - `css/public-style.css` - Plugin styling
  - `js/public-script.js` - Main plugin functionality
  - `js/text-scanner.js` - Text content scanning
- `assets/` - Plugin icons and media files

### Additional Directories
- `hovervid/` - Additional project files and utilities
- `plugin-api/` - Standalone API components
- `tests/` - Application test suites
- `vendor/` - Composer dependencies
- `node_modules/` - NPM dependencies

## How It Works

### Domain Authorization System
The platform uses a PostgreSQL database to manage domain authorizations. Each domain must be registered and verified before the WordPress plugin will function. The system checks the `is_verified` column in the domains table to determine if a domain should have access to the plugin functionality.

### WordPress Plugin Functionality
When installed on a WordPress site, the plugin:
1. Scans the website content for translatable text
2. Injects translation icons next to text elements
3. Provides a floating video player for sign language translations
4. Continuously verifies domain authorization status
5. Disables functionality if domain verification is revoked

### Real-time Verification
The plugin checks domain verification status every 30 seconds and responds immediately to changes. If a domain's verification is revoked, the plugin automatically disables all functionality and shows appropriate messages to users.

## Database Schema

The system uses PostgreSQL with the following key tables:

**domains table:**
- `id` - Primary key
- `domain` - Website domain name
- `is_verified` - Main control column (boolean)
- `is_active` - Legacy compatibility (boolean)
- `user_id` - Associated user
- `platform` - Platform type (wordpress, etc.)
- `plugin_status` - Current plugin status
- `created_at`, `updated_at` - Timestamps

**users table:**
- Standard Laravel user authentication
- Links to domain ownership

**subscriptions table:**
- Manages user subscriptions and billing
- Controls domain access permissions

## Installation and Setup

### Laravel Application Setup
1. Clone the repository
2. Install dependencies: `composer install && npm install`
3. Configure environment: Copy `.env.example` to `.env` and update database credentials
4. Run migrations: `php artisan migrate`
5. Build assets: `npm run build`
6. Start the application: `php artisan serve`

### WordPress Plugin Installation
1. Copy the `hovervid-plugin` directory to the WordPress `wp-content/plugins/` folder
2. Update database credentials in `hovervid-plugin/includes/class-database.php`
3. Activate the plugin through the WordPress admin interface
4. The plugin will automatically verify domain authorization

### Database Configuration
Update the database connection settings in the plugin's database class:

```php
private $host = 'your-database-host';
private $database = 'your-database-name';
private $username = 'your-username';
private $password = 'your-password';
```

## Usage

### For Administrators
1. Access the Laravel admin panel to manage domains and users
2. Add authorized domains to the database
3. Control plugin functionality by updating the `is_verified` column
4. Monitor usage and manage subscriptions

### For Website Owners
1. Install the WordPress plugin on your domain
2. Contact the administrator to authorize your domain
3. Once verified, the plugin automatically activates
4. Visitors will see translation icons on text content

### Domain Management
Control plugin functionality through database updates:

```sql
-- Enable plugin for a domain
UPDATE domains SET is_verified = true WHERE domain = 'example.com';

-- Disable plugin for a domain
UPDATE domains SET is_verified = false WHERE domain = 'example.com';
```

## Technical Implementation

### Centralized Verification System
The plugin uses a singleton pattern for domain verification to ensure consistency across all components. The `SLVP_Domain_Verifier` class serves as the single source of truth for domain authorization status.

### Error Handling
The system includes comprehensive error handling:
- Graceful database connection failures
- User-friendly error messages
- Automatic recovery when services become available
- No fatal errors that would break WordPress sites

### Performance Optimization
- Efficient DOM scanning with performance limits
- Batched processing for large pages
- Throttled mutation observers for dynamic content
- Cached verification with periodic refresh

### Security Features
- Nonce verification for all AJAX requests
- SQL injection protection with prepared statements
- Domain authorization required for all functionality
- Input sanitization and validation

## Browser Support

The plugin supports modern browsers including Chrome, Firefox, Safari, and Edge. It includes mobile support for iOS Safari and Chrome Mobile. Video content uses MP4 format with H.264 encoding for broad compatibility.

## Development

### Local Development
For local development without database access, the plugin enters a "degraded mode" where it shows admin warnings but doesn't break the WordPress site. This allows developers to work on the plugin without requiring the full backend infrastructure.

### Testing
The system includes comprehensive error handling that prevents fatal errors during development. When the database is unavailable, the plugin displays appropriate admin notices and safely disables functionality.

## Support

For technical support or domain authorization requests, contact the system administrator with:
- Your domain name
- WordPress version
- Plugin version
- Detailed description of any issues

## License

This project is licensed under the GPL v2 or later, following WordPress plugin licensing standards.
