# Laravel Cookie Consent

A modern, lightweight, and fully self-contained **GDPR-ready cookie consent manager** for Laravel.  
Automatically detects, blocks, and manages analytics and marketing scripts with zero external dependencies.

---

## Features

- Automatic detection and blocking of tracking scripts  
- Dynamic cookie categories (necessary / statistics / marketing)  
- AJAX-based consent saving (no page reload)  
- Google Consent Mode v2 support  
- Cookie cleanup when consent is revoked  
- Re-openable floating icon  
- Artisan command for cache cleanup  
- Simple publish and setup process  
- Compatible with Laravel 10, 11, and 12+

---

## Installation

Install via Composer:

```
composer require taki47/laravel-cookie-consent
```

Publish assets:

```
php artisan vendor:publish --provider="Taki47\CookieConsent\CookieConsentServiceProvider" --tag=public
```

This will publish:
public/vendor/cookie-consent/


Before going live, scan your site so the banner can display cookie details (name, purpose, expiry) correctly:
```
php artisan cookie:scan
```
**IMPORTANT:** Before running the scan, make sure that APP_URL is set in your .env file to the website URL that the crawler needs to scan! Alternatively, you can pass the URL as an argument.

The `cookie:scan` command supports the following optional parameters:
* `{url? : (Optional) The website URL to scan, defaults to APP_URL}`
* `{--depth=2 : Crawl depth (default 2)}`
* `{--max-pages=100 : Maximum pages to crawl}`
* `{--fetch-external-js : Also fetch external JS files (may slow down)}`
* `{--delay=250 : Delay between requests in ms (politeness)}`


Include the consent banner in your base layout (for example layouts/app.blade.php):
```
@include('cookie-consent::banner')
```

Load the cookie script before any analytics scripts:
```
<script src="{{ asset('vendor/cookie-consent/js/cookie-consent.js') }}"></script>
```

## Artisan command
Clear cached cookie metadata and scan results:

```
php artisan cookie:clear-cache
```

Use --force to skip confirmation:

```
php artisan cookie:clear-cache --force
```

## How it works
Automatic script blocking
Known analytics and marketing scripts are detected and converted to
```<script type="text/plain">``` before execution.

User consent handling
User preferences are saved via AJAX to the cookie-consent.store route.

Re-enabling approved scripts
Scripts belonging to approved categories (e.g., statistics, marketing)
are dynamically reactivated without page reload.

Consent updates and cleanup
When a user revokes consent, the package deletes related cookies and
updates Google Consent Mode accordingly.

## Contributing
Pull requests are welcome!
Please follow PSR-12 coding standards and use English comments only.

## License
Released under the MIT License.
© 2025 Lajos Takacs <takiwebneked.hu>

## Support
If you find this package useful, please give it a star on GitHub ⭐
Your support helps keep the project open and actively maintained.