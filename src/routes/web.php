<?php

use Illuminate\Support\Facades\Route;
use Taki47\PrivacyPanel\Http\Controllers\PrivacyPanelController;

Route::controller(PrivacyPanelController::class)->group(function () {
    Route::post('/privacy-panel', 'store')->name('privacy-panel.store');
    Route::get('/privacy-panel/cookies', 'list')->name('privacy-panel.list');
});