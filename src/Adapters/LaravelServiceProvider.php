<?php
namespace DbSync\Adapters;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use DbSync\Core\ConfigResolver;

class LaravelServiceProvider extends ServiceProvider {
    public function boot() {
        $prefix = ConfigResolver::getEnv('DB_SYNC_PREFIX', 'db-sync');

        Route::group(['prefix' => $prefix], function() {
            // Web Dashboard & Processing (memerlukan session & CSRF web)
            Route::group(['middleware' => ['web']], function() {
                Route::get('/', [\DbSync\Http\Controllers\SyncController::class, 'index']);
                Route::post('/process', [\DbSync\Http\Controllers\SyncController::class, 'process']);
            });

            // Remote Sync API (dipanggil antar server via cURL, tanpa CSRF middleware)
            Route::post('/sync-api', [\DbSync\Http\Controllers\SyncController::class, 'handleApi']);
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../Views' => resource_path('views/vendor/db-sync'),
            ], 'db-sync-views');
        }
    }
}
