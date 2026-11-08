<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $pengguna = DB::table('pengguna')
            ->where('username', $data['username'])
            ->where('is_active', true)
            ->first();

        if (! $pengguna || ! Hash::check($data['password'], $pengguna->password_hash)) {
            return back()
                ->withErrors([
                    'login' => 'Username atau password salah.',
                ])
                ->withInput($request->only('username'));
        }

        $request->session()->regenerate();

        session([
            'pengguna_id' => $pengguna->id,
            'pengguna_username' => $pengguna->username,
            'pengguna_nama' => $pengguna->nama_pengguna,
            'pengguna_role' => $pengguna->role,
        ]);

        if (in_array($pengguna->role, ['SUPERADMIN', 'ADMIN_HR'], true)) {
            return redirect()->route('hrd.dashboard');
        }

        return redirect()->route('atasan.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->flush();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('sukses', 'Anda sudah logout.');
    }
}