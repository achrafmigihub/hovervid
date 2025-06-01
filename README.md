# HoverVid - Complete Sign Language Video Translation Platform

A comprehensive full-stack platform that provides sign language video translations for website content through a WordPress plugin and modern Laravel + Vue.js management system.

## 🎯 **Project Overview**

HoverVid is a complete accessibility solution that makes website content accessible through sign language translations. The platform consists of three main components:

- **🎨 Laravel + Vue.js Admin Dashboard**: Modern admin panel for domain management, user administration, and system control
- **🔌 WordPress Plugin**: Client-side plugin that scans website text and provides sign language video translations
- **🗄️ PostgreSQL Database**: Centralized domain verification, user management, and content storage

## 🏗️ **Complete Project Architecture**

### **🖥️ Backend System (Laravel + Vue.js)**
- **Framework**: Laravel 11.x with JWT authentication
- **Frontend**: Vue.js 3 + Vuetify 3 (Sneat Admin Template)
- **Build Tools**: Vite + TypeScript for modern development
- **Authentication**: JWT-based user authentication system
- **Database**: PostgreSQL with Eloquent ORM

### **🔌 WordPress Plugin**
- **Plugin Name**: HoverVid Sign Language Video Plugin
- **Compatibility**: WordPress 5.0+ and PHP 8.0+
- **Architecture**: Object-oriented with centralized verification system
- **Real-time Control**: Instant enable/disable based on database status

### **🗄️ Database Layer**
- **Type**: PostgreSQL
- **Models**: Users, Domains, Content
- **Security**: Prepared statements, input validation
- **Performance**: Optimized queries with proper indexing

## 📁 **Project Structure**

```
HoverVid/
├── 🎨 Laravel Backend Admin Dashboard
│   ├── app/
│   │   ├── Models/
│   │   │   ├── User.php                 # User management
│   │   │   ├── Domain.php               # Domain verification system
│   │   │   └── Content.php              # Content management
│   │   ├── Http/Controllers/            # API endpoints & web controllers
│   │   ├── Services/                    # Business logic services
│   │   └── Repositories/                # Data access layer
│   ├── resources/
│   │   ├── js/                          # Vue.js components & pages
│   │   ├── css/                         # Styling & themes
│   │   └── views/                       # Blade templates
│   ├── routes/
│   │   ├── web.php                      # Web routes
│   │   └── api.php                      # API endpoints
│   ├── database/
│   │   ├── migrations/                  # Database schema
│   │   └── seeders/                     # Sample data
│   ├── config/                          # Laravel configuration
│   ├── composer.json                    # PHP dependencies
│   ├── package.json                     # Node.js dependencies
│   └── vite.config.js                   # Frontend build configuration
│
├── 🔌 WordPress Plugin
│   └── hovervid-plugin/
│       ├── sign-language-video.php      # Main plugin file
│       ├── includes/
│       │   ├── class-domain-verifier.php    # Centralized verification
│       │   ├── class-video-player.php       # Main plugin controller
│       │   ├── class-text-processor.php     # Text scanning & processing
│       │   ├── class-api-handler.php        # AJAX endpoints
│       │   └── class-database.php           # Database connection
│       ├── public/
│       │   ├── css/public-style.css         # Plugin styles
│       │   ├── js/public-script.js          # Main plugin JavaScript
│       │   └── js/text-scanner.js           # Text scanning functionality
│       └── assets/
│           ├── hovervid-icon.svg            # Translation icon
│           └── hovervid-logo.svg            # Plugin logo
│
└── 🗄️ Plugin API
    └── plugin-api/                      # Additional API endpoints
```

## 🚀 **Laravel Admin Dashboard Features**

### **🎨 Modern UI/UX**
- **Design System**: Sneat Vuetify Admin Template
- **Responsive**: Mobile-first design approach
- **Dark/Light Mode**: Theme switching capability
- **Real-time**: Live data updates and notifications

### **👥 User Management**
- **Authentication**: JWT-based secure login system
- **Role-based Access**: Admin, Manager, User roles
- **User Profiles**: Complete user management interface
- **Security**: Password hashing, rate limiting

### **🌐 Domain Management**
- **Domain Registration**: Add/remove authorized domains
- **Real-time Control**: Instant plugin enable/disable
- **Status Monitoring**: Live plugin status tracking
- **Bulk Operations**: Manage multiple domains efficiently

### **📊 Analytics & Reporting**
- **Usage Statistics**: Plugin usage analytics
- **Performance Metrics**: System performance monitoring
- **User Activity**: Detailed activity logs
- **Export Features**: Data export capabilities

## 🔌 **WordPress Plugin Features**

### **🎯 Core Functionality**
- **Text Scanner**: Automatically detects translatable text content
- **Video Player**: Floating video player for sign language translations
- **Domain Verification**: Centralized verification system
- **Real-time Control**: Instant enable/disable based on database status

### **💻 User Experience**
- **Translation Icons**: Hover-activated icons on text elements
- **Floating Video Player**: Draggable, resizable video interface
- **Smooth Interactions**: Professional UI/UX design
- **Mobile Support**: Touch-friendly interactions

### **🔒 Security & Performance**
- **Nonce verification** for all AJAX requests
- **Domain authorization** required for activation
- **SQL injection protection** with prepared statements
- **Lazy loading** of translation icons
- **Throttled mutations** observer for dynamic content

## 🛠️ **Installation & Setup**

### **⚙️ System Requirements**
- **Backend**: PHP 8.2+, Node.js 18+, PostgreSQL 13+
- **WordPress**: 5.0+, PHP 8.0+
- **Web Server**: Apache/Nginx with SSL support

### **🎨 Laravel Backend Setup**

1. **Clone & Install Dependencies**
```bash
git clone https://github.com/your-username/hovervid.git
cd hovervid
composer install
npm install
```

2. **Environment Configuration**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Database Setup**
```bash
# Configure .env with PostgreSQL credentials
php artisan migrate
php artisan db:seed
```

4. **Development Server**
```bash
# Start all services (Laravel + Vite + Queue + Logs)
composer run dev

# Or manually:
php artisan serve
npm run dev
```

### **🔌 WordPress Plugin Setup**

1. **Plugin Installation**
```bash
# Upload to WordPress
cp -r hovervid-plugin/ /path/to/wordpress/wp-content/plugins/
```

2. **Database Configuration**
Update database credentials in `hovervid-plugin/includes/class-database.php`:
```php
private $host = 'your-database-host';
private $database = 'your-database-name';
private $username = 'your-username';
private $password = 'your-password';
```

3. **Plugin Activation**
- Activate plugin in WordPress admin
- Plugin automatically connects to verification database

## 🔄 **Development Workflow**

### **🎨 Frontend Development (Vue.js)**
```bash
npm run dev          # Development server with hot reload
npm run build        # Production build
npm run lint         # ESLint code formatting
```

### **🖥️ Backend Development (Laravel)**
```bash
php artisan serve    # Development server
php artisan migrate  # Run database migrations
php artisan queue:work  # Process background jobs
```

### **🧪 Testing**
```bash
# Laravel tests
php artisan test

# WordPress plugin testing
# Test domain verification
$verifier = SLVP_Domain_Verifier::get_instance();
echo $verifier->should_plugin_work() ? 'ENABLED' : 'DISABLED';
```

## 🔒 **Security Implementation**

### **🎨 Laravel Security**
- **JWT Authentication**: Secure token-based authentication
- **CSRF Protection**: Cross-site request forgery protection
- **SQL Injection Prevention**: Eloquent ORM with prepared statements
- **Rate Limiting**: API rate limiting and throttling
- **Input Validation**: Comprehensive request validation

### **🔌 WordPress Plugin Security**
- **Nonce Verification**: WordPress nonce system for AJAX
- **Domain Authorization**: Strict domain verification
- **Data Sanitization**: Input sanitization for all user data
- **Capability Checks**: WordPress capability verification

## 📊 **Database Schema**

### **👥 Users Table**
```sql
users (
    id: bigint PRIMARY KEY,
    name: varchar(255),
    email: varchar(255) UNIQUE,
    email_verified_at: timestamp,
    password: varchar(255),
    remember_token: varchar(100),
    created_at: timestamp,
    updated_at: timestamp
)
```

### **🌐 Domains Table**
```sql
domains (
    id: bigint PRIMARY KEY,
    domain: text UNIQUE,
    is_verified: boolean,      -- Main control column
    is_active: boolean,        -- Legacy compatibility
    user_id: bigint REFERENCES users(id),
    platform: text,
    plugin_status: text,
    created_at: timestamp,
    updated_at: timestamp
)
```

### **📝 Content Table**
```sql
content (
    id: bigint PRIMARY KEY,
    domain_id: bigint REFERENCES domains(id),
    content_text: text,
    video_url: text,
    status: varchar(50),
    created_at: timestamp,
    updated_at: timestamp
)
```

## 🎮 **Usage**

### **👨‍💼 For Administrators (Laravel Dashboard)**
1. **Login**: Access admin dashboard at `http://your-domain/admin`
2. **Domain Management**: Add/remove authorized domains
3. **User Management**: Manage user accounts and permissions
4. **Analytics**: Monitor plugin usage and performance
5. **Real-time Control**: Enable/disable plugins instantly

### **🌐 For Website Owners (WordPress)**
1. **Install Plugin**: Upload and activate HoverVid plugin
2. **Authorization**: Contact administrator for domain authorization
3. **Automatic Activation**: Plugin activates once domain is verified
4. **Content Access**: Visitors see translation icons on text content

### **🎯 Domain Control (Database)**
```sql
-- Enable plugin for a domain
UPDATE domains SET is_verified = true WHERE domain = 'example.com';

-- Disable plugin for a domain  
UPDATE domains SET is_verified = false WHERE domain = 'example.com';

-- Check domain status
SELECT domain, is_verified, plugin_status FROM domains WHERE domain = 'example.com';
```

## 🚨 **Error Handling & Monitoring**

### **🎨 Laravel Error Handling**
- **Exception Handling**: Comprehensive error logging
- **API Error Responses**: Standardized JSON error responses
- **Database Connection**: Graceful database failure handling
- **Queue Monitoring**: Background job failure notifications

### **🔌 WordPress Plugin Error Handling**
- **Database Unavailable**: Plugin enters "degraded mode"
- **Domain Authorization**: Clear error messages for unauthorized domains
- **Graceful Degradation**: No fatal errors, WordPress continues functioning
- **Admin Notifications**: User-friendly admin notices

## 🎨 **Technology Stack**

### **🖥️ Backend Stack**
- **🔧 PHP 8.2+**: Modern PHP with latest features
- **🎯 Laravel 11.x**: Robust web application framework
- **🔐 JWT Authentication**: Secure token-based authentication
- **🗄️ PostgreSQL**: Reliable relational database
- **⚡ Redis**: Caching and session storage

### **🎨 Frontend Stack**
- **⚛️ Vue.js 3**: Progressive JavaScript framework
- **🎨 Vuetify 3**: Material Design component framework
- **⚡ Vite**: Fast build tool and development server
- **📘 TypeScript**: Type-safe JavaScript development
- **🎯 Pinia**: State management for Vue.js

### **🔌 WordPress Integration**
- **🔌 WordPress 5.0+**: Latest WordPress compatibility
- **⚡ AJAX**: Real-time frontend-backend communication
- **🎨 CSS3/JavaScript**: Modern web technologies
- **📱 Responsive Design**: Mobile-first approach

## 🌐 **Browser Support**

- **✅ Modern Browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **📱 Mobile Support**: iOS Safari, Chrome Mobile, Samsung Internet
- **🎥 Video Formats**: MP4 with H.264 encoding
- **⚡ JavaScript**: ES6+ features with polyfills

## 🚀 **Performance Optimization**

### **🎨 Laravel Performance**
- **🔄 Redis Caching**: Database query caching
- **⚡ Queue System**: Background job processing
- **🗜️ Asset Optimization**: CSS/JS minification and compression
- **📊 Database Indexing**: Optimized database queries

### **🔌 WordPress Plugin Performance**
- **⏳ Lazy Loading**: On-demand resource loading
- **🔄 Batched Processing**: Efficient large page handling
- **⚡ Throttled Observers**: Performance-optimized DOM scanning
- **💾 Cached Verification**: 30-second verification caching

## 🎯 **Key Achievements**

✅ **Complete Full-Stack Platform** - Laravel + Vue.js + WordPress integration
✅ **Modern Admin Dashboard** - Professional UI with real-time capabilities  
✅ **Centralized Domain Control** - Single source of truth for verification
✅ **Real-time Plugin Management** - Instant enable/disable from dashboard
✅ **Graceful Error Handling** - No fatal errors, professional degradation
✅ **Security Hardened** - JWT auth, CSRF protection, input validation
✅ **Performance Optimized** - Caching, lazy loading, efficient processing
✅ **Mobile Responsive** - Touch-friendly, mobile-first design
✅ **Production Ready** - Docker support, comprehensive documentation

## 📚 **Documentation & Support**

- **📖 API Documentation**: Complete API endpoint documentation
- **🎥 Video Tutorials**: Step-by-step setup and usage guides
- **🐛 Issue Tracking**: GitHub Issues for bug reports and features
- **💬 Community Support**: Discussion forums and chat support
- **📧 Professional Support**: Enterprise support available

## 🤝 **Contributing**

1. **Fork the Repository**
2. **Create Feature Branch**: `git checkout -b feature/amazing-feature`
3. **Commit Changes**: `git commit -m 'Add amazing feature'`
4. **Push to Branch**: `git push origin feature/amazing-feature`
5. **Open Pull Request**

## 📄 **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📞 **Support & Contact**

- **🌐 Website**: [hovervid.com](https://hovervid.com)
- **📧 Email**: support@hovervid.com
- **💬 Discord**: [HoverVid Community](https://discord.gg/hovervid)
- **🐛 Issues**: [GitHub Issues](https://github.com/your-username/hovervid/issues)

---

**🎯 HoverVid - Making the web accessible through sign language technology** 

*Empowering digital inclusion with modern full-stack solutions* 🚀
