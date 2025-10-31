/**
 * Laravel Cookie Consent
 * -----------------------
 * Handles automatic blocking/unblocking of analytics & marketing scripts
 * based on user's consent.
 *
 * Features:
 * - Detects and blocks known tracking scripts (GA, FB, TikTok, etc.)
 * - Allows toggling of consent categories
 * - Updates Google Consent Mode dynamically
 * - Deletes cookies when consent is revoked
 * - Loads dynamic cookie details
 *
 * Author: Lajos Taki <https://takiwebneked.hu>
 * License: MIT
 */

(function () {
    // Observe script insertions early to block before execution
    const blockPatterns = /googletagmanager\.com|google-analytics\.com|facebook\.net|fbevents\.js|hotjar\.com|tiktok\.com|youtube\.com|linkedin\.com/i;

    const observer = new MutationObserver(mutations => {
        mutations.forEach(m => {
            m.addedNodes.forEach(node => {
                if (node.tagName === 'SCRIPT' && !node.hasAttribute('data-cookie-category')) {
                    const src = node.src || '';
                    const code = node.textContent || '';

                    if (blockPatterns.test(src) || blockPatterns.test(code)) {
                        node.type = 'text/plain';
                        node.setAttribute('data-cookie-category', 'statistics');
                        if (src) {
                            node.setAttribute('data-src', src);
                            node.removeAttribute('src');
                        }
                    }
                }
            });
        });
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
})();

document.addEventListener('DOMContentLoaded', function () {
    autoBlockScripts();

    // Re-enable scripts if consent cookie already exists
    const savedCookie = document.cookie.split('; ').find(row => row.startsWith('cookie-consent='));
    if (savedCookie) {
        try {
            const jsonValue = atob(savedCookie.split('=')[1]);
            const consent = JSON.parse(jsonValue);
            enableScriptsFor(consent);
        } catch (e) {
            console.warn('⚠️ Failed to parse cookie-consent:', e);
        }
    }

    const { translations, routes, csrf } = window.CookieConsent || {};

    const reopenBtn = document.getElementById('cookie-reopen-btn');
    const banner = document.getElementById('cookie-banner');
    const statsBox = document.getElementById('stats');
    const marketingBox = document.getElementById('marketing');

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(str = '') {
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    /**
     * Delete cookies by name pattern
     */
    function deleteCookies(patterns = []) {
        const cookies = document.cookie.split('; ');
        for (const cookie of cookies) {
            const [name] = cookie.split('=');
            if (patterns.some(p => name.includes(p))) {
                document.cookie = `${name}=; Max-Age=0; path=/`;
                document.cookie = `${name}=; Max-Age=0; domain=${window.location.hostname}; path=/`;
                document.cookie = `${name}=; Max-Age=0; domain=.${window.location.hostname}; path=/`;
            }
        }
    }

    /**
     * Send user consent to the backend and update UI
     */
    function sendConsent(consent) {
        fetch(routes.store, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            credentials: 'same-origin',
            body: JSON.stringify(consent)
        }).then(() => {
            enableScriptsFor(consent);

            // Update Google Consent Mode if available
            if (typeof gtag === 'function') {
                gtag('consent', 'update', {
                    analytics_storage: consent.statistics ? 'granted' : 'denied',
                    ad_storage: consent.marketing ? 'granted' : 'denied',
                    functionality_storage: 'granted',
                    security_storage: 'granted'
                });
            }

            // Clean up cookies when revoked
            if (!consent.statistics) deleteCookies(['_ga', '_gid', '_gat', '_ga_', '_ga_ZTS1ZMYVML']);
            if (!consent.marketing) deleteCookies(['_fbp', '_fbc', 'fr', '_hj']);

            // Hide banner, show reopen button
            banner.classList.add('d-none');
            reopenBtn.classList.remove('d-none');
        });
    }

    // ----------------------------------------------------------
    // UI Handlers
    // ----------------------------------------------------------
    const hasConsent = document.cookie.split('; ').some(row => row.startsWith('cookie-consent='));
    if (hasConsent) reopenBtn.classList.remove('d-none');

    reopenBtn.addEventListener('click', function () {
        banner.classList.remove('d-none');
        banner.style.display = 'block';
        reopenBtn.classList.add('d-none');
    });

    document.getElementById('accept-all')?.addEventListener('click', () =>
        sendConsent({ necessary: true, statistics: true, marketing: true })
    );

    document.getElementById('accept-selected')?.addEventListener('click', () =>
        sendConsent({
            necessary: true,
            statistics: statsBox?.checked || false,
            marketing: marketingBox?.checked || false
        })
    );

    document.getElementById('decline-all')?.addEventListener('click', () =>
        sendConsent({ necessary: true, statistics: false, marketing: false })
    );

    /**
     * Load and toggle cookie details dynamically
     */
    document.getElementById('show-details')?.addEventListener('click', async function () {
        const detailsBtn = document.getElementById('show-details'); if (detailsBtn.dataset.locked === 'true') return; detailsBtn.dataset.locked = 'true'; setTimeout(() => (detailsBtn.dataset.locked = 'false'), 300);
        const detailsBox = document.getElementById('cookie-details');
        if (!detailsBox) return;

        const isHidden = detailsBox.style.display === 'none' || !detailsBox.style.display;
        if (isHidden) {
            detailsBox.style.display = 'block';
            detailsBox.innerHTML = `<p class="text-center text-muted mb-2">
                <i class="bi bi-hourglass-split me-1"></i> ${translations.loading}
            </p>`;

            try {
                const res = await fetch(routes.list);
                const data = await res.json();
                let html = '';

                for (const [category, providers] of Object.entries(data)) {
                    if (!Object.keys(providers).length) continue;

                    html += `<h4 class="fw-bold mt-3">${translations[category] || category}</h4>`;

                    for (const [provider, cookies] of Object.entries(providers)) {
                        html += `<h5 class="mt-2 mb-1 text-muted">${provider}</h5><ul class="list-group mb-3">`;

                        cookies.forEach(c => {
                            html += `
                                <li class="list-group-item small">
                                    <strong>${escapeHtml(c.name)}</strong>
                                    <div class="text-muted">${escapeHtml(c.description)}</div>
                                    <div class="text-secondary">
                                        <span>${c.expiry ? `Expires: ${c.expiry}` : 'Session'}</span>
                                        ${c.url ? ` • <a href="${c.url}" target="_blank" rel="noopener">${translations.more_info}</a>` : ''}
                                    </div>
                                </li>`;
                        });
                        html += `</ul>`;
                    }
                }

                detailsBox.innerHTML = html || `<p class="text-center text-muted">${translations.no_cookies}</p>`;
            } catch (error) {
                detailsBox.innerHTML = `<p class="text-danger text-center">${translations.failed_load}</p>`;
            }
        } else {
            detailsBox.style.display = 'none';
        }
    });
});

/**
 * Detect and block known tracking scripts (inline and external)
 */
function autoBlockScripts() {
    const knownPatterns = [
        { match: /googletagmanager\.com|google-analytics\.com/, category: 'statistics' },
        { match: /facebook\.net|fbq\(|fbevents\.js/, category: 'marketing' },
        { match: /hotjar\.com/, category: 'statistics' },
        { match: /tiktok\.com/, category: 'marketing' },
        { match: /youtube\.com|youtube-nocookie\.com/, category: 'marketing' },
        { match: /linkedin\.com|licdn\.com/, category: 'marketing' },
    ];

    document.querySelectorAll('script:not([data-cookie-category]):not([type="text/plain"])').forEach(script => {
        const src = script.src || '';
        const inline = script.innerText;

        const matched = knownPatterns.find(p => p.match.test(src) || p.match.test(inline));
        if (matched) {
            script.setAttribute('data-cookie-category', matched.category);
            script.setAttribute('type', 'text/plain');
            if (src) {
                script.setAttribute('data-src', src);
                script.removeAttribute('src');
            }
        }
    });
}

/**
 * Execute scripts for allowed consent categories
 */
function enableScriptsFor(consent) {
    const allowed = Object.entries(consent)
        .filter(([_, value]) => value === true)
        .map(([key]) => key);

    document.querySelectorAll('script[type="text/plain"][data-cookie-category]').forEach(oldScript => {
        const category = oldScript.getAttribute('data-cookie-category');
        if (!allowed.includes(category)) return;

        const newScript = document.createElement('script');

        if (oldScript.dataset.src) {
            newScript.src = oldScript.dataset.src;
            newScript.async = true;
        } else {
            newScript.text = oldScript.innerText;
        }

        newScript.setAttribute('data-cookie-category', category);
        document.head.appendChild(newScript);
    });
}
