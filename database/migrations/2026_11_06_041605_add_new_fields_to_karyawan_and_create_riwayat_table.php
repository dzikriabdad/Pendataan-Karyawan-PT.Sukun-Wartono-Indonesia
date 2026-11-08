<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('nama_instansi', 150)->nullable()->after('jurusan');
            $table->string('nilai', 50)->nullable()->after('nama_instansi');
            $table->string('nama_jabatan', 150)->nullable()->after('golongan_id');
            $table->enum('bersedia_transfer', ['YA', 'TIDAK'])->nullable()->after('no_rekening');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn([
                'nama_instansi', 
                'nilai', 
                'nama_jabatan', 
                'bersedia_transfer'
            ]);
        });
    }
};