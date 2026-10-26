<?php

use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChainController;
use App\Http\Controllers\Api\V1\ChartExplanationController;
use App\Http\Controllers\Api\V1\ChartNoteController;
use App\Http\Controllers\Api\V1\ChartRequestController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CountryDirectoryController;
use App\Http\Controllers\Api\V1\CrossCheckController;
use App\Http\Controllers\Api\V1\DatasetController;
use App\Http\Controllers\Api\V1\EntityController;
use App\Http\Controllers\Api\V1\FirmController;
use App\Http\Controllers\Api\V1\IndicatorController;
use App\Http\Controllers\Api\V1\InsightController;
use App\Http\Controllers\Api\V1\MarketIntelController;
use App\Http\Controllers\Api\V1\ObservationController;
use App\Http\Controllers\Api\V1\OrgPriceController;
use App\Http\Controllers\Api\V1\OverviewController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\PriceWatchController;
use App\Http\Controllers\Api\V1\RankingController;
use App\Http\Controllers\Api\V1\RelationController;
use App\Http\Controllers\Api\V1\SecretController;
use App\Http\Controllers\Api\V1\SeriesController;
use App\Http\Controllers\Api\V1\StartupController;
use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:600,1')->group(function () {
    Route::get('datasets', [DatasetController::class, 'index']);
    Route::get('datasets/{key}', [DatasetController::class, 'show']);
    Route::get('series/{indicator}/{entity}', [SeriesController::class, 'show']);
    Route::get('observations', [ObservationController::class, 'index']);
    Route::get('entities', [EntityController::class, 'index']);
    Route::get('entities/{code}/latest', [EntityController::class, 'latest']);
    Route::get('entities/{code}/indicators', [EntityController::class, 'indicators']);
    Route::get('rankings', [RankingController::class, 'index']);
    Route::get('relations', [RelationController::class, 'index']);
    Route::get('relations/series', [RelationController::class, 'series']);
    Route::get('overview', OverviewController::class);
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('market-intel/products', [MarketIntelController::class, 'products']);
        Route::get('market-intel/catalog', [MarketIntelController::class, 'catalog']);
        Route::get('market-intel/analysis', [MarketIntelController::class, 'analysis']);
        Route::get('market-intel/brief', [MarketIntelController::class, 'brief']);
        Route::get('market-intel/capabilities', [MarketIntelController::class, 'capabilities']);
    });
    Route::get('insights/scorecard', [InsightController::class, 'scorecard']);
    Route::get('insights/signals', [InsightController::class, 'signals']);
    Route::get('readings', [InsightController::class, 'readings']);
    Route::get('corrections', [InsightController::class, 'corrections']);
    Route::get('indicators', [IndicatorController::class, 'index']);
    Route::get('indicators/{key}', [IndicatorController::class, 'show'])->where('key', '[A-Za-z0-9._\-]+');
    Route::get('search', [IndicatorController::class, 'search'])->middleware('throttle:120,1');
    Route::get('insights/crosschecks', [CrossCheckController::class, 'index']);
    Route::get('insights/crosschecks/detail', [CrossCheckController::class, 'detail']);
    Route::get('countries/directory', [CountryDirectoryController::class, 'index']);
    Route::get('places/stats', [PlaceController::class, 'stats']);
    Route::get('places', [PlaceController::class, 'index']);
    Route::get('startups/stats', [StartupController::class, 'stats']);
    Route::get('startups', [StartupController::class, 'index']);
    Route::get('firms/stats', [FirmController::class, 'stats']);
    Route::get('firms', [FirmController::class, 'index']);
    Route::get('firms/{code}', [FirmController::class, 'show'])->where('code', 'Q[0-9]+');
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
        Route::get('settings/secrets', [SecretController::class, 'index']);
        Route::put('settings/secrets/{name}', [SecretController::class, 'update']);
        Route::delete('settings/secrets/{name}', [SecretController::class, 'destroy']);
        // سلاسل القيمة (ذكاء السوق): القراءة لأي مسجّل، والتعديل لمدير النظام أو المحلل (يُفحص في المتحكم).
        Route::prefix('intel')->group(function () {
            Route::get('chains', [ChainController::class, 'index']);
            Route::post('chains', [ChainController::class, 'store']);
            Route::get('indicators', [ChainController::class, 'indicators']);
            Route::get('nodes', [ChainController::class, 'nodes']);
            Route::get('early-warning', [ChainController::class, 'earlyWarning']);
            Route::get('org', [OrgPriceController::class, 'me']);
            Route::get('org/prices', [OrgPriceController::class, 'index']);
            Route::post('org/prices', [OrgPriceController::class, 'store']);
            Route::post('org/prices/upload', [OrgPriceController::class, 'upload'])->middleware('throttle:20,1');
            Route::delete('org/prices/{id}', [OrgPriceController::class, 'destroy'])->whereNumber('id');
            Route::get('org/benchmark', [OrgPriceController::class, 'benchmark']);
            Route::get('uploads', [UploadController::class, 'index']);
            Route::post('uploads', [UploadController::class, 'store'])->middleware('throttle:20,1');
            Route::get('chains/{slug}', [ChainController::class, 'show']);
            Route::patch('chains/{slug}', [ChainController::class, 'updateChain']);
            Route::get('chains/{slug}/revisions', [ChainController::class, 'revisions']);
            Route::get('chains/{slug}/analytics', [ChainController::class, 'analytics']);
            Route::get('chains/{slug}/history', [ChainController::class, 'history']);
            Route::post('chains/{slug}/nodes', [ChainController::class, 'storeNode']);
            Route::patch('chains/{slug}/nodes/{node}', [ChainController::class, 'updateNode'])->whereNumber('node');
            Route::delete('chains/{slug}/nodes/{node}', [ChainController::class, 'destroyNode'])->whereNumber('node');
            Route::post('chains/{slug}/nodes/{node}/series', [ChainController::class, 'attachSeries'])->whereNumber('node');
            Route::delete('chains/{slug}/nodes/{node}/series/{series}', [ChainController::class, 'detachSeries'])->whereNumber(['node', 'series']);
            Route::post('chains/{slug}/edges', [ChainController::class, 'storeEdge']);
            Route::patch('chains/{slug}/edges/{edge}', [ChainController::class, 'updateEdge'])->whereNumber('edge');
            Route::delete('chains/{slug}/edges/{edge}', [ChainController::class, 'destroyEdge'])->whereNumber('edge');
            Route::post('chains/{slug}/edges/{edge}/sources', [ChainController::class, 'storeSource'])->whereNumber('edge');
            Route::delete('chains/{slug}/sources/{source}', [ChainController::class, 'destroySource'])->whereNumber('source');
        });
        Route::post('market-intel/ask', [MarketIntelController::class, 'ask'])->middleware('throttle:10,1');
        Route::get('market-intel/answer', [MarketIntelController::class, 'answer']);
        Route::get('market-intel/price-history', [PriceWatchController::class, 'history']);
        Route::get('market-intel/watches', [PriceWatchController::class, 'index']);
        Route::post('market-intel/watches', [PriceWatchController::class, 'store']);
        Route::delete('market-intel/watches/{id}', [PriceWatchController::class, 'destroy'])->whereNumber('id');
        // تستهلك رصيد مفاتيح المالك المدفوعة (SerpAPI/Anthropic) فتتطلب تسجيل الدخول وحدًّا أدنى للطلبات.
        Route::middleware('throttle:20,1')->group(function () {
            Route::get('market-intel/prices', [MarketIntelController::class, 'prices']);
            Route::get('market-intel/subscriptions', [MarketIntelController::class, 'subscriptions']);
            Route::get('market-intel/resolve', [MarketIntelController::class, 'resolve']);
            Route::get('market-intel/marketplaces', [MarketIntelController::class, 'marketplaces']);
        });
    });
});

// وكيل الذكاء الاصطناعي: رمز Bearer من AGENT_TOKEN (بلا جلسة).
Route::prefix('agent')->middleware(['agent', 'throttle:120,1'])->group(function () {
    Route::get('requests', [AgentController::class, 'requests']);
    Route::patch('requests/{chartRequest}', [AgentController::class, 'updateRequest']);
    Route::put('explanations', [AgentController::class, 'explanation']);
});
