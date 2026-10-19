<?php

namespace App\Http\Middleware;

use App\Helpers\ApiTokenHelper;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-API-TOKEN');
        if (! is_string($header) || $header === '') {
            throw new AuthenticationException(__('exceptions.api_token_missing'));
        }
        if (! ApiTokenHelper::validation($header)) {
            throw new AuthenticationException(__('exceptions.api_token_validation_failed'));
        }

        return $next($request);
    }
}
