/**
 * Laravel Privacy Panel
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

const privacyPanelScriptPatterns = [
    { match: /googletagmanager\.com|google-analytics\.com/i, category: 'statistics' },
    { match: /facebook\.net|fbq\(|fbevents\.js/i, category: 'marketing' },
    { match: /hotjar\.com/i, category: 'statistics' },
    { match: /tiktok\.com/i, category: 'marketing' },
    { match: /youtube\.com|youtube-nocookie\.com/i, category: 'marketing' },
    { match: /linkedin\.com|licdn\.com/i, category: 'marketing' },
];

function privacyPanelScriptCategory(script) {
    const src = script.src || script.getAttribute('data-src') || '';
    const code = script.textContent || '';
    return privacyPanelScriptPatterns.find(pattern =>
        pattern.match.test(src) || pattern.match.test(code)
    )?.category;
}

function privacyPanelCategoryAllowed(category) {
    return window.CookieConsent?.consent?.[category] === true;
}

(function () {
    // Observe script insertions early to block known trackers when not allowed.

    const observer = new MutationObserver(mutations => {
        mutations.forEach(m => {
            m.addedNodes.forEach(node => {
                if (node.tagName === 'SCRIPT' && !node.hasAttribute('data-cookie-category')) {
                    const src = node.src || '';
                    const category = privacyPanelScriptCategory(node);

                    if (category && !privacyPanelCategoryAllowed(category)) {
                        node.type = 'text/plain';
                        node.setAttribute('data-cookie-category', category);
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

    const { translations, routes, csrf, consent: savedConsent } = window.CookieConsent || {};

    // Laravel encrypts cookies by default. The server-side middleware therefore
    // exposes the already decrypted preferences instead of parsing document.cookie.
    if (savedConsent && typeof savedConsent === 'object') {
        enableScriptsFor(savedConsent);
    }

    const reopenBtn = document.getElementById('privacy-panel-btn');
    const banner = document.getElementById('privacy-panel');
    const statsBox = document.getElementById('stats');
    const marketingBox = document.getElementById('marketing');

    if (savedConsent && typeof savedConsent === 'object') {
        if (statsBox) statsBox.checked = savedConsent.statistics === true;
        if (marketingBox) marketingBox.checked = savedConsent.marketing === true;
    }

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
        }).then(response => {
            if (!response.ok) {
                throw new Error(`Failed to save consent (${response.status})`);
            }

            window.CookieConsent.consent = consent;
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
        }).catch(error => {
            console.error('Failed to save cookie consent:', error);
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
        const detailsBox = document.getElementById('privacy-panel-details');
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
    document.querySelectorAll('script:not([data-cookie-category]):not([type="text/plain"])').forEach(script => {
        const category = privacyPanelScriptCategory(script);
        if (category && !privacyPanelCategoryAllowed(category)) {
            script.setAttribute('data-cookie-category', category);
            script.setAttribute('type', 'text/plain');
            if (script.src) {
                script.setAttribute('data-src', script.src);
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

        for (const attribute of oldScript.attributes) {
            if (!['type', 'data-src', 'data-cookie-category'].includes(attribute.name)) {
                newScript.setAttribute(attribute.name, attribute.value);
            }
        }

        if (oldScript.dataset.src) {
            newScript.src = oldScript.dataset.src;
        } else {
            newScript.text = oldScript.textContent;
        }

        newScript.setAttribute('data-cookie-category', category);
        oldScript.replaceWith(newScript);
    });
}
