# HoverVid - Sign Language Video Translation Platform

A comprehensive platform that provides sign language video translations for website content through a WordPress plugin and Laravel-based management system.

## 🎯 **Project Overview**

HoverVid enables website owners to make their content accessible through sign language translations. The system consists of:

- **WordPress Plugin**: Scans website text and provides sign language video translations
- **Laravel Admin Panel**: Manages domains, subscriptions, and plugin licensing
- **PostgreSQL Database**: Centralized domain verification and content management

## 🏗️ **Architecture**

### **Components**
1. **`hovervid-plugin/`** - WordPress plugin for frontend text translation
2. **Laravel Backend** - Admin panel and API for domain management
3. **PostgreSQL Database** - Domain verification and content storage

### **Key Features**
- ✅ **Real-time domain verification** via `is_verified` column
- ✅ **Text content scanning** and translation icon injection
- ✅ **Centralized backend system** with no code duplication
- ✅ **Graceful error handling** for database connection issues
- ✅ **Complete functionality blocking** when domains are not verified

## 🚀 **WordPress Plugin Features**

### **Core Functionality**
- **Text Scanner**: Automatically detects translatable text content
- **Video Player**: Floating video player for sign language translations
- **Domain Verification**: Centralized verification system
- **Real-time Control**: Instant enable/disable based on database status

### **User Experience**
- **Translation Icons**: Hover-activated icons on text elements
- **Floating Video Player**: Draggable, resizable video interface
- **Smooth Interactions**: Professional UI/UX design
- **Mobile Support**: Touch-friendly interactions

## 🔧 **Technical Implementation**

### **Centralized Verification System**
```php
class SLVP_Domain_Verifier {
    // Single source of truth for domain verification
    public function should_plugin_work() {
        return $this->is_domain_verified() && 
               $this->domain_exists() && 
               !$this->has_error();
    }
}
```

### **Plugin Behavior**
- **`is_verified = true`**: Full functionality enabled
- **`is_verified = false`**: All functionality disabled, license expired message
- **Domain not in database**: Plugin blocked, authorization error

### **Real-time Monitoring**
- Checks domain status every 30 seconds
- Updates on window focus and page visibility changes
- Immediate response to database changes

## 📊 **Database Schema**

### **Domains Table**
```sql
domains (
    id: bigint,
    domain: text,
    is_verified: boolean,  -- Main control column
    is_active: boolean,    -- Legacy compatibility
    user_id: bigint,
    platform: text,
    plugin_status: text,
    created_at: timestamp,
    updated_at: timestamp
)
```

## 🛠️ **Installation & Setup**

### **WordPress Plugin**
1. Upload `hovervid-plugin/` to `/wp-content/plugins/`
2. Activate the plugin in WordPress admin
3. Plugin automatically connects to verification database

### **Laravel Backend**
1. Configure PostgreSQL database connection
2. Run migrations: `php artisan migrate`
3. Set up domain management interface

### **Database Configuration**
Update database credentials in `hovervid-plugin/includes/class-database.php`:
```php
private $host = 'your-database-host';
private $database = 'your-database-name';
private $username = 'your-username';
private $password = 'your-password';
```

## 🎮 **Usage**

### **For Website Owners**
1. Install WordPress plugin on your domain
2. Contact provider to authorize your domain
3. Once verified, plugin automatically activates
4. Visitors see translation icons on text content

### **For Administrators**
1. Access Laravel admin panel
2. Manage domain authorizations via database
3. Control plugin functionality with `is_verified` column
4. Monitor plugin usage and status

### **Domain Control**
```sql
-- Enable plugin for a domain
UPDATE domains SET is_verified = true WHERE domain = 'example.com';

-- Disable plugin for a domain  
UPDATE domains SET is_verified = false WHERE domain = 'example.com';
```

## 🔒 **Security Features**

- **Nonce verification** for all AJAX requests
- **Domain authorization** required for activation
- **SQL injection protection** with prepared statements
- **Graceful error handling** prevents information disclosure
- **Input sanitization** for all user data

## ⚡ **Performance**

- **Lazy loading** of translation icons
- **Batched processing** for large pages
- **Throttled mutations** observer for dynamic content
- **Efficient DOM scanning** with performance limits
- **Cached verification** with 30-second refresh intervals

## 🧪 **Testing**

### **Plugin Verification**
```php
// Test domain verification
$verifier = SLVP_Domain_Verifier::get_instance();
echo $verifier->should_plugin_work() ? 'ENABLED' : 'DISABLED';
```

### **Database Connection**
The plugin gracefully handles database unavailability:
- Shows admin warning when database is unreachable
- Continues WordPress functionality without crashes
- Automatically recovers when database becomes available

## 🚨 **Error Handling**

### **Database Unavailable**
- Plugin enters "degraded mode"
- Shows user-friendly admin notice
- All functionality safely disabled
- No fatal errors or crashes

### **Domain Not Authorized**
- Plugin activation prevented
- Clear error message displayed
- Automatic silent deactivation
- Instructions for domain authorization

## 🎯 **Browser Support**

- **Modern Browsers**: Chrome, Firefox, Safari, Edge
- **Mobile Support**: iOS Safari, Chrome Mobile
- **Video Formats**: MP4 with H.264 encoding
- **JavaScript**: ES6+ features with fallbacks

## 📝 **File Structure**

```
hovervid-plugin/
├── sign-language-video.php          # Main plugin file
├── includes/
│   ├── class-domain-verifier.php    # Centralized verification
│   ├── class-video-player.php       # Main plugin controller
│   ├── class-text-processor.php     # Text scanning and processing
│   ├── class-api-handler.php        # AJAX endpoints
│   └── class-database.php           # Database connection
├── public/
│   ├── css/public-style.css         # Plugin styles
│   ├── js/public-script.js          # Main plugin JavaScript
│   └── js/text-scanner.js           # Text scanning functionality
└── assets/
    ├── hovervid-icon.svg            # Translation icon
    └── hovervid-logo.svg            # Plugin logo
```

## 🔄 **Version Control**

- **Git**: Version controlled with comprehensive history
- **Branches**: Feature development and release management
- **Documentation**: Inline code documentation and README
- **Clean Codebase**: No test files or debug scripts in repository

## 📞 **Support**

For technical support or domain authorization requests, contact the plugin provider with:
- Your domain name
- WordPress version
- Plugin version
- Detailed description of any issues

## 🎉 **Key Achievements**

✅ **Centralized Backend System** - Single source of truth for verification
✅ **Real-time Domain Control** - Instant plugin enable/disable
✅ **Graceful Error Handling** - No fatal errors, professional degradation
✅ **Complete Functionality Blocking** - True disable when not verified
✅ **Professional UI/UX** - Smooth, accessible user experience
✅ **Performance Optimized** - Efficient scanning and processing
✅ **Security Hardened** - Protection against common vulnerabilities

---

**HoverVid makes web content accessible through sign language translation technology.** 🎯
