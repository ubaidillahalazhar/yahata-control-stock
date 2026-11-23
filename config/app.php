<?php

/*
 * Sejak Laravel 11, nilai yang tidak didefinisikan di sini otomatis diambil dari
 * konfigurasi default framework (vendor/laravel/framework/config/app.php).
 * File ini hanya menimpa nilai yang spesifik untuk aplikasi Yahata.
 */

return [

    'name' => env('APP_NAME', 'Yahata Control Stock'),

    'company' => env('APP_COMPANY', 'PT. Yahata Fastener Indonesia'),

    'department' => env('APP_DEPARTMENT', 'Production Department'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    /*
     * Dipakai sebagai base URL label QR Code. Isi dengan alamat yang bisa diakses
     * HP operator di jaringan pabrik, mis. http://192.168.1.10:8000
     */
    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),

    'locale' => env('APP_LOCALE', 'id'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'id_ID'),

];
