# Hovervid WordPress Plugin

A powerful WordPress plugin for managing video content with domain-based licensing and real-time verification.

## Features

- Domain-based licensing system
- Real-time domain verification
- Video player integration
- Text processing capabilities
- Session management
- User authentication
- Admin dashboard
- API endpoints for plugin management

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- PostgreSQL database
- Modern web browser with JavaScript enabled

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel > Plugins > Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Activate the plugin
5. Configure the plugin settings in the admin dashboard

## Configuration

1. Set up your PostgreSQL database
2. Configure the plugin settings in WordPress admin
3. Add your domain for verification
4. Set up API keys and endpoints

## Development

### Local Development

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   npm install
   ```
3. Set up your environment variables
4. Run migrations:
   ```bash
   php artisan migrate
   ```
5. Start the development server:
   ```bash
   php artisan serve
   npm run dev
   ```

### Testing

Run the test suite:
```bash
php artisan test
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please open an issue in the GitHub repository or contact the development team.
