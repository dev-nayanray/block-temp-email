# Block Temporary Email (BTE) WordPress Plugin

## Description

Block Temporary Email (BTE) is a WordPress multisite plugin that prevents users from registering or checking out with disposable or temporary email addresses. It supports blocking on WordPress registration, WooCommerce checkout, and popular form plugins like Contact Form 7, WPForms, and Fluent Forms.

The plugin automatically fetches and updates a comprehensive blocklist of disposable email domains from a remote GitHub source weekly via WP-Cron. It also allows manual updates and admin management of blocklist and whitelist domains.

## Features

- Block disposable/temporary email domains on:
  - WordPress user registration
  - WooCommerce checkout
  - Contact Form 7, WPForms, Fluent Forms submissions
- Full multisite support with network admin control
- Admin settings page under **Settings > Block Temp Emails**
- Enable/disable blocking per context (registration, checkout, each form plugin)
- Add/remove domains to blocklist or whitelist
- Customize error message shown when blocking emails
- Enable admin email notifications on blocked attempts
- Role-based bypass for selected user roles (Admins, Editors, etc.)
- Analytics dashboard showing blocked email attempts over time
- Logging system tracking blocked attempts (timestamp, IP, email, source)
- WP-Cron job for weekly auto-update of blocklist from GitHub
- Placeholder for AI pattern detection (Pro version concept)
- Clean, secure, and i18n-ready code following WordPress coding standards

## Installation

1. Upload the `block-temp-email` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. For multisite, activate the plugin network-wide via the Network Admin Plugins page.
4. Navigate to **Settings > Block Temp Emails** to configure plugin settings.
5. Optionally, click **Update Blocklist Now** to fetch the latest disposable email domains immediately.

## Usage

- Enable or disable blocking for registration, WooCommerce checkout, and supported form plugins.
- Manage blocklist and whitelist domains from the admin settings page.
- Customize the error message displayed to users when their email is blocked.
- Enable admin notifications to receive emails when a disposable email attempt is blocked.
- Select user roles that should bypass email blocking.
- View analytics and recent blocked attempts in the admin dashboard.

## Development

- The plugin uses a local blocklist file (`includes/blocklist.txt`) with 4000+ disposable domains.
- It fetches and updates a remote blocklist weekly from [disposable-email-domains GitHub](https://github.com/disposable-email-domains/disposable-email-domains).
- Integrates with WordPress core, WooCommerce, Contact Form 7, WPForms, and Fluent Forms.
- Logging and notifications are implemented for monitoring blocked attempts.
- Fully supports WordPress multisite installations.

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- WooCommerce (optional, for checkout blocking)
- Contact Form 7, WPForms, Fluent Forms (optional, for form blocking)

## Changelog

### 1.0.0
- Initial release with core blocking, multisite support, admin panel, logging, notifications, and integrations.

## License

GPL v2 or later

## Support

For support, please open an issue on the plugin repository or contact the author.

## Author

NAyan Ray  
https://example.com
