<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departemen', function (Blueprint $table) {
            $table->id();
            $table->string('kode_departemen', 30)->unique();
            $table->string('nama_departemen', 100);
        });

        Schema::create('unit_organisasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departemen_id')->constrained('departemen')->cascadeOnDelete();
            $table->string('nama_unit', 100);
            $table->enum('tipe_unit', ['DIVISI', 'UNIT_KERJA', 'PERWAKILAN'])->default('UNIT_KERJA');
            $table->unique(['departemen_id', 'nama_unit']);
        });

        Schema::create('golongan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_golongan', 10)->unique();
        });

        Schema::create('bank', function (Blueprint $table) {
            $table->id();
            $table->enum('nama_bank', ['BRI', 'BNI'])->unique();
            $table->unsignedTinyInteger('panjang_digit');
        });

        Schema::create('jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jabatan', 100)->unique();
            $table->enum('kategori', ['UMUM', 'SATPAM'])->default('UMUM');
        });

        Schema::create('jabatan_borong', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jabatan_borong', 50)->unique();
        });

        Schema::create('jabatan_distribusi', function (Blueprint $table) {
            $table->id();
            $table->string('kode_jabatan', 20)->unique();
            $table->string('nama_jabatan', 100);
        });

        Schema::create('unit_kota', function (Blueprint $table) {
            $table->id();
            $table->string('nama_unit_kota', 20)->unique();
        });

        Schema::create('kota_perwakilan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kota_id')->constrained('unit_kota');
            $table->string('nama_kota', 100)->unique();
        });

        Schema::create('area', function (Blueprint $table) {
            $table->id();
            $table->string('nama_area', 100)->unique();
        });

        Schema::create('pengguna', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password_hash');
            $table->string('nama_pengguna', 100);
            $table->enum('role', ['SUPERADMIN', 'ADMIN_HR', 'ATASAN'])->default('ATASAN');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('atasan_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('pengguna')->cascadeOnDelete();
            $table->foreignId('unit_organisasi_id')->constrained('unit_organisasi')->cascadeOnDelete();
            $table->unique(['pengguna_id', 'unit_organisasi_id']);
        });

        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->unique();
            $table->char('nik_ktp', 16)->unique();
            $table->string('nama_lengkap', 150);
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->date('tgl_lahir');
            $table->enum('golongan_darah', ['A', 'B', 'AB', 'O', '-'])->default('-');
            $table->string('pendidikan_terakhir', 30)->nullable();
            $table->string('jurusan', 100)->nullable();
            $table->string('no_wa', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->enum('status_pernikahan', ['BELUM_KAWIN', 'KAWIN', 'CERAI_HIDUP', 'CERAI_MATI'])->default('BELUM_KAWIN');
            $table->string('disabilitas', 100)->nullable();

            $table->enum('tipe_karyawan', ['BULANAN', 'HARIAN', 'BORONG', 'DISTRIBUSI']);
            $table->enum('ket_karyawan', ['TETAP', 'TIDAK_TETAP', 'KONTRAK'])->default('TETAP');

            $table->foreignId('departemen_id')->constrained('departemen');
            $table->foreignId('unit_organisasi_id')->constrained('unit_organisasi');
            $table->foreignId('golongan_id')->nullable()->constrained('golongan');
            $table->foreignId('jabatan_id')->nullable()->constrained('jabatan');
            $table->foreignId('jabatan_borong_id')->nullable()->constrained('jabatan_borong');
            $table->foreignId('jabatan_distribusi_id')->nullable()->constrained('jabatan_distribusi');
            $table->foreignId('kota_perwakilan_id')->nullable()->constrained('kota_perwakilan');
            $table->foreignId('area_id')->nullable()->constrained('area');

            $table->date('tgl_masuk_kerja')->nullable();
            $table->date('tgl_penetapan')->nullable();

            $table->foreignId('bank_id')->nullable()->constrained('bank');
            $table->string('no_rekening', 25)->nullable();
            $table->string('nama_pemilik_rekening', 150)->nullable();

            $table->string('no_bpjs_kesehatan', 25)->nullable();
            $table->string('no_bpjs_ketenagakerjaan', 25)->nullable();
            $table->string('npwp', 25)->nullable();
            $table->string('no_anggota_koperasi', 30)->nullable();

            $table->enum('status_verifikasi', ['VALID', 'PERLU_CEK', 'TERVERIFIKASI'])->default('VALID');
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();
        });

        Schema::create('alamat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->enum('jenis_alamat', ['KTP', 'DOMISILI']);
            $table->text('alamat_lengkap');
            $table->string('kota', 100)->nullable();
        });

        Schema::create('pasangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->unique()->constrained('karyawan')->cascadeOnDelete();
            $table->string('nama_pasangan', 150);
            $table->string('pekerjaan_pasangan', 100)->nullable();
        });

        Schema::create('anak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->unsignedTinyInteger('anak_ke'); // 1 s/d 10
            $table->string('nama_anak', 150);
            $table->date('tgl_lahir_anak')->nullable();
            $table->string('pendidikan_anak', 50)->nullable();
            $table->unique(['karyawan_id', 'anak_ke']);
        });

        Schema::create('pengalaman_kerja_sebelum', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->string('nama_perusahaan', 150);
            $table->string('jabatan', 100)->nullable();
            $table->year('tahun_masuk')->nullable();
            $table->year('tahun_keluar')->nullable();
        });

        Schema::create('riwayat_pekerjaan_sukun', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->string('unit_bagian', 100);
            $table->string('jabatan', 100)->nullable();
            $table->date('tgl_mulai')->nullable();
            $table->date('tgl_selesai')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pekerjaan_sukun');
        Schema::dropIfExists('pengalaman_kerja_sebelum');
        Schema::dropIfExists('anak');
        Schema::dropIfExists('pasangan');
        Schema::dropIfExists('alamat');
        Schema::dropIfExists('karyawan');
        Schema::dropIfExists('atasan_unit');
        Schema::dropIfExists('pengguna');
        Schema::dropIfExists('area');
        Schema::dropIfExists('kota_perwakilan');
        Schema::dropIfExists('unit_kota');
        Schema::dropIfExists('jabatan_distribusi');
        Schema::dropIfExists('jabatan_borong');
        Schema::dropIfExists('jabatan');
        Schema::dropIfExists('bank');
        Schema::dropIfExists('golongan');
        Schema::dropIfExists('unit_organisasi');
        Schema::dropIfExists('departemen');
    }
};