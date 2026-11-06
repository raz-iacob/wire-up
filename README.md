<picture>
  <source media="(prefers-color-scheme: dark)" srcset="resources/images/logo-dark.svg">
  <source media="(prefers-color-scheme: light)" srcset="resources/images/logo-light.svg">
  <img alt="Wire Up Logo" src="resources/images/logo.svg" width="257" height="32">
</picture>

<br>

**Wire Up** is an open-source CMS built on Laravel and Livewire. You get a strict, fully tested app that updates itself. Your client gets an admin they can actually use — pages, products, members, languages — and stops calling you about copy changes.

### [wire-up.dev](https://wire-up.dev)

The live site is the fastest way to get a sense of what you are installing: a tour of the
[features](https://wire-up.dev/features), the [block catalogue](https://wire-up.dev/blocks), and
[twelve guides](https://wire-up.dev/guides) split between running a site and building one. It is
itself a Wire Up site, built through the admin and the MCP server rather than by editing templates.

---

### Key Features

- **Block-based page builder** - Compose pages from 20+ content blocks with a live desktop/tablet/mobile preview
- **Records & content types** - Define your own content types and fields in the admin, with public detail pages and categories
- **Media library** - Crops, SVG sanitising, HEIC conversion, Pexels import, signed image URLs with a transform cache
- **Design settings** - Colour tokens, light/dark schemes with per-theme logos, fonts, and site-wide custom CSS
- **Multi-language** - Per-locale publishing, owner-editable interface translations
- **Users, roles & members** - Per-action abilities, two-factor authentication, passkeys, member accounts and members-only content
- **AI-native** - An in-admin AI assistant plus an MCP server, so agents can build and edit the site programmatically
- **SEO & AI discoverability** - Meta, JSON-LD, sitemap, `llms.txt`, and Markdown responses for agents
- **Self-updating** - Install and update from git tags, with a database backup before every migration

## Built With

- **[Laravel 13](https://laravel.com)** - The PHP framework for web artisans
- **[Livewire 4](https://livewire.laravel.com)** - Full-stack framework for Laravel
- **[Flux UI Pro](https://fluxui.dev)** - Beautiful UI components for Livewire
- **[Tailwind CSS v4](https://tailwindcss.com)** - Utility-first CSS framework
- **[Pest 5](https://pestphp.com)** - Delightful PHP testing framework
- **[PHPStan + Larastan](https://phpstan.org)** - Static analysis for Laravel applications
- **[Rector](https://getrector.com)** - Automated refactoring and code quality checks
- **[Laravel Pint](https://laravel.com/docs/pint)** - Code style fixer
- **[Vite](https://vitejs.dev)** - Fast build tool and dev server

## Requirements

- PHP 8.5+
- Node.js 22+ (see `.nvmrc`)
- Composer 2.0+
- MySQL/PostgreSQL/SQLite
- PHP `gd` extension (image resizing/cropping)
- PHP `zip` extension (site export/import bundles)
- A **[Flux UI Pro](https://fluxui.dev)** license — the admin is built on it, so `composer install` needs your credentials in `auth.json` (see [Installation](#installation))
- _Optional:_ PHP `imagick` extension built with `libheif` — enables HEIC/HEIF uploads (converted to JPEG on upload). Without it, HEIC uploads are rejected with a friendly message; all other image formats are unaffected.

## Getting Started

### Installation

1. **Clone the repository**

    ```bash
    git clone https://github.com/wire-up-dev/wire-up.git
    cd wire-up
    ```

2. **Add your Flux UI Pro credentials** to `auth.json` (gitignored)

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

3. **Install PHP & Node.js dependencies and Environment setup**

    ```bash
    composer setup
    ```

## Testing

Wire Up uses Pest v5 for testing, including browser testing capabilities:

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

Requirements on the server: PHP 8.5, Composer, git. No database server is needed — Wire Up uses SQLite by default (MySQL and PostgreSQL are supported alternatives). Node.js 22+ is only needed when the frontend has to be built on the server: an install from a branch instead of a release, a release older than v0.8.0, or a fork that does not publish its own frontend assets.

1. Add a **deploy key** (read-only SSH key) to the GitHub repository and clone it:

    ```bash
    git clone git@github.com:wire-up-dev/wire-up.git
    cd wire-up
    git checkout "$(git describe --tags --abbrev=0)"
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

3. Create `.env` from `.env.example` with production values (`APP_ENV=production`, `APP_URL`). SQLite is preconfigured; to use MySQL/PostgreSQL instead, set the `DB_*` variables.

4. Install PHP dependencies and run the installer:

    ```bash
    composer install --no-dev
    php artisan wireup:install
    ```

    The installer generates the app key if missing, asks for the default site language, creates the SQLite database when needed, migrates, links storage, downloads the release's prebuilt frontend assets (or builds them with Node.js when the checkout is not a release), caches the app, and creates the first admin user.

5. Add the scheduler cron entry and a supervised queue worker:

    ```cron
    * * * * * cd /path/to/wire-up && php artisan schedule:run >> /dev/null 2>&1
    ```

    ```bash
    php artisan queue:work --tries=3
    ```

    The scheduler runs the daily checks and starts updates, backups and imports from the admin within a minute, each in a fresh process on the `system` queue. The queue worker sends e-mail and Slack notifications, webhooks, newsletter sign-ups and share images, all short jobs, so it needs no long timeout.

### Updating

Releases are git tags (`v0.x.y`) with matching sections in `CHANGELOG.md`. The scheduler checks for new releases daily (`php artisan wireup:check`); when one is available, the admin shows a badge on **Settings → Updates** with the release notes.

- **From the admin:** Settings → Updates → *Update now* (the scheduler starts it within a minute).
- **From the CLI:** `php artisan wireup:update` (options: `--tag=vX.Y.Z`, `--force`).
- **Automatic:** enable *Automatic updates* on Settings → Updates; the daily check then installs new releases unattended.

An update first downloads the release's prebuilt frontend assets (`build.tar.gz` on the GitHub Release), checks them against their published checksum and keeps a copy of the current ones. Then it puts the public site into maintenance mode (the admin stays reachable) and runs: **database backup** (`php artisan wireup:backup` → `storage/app/backups/db-backup-<date>-before-<tag>`, last 5 kept; MySQL needs `mysqldump` and `mysql` on the server, PostgreSQL needs `pg_dump` and `psql`), fetch + check out the tag, `composer install --no-dev`, `migrate --force`, swap in the new frontend assets, cache rebuild, `queue:restart`. A release without prebuilt assets is built on the server with `npm ci` + `npm run build` instead, and the update checks for Node.js before the site goes down. A new release is offered once its assets are published, a few minutes after the tag is pushed. If the download or the Node.js check fails, the site stays live.

**If a later step fails, the update rolls back on its own:** it checks out the commit it started from, reinstalls that version's PHP dependencies, restores the pre-update database backup when the migrations had started, puts the previous frontend assets back, rebuilds the caches, restarts the queue workers and brings the site back up. Settings → Updates and the email say which step failed. Anything changed in the admin while the update ran is lost when the database is restored. **If the rollback fails too, the site stays in maintenance mode** — review the error on Settings → Updates (or the console), fix it, then run `php artisan up`. To roll back by hand:

```bash
git checkout vPREVIOUS
composer install --no-dev
# restore the pre-update database backup if the migrations already ran:
php artisan wireup:backup:restore-database db-backup-<date>-before-vNEW.sqlite
# releases before v0.8.0 have no prebuilt assets: run npm ci && npm run build instead
rm -rf public/build && curl -fsSL https://github.com/wire-up-dev/wire-up/releases/download/vPREVIOUS/build.tar.gz | tar -xz -C public
php artisan optimize && php artisan up
```

`wireup:backup:restore-database` replaces the whole database with a `db-backup-*` file from `storage/app/backups` (`.sql` for MySQL and PostgreSQL, which it clears first, `.sqlite` for SQLite).

### Backups

`php artisan wireup:backup:create` writes `storage/app/backups/backup-<date>-manual.zip`: a dump of the database (`database.sql` or `database.sqlite`), the uploaded files from the media disk (`files/…`, when it is a local disk) and a `manifest.json`. Automatic backups are **off by default**; once switched on, the scheduler runs the same command with `--scheduled` at 03:00 (UTC) daily or weekly, keeps the newest automatic backups only (manual ones stay until deleted), refuses to start when the disk lacks room for another backup, and emails the contact address (and posts to Slack, if connected) when a run fails. Backups stay on the server, so download them to keep a copy elsewhere. They hold everything, including users and saved API keys, but not `.env` — keep your `APP_KEY` safe separately.

Restore one with:

```bash
php artisan wireup:backup:restore backup-<date>-automatic.zip
```

It puts the site in maintenance mode, loads the database (MySQL needs the `mysql` client, PostgreSQL needs `psql`), writes the uploaded files back, runs `migrate --force`, clears the cache and brings the site back up. It refuses a backup taken by a newer version of Wire Up or from a different database driver. If loading the database fails, the site stays in maintenance mode; fix the problem, then run `php artisan up`.

**Release routine:** add a `## vX.Y.Z` section to `CHANGELOG.md`, commit, tag (`git tag -a vX.Y.Z -m vX.Y.Z`), push with tags (`git push --follow-tags`). The `release` workflow then builds the frontend and publishes the GitHub Release with `build.tar.gz`, its checksum and that changelog section as the notes. Sites are offered the release once it is published.

### Moving a site

A whole site — content, media, settings, menus, translations — travels as a single zip bundle, from **Settings → Export / Import** in the admin or from the CLI:

```bash
php artisan wireup:export --path=/tmp/site.zip
```

```bash
php artisan wireup:import /tmp/site.zip --dry-run
```

API keys and mail credentials are left out unless you pass `--with-secrets`. An import **replaces** the current site, so use `--dry-run` first to see what a bundle holds; run `php artisan storage:link` afterwards if imported media does not appear.

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
- **Markdown for agents** - every published page and record also serves a clean Markdown
  representation of its content blocks from the same URL via content negotiation: requests with an
  `Accept: text/markdown` header (as sent by AI agents) get Markdown instead of HTML, cutting token
  noise while keeping canonical URLs.
- **`/robots.txt`** - dynamic; references the sitemap and reflects the indexing settings below.

**MCP server for AI agents:**

Wire Up ships a local [MCP](https://modelcontextprotocol.io) server so AI agents (Claude Code,
Cursor, and other MCP clients) can build and edit the site programmatically:

- Start it with `php artisan mcp:start wire-up`, or point your MCP client at that command; debug
  it interactively with `php artisan mcp:inspector wire-up`.
- The `block-types` resource documents every content block and its JSON shape. Tools cover the
  full site-building workflow: pages (`list-pages`, `get-page`, `create-page`,
  `update-page-blocks`, `publish-page`), media (`list-media`, `import-media-from-url`,
  `search-pexels`, `import-pexels-media`), site settings (`get-settings`, `update-design`,
  `update-identity`, `update-social`), and navigation (`get-menus`, `update-menu`). Agents can
  verify their work by fetching page URLs with an `Accept: text/markdown` header (see above).

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

- [Wire Up guides](https://wire-up.dev/guides) - Twelve guides covering both sides of the handover
- [Block catalogue](https://wire-up.dev/blocks) - Every block type, what it does and when to reach for it
- [AI and agents](https://wire-up.dev/ai-and-agents) - The MCP server and the in-admin assistant
- [Laravel Documentation](https://laravel.com/docs)
- [Livewire Documentation](https://livewire.laravel.com/docs)
- [Flux UI Documentation](https://fluxui.dev/docs)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)

## Built by Raz Iacob

Wire Up is built and maintained by [Raz Iacob](https://raziacob.com), a full-stack developer with more than 20 years of experience. If you'd like a Wire Up site built for you, an existing site moved off WordPress, or hosting and care for a site you already run, see [raziacob.com](https://raziacob.com).

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- [Laravel](https://laravel.com) team for the amazing framework
- [Livewire](https://livewire.laravel.com) team for making full-stack development delightful
- [Flux UI](https://fluxui.dev) for beautiful components
- [Tailwind CSS](https://tailwindcss.com) for excellent utility classes

---

Built with ❤️ using Laravel + Livewire by [Raz Iacob](https://raziacob.com)
