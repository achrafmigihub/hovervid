# HoverVid Platform

HoverVid is a platform that provides sign language video translations for website content. It consists of a Laravel-based admin panel for managing domains and subscriptions, along with a WordPress plugin that adds sign language translation capabilities to any WordPress website.

## Overview

The platform enables website owners to make their content accessible through sign language translations. When visitors hover over text content, they see translation icons that play corresponding sign language videos.

## Project Structure

This repository contains both the backend management system and the WordPress plugin:

```
├── app/                          # Laravel application logic
├── config/                       # Laravel configuration files
├── database/                     # Database migrations and seeders
├── resources/                    # Frontend views and assets
├── routes/                       # API and web routes
├── hovervid-plugin/             # WordPress plugin
│   ├── sign-language-video.php # Main plugin file
│   ├── includes/                # Plugin classes
│   ├── public/                  # Plugin assets and scripts
│   └── assets/                  # Icons and graphics
├── public/                      # Laravel public directory
├── storage/                     # Application storage
└── vendor/                      # Composer dependencies
```

## Components

### Laravel Backend
The Laravel application serves as the admin panel and API for managing the platform. It handles:

- Domain authorization and verification
- User management and subscriptions
- Content management for sign language videos
- API endpoints for plugin communication
- Admin dashboard for platform monitoring

### WordPress Plugin
Located in the `hovervid-plugin` directory, this plugin adds sign language translation functionality to WordPress websites:

- **Text Scanner**: Automatically detects translatable text content on pages
- **Video Player**: Floating, draggable video player for sign language translations
- **Domain Verification**: Real-time verification against the authorization database
- **Translation Icons**: Hover-activated icons that appear on text elements

## How It Works

### Domain Verification System
The plugin uses a centralized verification system that checks the `is_verified` column in the domains table:

- When `is_verified = true`: Plugin is fully functional
- When `is_verified = false`: Plugin shows license expired message and disables all functionality
- When domain is not in database: Plugin is completely blocked

### Real-time Control
The system monitors domain status in real-time:
- Checks verification status every 30 seconds
- Updates immediately when window gains focus
- Responds to database changes within 30 seconds

### Text Processing
The plugin scans webpage content and:
1. Identifies translatable text elements
2. Injects translation icons next to text
3. Handles click events to load appropriate sign language videos
4. Manages video playback in a floating player

## Database Schema

The core domains table structure:

```sql
domains (
    id              BIGINT PRIMARY KEY,
    domain          TEXT NOT NULL,
    is_verified     BOOLEAN DEFAULT FALSE,
    is_active       BOOLEAN DEFAULT TRUE,
    user_id         BIGINT,
    platform        TEXT DEFAULT 'wordpress',
    plugin_status   TEXT DEFAULT 'inactive',
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
)
```

## Installation

### Backend Setup

1. Clone the repository and install dependencies:
```bash
composer install
npm install
```

2. Configure your environment:
```bash
cp .env.example .env
php artisan key:generate
```

3. Set up the database:
```bash
php artisan migrate
php artisan db:seed
```

4. Start the development server:
```bash
php artisan serve
npm run dev
```

### WordPress Plugin Installation

1. Copy the `hovervid-plugin` folder to your WordPress `wp-content/plugins/` directory

2. Update database credentials in `hovervid-plugin/includes/class-database.php`:
```php
private $host = 'your-database-host';
private $database = 'your-database-name';
private $username = 'your-username';
private $password = 'your-password';
```

3. Activate the plugin through the WordPress admin panel

## Usage

### For Administrators

Use the Laravel admin panel to:
- Add authorized domains to the database
- Manage user subscriptions and licensing
- Control plugin functionality via the `is_verified` column
- Monitor plugin usage across domains

### Domain Control

Enable or disable plugin functionality by updating the database:

```sql
-- Enable plugin for a domain
UPDATE domains SET is_verified = true WHERE domain = 'example.com';

-- Disable plugin for a domain
UPDATE domains SET is_verified = false WHERE domain = 'example.com';
```

### For Website Owners

Once your domain is authorized:
1. Install the WordPress plugin
2. Plugin automatically connects to the verification system
3. Visitors will see translation icons on text content
4. Click icons to view sign language translations

## Technical Features

### Security
- Nonce verification for all AJAX requests
- SQL injection protection with prepared statements
- Domain authorization required for all operations
- Input sanitization and validation

### Performance
- Efficient DOM scanning with performance limits
- Batched text processing for large pages
- Throttled mutation observers for dynamic content
- Cached verification with periodic refresh

### Error Handling
- Graceful degradation when database is unavailable
- User-friendly admin notices for connection issues
- Automatic recovery when services become available
- No fatal errors that break WordPress functionality

## Browser Support

The plugin works with modern browsers including Chrome, Firefox, Safari, and Edge. It supports both desktop and mobile devices with touch-friendly interactions.

## API Endpoints

The Laravel backend provides several API endpoints for plugin communication:

- `POST /api/validate-domain` - Verify domain authorization
- `POST /api/update-status` - Update plugin status
- `GET /api/domain-status` - Get current domain status

## Development

### Local Development

For local development without the PostgreSQL database, the plugin will show an admin warning and disable all functionality gracefully without causing crashes.

### Testing

The system includes comprehensive error handling that allows for testing in various environments:
- Database available: Full functionality
- Database unavailable: Graceful degradation with admin notices
- Unauthorized domains: Clear error messages with instructions

## Configuration

### Environment Variables

Key environment variables for the Laravel application:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hovervid_db
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### Plugin Configuration

The WordPress plugin automatically detects the environment and adjusts its behavior accordingly. No additional configuration is typically required beyond database credentials.

## Contributing

When contributing to this project:
1. Follow PSR-12 coding standards for PHP
2. Use meaningful commit messages
3. Test thoroughly in both Laravel and WordPress environments
4. Update documentation for any new features

## Support

For technical support or domain authorization requests, contact the system administrator with:
- Your domain name
- WordPress version
- Plugin version
- Detailed description of any issues
