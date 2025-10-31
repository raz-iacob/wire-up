![Wire-Up Logo](resources/images/logo.svg)

Wire-Up is a powerful web framework that combines the robustness of Laravel with the reactive capabilities of Livewire to create dynamic websites without the complexity of traditional JavaScript frameworks. Built for developers who want to create modern web applications with server-side rendering while maintaining rich client-side interactions.

---

### Key Features

- **Real-time Interactivity** - Dynamic content updates without page refreshes
- **Server-side Rendering** - Fast initial page loads with SEO-friendly content
- **Modern UI Components** - Built with Flux UI Pro for beautiful, accessible interfaces
- **Developer Experience** - Hot reloading, type-safe code, and comprehensive testing
- **Responsive Design** - Mobile-first approach with Tailwind CSS
- **Security First** - Laravel's built-in security features and best practices

## Built With

- **[Laravel 12](https://laravel.com)** - The PHP framework for web artisans
- **[Livewire 4](https://livewire.laravel.com)** - Full-stack framework for Laravel
- **[Flux UI Pro](https://fluxui.dev)** - Beautiful UI components for Livewire
- **[Tailwind CSS v4](https://tailwindcss.com)** - Utility-first CSS framework
- **[Pest 4](https://pestphp.com)** - Delightful PHP testing framework
- **[Laravel Pint](https://laravel.com/docs/pint)** - Code style fixer
- **[Vite](https://vitejs.dev)** - Fast build tool and dev server

## Requirements

- PHP 8.4+
- Node.js 18+
- Composer 2.0+
- MySQL/PostgreSQL/SQLite

## Getting Started

### Installation

1. **Clone the repository**

    ```bash
    git clone https://github.com/your-username/wire-up.git
    cd wire-up
    ```

2. **Install PHP & Node.js dependencies and Environment setup**

    ```bash
    composer setup
    ```

## Project Structure

```
wire-up/
├── app/                   # Application logic
│   ├── Actions/           # Action classes (business logic)
│   ├── Enums/             # Application enums
│   ├── Http/
│   │   ├── Controllers/   # HTTP controllers
│   │   └── Requests/      # Form request validation
│   ├── Livewire/          # Livewire components
│   ├── Models/            # Eloquent models
│   ├── Providers/         # Service providers
│   └── Services/          # Business logic services
├── bootstrap/             # Application bootstrapping
├── config/                # Configuration files
├── database/
│   ├── factories/         # Model factories
│   ├── migrations/        # Database migrations
│   └── seeders/           # Database seeders
├── resources/
│   ├── css/               # Stylesheets
│   ├── images/            # Images and logos
│   ├── js/                # JavaScript files
│   └── views/             # Blade templates
├── routes/                # Application routes
├── tests/                 # Test suites
│   ├── Feature/           # Feature tests
│   ├── Unit/              # Unit tests
│   └── Browser/           # Browser tests (Pest v4)
└── public/                # Web-accessible files
```

## Testing

Wire-Up uses Pest v4 for testing, including browser testing capabilities:

```bash
# Run the test suit
composer test
```

## Code Style

The project uses Laravel Pint for code formatting:

```bash
# Fix code style issues
vendor/bin/pint

# Check for style issues without fixing
vendor/bin/pint --test
```

## Deployment

### Production Build

```bash
# Build assets for production
npm run build

# Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Environment Configuration

Ensure these environment variables are set in production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database configuration
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password

# Queue configuration (recommended)
QUEUE_CONNECTION=redis
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

- Follow Laravel conventions and best practices
- Write tests for new features
- Use meaningful commit messages
- Ensure code passes all tests and style checks

## Documentation

- [Laravel Documentation](https://laravel.com/docs)
- [Livewire Documentation](https://livewire.laravel.com/docs)
- [Flux UI Documentation](https://fluxui.dev/docs)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- [Laravel](https://laravel.com) team for the amazing framework
- [Livewire](https://livewire.laravel.com) team for making full-stack development delightful
- [Flux UI](https://fluxui.dev) for beautiful components
- [Tailwind CSS](https://tailwindcss.com) for excellent utility classes

---

Built with ❤️ using Laravel + Livewire
