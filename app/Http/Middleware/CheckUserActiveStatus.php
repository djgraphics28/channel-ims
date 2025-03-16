<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActiveStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the user is authenticated
        if (Auth::check()) {
            // Check if the user is active
            if (!Auth::user()->is_active) {
                // If user is not active, log them out
                Auth::logout();
                // Redirect them to the login page with an error message
                return redirect()->route('login')->with('error', 'Your account is disabled. Please contact your admin support.');
            }
        }

        return $next($request);
    }
}
