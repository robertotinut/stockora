<?php

namespace App\Http\Middleware;

use App\Support\StockoraLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = StockoraLocale::resolveForRequest($request);
        StockoraLocale::apply($locale);

        return $next($request);
    }
}
