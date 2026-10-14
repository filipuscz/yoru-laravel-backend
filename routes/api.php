<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\ExampleController;
use App\Http\Controllers\Api\v1\LanguageController;
use App\Http\Controllers\Api\v1\NotificationController;
use App\Http\Controllers\Api\v1\PermissionController;
use App\Http\Controllers\Api\v1\RoleController;
use App\Http\Controllers\Api\v1\SettingController;
use App\Http\Controllers\Api\v1\UserController;
use App\Http\Middleware\CheckApiToken;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware([CheckApiToken::class, 'throttle:api'])
    ->group(function () {
        Route::middleware('auth:api')->group(function () {
            Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
            Route::patch('settings', [SettingController::class, 'bulkUpdate'])->name('settings.update');

            Route::post('notifications/read', [NotificationController::class, 'markAllAsRead'])->name('notifications.read');
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
            Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

            Route::post('users/bulk-update', [UserController::class, 'bulkUpdate'])->name('users.bulk-update');
            Route::post('users/bulk-delete', [UserController::class, 'bulkDestroy'])->name('users.bulk-delete');
            Route::apiResource('users', UserController::class);

            Route::post('examples/bulk-update', [ExampleController::class, 'bulkUpdate'])->name('examples.bulk-update');
            Route::post('examples/bulk-delete', [ExampleController::class, 'bulkDestroy'])->name('examples.bulk-delete');
            Route::apiResource('examples', ExampleController::class);

            Route::middleware(RoleMiddleware::using('admin', 'api'))->group(function () {
                Route::post('roles/bulk-update', [RoleController::class, 'bulkUpdate'])->name('roles.bulk-update');
                Route::post('roles/bulk-delete', [RoleController::class, 'bulkDestroy'])->name('roles.bulk-delete');
                Route::apiResource('roles', RoleController::class);

                Route::post('permissions/bulk-update', [PermissionController::class, 'bulkUpdate'])->name('permissions.bulk-update');
                Route::post('permissions/bulk-delete', [PermissionController::class, 'bulkDestroy'])->name('permissions.bulk-delete');
                Route::apiResource('permissions', PermissionController::class);
            });
        });

        Route::name('auth.')
            ->prefix('auth')
            ->controller(AuthController::class)
            ->group(function () {
                Route::post('/login', 'login')->name('login')->middleware(['guest', 'throttle:auth:login'])->withoutMiddleware(CheckApiToken::class);
                Route::post('/logout', 'logout')->name('logout')->middleware('auth:api');
                Route::post('/register', 'register')->name('register')->middleware(['guest', 'throttle:auth:register'])->withoutMiddleware(CheckApiToken::class);
                Route::get('/me', 'me')->name('me')->middleware('auth:api');
                Route::get('/api-token', 'generateApiToken')->name('api-token')->middleware('auth:api')->withoutMiddleware(CheckApiToken::class);
            });

        Route::get('languages/{locale}', [LanguageController::class, 'show'])
            ->name('languages.show')
            ->withoutMiddleware(CheckApiToken::class);
    });
