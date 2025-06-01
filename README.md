# HoverVid

A sign language video translation platform that makes web content accessible through WordPress plugin integration and Laravel-based domain management.

## Overview

HoverVid consists of a WordPress plugin that automatically detects text content on websites and provides sign language video translations, paired with a Laravel backend system for domain authorization and subscription management. The system uses PostgreSQL for centralized domain verification and content management.

## Project Structure

This repository contains the complete HoverVid platform:

**Laravel Backend Application**
- Admin panel for domain and subscription management
- API endpoints for plugin authorization
- User management and billing system
- Database migrations and seeders

**WordPress Plugin (`hovervid-plugin/`)**
- Frontend text scanning and translation interface
- Video player component
- Domain verification system
- AJAX handlers for real-time functionality

**Key Directories**
```
app/                    Laravel application logic
resources/              Frontend assets and views
database/               Migrations and database schema
routes/                 API and web route definitions
config/                 Application configuration
hovervid-plugin/        WordPress plugin files
public/                 Public web assets
storage/                Application storage and logs
```

## WordPress Plugin Features

The WordPress plugin automatically scans website content and adds translation functionality:

**Text Processing**
- Scans text content across pages
- Adds hover-activated translation icons
- Processes dynamic content updates

**Video Player**
- Floating, draggable video interface
- Touch-friendly mobile support
- Resizable player window

**Domain Authorization**
- Real-time verification against database
- Graceful handling of unauthorized domains
- Automatic enable/disable based on subscription status

## Technical Implementation

**Backend Architecture**
- Laravel 10+ framework
- PostgreSQL database
- RESTful API design
- Domain-based authorization system

**Frontend Technology**
- Vue.js 3 with Composition API
- Vite build system
- TypeScript support
- Responsive design

**WordPress Integration**
- Modern PHP practices
- WordPress hooks and filters
- AJAX endpoints
- Nonce security verification

## Database Schema

The system uses PostgreSQL with key tables:

**domains** - Domain authorization and verification
```sql
id, domain, is_verified, is_active, user_id, platform, 
plugin_status, created_at, updated_at
```

**users** - System users and subscriptions
**subscriptions** - Domain subscription management
**content** - Translation content and videos

## Installation

**Backend Setup**
1. Clone the repository
2. Install PHP dependencies: `composer install`
3. Install Node dependencies: `npm install`
4. Configure environment: Copy `.env.example` to `.env`
5. Set up PostgreSQL database
6. Run migrations: `php artisan migrate`

**WordPress Plugin**
1. Copy `hovervid-plugin/` to WordPress plugins directory
2. Configure database connection in plugin files
3. Activate through WordPress admin

**Database Configuration**
Update database credentials in `hovervid-plugin/includes/class-database.php`:
```php
private $host = 'your-database-host';
private $database = 'your-database-name';
private $username = 'your-username';
private $password = 'your-password';
```

## Usage

**For Administrators**
- Access Laravel admin panel to manage domains
- Control plugin functionality via database
- Monitor usage and subscription status

**For Website Owners**
- Install WordPress plugin on authorized domains
- Plugin automatically activates when domain is verified
- Visitors see translation icons on text content

**Domain Management**
```sql
-- Enable plugin for a domain
UPDATE domains SET is_verified = true WHERE domain = 'example.com';

-- Disable plugin for a domain
UPDATE domains SET is_verified = false WHERE domain = 'example.com';
```

## Development

**Local Development**
```bash
# Start Laravel development server
php artisan serve

# Start Vite development server
npm run dev

# Run tests
php artisan test
```

**Plugin Development**
The WordPress plugin includes a centralized verification system that handles database connectivity gracefully. When the database is unavailable, the plugin enters a degraded mode with user-friendly error messages.

**Key Classes**
- `SLVP_Domain_Verifier` - Centralized domain verification
- `SLVP_Video_Player` - Main plugin controller
- `SLVP_Text_Processor` - Content scanning and processing
- `SLVP_Api_Handler` - AJAX endpoint management

## Security

- Domain authorization required for plugin activation
- SQL injection protection with prepared statements
- Nonce verification for AJAX requests
- Input sanitization and validation
- Graceful error handling

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile devices (iOS Safari, Chrome Mobile)
- Video format: MP4 with H.264 encoding
- JavaScript: ES6+ with fallbacks

## Configuration

**Environment Variables**
Key environment variables for Laravel application:
```
DB_HOST=127.0.0.1
DB_DATABASE=hovervid_db
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

**Plugin Configuration**
The WordPress plugin automatically detects the environment and connects to the appropriate database. For local development without database access, the plugin displays a warning and enters safe mode.

## Deployment

**Production Deployment**
1. Set up production database
2. Configure environment variables
3. Build assets: `npm run build`
4. Deploy Laravel application
5. Configure web server (Apache/Nginx)
6. Set up SSL certificates

**Plugin Distribution**
The WordPress plugin can be distributed as a standard WordPress plugin package. Domain authorization is handled server-side through the database verification system.

## Support

For technical support or domain authorization:
- Provide domain name and WordPress version
- Include detailed description of any issues
- Check plugin logs for error messages

---

HoverVid makes web content accessible through sign language video translation technology.
