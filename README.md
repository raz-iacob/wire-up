<picture>
  <source media="(prefers-color-scheme: dark)" srcset="resources/images/logo-dark.svg">
  <source media="(prefers-color-scheme: light)" srcset="resources/images/logo-light.svg">
  <img alt="Wire-Up Logo" src="resources/images/logo.svg" width="172" height="32">
</picture>

<br>

**Wire-Up** is an opinionated, strict and fully tested Livewire Laravel Starter Kit.

---

### Key Features

- **Multi-language Support** - Easily build applications with localization and translations
- **Real-time Interactivity** - Dynamic content updates without page refreshes
- **Server-side Rendering** - Fast initial page loads with SEO-friendly content
- **Modern UI Components** - Built with Flux UI Pro for beautiful, accessible interfaces
- **Developer Experience** - Hot reloading, type-safe code, and comprehensive testing
- **Responsive Design** - Mobile-first approach with Tailwind CSS
- **Security First** - Laravel's built-in security features and best practices

## Built With

- **[Laravel 13](https://laravel.com)** - The PHP framework for web artisans
- **[Livewire 4](https://livewire.laravel.com)** - Full-stack framework for Laravel
- **[Flux UI Pro](https://fluxui.dev)** - Beautiful UI components for Livewire
- **[Tailwind CSS v4](https://tailwindcss.com)** - Utility-first CSS framework
- **[Pest 4](https://pestphp.com)** - Delightful PHP testing framework
- **[PHPStan + PestStan](https://phpstan.org)** - Static analysis for application and Pest tests
- **[Rector](https://getrector.com)** - Automated refactoring and code quality checks
- **[Laravel Pint](https://laravel.com/docs/pint)** - Code style fixer
- **[Vite](https://vitejs.dev)** - Fast build tool and dev server

## Requirements

- PHP 8.5+
- Node.js 22+ (see `.nvmrc`)
- Composer 2.0+
- MySQL/PostgreSQL/SQLite
- PHP `gd` extension (image resizing/cropping)
- _Optional:_ PHP `imagick` extension built with `libheif` — enables HEIC/HEIF uploads (converted to JPEG on upload). Without it, HEIC uploads are rejected with a friendly message; all other image formats are unaffected.

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

## Testing

Wire-Up uses Pest v4 for testing, including browser testing capabilities:

```bash
# Run the full quality pipeline (type coverage, tests, lint, static analysis)
composer test

# Run just the Pest test suite
composer test:unit

# Run static analysis only
composer test:types
```

## Code Style

The project uses Laravel Pint + Prettier for formatting:

```bash
# Fix code style issues
vendor/bin/pint
npm run lint

# Check for style issues without fixing
composer test:lint
```

## Deployment

### Server installation

Requirements on the server: PHP 8.5, Composer, Node.js 22+, git, MySQL.

1. Add a **deploy key** (read-only SSH key) to the GitHub repository and clone it:

    ```bash
    git clone git@github.com:raz-iacob/wire-up.git
    cd wire-up
    ```

2. Place your Flux UI Pro credentials in `auth.json` (gitignored):

    ```json
    {
        "http-basic": {
            "composer.fluxui.dev": {
                "username": "your-email",
                "password": "your-license-key"
            }
        }
    }
    ```

3. Create `.env` from `.env.example` with production values (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, MySQL credentials).

4. Install PHP dependencies and run the installer:

    ```bash
    composer install --no-dev
    php artisan wireup:install
    ```

    The installer generates the app key if missing, migrates the database, links storage, builds the frontend, caches the app, and creates the first admin user.

5. Add the scheduler cron entry and a supervised queue worker (required for e-mail/Slack notifications and admin-triggered updates):

    ```cron
    * * * * * cd /path/to/wire-up && php artisan schedule:run >> /dev/null 2>&1
    ```

    ```bash
    php artisan queue:work --tries=3 --timeout=3600
    ```

    Keep `DB_QUEUE_RETRY_AFTER=3700` in `.env` (it must exceed the worker `--timeout`, or a long-running update job gets re-dispatched mid-run).

### Updating

Releases are git tags (`v0.x.y`) with matching sections in `CHANGELOG.md`. The scheduler checks for new releases daily (`php artisan wireup:check`); when one is available, the admin shows a badge on **Settings → Updates** with the release notes.

- **From the admin:** Settings → Updates → *Update now* (runs as a queued job — requires the queue worker above).
- **From the CLI:** `php artisan wireup:update` (options: `--tag=vX.Y.Z`, `--force`).
- **Automatic:** enable *Automatic updates* on Settings → Updates; the daily check then installs new releases unattended.

An update puts the public site into maintenance mode (the admin stays reachable), then: fetch + check out the tag, `composer install --no-dev`, `migrate --force`, `npm ci` + build, cache rebuild, `queue:restart`, and back up. **If a step fails, the site stays in maintenance mode** — review the error on Settings → Updates (or the console), fix it, then run `php artisan up`. Roll back with:

```bash
git checkout vPREVIOUS
composer install --no-dev && php artisan migrate --force
npm ci && npm run build && php artisan optimize && php artisan up
```

**Release routine:** add a `## vX.Y.Z` section to `CHANGELOG.md`, commit, tag (`git tag vX.Y.Z`), push with tags.

> **Pexels media library integration:** Add a free Pexels API key (from [pexels.com/api](https://www.pexels.com/api/)) under **Settings → Integrations** in the admin to enable the integration. Editors can then search Pexels photos and videos directly from the media picker and import them into the library. When no key is set, the Pexels option is hidden. Per the [Pexels API Guidelines](https://www.pexels.com/api/documentation/#guidelines), photographer attribution and a link back to Pexels are shown in the picker, and the photographer/source details are stored with each imported asset (in `media.metadata`) so credit can be surfaced wherever the media is used.

## SEO & AI discoverability

Every public page renders a full discovery layer from the head component, and the site exposes
machine-readable resources for crawlers and AI agents:

- **Social & search meta** - canonical URL, Open Graph, Twitter cards, `hreflang` alternates (for
  multi-locale sites), `theme-color`, and JSON-LD structured data (`Organization`, `WebSite`,
  `WebPage`, `BreadcrumbList`). Descriptions fall back to a clean excerpt derived from a page's
  content blocks when no description is set.
- **Block-level structured data** - Location, Team, Pricing, Video, Audio, and Gallery blocks emit
  matching Schema.org types (`LocalBusiness`, `Person`, `Offer`, `VideoObject`, `AudioObject`,
  `ImageObject`) into the page's JSON-LD.
- **Share images** - each page's Open Graph image is used when set; otherwise the **Default share
  image** from **Settings → Identity** is used.
- **`/sitemap.xml`** - dynamically lists published pages across their published locales with
  `lastmod` and `hreflang` alternates.
- **`/llms.txt`** - a Markdown index of published pages (title, URL, description) for AI crawlers.
- **`/llms-full.txt`** - the full plain-text content of every published page.
- **`/robots.txt`** - dynamic; references the sitemap and reflects the indexing settings below.

**Controlling indexing:**

- **Settings → Identity** has a *Discourage search engines* toggle. When on, every page gets a
  `noindex` robots tag, `robots.txt` returns `Disallow: /`, images send an `X-Robots-Tag` header,
  and `/sitemap.xml` + `/llms.txt` are emptied.
- Each page's **SEO Settings** has its own *Discourage search engines from indexing this page*
  toggle, which adds `noindex` to that page only and drops it from the sitemap.

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
