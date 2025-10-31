<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Taki47\CookieConsent\Http\Controllers\CookieConsentController;

Route::controller(CookieConsentController::class)->group(function () {
    Route::post('/cookie-consent', 'store')->name('cookie-consent.store');
    Route::get('/cookie-consent/cookies', 'list')->name('cookie-consent.list');
});