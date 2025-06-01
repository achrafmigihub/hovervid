# HoverVid

A comprehensive sign language video translation platform that makes web content accessible through sign language interpretation. The system consists of a Laravel-based management platform and a WordPress plugin for frontend integration.

## Overview

HoverVid enables website owners to provide sign language translations for their content. Users can click on text elements to view corresponding sign language video interpretations, making websites more accessible to the deaf and hard-of-hearing community.

The platform includes:
- Laravel-based admin panel for managing domains, users, and content
- WordPress plugin for frontend text scanning and video playback
- PostgreSQL database for centralized domain verification and content management
- Real-time domain authorization system

## Project Structure

```
├── app/                          # Laravel application
├── config/                       # Laravel configuration files
├── database/                     # Database migrations and seeders
├── resources/                    # Laravel frontend resources
├── routes/                       # Laravel routing
├── public/                       # Laravel public assets
├── storage/                      # Laravel storage
├── hovervid-plugin/              # WordPress plugin
│   ├── sign-language-video.php   # Main plugin file
│   ├── includes/                 # PHP classes
│   │   ├── class-domain-verifier.php
│   │   ├── class-video-player.php
│   │   ├── class-text-processor.php
│   │   ├── class-api-handler.php
│   │   └── class-database.php
│   ├── public/                   # Plugin assets
│   │   ├── css/
│   │   └── js/
│   └── assets/                   # Plugin images and icons
└── vendor/                       # Composer dependencies
```

## Features

### Laravel Backend
- User management and authentication
- Domain authorization and verification
- Content management system
- API endpoints for plugin communication
- Real-time status monitoring
- Subscription and licensing management

### WordPress Plugin
- Automatic text content detection
- Translation icon injection
- Floating video player interface
- Real-time domain verification
- Mobile-responsive design
- Performance optimized scanning

## Installation

### Laravel Application

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

3. Set up your database in `.env`:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hovervid_db
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

4. Run migrations:
```bash
php artisan migrate
```

5. Start the development server:
```bash
php artisan serve
```

### WordPress Plugin

1. Copy the `hovervid-plugin` directory to your WordPress `wp-content/plugins/` folder

2. Update database credentials in `hovervid-plugin/includes/class-database.php`

3. Activate the plugin through WordPress admin panel

## Database Schema

The system uses PostgreSQL with the following key tables:

### domains
- id (primary key)
- domain (text, unique)
- is_verified (boolean) - Controls plugin functionality
- is_active (boolean) - Legacy compatibility
- user_id (foreign key)
- platform (text)
- plugin_status (text)
- created_at, updated_at (timestamps)

### users
- Standard Laravel user authentication table
- Manages admin and customer accounts

### content
- Stores sign language video content
- Links to specific text phrases and translations

## Configuration

### Domain Verification

The plugin uses a centralized verification system. Domain access is controlled through the `is_verified` column in the domains table:

```sql
-- Enable plugin for a domain
UPDATE domains SET is_verified = true WHERE domain = 'example.com';

-- Disable plugin for a domain
UPDATE domains SET is_verified = false WHERE domain = 'example.com';
```

### Plugin Behavior

- **Verified domains (is_verified = true)**: Full plugin functionality enabled
- **Unverified domains (is_verified = false)**: Plugin disabled with license message
- **Unauthorized domains**: Plugin blocked entirely

## Usage

### For Administrators

1. Access the Laravel admin panel
2. Add authorized domains to the database
3. Manage user subscriptions and verification status
4. Monitor plugin usage across domains

### For Website Owners

1. Install the WordPress plugin
2. Request domain authorization from the provider
3. Once verified, the plugin automatically activates
4. Visitors will see translation icons on text content

### For End Users

1. Visit a website with HoverVid installed
2. Hover over text to see translation icons
3. Click icons to view sign language video interpretations
4. Use the floating video player controls

## API Endpoints

The Laravel application provides several API endpoints for plugin communication:

- `POST /api/validate-domain` - Check domain authorization status
- `POST /api/update-status` - Update plugin status for a domain
- `GET /api/domain-status` - Get current domain verification status

## Security

- CSRF protection on all forms and API endpoints
- SQL injection prevention with prepared statements
- Domain authorization required for plugin activation
- Nonce verification for WordPress AJAX requests
- Input sanitization and validation

## Development

### Local Development Setup

1. Set up Laravel development environment
2. Configure local PostgreSQL database
3. Add test domains to the database for plugin testing
4. Use Laravel's built-in development server

### Plugin Development

The WordPress plugin includes several key components:

- **Domain Verifier**: Centralized verification system
- **Text Processor**: Scans and processes page content
- **Video Player**: Handles video playback and UI
- **API Handler**: Manages AJAX requests to Laravel backend

### Testing

Add test domains to your local database:
```sql
INSERT INTO domains (domain, is_verified, is_active, user_id) 
VALUES ('localhost', true, true, 1);
```

## Performance

The system is optimized for performance with:
- Efficient DOM scanning algorithms
- Batched processing for large pages
- Cached verification with 30-second refresh intervals
- Throttled mutation observers for dynamic content
- Lazy loading of translation assets

## Browser Support

- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is proprietary software. All rights reserved.

## Support

For technical support or domain authorization requests, contact the development team with:
- Your domain name
- WordPress version
- Plugin version
- Detailed description of any issues
