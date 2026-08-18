# RecordStore

RecordStore is a self-hosted digital music store for selling downloadable tracks, previews and releases from a standard PHP/MySQL or MariaDB server.

## Product features

- Track, artist, genre and release catalogue management
- Featured tracks, new releases, charts and search
- SEO-friendly routes, canonical metadata, Open Graph and social sharing
- Audio previews with waveform playback, seeking and responsive desktop/mobile controls
- Asynchronous basket and favourites/wishlist actions
- PayPal checkout, order records, receipts and protected digital downloads
- Download limits, download-again support and customer purchase history
- Discount codes, user-locked promotions, genre sales and multi-track offers
- Customer verification, account disabling/banning and audit logging
- Transactional email, marketing emails, HTML templates and delivery logs
- Newsletter signup, release announcements and abandoned-basket reminders
- Admin reporting for revenue, orders, users, downloads and popular tracks
- Site customisation for colours, fonts, branding and fallback artwork
- Installer with database setup, private-storage detection and installation state
- Database-backed configuration, automated backups, health checks and deployment safeguards

## Installation

Upload the release package to a PHP-enabled web server, open the site in a browser, and follow `install.php`. The installer creates the database schema, configures storage and creates the first administrator account.

For a new installation, upload the package to the document root and open the site in a browser. The installer detects whether private storage can be placed outside the web root and provides a protected in-web-root fallback where necessary.

## Package contents

- `public/` — public web application
- `private/` — private application code, configuration template, database schema and protected masters

The package contains no deployment credentials, SSH automation or hosting-panel-specific scripts. Configure the database and application settings through the installer.
