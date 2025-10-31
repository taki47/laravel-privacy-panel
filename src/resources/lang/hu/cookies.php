<?php

return [
    'xsrf-token' => [
        'category' => 'szükséges',
        'provider' => 'Alkalmazás',
        'description' => 'Alapvető süti, amely a rendszer működéséhez szükséges (pl. munkamenet- vagy CSRF-védelem).',
        'expiry' => null,
        'url' => null
    ],
    'csrf-token' => [
        'category' => 'szükséges',
        'provider' => 'Alkalmazás',
        'description' => 'Alapvető süti, amely a rendszer működéséhez szükséges (pl. munkamenet- vagy CSRF-védelem).',
        'expiry' => null,
        'url' => null
    ],
    'laravel-session' => [
        'category' => 'szükséges',
        'provider' => 'Alkalmazás',
        'description' => 'Alapvető süti, amely a rendszer működéséhez szükséges (pl. munkamenet- vagy CSRF-védelem).',
        'expiry' => null,
        'url' => null
    ],
    'session' => [
        'category' => 'szükséges',
        'provider' => 'Alkalmazás',
        'description' => 'Alapvető süti, amely a rendszer működéséhez szükséges (pl. munkamenet- vagy CSRF-védelem).',
        'expiry' => null,
        'url' => null
    ],
    '_ga' => [
        'category' => 'statisztikai',
        'provider' => 'Google Analytics',
        'description' => 'Egyedi azonosítót regisztrál, amely statisztikai adatokat generál arról, hogyan használja a látogató a weboldalt.',
        'expiry' => '2 év',
        'url' => 'https://business.safety.google/privacy/'
    ],
    '_gid' => [
        'category' => 'statisztikai',
        'provider' => 'Google Analytics',
        'description' => 'Egyedi azonosítót regisztrál a munkamenetek statisztikai adatainak generálásához.',
        'expiry' => '1 nap',
        'url' => 'https://business.safety.google/privacy/'
    ],
    '_gat' => [
        'category' => 'statisztikai',
        'provider' => 'Google Analytics',
        'description' => 'A kérések arányának korlátozására szolgál.',
        'expiry' => '1 perc',
        'url' => 'https://business.safety.google/privacy/'
    ],
    '_gcl_au' => [
        'category' => 'marketing',
        'provider' => 'Google Ads',
        'description' => 'A Google AdSense használja a hirdetések hatékonyságának kísérleti mérésére.',
        'expiry' => '3 hónap',
        'url' => 'https://business.safety.google/privacy/'
    ],
    '_fbp' => [
        'category' => 'marketing',
        'provider' => 'Meta / Facebook',
        'description' => 'A Facebook használja különböző hirdetési termékek megjelenítésére.',
        'expiry' => '3 hónap',
        'url' => 'https://www.facebook.com/privacy/policy/'
    ],
    '_fbc' => [
        'category' => 'marketing',
        'provider' => 'Meta / Facebook',
        'description' => 'A Facebook-hirdetésekhez kapcsolódó utolsó látogatás hozzárendelését tárolja.',
        'expiry' => '3 hónap',
        'url' => 'https://www.facebook.com/privacy/policy/'
    ],
    '_hjsessionuser' => [
        'category' => 'statisztikai',
        'provider' => 'Hotjar',
        'description' => 'Egyedi azonosítót állít be a munkamenethez, hogy analitikai adatokat gyűjtsön a felhasználói viselkedésről.',
        'expiry' => '1 év',
        'url' => 'https://www.hotjar.com/legal/policies/privacy/'
    ],
    '_pk_id' => [
        'category' => 'statisztikai',
        'provider' => 'Matomo',
        'description' => 'Egyedi felhasználói azonosítót tárol az analitikai mérésekhez.',
        'expiry' => '13 hónap',
        'url' => 'https://matomo.org/privacy-policy/'
    ],
    '_pk_ses' => [
        'category' => 'statisztikai',
        'provider' => 'Matomo',
        'description' => 'Rövid életű süti, amelyet a Matomo ideiglenes adatok tárolására használ.',
        'expiry' => '30 perc',
        'url' => 'https://matomo.org/privacy-policy/'
    ],
    '_ttp' => [
        'category' => 'marketing',
        'provider' => 'TikTok',
        'description' => 'A hirdetési kampányok teljesítményének mérésére és javítására szolgál.',
        'expiry' => '13 hónap',
        'url' => 'https://www.tiktok.com/legal/page/us/privacy-policy/en'
    ],
    'ysc' => [
        'category' => 'marketing',
        'provider' => 'YouTube',
        'description' => 'Egyedi azonosítót regisztrál annak nyilvántartására, hogy a felhasználó mely YouTube-videókat nézte meg.',
        'expiry' => 'Munkamenet',
        'url' => 'https://www.youtube.com/howyoutubeworks/privacy/'
    ],
];
