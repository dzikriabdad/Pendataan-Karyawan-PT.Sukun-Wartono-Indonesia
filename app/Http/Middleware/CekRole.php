<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CekRole
{
    public function handle(
        Request $request,
        Closure $next,
        ...$roles
    ): Response {
        $role = strtoupper((string) session('pengguna_role'));

        // Normalisasi role lama jika diperlukan (misal adminhr dianggap ADMIN_HR)
        if ($role === 'ADMINHR' || $role === 'ADMIN_HR') {
            $roles = array_merge($roles, ['ADMINHR', 'ADMIN_HR']);
        }

        if (! in_array($role, $roles, true)) {
            abort(403, 'Anda tidak memiliki hak akses ke halaman ini.');
        }

        return $next($request);
    }
}