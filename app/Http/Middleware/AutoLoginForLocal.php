<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AutoLoginForLocal
{
    public function handle(Request $request, Closure $next)
    {
        // Only auto-login in local environment
        if (app()->environment('local') && ! Auth::check()) {
            // Find or create the admin user
            $user = User::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Admin User',
                    'password' => Hash::make('password'),
                ]
            );

            Auth::login($user);
        }

        return $next($request);
    }
}
