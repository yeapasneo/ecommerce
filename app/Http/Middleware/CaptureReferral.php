<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Remember a referral code from any link (?ref=CODE) for 30 days, until the visitor signs up. */
class CaptureReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $code = $request->query('ref');
        if (is_string($code) && preg_match('/^[A-Za-z0-9]{4,12}$/', $code) && ! $request->user()) {
            $response->headers->setCookie(cookie('ref', strtoupper($code), 60 * 24 * 30));
        }

        return $response;
    }
}
