<?php

/**
 * Class CookieScanCommand
 *
 * This Artisan command crawls a given website and scans for cookies.
 * It respects robots.txt rules, preserves port numbers for local environments,
 * categorizes detected cookies, and fetches additional metadata from the
 * CookieDatabase.org API if available.
 *
 * The results are saved in two JSON files:
 *  - storage/app/cookie-scan.json        → Full cookie summary
 *  - storage/app/cookie-info-cache.json  → Cached cookie metadata
 *
 * Author: Takács Lajos (takiwebneked.hu)
 */

namespace Taki47\CookieConsent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CookieScanCommand extends Command
{
    /** @var string The command signature */
    protected $signature = 'cookie:scan 
        {url? : (Optional) The website URL to scan, defaults to APP_URL} 
        {--depth=2 : Crawl depth (default 2)} 
        {--max-pages=100 : Maximum pages to crawl} 
        {--fetch-external-js : Also fetch external JS files (may slow down)} 
        {--delay=250 : Delay between requests in ms (politeness)}';

    /** @var string Command description */
    protected $description = 'Crawl a site (respecting robots.txt), detect cookies and known providers, save results to storage/app/cookie-scan.json';

    /** @var array Robots.txt rules (disallow/allow) */
    protected array $robotsDisallow = [];
    protected array $robotsAllow = [];

    /** @var array Cookie names to skip when fetching from remote API */
    protected array $skipRemoteFor = [
        'xsrf-token',
        'csrf-token',
        'laravel-session',
        'session',
    ];

    /**
     * @var array
     * Known provider patterns mapped to their typical cookie names.
     *
     * The crawler searches the page HTML for these provider URLs or script references.
     * If a match is found, the related cookies are assumed to be used on that page.
     *
     * This helps identify common analytics and marketing cookies (e.g. Google Analytics, Facebook, Hotjar, etc.)
     * even if they are not yet set in the user's browser.
     */
    protected array $providerPatterns = [
        'googletagmanager.com/gtag' => ['_ga', '_gid', '_gat'],
        'analytics.js' => ['_ga', '_gid'],
        'googletagmanager.com/gtm.js' => ['_ga', '_gid', '_gat', '_gcl_au'],
        'connect.facebook.net' => ['_fbp', '_fbc'],
        'static.hotjar.com' => ['_hjSessionUser', '_hjIncludedInSample'],
        'youtube.com/embed' => ['YSC', 'VISITOR_INFO1_LIVE'],
        'matomo.js' => ['_pk_id','_pk_ses'],
        'piwik.js' => ['_pk_id','_pk_ses'],
        'analytics.tiktok.com' => ['_ttp'],
    ];

    /**
     * Main entry point for the cookie scanner command.
     *
     * This method performs a recursive crawl of the specified website (or APP_URL by default),
     * respecting robots.txt rules, following internal links, and collecting cookies from
     * HTTP headers, inline JavaScript, and embedded third-party scripts or iframes.
     *
     * Detected cookies are categorized, optionally enriched via the CookieDatabase API,
     * and written into structured JSON reports under the /storage/app directory.
     */
    public function handle()
    {
        // Determine the target URL
        $url = $this->argument('url') ?? config('app.url');

        if (!$url) {
            $this->error('Missing URL and no APP_URL set in .env');
            return 1;
        }

        // Read command options
        $depthLimit = (int) $this->option('depth');
        $maxPages = (int) $this->option('max-pages');
        $fetchExternalJs = (bool) $this->option('fetch-external-js');
        $delayMs = (int) $this->option('delay');

        // Validate base URL
        $parsedBase = parse_url($url);
        if (!isset($parsedBase['host'])) {
            $this->error("Invalid base URL: $url");
            return 1;
        }

        $baseHost = $parsedBase['host'];
        $baseScheme = $parsedBase['scheme'] ?? 'https';

        // Log initial crawl settings
        $this->info("Starting crawl: {$url}");
        $this->info("Depth: {$depthLimit}, Max pages: {$maxPages}, JS fetch: " . ($fetchExternalJs ? 'on' : 'off'));
        $this->info("Checking robots.txt rules...");

        // Parse robots.txt with port awareness
        $port = isset($parsedBase['port']) ? ':' . $parsedBase['port'] : '';
        $this->parseRobotsTxt("{$baseScheme}://{$baseHost}{$port}/robots.txt");

        // Display allowed/disallowed crawl rules
        if (empty($this->robotsDisallow)) {
            $this->info("No disallow rules found or robots.txt missing — full crawl allowed.");
        } else {
            $this->warn("Found " . count($this->robotsDisallow) . " disallow rule(s). Respecting them during crawl.");
        }

        // --- Initialize crawl structures
        $queue = [];
        $visited = [];
        $results = [
            'pages' => [],
            'cookies' => [],
            'robots' => [
                'allow' => $this->robotsAllow,
                'disallow' => $this->robotsDisallow,
            ],
        ];

        // Closure to enqueue URLs for later crawl
        $enqueue = function ($u, $d) use (&$queue, &$visited) {
            $key = (string) Str::of($u)->before('#');
            if (!isset($visited[$key])) {
                $queue[] = ['url' => $key, 'depth' => $d];
                $visited[$key] = false;
            }
        };

        // Add initial URL to the queue
        $enqueue($this->normalizeUrl($url, $baseScheme), 0);

        // --- Crawl loop
        while (!empty($queue) && count(array_filter($visited, fn($v) => $v === true)) < $maxPages) {
            $item = array_shift($queue);
            $pageUrl = $item['url'];
            $depth = $item['depth'];

            // Skip already visited or disallowed URLs
            if ($visited[$pageUrl] === true || !$this->isAllowedByRobots($pageUrl)) {
                continue;
            }

            $this->line("→ Fetching [depth {$depth}] {$pageUrl}");
            try {
                // Perform HTTP GET request
                $response = Http::withHeaders([
                    'User-Agent' => 'CookieScanner/1.0 (+https://example.com)'
                ])->get($pageUrl);

                // Optional delay between requests
                usleep($delayMs * 1000);
            } catch (\Throwable $e) {
                $this->warn("Request failed: " . $e->getMessage());
                $visited[$pageUrl] = true;
                continue;
            }

            // Store per-page metadata
            $pageMeta = [
                'status' => $response->status(),
                'set_cookies' => [],
                'detected_providers' => [],
            ];

            // --- Parse cookies from response headers
            $headers = $response->headers();
            if (isset($headers['Set-Cookie'])) {
                foreach ($headers['Set-Cookie'] as $line) {
                    $cookieName = explode('=', $line, 2)[0];
                    $pageMeta['set_cookies'][] = $cookieName;
                    $this->recordCookie($results, $cookieName, $pageUrl, 'header');
                }
            }

            // --- Parse inline JavaScript cookie assignments
            $html = (string) $response->body();
            preg_match_all('/document\.cookie\s*=\s*[\'"]([^\'"]+)[\'"]/i', $html, $inlineMatches);
            if (!empty($inlineMatches[1])) {
                foreach ($inlineMatches[1] as $cstr) {
                    $name = explode('=', $cstr, 2)[0];
                    $pageMeta['set_cookies'][] = $name;
                    $this->recordCookie($results, $name, $pageUrl, 'inline-js');
                }
            }

            // --- Parse external <script> and <iframe> elements
            libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            if (@$doc->loadHTML($html)) {
                $xpath = new \DOMXPath($doc);

                // Detect known provider patterns
                foreach ($xpath->query('//script[@src] | //iframe[@src]') as $node) {
                    $src = $node->getAttribute('src');
                    $abs = $this->resolveUrl($src, $pageUrl);
                    foreach ($this->providerPatterns as $pattern => $cookieNames) {
                        if (str_contains($abs, $pattern)) {
                            foreach ($cookieNames as $cn) {
                                $this->recordCookie($results, $cn, $pageUrl, "script:$pattern");
                                $pageMeta['detected_providers'][] = $pattern;
                            }
                        }
                    }
                }

                // --- Extract internal links for further crawling
                foreach ($xpath->query('//a[@href]') as $a) {
                    $href = $a->getAttribute('href');
                    $abs = $this->resolveUrl($href, $pageUrl);
                    $p = parse_url($abs);
                    if (!$p || !isset($p['host']) || $p['host'] !== $baseHost) continue;
                    if (!$this->isAllowedByRobots($abs)) continue;
                    if ($depth + 1 <= $depthLimit) {
                        $enqueue($abs, $depth + 1);
                    }
                }
            }

            // Mark page as visited and store its metadata
            $results['pages'][$pageUrl] = $pageMeta;
            $visited[$pageUrl] = true;
        }

        // --- Post-processing: build final cookie summary
        $this->info('🔧 Generating cookie summary...');
        $cache = $this->loadCookieInfoCache();

        $cookieList = [];
        foreach ($results['cookies'] as $name => $meta) {
            $norm = $this->norm($name);

            $info = $cache[$norm] ?? $this->fetchCookieInfo($name);
            if ($info) {
                if (empty($info['category'])) {
                    $info['category'] = $this->guessCategory($name, $meta['detected_by']);
                }
                $cache[$norm] = $info;
            } else {
                $info = [
                    'name' => $name,
                    'provider' => null,
                    'description' => null,
                    'category' => $this->guessCategory($name, $meta['detected_by']),
                    'expiry' => null,
                    'url' => null,
                    'last_checked' => now()->toIso8601String(),
                ];
            }

            $cookieList[] = [
                'name' => $name,
                'category' => $info['category'] ?? 'unclassified',
                'provider' => $info['provider'] ?? null,
                'description' => $info['description'] ?? null,
                'expiry' => $info['expiry'] ?? null,
                'url' => $info['url'] ?? null,
                'detected_by' => $meta['detected_by'],
                'sources' => $meta['sources'],
            ];
        }

        // --- Save reports
        Storage::disk('local')->put('cookie-scan.json', json_encode([
            'generated_at' => now()->toIso8601String(),
            'source' => $url,
            'cookies' => $cookieList,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Storage::disk('local')->put('cookie-info-cache.json', json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Summary with online data saved to storage/app/cookie-scan.json');
        $this->info('Total unique cookies: ' . count($cookieList));
    }

    /**
     * Fetches and parses the robots.txt file for the target domain.
     *
     * This method retrieves and processes the site's robots.txt rules, respecting
     * both `Disallow` and `Allow` directives. It ensures the crawler operates only
     * in permitted areas, according to the site’s declared policies.
     *
     * Features:
     *  - Ignores commented and empty lines
     *  - Respects both global (*) and custom "CookieScanner" user-agent sections
     *  - Handles SSL chain issues gracefully (verify=false for local/LE certs)
     *  - Supports custom ports and returns cleanly if robots.txt is missing or empty
     *
     * @param string $robotsUrl The absolute URL to the robots.txt file.
     */
    protected function parseRobotsTxt(string $robotsUrl)
    {
        try {
            // Fetch the robots.txt file with relaxed SSL verification (for local/dev)
            $resp = Http::withOptions([
                    'allow_redirects' => true,
                    'verify' => false, // allows Let's Encrypt or local SSL issues
                    'timeout' => 15,
                ])
                ->withHeaders([
                    'User-Agent' => 'CookieScanner/1.0 (+https://takiwebneked.hu)',
                ])
                ->get($robotsUrl);

            // Handle missing or failed responses
            if (!$resp->ok()) {
                $this->warn("robots.txt not found (HTTP {$resp->status()})");
                return;
            }

            $body = trim($resp->body());
            if ($body === '') {
                $this->warn("robots.txt request returned empty body (possibly blocked by firewall)");
                return;
            }

            // Split and process file line by line
            $lines = explode("\n", $body);
            $inSection = false;

            foreach ($lines as $line) {
                $line = trim($line);

                // Skip comments and empty lines
                if ($line === '' || str_starts_with($line, '#')) continue;

                // Detect user-agent blocks
                if (stripos($line, 'User-agent:') === 0) {
                    $agent = trim(substr($line, strlen('User-agent:')));
                    $inSection = ($agent === '*' || $agent === 'CookieScanner');
                    continue;
                }

                // Parse Allow / Disallow rules within active section
                if ($inSection) {
                    if (stripos($line, 'Disallow:') === 0) {
                        $path = trim(substr($line, strlen('Disallow:')));
                        if ($path !== '') $this->robotsDisallow[] = $path;
                    } elseif (stripos($line, 'Allow:') === 0) {
                        $path = trim(substr($line, strlen('Allow:')));
                        if ($path !== '') $this->robotsAllow[] = $path;
                    }
                }
            }

            // Log summary
            $this->info("robots.txt successfully parsed (" . count($this->robotsDisallow) . " disallow rule(s))");

        } catch (\Throwable $e) {
            // Gracefully handle HTTP and network-level exceptions
            $this->warn("Could not fetch robots.txt: " . $e->getMessage());
        }
    }

    /**
     * Checks whether a given URL is allowed to be crawled according to robots.txt rules.
     *
     * This method compares the URL’s path component against the previously parsed
     * `Allow` and `Disallow` directives from robots.txt.
     *
     * Evaluation order:
     *  1. If the path matches any "Allow" rule → crawl is permitted.
     *  2. Otherwise, if it matches any "Disallow" rule → crawl is denied.
     *  3. If no match is found → crawl is permitted by default.
     *
     * @param  string  $url  The absolute URL to check.
     * @return bool          True if crawling is allowed, false if disallowed.
     */
    protected function isAllowedByRobots(string $url): bool
    {
        // Extract the path component (defaults to root "/")
        $path = parse_url($url, PHP_URL_PATH) ?? '/';

        // Explicitly allowed paths take priority
        foreach ($this->robotsAllow as $allow) {
            if (Str::startsWith($path, $allow)) return true;
        }

        // Denied paths override default behavior
        foreach ($this->robotsDisallow as $dis) {
            if (Str::startsWith($path, $dis)) return false;
        }

        // Default: allowed if no matching rules were found
        return true;
    }

    /**
     * Normalizes a given URL to ensure it is absolute and properly formatted.
     *
     * This method ensures that each URL has a valid scheme (e.g. http/https),
     * correctly handles protocol-relative URLs (starting with `//`),
     * and applies a default scheme when missing.
     *
     * Examples:
     *  - `example.com` → `https://example.com`
     *  - `//cdn.example.com/lib.js` → `https://cdn.example.com/lib.js`
     *  - `https://example.com` → unchanged
     *
     * @param  string  $u               The URL to normalize.
     * @param  string  $defaultScheme   The default scheme to apply if missing (default: https).
     * @return string                   The normalized absolute URL.
     */
    protected function normalizeUrl(string $u, string $defaultScheme = 'https'): string
    {
        $u = trim($u);
        $p = parse_url($u);

        // If URL cannot be parsed, return as-is
        if ($p === false) return $u;

        // If missing scheme, apply default or handle protocol-relative URLs
        if (!isset($p['scheme'])) {
            if (Str::startsWith($u, '//')) return 'https:' . $u;
            return $defaultScheme . '://' . ltrim($u, '/');
        }

        // Otherwise, return unchanged
        return $u;
    }

    /**
     * Resolves a relative or partial URL into an absolute URL using a given base.
     *
     * This method ensures that all discovered links (e.g. from <a>, <script>, or <iframe>)
     * are converted into full, crawlable URLs. It handles relative paths, protocol-relative
     * links (starting with `//`), and fully qualified URLs gracefully.
     *
     * Examples:
     *  - `about` + `https://example.com` → `https://example.com/about`
     *  - `//cdn.example.com/lib.js` + `https://example.com` → `https://cdn.example.com/lib.js`
     *  - `https://other.com/page` → unchanged
     *
     * @param  string  $url   The URL to resolve (may be relative or absolute).
     * @param  string  $base  The base URL to resolve against.
     * @return string         The absolute, fully qualified URL.
     */
    protected function resolveUrl(string $url, string $base): string
    {
        // If URL already contains a scheme (absolute URL), return it as-is
        $p = parse_url($url);
        if ($p !== false && isset($p['scheme'])) return $url;

        // Handle protocol-relative URLs (e.g., //cdn.example.com)
        if (Str::startsWith($url, '//')) {
            $baseScheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return $baseScheme . ':' . $url;
        }

        // Default: resolve relative path against base
        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Records a detected cookie into the global results array.
     *
     * This helper method ensures that each cookie is only registered once,
     * while keeping track of all pages and detection methods (sources) where
     * the cookie was encountered.
     *
     * Example:
     *  If the same cookie appears on multiple pages or is set both by
     *  headers and inline JavaScript, all of those occurrences are merged
     *  under one cookie entry.
     *
     * @param  array   &$results     Reference to the global crawl results array.
     * @param  string  $cookieName   The name of the detected cookie.
     * @param  string  $pageUrl      The page URL where the cookie was found.
     * @param  string  $detectedBy   The detection source (e.g., header, inline-js, script:provider).
     * @return void
     */
    protected function recordCookie(array &$results, string $cookieName, string $pageUrl, string $detectedBy)
    {
        $cookieName = trim($cookieName);
        if ($cookieName === '') return;

        // Initialize new cookie entry if not yet present
        if (!isset($results['cookies'][$cookieName])) {
            $results['cookies'][$cookieName] = ['sources' => [], 'detected_by' => []];
        }

        // Record the page where this cookie was seen
        if (!in_array($pageUrl, $results['cookies'][$cookieName]['sources'], true)) {
            $results['cookies'][$cookieName]['sources'][] = $pageUrl;
        }

        // Record how this cookie was detected (header, JS, etc.)
        if (!in_array($detectedBy, $results['cookies'][$cookieName]['detected_by'], true)) {
            $results['cookies'][$cookieName]['detected_by'][] = $detectedBy;
        }
    }

    /**
     * Attempts to automatically categorize a cookie based on its name or detection context.
     *
     * This method uses pattern matching to infer the likely category of a cookie
     * (e.g. necessary, statistics, marketing) when explicit information is not available
     * from the CookieDatabase API or the built-in catalog.
     *
     * Detection is based on:
     *  - Known naming conventions (e.g. "_ga" → Google Analytics)
     *  - Common vendor identifiers in detection sources (e.g. "facebook", "hotjar")
     *
     * @param  string  $name         The cookie name to evaluate.
     * @param  array   $detectedBy   List of detection sources (script or provider hints).
     * @return string                The inferred category ("necessary", "statistics", "marketing", or "unclassified").
     */
    protected function guessCategory(string $name, array $detectedBy): string
    {
        $name = strtolower($name);

        // Match common system or session cookies
        if (Str::contains($name, ['xsrf', 'csrf', 'session', 'laravel'])) {
            return 'necessary';
        }

        // Match analytics and measurement cookies
        if (Str::contains($name, ['_ga', '_gid', '_gat', '_hj', '_pk_'])) {
            return 'statistics';
        }

        // Match marketing and advertising cookies
        if (Str::contains($name, ['_fb', '_fbc', '_gcl', '_tt', 'ysc'])) {
            return 'marketing';
        }

        // Infer category based on detected script or provider context
        foreach ($detectedBy as $detector) {
            if (Str::contains($detector, ['facebook', 'tiktok', 'doubleclick'])) {
                return 'marketing';
            }
            if (Str::contains($detector, ['google', 'matomo', 'piwik', 'hotjar'])) {
                return 'statistics';
            }
        }

        // Fallback: could not determine category
        return 'unclassified';
    }

    /**
     * Loads the local cookie metadata cache from storage.
     *
     * This method retrieves the cached JSON file that stores previously
     * fetched cookie details (e.g. provider, description, category).
     * The cache helps reduce redundant API requests to CookieDatabase.org
     * across multiple scans.
     *
     * If the file does not exist or cannot be read, an empty array is returned.
     *
     * @return array  Associative array of cached cookie metadata, or an empty array on failure.
     */
    protected function loadCookieInfoCache(): array
    {
        try {
            // Attempt to read and decode the cache file from local storage
            $data = Storage::disk('local')->get('cookie-info-cache.json');
            return json_decode($data, true) ?? [];
        } catch (\Throwable $e) {
            // Gracefully fall back if file is missing or unreadable
            return [];
        }
    }

    /**
     * Fetches detailed information about a given cookie from available sources.
     *
     * The lookup process follows a prioritized multi-step approach:
     *
     *  1. **Built-in catalog (fast path):**  
     *     Returns predefined metadata for common cookies such as Google Analytics or Facebook Pixel.
     *
     *  2. **Application-level cookies:**  
     *     Automatically marks local/session/CSRF cookies as “necessary” without querying remote APIs.
     *
     *  3. **Remote API lookup (CookieDatabase.org):**  
     *     Attempts to retrieve cookie metadata using multiple name variations.  
     *     Implements retry logic with exponential backoff and SSL-tolerant requests.
     *
     *  4. **Fallback:**  
     *     If no match is found or all lookups fail, returns an entry with an inferred category
     *     using {@see guessCategory()}.
     *
     * @param  string  $name  The cookie name to look up.
     * @return array|null     Detailed cookie metadata, or minimal fallback info on failure.
     */
    protected function fetchCookieInfo(string $name): ?array
    {
        // 1) Check the built-in catalog first (fast path)
        $n = $this->norm($name);
        $builtin = trans("cookie-consent::cookies.{$n}");

        if (is_array($builtin) && !empty($builtin)) {
            return $builtin + [
                'name' => $name,
                'last_checked' => now()->toIso8601String(),
            ];
        }

        // 2) Generate possible name variations for improved API matching
        $candidates = array_unique([
            $name,
            ltrim($name, '_'),
            strtoupper(ltrim($name, '_')),
            strtolower(ltrim($name, '_')),
        ]);

        // HTTP client configuration
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
        $timeout = 20;  // seconds
        $retries = 3;

        // Attempt to fetch from the CookieDatabase.org API
        foreach ($candidates as $candidate) {
            $endpoint = "https://cookiedatabase.org/wp-json/cdb/v1/cookie/" . urlencode($candidate);

            for ($i = 0; $i < $retries; $i++) {
                try {
                    // Short delay to avoid API rate-limiting
                    usleep(250 * 1000);

                    $resp = \Illuminate\Support\Facades\Http::withHeaders([
                            'Accept' => 'application/json',
                            'User-Agent' => $ua,
                            'Referer' => 'https://google.com',
                        ])
                        ->timeout($timeout)
                        ->withOptions([
                            'allow_redirects' => true,
                            'verify' => false, // tolerate SSL chain issues
                        ])
                        ->get($endpoint);

                    // If 404, move on to the next name candidate
                    if ($resp->status() === 404) {
                        break;
                    }

                    // Parse a valid response
                    if ($resp->ok()) {
                        $data = $resp->json();
                        if (!empty($data) && isset($data['name'])) {
                            return [
                                'name' => $name, // always preserve the original cookie name
                                'provider' => $data['provider'] ?? null,
                                'description' => $data['description'] ?? null,
                                'category' => strtolower($data['category'] ?? 'unclassified'),
                                'expiry' => $data['expiry'] ?? null,
                                'url' => $data['url'] ?? null,
                                'last_checked' => now()->toIso8601String(),
                            ];
                        }
                    }

                    // Retry after exponential backoff
                    usleep((200 + $i * 400) * 1000);

                } catch (\Throwable $e) {
                    // Handle network or timeout errors gracefully
                    usleep((300 + $i * 500) * 1000);
                    if ($i === $retries - 1) {
                        $this->warn("Could not fetch online info for {$candidate}: " . $e->getMessage());
                    }
                }
            }
        }

        // 4) Fallback: no data found → use local heuristics
        return [
            'name' => $name,
            'provider' => null,
            'description' => null,
            'category' => $this->guessCategory($name, []),
            'expiry' => null,
            'url' => null,
            'last_checked' => now()->toIso8601String(),
        ];
    }

    /**
     * Normalizes a cookie name for consistent comparison and caching.
     *
     * Converts the given cookie name to lowercase and trims any surrounding
     * whitespace. This ensures reliable matching across different sources,
     * header formats, or case variations.
     *
     * Example:
     *  - "  XSRF-TOKEN  " → "xsrf-token"
     *
     * @param  string  $name  The raw cookie name to normalize.
     * @return string          The normalized, lowercase cookie name.
     */
    protected function norm(string $name): string
    {
        return strtolower(trim($name));
    }

    /**
     * Determines whether a cookie should be excluded from remote API lookups.
     *
     * This method identifies internal or framework-level cookies (such as session
     * or CSRF tokens) that do not need to be queried on external sources like
     * CookieDatabase.org. Such cookies are considered “necessary” and handled locally.
     *
     * The check uses both:
     *  - Partial name matching (e.g. any name containing "session")
     *  - Exact matching against the predefined $skipRemoteFor list
     *
     * @param  string  $name  The cookie name to evaluate.
     * @return bool           True if the cookie should be skipped, false otherwise.
     */
    protected function shouldSkipRemote(string $name): bool
    {
        $n = $this->norm($name);

        // Skip any cookie whose name contains "session"
        if (Str::contains($n, ['session'])) return true;

        // Skip exact matches from the skipRemoteFor list
        foreach ($this->skipRemoteFor as $skip) {
            if ($n === $skip) return true;
        }

        // Default: allow remote lookup
        return false;
    }
}
