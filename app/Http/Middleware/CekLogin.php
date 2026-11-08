<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CekLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('pengguna_id')) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'login' => 'Silakan login terlebih dahulu.',
                ]);
        }

        return $next($request);
    }
}