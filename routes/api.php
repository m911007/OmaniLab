<?php

use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CrossCheckController;
use App\Http\Controllers\Api\V1\DatasetController;
use App\Http\Controllers\Api\V1\EntityController;
use App\Http\Controllers\Api\V1\InsightController;
use App\Http\Controllers\Api\V1\ObservationController;
use App\Http\Controllers\Api\V1\OverviewController;
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
    Route::get('companies', [CompanyController::class, 'index']);
    Route::get('companies/stats', [CompanyController::class, 'stats']);
    Route::get('companies/{code}', [CompanyController::class, 'show']);
});
