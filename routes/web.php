<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PendataanController;

Route::get('/', function () {
    return redirect()->route('pendataan.create');
});

Route::get('/pendataan', [
    PendataanController::class,
    'create',
])->name('pendataan.create');

Route::post('/pendataan', [
    PendataanController::class,
    'store',
])->name('pendataan.store');

Route::get('/login', [
    AuthController::class,
    'showLogin',
])->name('login');

Route::post('/login', [
    AuthController::class,
    'login',
])->name('login.proses');

Route::post('/logout', [
    AuthController::class,
    'logout',
])->name('logout');

// Route khusus Dashboard & Aksi Admin HR / HRD Pusat / Superadmin (Termasuk Hapus, Detail, & Edit HRD)
Route::middleware(['cek.login', 'cek.role:SUPERADMIN,ADMIN_HR,HRDPUSAT'])->group(function () {
    Route::get('/hrd/dashboard', [
        PendataanController::class,
        'dashboard',
    ])->name('hrd.dashboard');

    Route::get('/pendataan/detail/{id}', [
        PendataanController::class,
        'detail',
    ])->name('pendataan.detail');

    Route::get('/hrd/karyawan/edit/{id}', [
        PendataanController::class,
        'editHrd',
    ])->name('hrd.karyawan.edit');

    Route::put('/hrd/karyawan/update/{id}', [
        PendataanController::class,
        'updateHrd',
    ])->name('hrd.karyawan.update');

    // Route untuk hapus data karyawan (bisa diakses oleh HRDPUSAT dan SUPERADMIN)
    Route::delete('/hrd/karyawan/{id}', [
        PendataanController::class,
        'destroy',
    ])->name('pendataan.destroy');

    // Route Import & Download Template Excel HRD
    Route::post('/hrd/karyawan/import', [
        PendataanController::class,
        'importExcel',
    ])->name('pendataan.import');

    Route::get('/hrd/karyawan/template', [
        PendataanController::class,
        'downloadTemplate',
    ])->name('pendataan.template');
});

// Route khusus Dashboard Atasan / Kepala Bagian (Tanpa akses ke detail karyawan)
Route::middleware(['cek.login', 'cek.role:ATASAN'])->group(function () {
    Route::get('/atasan/dashboard', [
        PendataanController::class,
        'dashboardAtasan',
    ])->name('atasan.dashboard');
});

// Route untuk Edit Mandiri oleh Karyawan (tanpa share token manual)
Route::get('/pendataan/ubah-data', [
    PendataanController::class,
    'showMandiriForm',
])->name('pendataan.ubah-data');

Route::post('/pendataan/ubah-data', [
    PendataanController::class,
    'verifyMandiri',
])->name('pendataan.ubah-data.verify');

Route::get('/pendataan/edit/{token}', [
    PendataanController::class,
    'editByToken',
])->name('pendataan.edit.token');

Route::put('/pendataan/update/{token}', [
    PendataanController::class,
    'updateByToken',
])->name('pendataan.update.token');