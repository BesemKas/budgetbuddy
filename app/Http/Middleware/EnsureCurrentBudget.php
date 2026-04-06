<?php

namespace App\Http\Middleware;

use App\Services\CurrentBudget;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentBudget
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            app(CurrentBudget::class)->bootstrapSessionIfNeeded($request->user());
        }

        return $next($request);
    }
}
