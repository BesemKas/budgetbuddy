<?php

namespace App\Http\Middleware;

use App\Enums\SmartMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareSmartModeWithViews
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        View::share('smartMode', $user?->smart_mode ?? SmartMode::Standard);

        return $next($request);
    }
}
