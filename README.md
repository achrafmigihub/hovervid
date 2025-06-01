# HoverVid

A comprehensive platform for providing sign language video translations on websites. The system consists of a Laravel-based management panel and a WordPress plugin that enables website owners to offer sign language translations for their content.

## Overview

HoverVid allows website visitors to access sign language translations by hovering over text content. The platform includes domain authorization, real-time verification, and a centralized management system for controlling plugin access across multiple websites.

## Project Structure

```
├── app/                     # Laravel application core
├── config/                  # Laravel configuration files
├── database/                # Database migrations and seeders
├── resources/               # Views, assets, and frontend resources
├── routes/                  # API and web routes
├── public/                  # Web-accessible files
├── storage/                 # Application storage
├── tests/                   # Application tests
├── hovervid-plugin/         # WordPress plugin
│   ├── admin/               # Admin interface
│   ├── assets/              # Plugin assets (icons, logos)
│   ├── includes/            # Plugin core classes
│   ├── public/              # Frontend assets (CSS, JS)
│   └── sign-language-video.php  # Main plugin file
├── plugin-api/              # Standalone API endpoints
├── composer.json            # PHP dependencies
├── package.json             # Node.js dependencies
└── artisan                  # Laravel command-line interface
```

## Features

### Laravel Backend
- Domain management and authorization
- User registration and subscription handling
- Real-time plugin status monitoring
- Database-driven domain verification
- Admin panel for managing authorized domains

### WordPress Plugin
- Automatic text content scanning
- Floating video player for translations
- Real-time domain verification
- Graceful error handling
- Mobile-responsive interface

## Installation

### Laravel Application

1. Clone the repository and install dependencies:
```bash
composer install
npm install
```

2. Copy environment configuration:
```bash
cp .env.example .env
```

3. Configure your database connection in `.env`:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hovervid_db
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

4. Generate application key and run migrations:
```bash
php artisan key:generate
php artisan migrate
```

5. Start the development server:
```bash
php artisan serve
```

### WordPress Plugin

1. Copy the `hovervid-plugin` directory to your WordPress installation:
```bash
cp -r hovervid-plugin /path/to/wordpress/wp-content/plugins/
```

2. Configure the database connection in `hovervid-plugin/includes/class-database.php`

3. Activate the plugin through the WordPress admin panel

## Database Schema

The system uses PostgreSQL with the following main table:

```sql
CREATE TABLE domains (
    id BIGSERIAL PRIMARY KEY,
    domain TEXT NOT NULL UNIQUE,
    is_verified BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    user_id BIGINT,
    platform TEXT DEFAULT 'wordpress',
    plugin_status TEXT DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Usage

### Managing Domains

Add authorized domains through the Laravel admin panel or directly via database:

```sql
INSERT INTO domains (domain, is_verified, user_id) 
VALUES ('example.com', true, 1);
```

### Plugin Behavior

The WordPress plugin behavior is controlled by the `is_verified` column:

- `is_verified = true`: Plugin functions normally
- `is_verified = false`: Plugin shows license expired message
- Domain not in database: Plugin shows unauthorized message

### Real-time Control

Changes to domain verification status are reflected in the WordPress plugin within 30 seconds. The plugin checks domain status:

- Every 30 seconds automatically
- When the browser window regains focus
- When the page becomes visible
- Before any user interaction

## API Endpoints

The system provides AJAX endpoints for real-time communication:

- `POST /wp-admin/admin-ajax.php?action=slvp_check_domain` - Check domain status
- `POST /wp-admin/admin-ajax.php?action=slvp_get_video` - Retrieve translation videos

## Configuration

### WordPress Plugin

The plugin uses a centralized domain verifier that handles all database connections and verification logic. Database connection parameters are configured in `includes/class-database.php`.

### Laravel Backend

Key configuration files:
- `config/database.php` - Database configuration
- `config/app.php` - Application settings
- `.env` - Environment variables

## Development

### Requirements

- PHP 8.1 or higher
- PostgreSQL 12 or higher
- Node.js 16 or higher
- Composer
- WordPress 5.0 or higher (for plugin)

### Local Development

1. Set up the Laravel application following the installation steps
2. Configure a local PostgreSQL database
3. Add your local domain to the database for testing
4. Install the WordPress plugin on your local WordPress site

### Error Handling

The plugin includes comprehensive error handling:

- Database connection failures are handled gracefully
- Plugin continues to function when the database is unavailable
- Clear error messages are displayed to administrators
- No fatal errors occur that could break WordPress sites

## Security

- Domain authorization prevents unauthorized usage
- NONCE verification for all AJAX requests
- SQL injection protection with prepared statements
- Input sanitization for all user data
- Graceful degradation when services are unavailable

## Browser Support

- Chrome, Firefox, Safari, Edge (latest versions)
- Mobile browsers (iOS Safari, Chrome Mobile)
- JavaScript ES6+ with appropriate fallbacks

## License

This project is licensed under the GPL v2 or later.

## Support

For technical support or domain authorization requests, contact support with:
- Domain name
- WordPress and plugin versions
- Description of any issues
