<?php

use App\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

// نسخة بلا جافاسكربت لحالة البيانات (احتياطية؛ الأساس صفحة /status في التطبيق).
Route::get('/legacy/status', StatusPageController::class)->name('status.legacy');

// تطبيق React الواحد: كل مسار غير /api يعيد الصفحة ويتولى React التوجيه. تُبنى بـ: cd frontend && npm run build:laravel
Route::get('/{any?}', function () {
    $file = public_path('spa.html');
    abort_unless(is_file($file), 503, 'الواجهة لم تُبنَ بعد: شغّل npm run build:laravel داخل frontend/.');

    return response()->file($file, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-cache']);
})->where('any', '^(?!api/|up$|legacy/).*$')->name('spa');
