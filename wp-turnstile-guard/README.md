# WP Turnstile Guard

Adds **Cloudflare Turnstile** protection to:
- WordPress **login**
- **Registration**
- **Comments**

Lightweight settings page, minimal footprint. Only loads the Turnstile script where needed.

## Setup
1. Get **Site Key** and **Secret Key** from Cloudflare → Turnstile.
2. Install & activate the plugin.
3. Go to **Settings → Turnstile Guard** and paste your keys.
4. Choose where to enable (Login, Registration, Comments).

## Notes
- Works with WP 6.0+, PHP 7.4+.
- Enqueues Turnstile script only on relevant screens.
- Uses server-side verification against `https://challenges.cloudflare.com/turnstile/v0/siteverify`.

## Filters
You can extend or modify behavior using standard WP hooks if needed.

## License
GPL-2.0-or-later
