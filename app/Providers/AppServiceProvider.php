<?php

namespace App\Providers;

use App\Models\Passport\AuthCode;
use App\Models\Passport\Client;
use App\Models\Passport\DeviceCode;
use App\Models\Passport\RefreshToken;
use App\Models\Passport\Token;
use App\Services\SettingService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Scramble::ignoreDefaultRoutes();
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Passport::useClientModel(Client::class);
        Passport::useTokenModel(Token::class);
        Passport::useAuthCodeModel(AuthCode::class);
        Passport::useRefreshTokenModel(RefreshToken::class);
        Passport::useDeviceCodeModel(DeviceCode::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth:login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('auth:register', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        RateLimiter::for('mcp', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        Passport::authorizationView(function (array $parameters) {
            return response()->view('mcp.authorize', $parameters);
        });

        try {
            $settingService = app(SettingService::class);
            $settings = $settingService->allSettings();

            // Inject SMTP Settings Dynamically
            if (! empty($settings['smtp_host'])) {
                config(['mail.default' => 'smtp']);
                config(['mail.mailers.smtp.host' => $settings['smtp_host']]);
            }
            if (isset($settings['smtp_port'])) {
                config(['mail.mailers.smtp.port' => $settings['smtp_port']]);
            }
            if (isset($settings['smtp_username'])) {
                config(['mail.mailers.smtp.username' => $settings['smtp_username']]);
            }
            if (isset($settings['smtp_password'])) {
                config(['mail.mailers.smtp.password' => $settings['smtp_password']]);
            }
            if (isset($settings['smtp_encryption'])) {
                config(['mail.mailers.smtp.encryption' => $settings['smtp_encryption']]);
            }
            if (isset($settings['mail_from_address'])) {
                config(['mail.from.address' => $settings['mail_from_address']]);
            }
            if (isset($settings['mail_from_name'])) {
                config(['mail.from.name' => $settings['mail_from_name']]);
            }

        } catch (\Throwable $e) {
            // Database might not exist yet during initial setup/migrations. Ignore.
        }

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->info->description = 'API for the best Todo app!';
                $openApi->components->securitySchemes['bearer'] = SecurityScheme::http('bearer');
                $openApi->security[] = new SecurityRequirement([
                    'bearer' => [],
                ]);
            });
        Scramble::registerApi('api/v1', [
            'api_path' => 'api/v1',
        ]);
        foreach (['user', 'example', 'role', 'permission', 'notification'] as $parameter) {
            Route::pattern($parameter, '[0-9]+|[a-z0-9-]+');
        }
    }
}
