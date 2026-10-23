<?php
namespace DbSync\Adapters;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use DbSync\Core\ConfigResolver;

class LaravelServiceProvider extends ServiceProvider {
    public function boot() {
        $prefix = ConfigResolver::getEnv('DB_SYNC_PREFIX', 'db-sync');

        // Middleware kustom dari .env (default: 'web', dapat diganti ke 'web,auth' dll)
        $middlewareEnv = (string) ConfigResolver::getEnv('DB_SYNC_MIDDLEWARE', 'web');
        $webMiddleware = array_values(array_filter(array_map('trim', explode(',', $middlewareEnv))));

        Route::group(['prefix' => $prefix], function() use ($webMiddleware) {
            // Web Dashboard, Authentication & Processing
            Route::group(['middleware' => $webMiddleware], function() {
                Route::get('/login', [\DbSync\Http\Controllers\SyncController::class, 'login']);
                Route::post('/login', [\DbSync\Http\Controllers\SyncController::class, 'login']);
                Route::get('/logout', [\DbSync\Http\Controllers\SyncController::class, 'logout']);
                Route::post('/logout', [\DbSync\Http\Controllers\SyncController::class, 'logout']);
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
