<?php

use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChartExplanationController;
use App\Http\Controllers\Api\V1\ChartNoteController;
use App\Http\Controllers\Api\V1\ChartRequestController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CountryDirectoryController;
use App\Http\Controllers\Api\V1\CrossCheckController;
use App\Http\Controllers\Api\V1\DatasetController;
use App\Http\Controllers\Api\V1\EntityController;
use App\Http\Controllers\Api\V1\InsightController;
use App\Http\Controllers\Api\V1\ObservationController;
use App\Http\Controllers\Api\V1\OverviewController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\RankingController;
use App\Http\Controllers\Api\V1\RelationController;
use App\Http\Controllers\Api\V1\SeriesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:600,1')->group(function () {
    Route::get('datasets', [DatasetController::class, 'index']);
    Route::get('datasets/{key}', [DatasetController::class, 'show']);
    Route::get('series/{indicator}/{entity}', [SeriesController::class, 'show']);
    Route::get('observations', [ObservationController::class, 'index']);
    Route::get('entities', [EntityController::class, 'index']);
    Route::get('entities/{code}/latest', [EntityController::class, 'latest']);
    Route::get('rankings', [RankingController::class, 'index']);
    Route::get('relations', [RelationController::class, 'index']);
    Route::get('relations/series', [RelationController::class, 'series']);
    Route::get('overview', OverviewController::class);
    Route::get('insights/scorecard', [InsightController::class, 'scorecard']);
    Route::get('insights/signals', [InsightController::class, 'signals']);
    Route::get('insights/crosschecks', [CrossCheckController::class, 'index']);
    Route::get('insights/crosschecks/detail', [CrossCheckController::class, 'detail']);
    Route::get('countries/directory', [CountryDirectoryController::class, 'index']);
    Route::get('places/stats', [PlaceController::class, 'stats']);
    Route::get('places', [PlaceController::class, 'index']);
    Route::get('companies', [CompanyController::class, 'index']);
    Route::get('companies/stats', [CompanyController::class, 'stats']);
    Route::get('companies/{code}', [CompanyController::class, 'show']);
});

// الحسابات وما يكتبه المسجَّلون: جلسة بملف ارتباط (مخطط app فقط). الواجهة ترسل X-XSRF-TOKEN.
Route::prefix('v1')->middleware(['web', 'throttle:120,1'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('chart-explanations', [ChartExplanationController::class, 'index']);

    Route::middleware('auth')->group(function () {
        Route::get('chart-requests', [ChartRequestController::class, 'index']);
        Route::post('chart-requests', [ChartRequestController::class, 'store']);
        Route::patch('chart-requests/{chartRequest}', [ChartRequestController::class, 'update']);
        Route::get('chart-notes', [ChartNoteController::class, 'index']);
        Route::post('chart-notes', [ChartNoteController::class, 'store']);
        Route::delete('chart-notes/{note}', [ChartNoteController::class, 'destroy']);
        Route::put('chart-explanations', [ChartExplanationController::class, 'upsert']);
        Route::delete('chart-explanations', [ChartExplanationController::class, 'destroy']);
    });
});

// وكيل الذكاء الاصطناعي: رمز Bearer من AGENT_TOKEN (بلا جلسة).
Route::prefix('agent')->middleware(['agent', 'throttle:120,1'])->group(function () {
    Route::get('requests', [AgentController::class, 'requests']);
    Route::patch('requests/{chartRequest}', [AgentController::class, 'updateRequest']);
    Route::put('explanations', [AgentController::class, 'explanation']);
});
