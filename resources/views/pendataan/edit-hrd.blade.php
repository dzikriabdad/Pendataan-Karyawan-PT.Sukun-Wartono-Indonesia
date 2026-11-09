<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Karyawan HRD - {{ $karyawan->nama_lengkap }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak]{display:none!important}
        body{background:#eef2f8}
        .lb{display:block;font-size:.875rem;font-weight:600;color:#334155}
        .req{color:#dc2626}
        .hint{margin-top:.35rem;font-size:.75rem;color:#64748b}
        .in{margin-top:.35rem;width:100%;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.6rem 1rem;font-size:.95rem;color:#0f172a}
        .in:focus{outline:none;border-color:#1d4ed8;box-shadow:0 0 0 3px rgba(29,78,216,.15)}
        .cd{border:1px solid #e2e8f0;background:#fff;border-radius:.75rem;padding:1.5rem;box-shadow:0 1px 3px rgba(10,42,94,.08)}
        .sb{border:1px solid #dbeafe;background:#eff6ff;border-radius:.5rem;padding:1rem}
    </style>
</head>
<body class="text-slate-800 antialiased py-10 px-4">

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Edit Data Karyawan (Panel HRD)</h1>
            <p class="text-xs text-slate-500 mt-1">HRD memiliki hak penuh untuk mengubah seluruh data termasuk Nama, NIK KTP, Pendidikan, dan Alamat KTP.</p>
        </div>
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/dashboard') }}" class="rounded-lg bg-slate-800 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-700 transition">
            &larr; Batal & Kembali
        </a>
    </div>

    @if(session('sukses'))
        <div class="rounded-lg bg-emerald-50 p-4 text-emerald-800 text-sm font-semibold border border-emerald-200">
            {{ session('sukses') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg bg-red-50 p-4 text-red-800 text-sm font-semibold border border-red-200">
            <p>Terdapat data yang belum lengkap atau salah:</p>
            <ul class="list-disc list-inside mt-1 font-normal text-xs">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ url('/hrd/karyawan/update/' . $karyawan->id) }}" method="POST" class="space-y-6" x-data="{
        tipe: '{{ old('tipe_karyawan', $karyawan->tipe_karyawan) }}',
        unitId: '{{ old('unit_organisasi_id', $karyawan->unit_organisasi_id) }}',
        jabatanDipilih: '{{ old('nama_jabatan', $karyawan->nama_jabatan) }}',
        nikah: '{{ old('status_pernikahan', $karyawan->status_pernikahan) }}',
        anakN: {{ (int) old('jumlah_anak', count($anak ?? [])) }},
        anakList: @js(array_values(old('anak', $anak->map(fn($a) => [
            'nama_anak' => $a->nama_anak,
            'tgl_lahir_anak' => $a->tgl_lahir_anak,
            'pendidikan_anak' => $a->pendidikan_anak,
        ])->toArray() ?? []))),
        pendList: @js(array_values(old('riwayat_pendidikan', count($pendidikan ?? []) > 0 ? $pendidikan->map(fn($p) => [
            'jenjang' => $p->jenjang,
            'nama_instansi' => $p->nama_instansi,
            'jurusan' => $p->jurusan,
            'nilai_ipk' => $p->nilai_ipk,
        ])->toArray() : [['jenjang' => 'SD', 'nama_instansi' => '', 'jurusan' => '', 'nilai_ipk' => '']]))),
        sukunList: @js(array_values(old('riwayat_sukun', count($riwayatSukun ?? []) > 0 ? $riwayatSukun->map(fn($r) => [
            'bagian' => $r->unit_bagian,
            'jabatan' => $r->jabatan,
            'tahun_mulai' => $r->tgl_mulai ? substr($r->tgl_mulai, 0, 4) : '',
            'tahun_selesai' => $r->tgl_selesai ? substr($r->tgl_selesai, 0, 4) : '',
        ])->toArray() : [['bagian' => '', 'jabatan' => '', 'tahun_mulai' => '', 'tahun_selesai' => '']]))),
        luarList: @js(array_values(old('riwayat_luar', count($riwayatLuar ?? []) > 0 ? $riwayatLuar->map(fn($p) => [
            'perusahaan' => $p->nama_perusahaan,
            'jabatan' => $p->jabatan,
            'tahun_masuk' => $p->tahun_masuk,
            'tahun_keluar' => $p->tahun_keluar,
        ])->toArray() : [['perusahaan' => '', 'jabatan' => '', 'tahun_masuk' => '', 'tahun_keluar' => '']])))
    }">
        @csrf
        @method('PUT')

        {{-- 1. PEKERJAAN, BAGIAN & JABATAN --}}
        <div class="cd space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b pb-2">1. Pekerjaan, Bagian & Jabatan</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="lb">Jenis Karyawan <span class="req">*</span></label>
                    <select name="tipe_karyawan" x-model="tipe" class="in" required>
                        <option value="BULANAN">Bulanan</option>
                        <option value="HARIAN">Harian</option>
                        <option value="BORONG">Borong</option>
                        <option value="DISTRIBUSI">Distribusi</option>
                    </select>
                </div>
                <div>
                    <label class="lb">Status Karyawan <span class="req">*</span></label>
                    <select name="ket_karyawan" class="in" required>
                        <option value="TETAP" @selected($karyawan->ket_karyawan === 'TETAP')>TETAP</option>
                        <option value="HARIAN_LEPAS" @selected($karyawan->ket_karyawan === 'HARIAN_LEPAS' || $karyawan->ket_karyawan === 'TIDAK_TETAP')>HARIAN LEPAS</option>
                        <option value="KONTRAK" @selected($karyawan->ket_karyawan === 'KONTRAK')>KONTRAK</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="lb">Bagian / Unit Kerja <span class="req">*</span></label>
                    <select name="unit_organisasi_id" x-model="unitId" class="in" required>
                        <option value="">-- Pilih Unit Kerja --</option>
                        @foreach ($departemen as $dep)
                            <optgroup label="{{ $dep->nama_departemen }}">
                                @foreach ($unitOrganisasi->where('departemen_id', $dep->id) as $u)
                                    <option value="{{ $u->id }}" @selected($karyawan->unit_organisasi_id == $u->id)>
                                        {{ $u->nama_unit }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="lb">Nama Jabatan <span class="req">*</span></label>
                    <input type="text" name="nama_jabatan" x-model="jabatanDipilih" class="in uppercase" placeholder="Contoh: AM JATIM 2, RM REGIONAL 1, SALES KANVAS, PELAKSANA" required>
                </div>
            </div>
        </div>

        {{-- 2. DATA DIRI, ORANG TUA & PENDIDIKAN (SEMUA BISA DIEDIT HRD) --}}
        <div class="cd space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b pb-2">2. Data Diri, Orang Tua & Pendidikan</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="lb">Nama Lengkap Sesuai KTP <span class="req">*</span></label>
                    <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap', $karyawan->nama_lengkap) }}" class="in uppercase" required>
                </div>
                <div>
                    <label class="lb">NIK KTP (16 Angka) <span class="req">*</span></label>
                    <input type="text" name="nik_ktp" value="{{ old('nik_ktp', $karyawan->nik_ktp) }}" maxlength="16" inputmode="numeric" class="in font-mono" required>
                </div>
                <div>
                    <label class="lb">NIP</label>
                    <input type="text" name="nip" value="{{ old('nip', $karyawan->nip) }}" class="in font-mono" placeholder="Kosongkan jika baru/kontrak">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-blue-50/60 p-3 rounded-lg border border-blue-200">
                <div>
                    <label class="lb text-blue-900">Nama Ayah Kandung <span class="req">*</span></label>
                    <input type="text" name="nama_ayah" value="{{ old('nama_ayah', $karyawan->nama_ayah) }}" class="in uppercase" required>
                </div>
                <div>
                    <label class="lb text-blue-900">Nama Ibu Kandung <span class="req">*</span></label>
                    <input type="text" name="nama_ibu" value="{{ old('nama_ibu', $karyawan->nama_ibu) }}" class="in uppercase" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="lb">Jenis Kelamin <span class="req">*</span></label>
                    <select name="jenis_kelamin" class="in" required>
                        <option value="L" @selected($karyawan->jenis_kelamin === 'L')>Laki-laki</option>
                        <option value="P" @selected($karyawan->jenis_kelamin === 'P')>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="lb">Tanggal Lahir <span class="req">*</span></label>
                    <input type="date" name="tgl_lahir" value="{{ old('tgl_lahir', $karyawan->tgl_lahir) }}" class="in" required>
                </div>
                <div>
                    <label class="lb">Golongan Darah</label>
                    <select name="golongan_darah" class="in">
                        @foreach(['-'=>'Tidak diketahui', 'A'=>'A', 'B'=>'B', 'AB'=>'AB', 'O'=>'O'] as $gV => $gL)
                            <option value="{{ $gV }}" @selected($karyawan->golongan_darah === $gV)>{{ $gL }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="lb">Disabilitas (Jika ada)</label>
                    <input type="text" name="disabilitas" value="{{ old('disabilitas', $karyawan->disabilitas) }}" class="in" placeholder="Kosongkan jika normal">
                </div>
                <div>
                    <label class="lb">NPWP</label>
                    <input type="text" name="npwp" value="{{ old('npwp', $karyawan->npwp) }}" class="in font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="lb">Nomor HP / WhatsApp</label>
                    <input type="text" name="no_wa" value="{{ old('no_wa', $karyawan->no_wa) }}" class="in" placeholder="08xxxxxxxxxx">
                </div>
                <div>
                    <label class="lb">Email</label>
                    <input type="email" name="email" value="{{ old('email', $karyawan->email) }}" class="in">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="lb">Alamat Lengkap Sesuai KTP <span class="req">*</span></label>
                    <textarea name="alamat_ktp" rows="3" class="in" required>{{ old('alamat_ktp', $alamatKtp->alamat_lengkap ?? '') }}</textarea>
                </div>
                <div>
                    <label class="lb">Alamat Domisili Saat Ini <span class="req">*</span></label>
                    <textarea name="alamat_domisili" rows="3" class="in" required>{{ old('alamat_domisili', $alamatDom->alamat_lengkap ?? '') }}</textarea>
                </div>
            </div>
        </div>

        {{-- 3. REPEATER RIWAYAT PENDIDIKAN (SD S/D TERAKHIR TANPA TAHUN) --}}
        <div class="cd space-y-4">
            <div class="flex items-center justify-between border-b pb-2">
                <div>
                    <h3 class="text-base font-bold text-slate-900">3. Riwayat Pendidikan (SD s/d Terakhir)</h3>
                    <p class="text-xs text-slate-500">Isi riwayat jenjang pendidikan dari SD hingga pendidikan terakhir.</p>
                </div>
                <button type="button" @click="pendList.push({ jenjang: '', nama_instansi: '', jurusan: '', nilai_ipk: '' })" class="rounded bg-blue-100 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-200">
                    + Tambah Pendidikan
                </button>
            </div>

            <template x-for="(rp, index) in pendList" :key="index">
                <div class="sb space-y-3 relative bg-white border border-slate-200 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold uppercase text-slate-400" x-text="'Pendidikan #' + (index + 1)"></span>
                        <button type="button" x-show="pendList.length > 1" @click="pendList.splice(index, 1)" class="text-xs text-red-500 hover:underline">Hapus</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="lb">Jenjang Pendidikan</label>
                            <select :name="'riwayat_pendidikan[' + index + '][jenjang]'" x-model="rp.jenjang" class="in">
                                <option value="SD">SD</option>
                                <option value="SMP">SMP / MTs</option>
                                <option value="SMA/SMK">SMA / SMK / MA</option>
                                <option value="D1">D1</option>
                                <option value="D2">D2</option>
                                <option value="D3">D3</option>
                                <option value="D4">D4</option>
                                <option value="S1">S1</option>
                                <option value="S2">S2</option>
                                <option value="S3">S3</option>
                            </select>
                        </div>
                        <div>
                            <label class="lb">Nama Sekolah / Instansi / Universitas</label>
                            <input type="text" :name="'riwayat_pendidikan[' + index + '][nama_instansi]'" x-model="rp.nama_instansi" class="in uppercase" placeholder="Nama Sekolah / Kampus">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="lb">Jurusan / Program Studi</label>
                            <input type="text" :name="'riwayat_pendidikan[' + index + '][jurusan]'" x-model="rp.jurusan" class="in uppercase" placeholder="Jurusan (Kosongkan jika SD/SMP)">
                        </div>
                        <div>
                            <label class="lb">Nilai Akhir / IPK</label>
                            <input type="text" :name="'riwayat_pendidikan[' + index + '][nilai_ipk]'" x-model="rp.nilai_ipk" class="in" placeholder="Nilai / IPK">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- 4. REKENING & ADMINISTRASI --}}
        <div class="cd space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b pb-2">4. Rekening & Administrasi</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="lb">Nomor Rekening</label>
                    <input type="text" name="no_rekening" value="{{ old('no_rekening', $karyawan->no_rekening) }}" class="in font-mono" placeholder="BNI (10) / BRI (15)">
                </div>
                <div>
                    <label class="lb">Atas Nama Rekening</label>
                    <input type="text" name="nama_pemilik_rekening" value="{{ old('nama_pemilik_rekening', $karyawan->nama_pemilik_rekening) }}" class="in uppercase">
                </div>
                <div>
                    <label class="lb">Kesediaan Transfer Gaji</label>
                    <select name="bersedia_transfer" class="in">
                        <option value="YA" @selected($karyawan->bersedia_transfer === 'YA')>Iya, Bersedia</option>
                        <option value="TIDAK" @selected($karyawan->bersedia_transfer === 'TIDAK')>Tidak</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="lb">No. BPJS Kesehatan</label>
                    <input type="text" name="no_bpjs_kesehatan" value="{{ old('no_bpjs_kesehatan', $karyawan->no_bpjs_kesehatan) }}" class="in">
                </div>
                <div>
                    <label class="lb">No. BPJS Ketenagakerjaan</label>
                    <input type="text" name="no_bpjs_ketenagakerjaan" value="{{ old('no_bpjs_ketenagakerjaan', $karyawan->no_bpjs_ketenagakerjaan) }}" class="in">
                </div>
                <div>
                    <label class="lb">No. Anggota Koperasi KKSG</label>
                    <input type="text" name="no_anggota_koperasi" value="{{ old('no_anggota_koperasi', $karyawan->no_anggota_koperasi) }}" class="in">
                </div>
            </div>
        </div>

        {{-- 5. RIWAYAT PEKERJAAN DI SUKUN (JENJANG KARIR) --}}
        <div class="cd space-y-4">
            <div class="flex items-center justify-between border-b pb-2">
                <div>
                    <h3 class="text-base font-bold text-slate-900">5. Riwayat Pekerjaan di PT. Sukun (Jenjang Karir)</h3>
                    <p class="text-xs text-slate-500">Riwayat mutasi bagian/jabatan karyawan selama bekerja di Sukun.</p>
                </div>
                <button type="button" @click="sukunList.push({ bagian: '', jabatan: '', tahun_mulai: '', tahun_selesai: '' })" class="rounded bg-blue-100 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-200">
                    + Tambah Riwayat
                </button>
            </div>

            <template x-for="(rw, index) in sukunList" :key="index">
                <div class="sb space-y-3 relative bg-white border border-slate-200 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold uppercase text-slate-400" x-text="'Riwayat Sukun #' + (index + 1)"></span>
                        <button type="button" @click="sukunList.splice(index, 1)" class="text-xs text-red-500 hover:underline">Hapus</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="lb">Bagian / Unit Kerja</label>
                            <input type="text" :name="'riwayat_sukun[' + index + '][bagian]'" x-model="rw.bagian" class="in uppercase">
                        </div>
                        <div>
                            <label class="lb">Jabatan</label>
                            <input type="text" :name="'riwayat_sukun[' + index + '][jabatan]'" x-model="rw.jabatan" class="in uppercase">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="lb">Tahun Mulai</label>
                            <input type="text" :name="'riwayat_sukun[' + index + '][tahun_mulai]'" x-model="rw.tahun_mulai" class="in" placeholder="Contoh: 2019">
                        </div>
                        <div>
                            <label class="lb">Tahun Selesai / Sekarang</label>
                            <input type="text" :name="'riwayat_sukun[' + index + '][tahun_selesai]'" x-model="rw.tahun_selesai" class="in" placeholder="Contoh: 2022">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- 6. PENGALAMAN KERJA SEBELUM DI SUKUN --}}
        <div class="cd space-y-4">
            <div class="flex items-center justify-between border-b pb-2">
                <div>
                    <h3 class="text-base font-bold text-slate-900">6. Pengalaman Kerja Sebelum di Sukun</h3>
                    <p class="text-xs text-slate-500">Riwayat pekerjaan di perusahaan lain sebelum masuk PT. Sukun.</p>
                </div>
                <button type="button" @click="luarList.push({ perusahaan: '', jabatan: '', tahun_masuk: '', tahun_keluar: '' })" class="rounded bg-blue-100 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-200">
                    + Tambah Pengalaman
                </button>
            </div>

            <template x-for="(rwl, index) in luarList" :key="index">
                <div class="sb space-y-3 relative bg-white border border-slate-200 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold uppercase text-slate-400" x-text="'Pengalaman Luar #' + (index + 1)"></span>
                        <button type="button" @click="luarList.splice(index, 1)" class="text-xs text-red-500 hover:underline">Hapus</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="lb">Nama Perusahaan</label>
                            <input type="text" :name="'riwayat_luar[' + index + '][perusahaan]'" x-model="rwl.perusahaan" class="in uppercase">
                        </div>
                        <div>
                            <label class="lb">Jabatan</label>
                            <input type="text" :name="'riwayat_luar[' + index + '][jabatan]'" x-model="rwl.jabatan" class="in uppercase">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="lb">Tahun Masuk</label>
                            <input type="text" :name="'riwayat_luar[' + index + '][tahun_masuk]'" x-model="rwl.tahun_masuk" class="in" placeholder="Contoh: 2017">
                        </div>
                        <div>
                            <label class="lb">Tahun Keluar</label>
                            <input type="text" :name="'riwayat_luar[' + index + '][tahun_keluar]'" x-model="rwl.tahun_keluar" class="in" placeholder="Contoh: 2020">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- 7. STATUS KELUARGA & ANAK --}}
        <div class="cd space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b pb-2">7. Status Keluarga & Anak</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="lb">Status Pernikahan</label>
                    <select name="status_pernikahan" x-model="nikah" class="in">
                        <option value="BELUM_KAWIN">Belum menikah</option>
                        <option value="KAWIN">Sudah menikah</option>
                        <option value="CERAI_HIDUP">Cerai hidup</option>
                        <option value="CERAI_MATI">Cerai mati</option>
                    </select>
                </div>
                <div x-show="nikah === 'KAWIN'" class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="lb">Nama Suami / Istri</label>
                        <input type="text" name="nama_pasangan" value="{{ old('nama_pasangan', $pasangan->nama_pasangan ?? '') }}" class="in uppercase">
                    </div>
                    <div>
                        <label class="lb">Pekerjaan Pasangan</label>
                        <input type="text" name="pekerjaan_pasangan" value="{{ old('pekerjaan_pasangan', $pasangan->pekerjaan_pasangan ?? '') }}" class="in">
                    </div>
                </div>
            </div>

            <div x-show="nikah !== 'BELUM_KAWIN'" class="pt-2">
                <label class="lb">Jumlah Anak (Maksimal 10)</label>
                <select name="jumlah_anak" x-model.number="anakN" class="in">
                    <option value="0">Tidak ada / 0 anak</option>
                    @for($n = 1; $n <= 10; $n++)
                        <option value="{{ $n }}">{{ $n }} anak</option>
                    @endfor
                </select>
            </div>

            <div class="space-y-4 pt-2" x-show="nikah !== 'BELUM_KAWIN' && anakN > 0">
                <template x-for="i in anakN" :key="i">
                    <div class="sb space-y-3 bg-white p-4 rounded-lg border border-slate-200">
                        <p class="text-sm font-semibold text-blue-900" x-text="'Anak ke-' + i"></p>
                        <div>
                            <label class="lb">Nama Lengkap Anak</label>
                            <input type="text" :name="'anak[' + i + '][nama_anak]'" x-model="anakList[i - 1] ? anakList[i - 1].nama_anak : ''" class="in uppercase" placeholder="Nama Lengkap Anak">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lb">Tanggal Lahir</label>
                                <input type="date" :name="'anak[' + i + '][tgl_lahir_anak]'" x-model="anakList[i - 1] ? anakList[i - 1].tgl_lahir_anak : ''" class="in">
                            </div>
                            <div>
                                <label class="lb">Pendidikan</label>
                                <select :name="'anak[' + i + '][pendidikan_anak]'" class="in" x-model="anakList[i - 1] ? anakList[i - 1].pendidikan_anak : 'BELUM SEKOLAH'">
                                    @foreach (['BELUM SEKOLAH'=>'Belum sekolah','TK'=>'TK / PAUD','SD'=>'SD','SMP'=>'SMP','SMA/SMK'=>'SMA / SMK','KULIAH'=>'Kuliah','BEKERJA'=>'Sudah bekerja'] as $v => $t)
                                        <option value="{{ $v }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="w-full rounded-lg bg-blue-600 px-5 py-3.5 text-base font-bold text-white hover:bg-blue-700 transition shadow">
                Simpan Seluruh Perubahan Karyawan
            </button>
        </div>
    </form>
</div>

</body>
</html>