{{-- 
|--------------------------------------------------------------------------
| Cookie Consent Banner
|--------------------------------------------------------------------------
| Displays the GDPR cookie consent banner at the bottom of the page.
| - Allows users to choose cookie categories (necessary, statistics, marketing)
| - Saves preferences via AJAX to the backend controller
| - Optionally lists current cookies by category (fetched dynamically)
|
| Author: Lajos Takacs <https://takiwebneked.hu>
| License: MIT
| Version: 1.0.0
--}}

{{-- JSON adatátadás a JS-nek --}}
@php
    $translations = __('cookie-consent::cookiebanner');
@endphp
<script>
    window.CookieConsent = {
        translations: @json($translations),
        routes: {
            store: "{{ route('cookie-consent.store') }}",
            list: "{{ route('cookie-consent.list') }}"
        },
        csrf: "{{ csrf_token() }}"
    };
</script>

<link rel="stylesheet" href="{{ asset('vendor/cookie-consent/css/cookie-consent.css') }}">
<script src="{{ asset('vendor/cookie-consent/js/cookie-consent.js') }}" defer></script>

<div id="cookie-banner"
     class="position-fixed bottom-0 start-0 w-100 bg-light border-top shadow-lg py-4 px-3 {{ Cookie::get('cookie-consent') ? 'd-none' : '' }}"
     style="z-index: 1055;">
    <div class="container text-center">
        <h2 class="fw-bold mb-2 text-dark">
            <i class="bi bi-shield-check text-success me-2"></i> {{ __("cookie-consent::cookiebanner.title") }}
        </h2>

        <p class="text-muted mb-3">
            {{ __("cookie-consent::cookiebanner.description") }}
        </p>

        {{-- Category toggles --}}
        <div class="d-flex justify-content-center flex-wrap gap-4 mb-4">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="necessary" checked disabled>
                <label class="form-check-label fw-semibold" for="necessary">{{ __("cookie-consent::cookiebanner.categories.necessary") }}</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="stats">
                <label class="form-check-label fw-semibold" for="stats">{{ __("cookie-consent::cookiebanner.categories.statistics") }}</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="marketing">
                <label class="form-check-label fw-semibold" for="marketing">{{ __("cookie-consent::cookiebanner.categories.marketing") }}</label>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="d-flex justify-content-center flex-wrap gap-3 mb-3">
            <button id="accept-all" class="btn btn-success px-4">
                <i class="bi bi-check-circle me-1"></i>{{ __("cookie-consent::cookiebanner.buttons.accept_all") }}
            </button>
            <button id="accept-selected" class="btn btn-primary px-4">
                <i class="bi bi-sliders me-1"></i>{{ __("cookie-consent::cookiebanner.buttons.accept_selected") }}
            </button>
            <button id="decline-all" class="btn btn-secondary px-4">
                <i class="bi bi-x-circle me-1"></i>{{ __("cookie-consent::cookiebanner.buttons.allow_necessary") }}
            </button>
            <button id="show-details" class="btn btn-info px-4">
                <i class="bi bi-info-circle me-1"></i>{{ __("cookie-consent::cookiebanner.buttons.show_details") }}
            </button>
        </div>

        {{-- Dynamic cookie list section --}}
        <div id="cookie-details"
             class="text-start bg-white border rounded shadow-sm mx-auto p-3"
             style="max-width: 600px; display: none;">
            <p class="text-center text-muted mb-2">
                <i class="bi bi-hourglass-split me-1"></i> {{ __("cookie-consent::cookiebanner.loading") }}
            </p>
        </div>
    </div>
</div>

<button 
    id="cookie-reopen-btn"
    type="button"
    class="btn btn-dark rounded-circle position-fixed bottom-0 start-0 m-4 d-none shadow-lg d-flex justify-content-center align-items-center"
    title="{{ __("cookie-consent::cookiebanner.reopenbutton.title") }}"
    aria-label="{{ __("cookie-consent::cookiebanner.reopenbutton.label") }}"
>
    <i class="fa-solid fa-shield-halved"></i>
</button>
