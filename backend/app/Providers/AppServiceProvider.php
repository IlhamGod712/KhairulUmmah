<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePasswordResetUrl();
    }

    /**
     * Point the reset email at the SPA instead of a server-rendered page.
     *
     * Laravel's stock `ResetPassword` notification builds its button URL with
     * `route('password.reset', ...)`. This is an API-only application with no
     * such web route, so the stock notification would throw a
     * `RouteNotFoundException` the moment a real reset email is rendered. This
     * is the framework's own hook for redirecting the link elsewhere, so the
     * notification itself stays untouched.
     */
    protected function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function (mixed $notifiable, string $token): string {
            $query = http_build_query([
                'token' => $token,
                'email' => $notifiable instanceof User
                    ? $notifiable->getEmailForPasswordReset()
                    : '',
            ]);

            return rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.$query;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        RateLimiter::for('auth', function (Request $request): Limit {
            // Keyed on the email as well as the IP so one attacker rotating
            // addresses cannot brute force a single account, and a shared
            // office NAT cannot lock every guardian out.
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by(mb_substr($request->ip().'|'.$email, 0, 200));
        });

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
