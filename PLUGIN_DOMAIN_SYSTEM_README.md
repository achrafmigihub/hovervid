# HoverVid Plugin Domain Validation System

This document explains the complete backend system for validating WordPress plugin installations based on domain authorization.

## Overview

The HoverVid plugin domain validation system ensures that the WordPress plugin only works on authorized domains that exist in our PostgreSQL database. When someone tries to install and activate the plugin on their website, the system checks if their domain is registered and active in our system.

## System Components

### 1. Database Schema

The `domains` table stores authorized domains:

```sql
CREATE TABLE domains (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    domain TEXT UNIQUE NOT NULL,
    platform TEXT DEFAULT 'wordpress',
    plugin_status TEXT DEFAULT 'inactive',
    status TEXT DEFAULT 'inactive',
    is_active BOOLEAN DEFAULT false,
    is_verified BOOLEAN DEFAULT false,
    api_key UUID,
    verification_token VARCHAR(32),
    last_checked_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
);
```

### 2. Backend API Endpoints

#### Domain Validation Endpoint
```
POST /api/plugin/validate-domain
```

**Request:**
```json
{
    "domain": "example.com"
}
```

**Response (Authorized):**
```json
{
    "success": true,
    "authorized": true,
    "domain_exists": true,
    "is_active": true,
    "is_verified": true,
    "status": "active",
    "plugin_status": "inactive",
    "message": "Domain 'example.com' is authorized to use the HoverVid plugin.",
    "code": "DOMAIN_AUTHORIZED"
}
```

**Response (Unauthorized):**
```json
{
    "success": false,
    "authorized": false,
    "domain_exists": false,
    "is_active": false,
    "message": "Domain 'example.com' is not registered in our system. Please contact support to register your domain.",
    "code": "DOMAIN_NOT_FOUND"
}
```

#### Plugin Status Tracking
```
POST /api/plugin/activation
POST /api/plugin/deactivation
```

### 3. Laravel Backend Components

#### Models

**Domain Model** (`app/Models/Domain.php`)
- Handles database operations for domains
- Includes validation and authorization logic
- Tracks plugin status and activation

**User Model** (`app/Models/User.php`)
- Manages domain ownership relationships

#### Services

**PluginDomainValidationService** (`app/Services/PluginDomainValidationService.php`)
- Core domain validation logic
- Domain format validation
- Plugin status management
- Statistics generation

#### Controllers

**PluginDomainController** (`app/Http/Controllers/API/PluginDomainController.php`)
- Handles API requests from WordPress plugins
- Validates domain authorization
- Records plugin activation/deactivation events

### 4. WordPress Plugin Integration

#### Plugin Files Structure
```
hovervid-plugin/
├── sign-language-video.php          # Main plugin file
├── includes/
│   ├── class-config.php             # Configuration management
│   ├── class-api-validator.php      # Backend API communication
│   └── class-video-player.php       # Video player functionality
├── config.php                       # Plugin configuration
└── assets/                          # Plugin assets
```

#### Key Plugin Functions

**Domain Authorization Check:**
```php
function slvp_check_domain_authorization() {
    $current_domain = $_SERVER['HTTP_HOST'] ?? '';
    
    // Development domains are always authorized
    $force_active_domains = [
        'localhost' => true,
        // Add other development domains
    ];
    
    if (isset($force_active_domains[$current_domain])) {
        return ['is_active' => true, 'domain_exists' => true];
    }
    
    // Check with backend API
    $api_validator = HoverVid_API_Validator::get_instance();
    return $api_validator->check_domain_status($current_domain);
}
```

**Plugin Activation Hook:**
```php
function slvp_activate_plugin() {
    $domain_status = slvp_check_domain_authorization();
    
    if (!$domain_status['is_active'] || !$domain_status['domain_exists']) {
        // Deactivate plugin and show error
        deactivate_plugins(plugin_basename(__FILE__), true);
        wp_die('Domain not authorized for HoverVid plugin.');
    }
}
```

## How It Works

### 1. Plugin Installation Flow

1. **User installs plugin** on their WordPress site
2. **Plugin activation triggered** - WordPress calls `slvp_activate_plugin()`
3. **Domain extraction** - Plugin gets domain from `$_SERVER['HTTP_HOST']`
4. **API call to backend** - Plugin calls `/api/plugin/validate-domain`
5. **Domain validation** - Backend checks if domain exists and is active
6. **Authorization decision:**
   - ✅ **Authorized**: Plugin activates normally
   - ❌ **Unauthorized**: Plugin deactivates with error message

### 2. Domain Validation Logic

The system checks multiple conditions:

```php
// 1. Domain exists in database
$domainRecord = Domain::where('domain', $cleanDomain)->first();

// 2. Domain is active
if (!$domainRecord->is_active) {
    return ['authorized' => false, 'reason' => 'domain_inactive'];
}

// 3. Domain status is active
if ($domainRecord->status !== 'active') {
    return ['authorized' => false, 'reason' => 'domain_status_invalid'];
}

// 4. All checks passed
return ['authorized' => true];
```

### 3. Error Handling

When domain is not authorized, the plugin:
- Shows error message in WordPress admin
- Logs the attempt for security tracking
- Prevents plugin functionality from loading
- Provides clear instructions for users

## Configuration

### Backend Configuration

Update your `.env` file:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hovervid_db
DB_USERNAME=postgres
DB_PASSWORD=postgres_hovervid
```

### Plugin Configuration

Update `hovervid-plugin/config.php`:
```php
return [
    'laravel_backend' => [
        'production' => 'https://your-api.com',
        'local' => 'http://localhost:8000',
        'staging' => 'https://staging-api.com',
    ],
];
```

## Testing the System

### 1. Seed Test Data

Run the database seeder to create test domains:
```bash
php artisan db:seed --class=DomainTestSeeder
```

### 2. Test Script

Run the test script to validate the system:
```bash
php test-plugin-domain-validation.php
```

### 3. Manual Testing

1. **Add a domain to database:**
```sql
INSERT INTO domains (user_id, domain, status, is_active, is_verified, platform)
VALUES (1, 'mywebsite.com', 'active', true, true, 'wordpress');
```

2. **Install plugin on authorized domain** - Should work normally
3. **Install plugin on unauthorized domain** - Should show error and deactivate

## Security Features

### 1. Domain Validation
- Strict domain format validation
- Protocol and www prefix removal
- Case-insensitive matching

### 2. Development Override
- Localhost and development domains allowed
- Easy testing without database entries

### 3. API Security
- Request validation and sanitization
- Rate limiting on API endpoints
- Detailed logging of authorization attempts

### 4. Error Messages
- Generic error messages for security
- Detailed logging for administrators
- No exposure of internal system details

## Monitoring and Analytics

### Plugin Statistics
Access plugin statistics via API:
```
GET /api/plugin/stats
```

Returns:
```json
{
    "total_domains": 150,
    "active_domains": 120,
    "active_plugins": 85,
    "verified_domains": 100,
    "generated_at": "2024-01-01T12:00:00Z"
}
```

### Logging
The system logs:
- Domain validation attempts
- Plugin activation/deactivation events
- Authorization failures
- API errors and exceptions

## Troubleshooting

### Common Issues

1. **Plugin immediately deactivates**
   - Check if domain exists in database
   - Verify domain is active (`is_active = true`)
   - Check backend API connectivity

2. **API connection errors**
   - Verify backend URL in plugin config
   - Check firewall and network connectivity
   - Validate SSL certificates in production

3. **Domain not found**
   - Add domain to database using seeder or admin panel
   - Ensure exact domain match (no www, protocol, etc.)

### Debug Mode

Enable debug logging in plugin config:
```php
'settings' => [
    'enable_debug_logging' => true,
],
```

Check WordPress error logs for detailed information.

## Deployment

### Production Setup

1. **Database Migration:**
```bash
php artisan migrate
```

2. **Seed Initial Data:**
```bash
php artisan db:seed --class=DomainTestSeeder
```

3. **Configure API URLs:**
   - Update plugin config for production API
   - Set up SSL certificates
   - Configure rate limiting

4. **Security Checklist:**
   - Enable HTTPS for all API calls
   - Set up proper CORS headers
   - Configure database backups
   - Monitor failed authorization attempts

## Support

For issues with the domain validation system:

1. Check the system logs for error details
2. Verify database connectivity and schema
3. Test API endpoints manually
4. Contact support with domain and error details

---

This system ensures that the HoverVid plugin only works on authorized domains while providing a smooth experience for legitimate users and clear error messages for unauthorized attempts. 
