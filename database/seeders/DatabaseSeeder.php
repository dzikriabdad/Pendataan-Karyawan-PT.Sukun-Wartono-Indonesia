<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ===== 1. DEPARTEMEN & UNIT ORGANISASI DARI jikri.csv =====
        $path = base_path('jikri.csv');

        if (! is_file($path)) {
            throw new \RuntimeException(
                'File jikri.csv tidak ditemukan di root project: '.$path
            );
        }

        $fh = fopen($path, 'r');

        if ($fh === false) {
            throw new \RuntimeException('Gagal membuka jikri.csv.');
        }

        $grup = [];

        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($row) === 1 && isset($row[0])) {
                $row = str_getcsv($row[0], ',', '"', '\\');
            }

            if (count($row) < 2) {
                continue;
            }

            $dep = trim($row[0], " \t\n\r\0\x0B\xEF\xBB\xBF");
            $unit = trim($row[1]);

            if ($dep === 'nama_departemen' && $unit === 'nama_divisi') {
                continue;
            }

            if ($dep === '' || $unit === '') {
                continue;
            }

            $grup[$dep][] = $unit;
        }

        fclose($fh);

        if (empty($grup)) {
            throw new \RuntimeException(
                'Tidak ada data departemen/unit yang terbaca dari jikri.csv. Periksa format file.'
            );
        }

        $jumlahBarisUnit = array_sum(array_map('count', $grup));

        $this->command?->info(
            'CSV terbaca: '.count($grup).' departemen, '.
            $jumlahBarisUnit.' baris unit.'
        );

        // HATI-HATI: bagian ini menghapus data lama sebelum impor ulang.
        DB::table('atasan_unit')->delete();
        // Hapus akun atasan agar tidak menumpuk saat seeder dijalankan ulang
        DB::table('pengguna')->where('role', 'ATASAN')->delete(); 
        DB::table('karyawan')->delete();
        DB::table('unit_organisasi')->delete();
        DB::table('departemen')->delete();

        foreach ($grup as $dep => $units) {
            $kode = strtoupper(
                preg_replace('/[^A-Za-z0-9]+/', '_', $dep)
            );

            $depId = DB::table('departemen')->insertGetId([
                'kode_departemen' => $kode,
                'nama_departemen' => $dep,
            ]);

            foreach (array_unique($units) as $unit) {
                DB::table('unit_organisasi')->insert([
                    'departemen_id' => $depId,
                    'nama_unit' => $unit,
                    'tipe_unit' => (
                        $dep === 'DISTRIBUSI' &&
                        str_starts_with($unit, 'PERWAKILAN')
                    ) ? 'PERWAKILAN' : 'UNIT_KERJA',
                ]);
            }
        }

        // ===== 2. REFERENSI LAIN =====

        if (DB::table('golongan')->count() === 0) {
            foreach ([
                'I-A', 'I-B', 'I-C', 'I-D',
                'II-A', 'II-B', 'II-C', 'II-D',
                'III-A', 'III-B', 'III-C', 'III-D',
                'IV-A', 'IV-B', 'IV-C', 'IV-D',
            ] as $golongan) {
                DB::table('golongan')->insert([
                    'kode_golongan' => $golongan,
                ]);
            }
        }

        if (DB::table('bank')->count() === 0) {
            DB::table('bank')->insert([
                ['nama_bank' => 'BRI', 'panjang_digit' => 15],
                ['nama_bank' => 'BNI', 'panjang_digit' => 10],
            ]);
        }

        if (DB::table('jabatan')->count() === 0) {
            DB::table('jabatan')->insert([
                ['nama_jabatan' => 'Staff', 'kategori' => 'UMUM'],
                ['nama_jabatan' => 'Admin Bagian', 'kategori' => 'UMUM'],
                ['nama_jabatan' => 'Kepala Bagian / Mandor', 'kategori' => 'UMUM'],
                ['nama_jabatan' => 'Operator / Pekerja Umum', 'kategori' => 'UMUM'],
                ['nama_jabatan' => 'Sopir / Driver', 'kategori' => 'UMUM'],
                ['nama_jabatan' => 'Kebersihan / Umum', 'kategori' => 'UMUM'],
                ['nama_jabatan' => 'Anggota Satpam', 'kategori' => 'SATPAM'],
                ['nama_jabatan' => 'Danru Satpam', 'kategori' => 'SATPAM'],
                ['nama_jabatan' => 'Koordinator Satpam', 'kategori' => 'SATPAM'],
            ]);
        }

        if (DB::table('jabatan_borong')->count() === 0) {
            foreach ([
                'Klobot', 'Bos SKT', 'Press SKT', 'Bos SKM', 'Press SKM',
                'Borong Slop', 'Borong Ocek', 'Borong Angkut', 'Borong Nyutir',
            ] as $jabatanBorong) {
                DB::table('jabatan_borong')->insert([
                    'nama_jabatan_borong' => $jabatanBorong,
                ]);
            }
        }

        if (DB::table('jabatan_distribusi')->count() === 0) {
            $jabatanDistribusi = [
                ['ADMIN', 'Admin'], ['AM', 'Area Manager'], ['DRIVER', 'Driver'],
                ['HELPER', 'Helper'], ['JAGA GUDANG', 'Jaga Gudang'], ['KANVAS', 'Kanvas'],
                ['KULI GUDANG', 'Kuli Gudang'], ['OB', 'Office Boy'], ['OJT AM', 'OJT Area Manager'],
                ['OJT TL', 'OJT Team Leader'], ['PENJAGA MALAM', 'Penjaga Malam'], ['PIC', 'PIC'],
                ['PLT SS', 'Pelaksana Tugas SS'], ['RM', 'Regional Manager'], ['SMP', 'SMP'],
                ['SPM', 'Salesman'], ['SPM CCO', 'SPM CCO'], ['SS', 'Supervisor Senior'],
                ['SWS', 'Senior WS'], ['TL', 'Team Leader'], ['TL CCO', 'TL CCO'],
                ['TRAINER', 'Trainer'], ['WS', 'Wiraniaga Senior'],
            ];

            foreach ($jabatanDistribusi as $item) {
                DB::table('jabatan_distribusi')->insert([
                    'kode_jabatan' => $item[0],
                    'nama_jabatan' => $item[1],
                ]);
            }
        }

        if (DB::table('unit_kota')->count() === 0) {
            $unit1 = DB::table('unit_kota')->insertGetId(['nama_unit_kota' => 'UNIT 1']);
            $unit2 = DB::table('unit_kota')->insertGetId(['nama_unit_kota' => 'UNIT 2']);
            $unit3 = DB::table('unit_kota')->insertGetId(['nama_unit_kota' => 'UNIT 3']);

            $kotaUnit1 = [
                'BABAT', 'BALI', 'BANYUWANGI', 'CILACAP', 'DEMAK', 'JEMBER', 'JOMBANG',
                'KDS-JEPARA & PATI', 'KUDUS', 'MADIUN', 'MADURA', 'MAGELANG', 'MALANG',
                'NGANJUK', 'NGAWI', 'PEKALONGAN', 'PROBOLINGGO', 'PURWOKERTO', 'SEMARANG',
                'SOLO', 'SURABAYA', 'TEGAL', 'TULUNGAGUNG', 'WONOGIRI', 'WONOSOBO', 'YOGYAKARTA',
            ];

            $kotaUnit2 = [
                'BANDUNG', 'BEKASI', 'BOGOR', 'CIREBON', 'JAKARTA', 'LAMPUNG', 'SERANG',
                'SUBANG', 'SUKABUMI', 'SUMEDANG', 'TANGERANG', 'TASIKMALAYA',
            ];

            $kotaUnit3 = [
                'BANJARMASIN', 'INDRAGIRI HULU', 'JAMBI', 'LOMBOK', 'PANGKALANBUN', 'PEKANBARU', 'SAROLANGUN',
            ];

            foreach ($kotaUnit1 as $kota) DB::table('kota_perwakilan')->insert(['unit_kota_id' => $unit1, 'nama_kota' => $kota]);
            foreach ($kotaUnit2 as $kota) DB::table('kota_perwakilan')->insert(['unit_kota_id' => $unit2, 'nama_kota' => $kota]);
            foreach ($kotaUnit3 as $kota) DB::table('kota_perwakilan')->insert(['unit_kota_id' => $unit3, 'nama_kota' => $kota]);
        }

        if (DB::table('area')->count() === 0) {
            foreach ([
                'JAWA TENGAH', 'JAWA TIMUR', 'JAWA BARAT & JABODETABEK', 'LUAR JAWA / SUMATERA / KALIMANTAN / BALI',
            ] as $namaArea) {
                DB::table('area')->insert([
                    'nama_area' => $namaArea,
                ]);
            }
        }

        // ===== 3. PEMBUATAN AKUN PENGGUNA =====
        
        // Buat atau update akun khusus Admin HR
        DB::table('pengguna')->updateOrInsert(
            ['username' => 'adminhr'],
            [
                'password_hash' => Hash::make('sukun123'),
                'nama_pengguna' => 'Admin HRD Sukun',
                'role'          => 'ADMIN_HR',
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]
        );

        // Buat otomatis akun untuk setiap Atasan Unit berdasarkan data yang baru di-seed
        $units = DB::table('unit_organisasi')->orderBy('id')->get();

        foreach ($units as $unit) {
            $namaUnit = trim($unit->nama_unit);
            
            // MENGGUNAKAN Str::slug() AGAR LEBIH AMAN & RAPI
            $slug = Str::slug($namaUnit, '_');
            
            $baseUsername = substr('kepala_' . $slug, 0, 45);
            $username = $baseUsername;

            // Cek jika username sudah ada (bentrok), tambahkan angka 1, 2, dst
            $counter = 1;
            while (DB::table('pengguna')->where('username', $username)->exists()) {
                $suffix = '_' . $counter;
                $username = substr($baseUsername, 0, 50 - strlen($suffix)) . $suffix;
                $counter++;
            }

            // Insert data pengguna ke database
            $penggunaId = DB::table('pengguna')->insertGetId([
                'username'      => $username,
                'password_hash' => Hash::make('password123'), // Default password
                'nama_pengguna' => 'Kepala ' . $namaUnit,
                'role'          => 'ATASAN',
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Relasikan akun atasan ini dengan tabel unit_organisasi
            DB::table('atasan_unit')->insert([
                'pengguna_id'        => $penggunaId,
                'unit_organisasi_id' => $unit->id,
            ]);
        }
        
        $this->command?->info('Berhasil membuat akun Admin HR dan ' . count($units) . ' akun Atasan Unit.');
    }
}