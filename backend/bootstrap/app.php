<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The React SPA is a separate origin, so Sanctum's SPA mode is what
        // authenticates it: a session cookie plus the XSRF-TOKEN cookie, both
        // httpOnly-ish and unreadable from JavaScript. `statefulApi()` prepends
        // EncryptCookies, AddQueuedCookies, StartSession and ValidateCsrfToken
        // to the api group, but only for requests coming from the domains
        // listed in `config/sanctum.php` -> stateful. Everything else falls
        // through to bearer token auth, so non-browser clients still work.
        $middleware->statefulApi();

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // This is an API only application, so an unauthenticated request gets
        // a 401 JSON envelope instead of a redirect to a login page.
        $middleware->redirectGuestsTo(fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Keep the one envelope everywhere. Laravel renders a validation failure
        // as `{ message, errors }`, which breaks the convention every other
        // endpoint follows. `data.errors` is the canonical location the SPA
        // reads; the top-level `errors` key is kept as well because Laravel's own
        // `assertJsonValidationErrors()` helper and the wider ecosystem look
        // there, and duplicating one small map is cheaper than surprising every
        // future test author.
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $errors = $exception->errors();

            return response()->json([
                'success' => false,
                // Laravel's default summary ends in "(and N more errors)", which
                // is noise to read in a form. Lead with the first real message.
                'message' => collect($errors)->flatten()->first() ?? $exception->getMessage(),
                'data' => [
                    'errors' => $errors,
                ],
                'errors' => $errors,
            ], $exception->status);
        });

        /*
         * Laravel's 401 is a bare `{"message": "Unauthenticated."}`, which is a
         * second response shape on an API that has already committed to one
         * envelope. `redirectGuestsTo(null)` in the middleware block is what
         * turns the redirect into a 401 in the first place; this only dresses
         * it. The wording says nothing about whether the account exists,
         * because the status code has not distinguished that yet either.
         */
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Sesi Anda tidak valid atau sudah berakhir. Silakan masuk kembali.',
                'data' => null,
            ], 401);
        });

        /*
         * A 404 that is *decided* by the application — someone else's record,
         * or a row that was deleted between the list and the click — must still
         * arrive in the one envelope, because the SPA unwraps `data` on every
         * response and a bare Laravel `{"message": "..."}` is a different shape
         * it has never seen.
         *
         * Registering this on NotFoundHttpException also covers the
         * ModelNotFoundException the handler converts it into, which is how
         * the wali registration detail endpoint answers a registration that
         * belongs to another guardian. The wording is deliberately flat: the
         * response must not distinguish "does not exist" from "not yours".
         */
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Data yang diminta tidak ditemukan.',
                'data' => null,
            ], 404);
        });
    })->create();
