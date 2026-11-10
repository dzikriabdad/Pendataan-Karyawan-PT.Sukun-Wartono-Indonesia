@php
$step = 1;

$g2 = [
    'tipe_karyawan',
    'ket_karyawan',
    'unit_organisasi_id',
    'nama_jabatan',
    'jabatan_borong_id',
    'jabatan_distribusi_id',
    'kota_perwakilan_id',
    'area_id',
    'golongan_id',
    'no_rekening',
    'bersedia_transfer',
];

$g3 = [
    'nama_ayah',
    'nama_ibu',
    'status_pernikahan',
    'nama_pasangan',
    'jumlah_anak',
    'anak',
];

$g4 = [
    'riwayat_luar',
    'riwayat_sukun',
    'tgl_masuk_tanggal',
    'tgl_masuk_bulan',
    'tgl_masuk_tahun',
];

foreach ($errors->keys() as $k) {
    if (in_array($k, $g2, true) || str_starts_with($k, 'bersedia')) {
        $step = max($step, 2);
    } elseif (in_array($k, $g3, true) || str_starts_with($k, 'anak')) {
        $step = max($step, 3);
    } elseif (in_array($k, $g4, true) || str_starts_with($k, 'riwayat_sukun') || str_starts_with($k, 'riwayat_luar')) {
        $step = max($step, 4);
    }
}
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pendataan Karyawan - PT. Sukun Wartono Indonesia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak]{display:none!important}
        body{background:#eef2f8}
        .topbar{background:linear-gradient(100deg,#0a2a5e,#123f8c 45%,#1d5fc2)}
        .logo{background:linear-gradient(135deg,#3b82f6,#1d4ed8)}
        .lb{display:block;font-size:.875rem;font-weight:600;color:#334155}
        .req{color:#dc2626}
        .hint{margin-top:.35rem;font-size:.75rem;color:#64748b}
        .in{margin-top:.35rem;width:100%;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.6rem 1rem;font-size:1rem;color:#0f172a}
        .in:focus{outline:none;border-color:#1d4ed8;box-shadow:0 0 0 3px rgba(29,78,216,.15)}
        .cd{border:1px solid #e2e8f0;background:#fff;border-radius:.75rem;padding:1.5rem;box-shadow:0 1px 3px rgba(10,42,94,.08)}
        .sb{border:1px solid #dbeafe;background:#eff6ff;border-radius:.5rem;padding:1rem}
        .bt{border-radius:.5rem;font-size:.875rem;font-weight:600;padding:.6rem 1.5rem}
        .bp{background:#1d4ed8;color:#fff}
        .bp:hover{background:#1e40af}
        .bl{border:1px solid #cbd5e1;background:#fff;color:#334155}
        .bl:hover{background:#f8fafc}
        .al{border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:.5rem;padding:.75rem 1rem;font-size:.875rem;font-weight:600}
        .accent{height:3px;width:56px;background:linear-gradient(90deg,#0a2a5e,#3b82f6);border-radius:2px}
    </style>
</head>

<body class="text-slate-800 antialiased">
<header class="topbar text-white">
    <div class="mx-auto flex h-20 max-w-3xl items-center justify-between px-4 sm:px-6">
        <div class="flex items-center gap-3">
            <div class="logo grid h-11 w-11 place-items-center rounded-xl">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <rect x="8" y="2" width="8" height="4" rx="1"/>
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <path d="M9 12h6M9 16h6"/>
                </svg>
            </div>
            <div class="leading-tight">
                <p class="text-base font-semibold">Sistem Pendataan Karyawan</p>
                <p class="text-xs text-blue-200">PT. Sukun Wartono Indonesia</p>
            </div>
        </div>
        <span class="hidden rounded-full border border-white/40 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-widest text-blue-100 sm:inline">
            INTERNAL
        </span>
    </div>
</header>

<main
    class="mx-auto max-w-3xl px-4 py-8 sm:px-6"
    x-data="formKaryawan({
        step: {{ $step }},
        isError: {{ $errors->any() ? 'true' : 'false' }},
        isSukses: {{ session('sukses') ? 'true' : 'false' }},
        errs: {},
        
        {{-- Langkah 1 --}}
        nik: @js(old('nik_ktp', '')),
        nip: @js(old('nip', '')),
        nama: @js(old('nama_lengkap', '')),
        jk: @js(old('jenis_kelamin', '')),
        tglLahir: @js(old('tgl_lahir', '')),
        golDarah: @js(old('golongan_darah', '-')),
        riwayatPendidikan: @js(array_values(old('riwayat_pendidikan', [['jenjang' => 'SD', 'nama_instansi' => '', 'jurusan' => '', 'nilai_ipk' => '']]))),
        wa: @js(old('no_wa', '')),
        emailForm: @js(old('email', '')),
        disabilitas: @js(old('disabilitas', '')),
        npwp: @js(old('npwp', '')),
        alamatKtp: @js(old('alamat_ktp', '')),
        alamatSama: {{ old('alamat_domisili') ? 'false' : 'true' }},
        alamatDom: @js(old('alamat_domisili', '')),

        {{-- Langkah 2 --}}
        tipe: @js(old('tipe_karyawan', 'BULANAN')),
        statusKerjaan: @js(old('ket_karyawan', 'TETAP')),
        unitId: @js(old('unit_organisasi_id', '')),
        unitNama: '',
        jabatanDipilih: @js(old('nama_jabatan', '')),
        distribusiJabatan: '',
        rmRegional: '',
        amWilayah: '',
        borongId: @js(old('jabatan_borong_id', '')),
        kotaId: @js(old('kota_perwakilan_id', '')),
        areaId: @js(old('area_id', '')),
        rek: @js(old('no_rekening', '')),
        bersediaTransfer: @js(old('bersedia_transfer', '')),
        bpjsKes: @js(old('no_bpjs_kesehatan', '')),
        bpjsKet: @js(old('no_bpjs_ketenagakerjaan', '')),
        kop: @js(old('no_anggota_koperasi', '')),

        {{-- Langkah 3 --}}
        ayahNama: @js(old('nama_ayah', '')),
        ibuNama: @js(old('nama_ibu', '')),
        nikah: @js(old('status_pernikahan', 'BELUM_KAWIN')),
        pasanganNama: @js(old('nama_pasangan', '')),
        kerjaPasangan: @js(old('pekerjaan_pasangan', '')),
        anakN: {{ old('status_pernikahan') === 'BELUM_KAWIN' ? 0 : (int) old('jumlah_anak', 0) }},
        anakList: @js(array_values(old('anak', []))),
        
        {{-- Langkah 4 --}}
        riwayatLuar: @js(array_values(old('riwayat_luar', [['perusahaan' => '', 'jabatan' => '', 'tahun_masuk' => '', 'tahun_keluar' => '']]))),
        riwayatSukun: @js(array_values(old('riwayat_sukun', [['bagian' => '', 'jabatan' => '', 'tahun_mulai' => '', 'tahun_selesai' => '']]))),
        masukTgl: @js(old('tgl_masuk_tanggal', '')),
        masukBulan: @js(old('tgl_masuk_bulan', '')),
        masukTahun: @js(old('tgl_masuk_tahun', ''))
    })"
>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Formulir Pendataan Karyawan</h1>
        <p class="mt-1 text-sm text-slate-500">Lengkapi data sesuai dokumen resmi. Data akan diverifikasi oleh HRD.</p>
        <div class="accent mt-3"></div>
    </div>

    <ol class="mb-8 grid grid-cols-4 gap-3">
        <template x-for="(lb, i) in ['Data Diri','Pekerjaan','Keluarga','Riwayat']" :key="i">
            <li>
                <div class="flex items-center gap-2">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-semibold"
                          :class="step >= i+1 ? 'bg-blue-700 text-white' : 'border border-slate-300 bg-white text-slate-400'"
                          x-text="i+1"></span>
                    <span class="hidden text-xs font-medium sm:block"
                          :class="step === i+1 ? 'text-blue-800' : 'text-slate-500'"
                          x-text="lb"></span>
                </div>
                <div class="mt-2 h-0.5 rounded" :class="step >= i+1 ? 'bg-blue-700' : 'bg-slate-200'"></div>
            </li>
        </template>
    </ol>

    @if (session('sukses'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-4">
            <p class="text-sm font-semibold text-emerald-900">Data berhasil dikirim</p>
            <p class="mt-1 text-sm text-emerald-800">{{ session('sukses') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="al mb-6">
            <p>Terdapat data yang perlu diperbaiki:</p>
            <ul class="mt-1 list-inside list-disc font-normal">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="form-karyawan" action="{{ route('pendataan.store') }}" method="POST" @submit="kirim($event)">
        @csrf

        {{-- LANGKAH 1 --}}
        <section x-show="step === 1" class="cd space-y-5">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Data Diri dan Alamat</h2>
                <p class="mt-0.5 text-sm text-slate-500">Isi sesuai KTP. Nomor KTP menjadi kunci agar data tidak ganda.</p>
            </div>
            <div class="al" x-show="ada(1)" x-cloak x-text="pesan(1)"></div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nik_ktp" class="lb">Nomor KTP / NIK (16 angka) <span class="req">*</span></label>
                    <input id="nik_ktp" type="text" name="nik_ktp" x-model="nik" maxlength="16" inputmode="numeric" placeholder="16 angka" class="in tracking-wide">
                    <p class="hint" :class="nik.length === 16 ? 'text-emerald-700' : ''"><span x-text="nik.length"></span>/16 angka terisi</p>
                </div>
                <div>
                    <label for="nip" class="lb">NIP (Nomor Induk Pegawai di ID Card)</label>
                    <input id="nip" type="text" name="nip" x-model="nip" placeholder="Kosongkan jika tidak ada/baru" class="in">
                </div>
            </div>

            <div>
                <label for="nama_lengkap" class="lb">Nama lengkap sesuai KTP <span class="req">*</span></label>
                <input id="nama_lengkap" type="text" name="nama_lengkap" x-model="nama" placeholder="Nama lengkap" class="in uppercase">
            </div>

            <div>
                <span class="lb">Jenis kelamin <span class="req">*</span></span>
                <div class="mt-1.5 grid grid-cols-2 gap-3">
                    <label class="cursor-pointer rounded-lg border px-4 py-2.5 text-center text-sm font-medium"
                           :class="jk === 'L' ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-blue-50'">
                        <input type="radio" name="jenis_kelamin" value="L" x-model="jk" class="sr-only">Laki-laki
                    </label>
                    <label class="cursor-pointer rounded-lg border px-4 py-2.5 text-center text-sm font-medium"
                           :class="jk === 'P' ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-blue-50'">
                        <input type="radio" name="jenis_kelamin" value="P" x-model="jk" class="sr-only">Perempuan
                    </label>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="tgl_lahir" class="lb">Tanggal lahir <span class="req">*</span></label>
                    <input id="tgl_lahir" type="date" name="tgl_lahir" x-model="tglLahir" class="in">
                </div>
                <div>
                    <label for="golongan_darah" class="lb">Golongan darah</label>
                    <select id="golongan_darah" name="golongan_darah" x-model="golDarah" class="in">
                        <option value="-">Tidak diketahui</option>
                        @foreach (['A','B','AB','O'] as $g)
                            <option value="{{ $g }}">{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- REPEATER RIWAYAT PENDIDIKAN DARI SD S/D TERAKHIR --}}
            <div class="sb space-y-4">
                <div class="flex items-center justify-between border-b border-blue-200 pb-2">
                    <div>
                        <span class="lb text-blue-900 font-bold">Riwayat Pendidikan (SD s/d Terakhir) <span class="req">*</span></span>
                        <p class="hint mt-0.5">Isi riwayat pendidikan Anda dari SD hingga pendidikan terakhir.</p>
                    </div>
                    <button type="button" @click="riwayatPendidikan.push({ jenjang: '', nama_instansi: '', jurusan: '', nilai_ipk: '' })" class="rounded-md bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-200">
                        + Tambah Pendidikan
                    </button>
                </div>

                <template x-for="(rp, index) in riwayatPendidikan" :key="index">
                    <div class="relative rounded-lg border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400" x-text="'Pendidikan #' + (index + 1)"></span>
                            <button type="button" x-show="riwayatPendidikan.length > 1" @click="riwayatPendidikan.splice(index, 1)" class="text-xs font-medium text-red-500 hover:text-red-700 hover:underline">
                                Hapus
                            </button>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="lb">Jenjang Pendidikan <span class="req">*</span></label>
                                <select :name="'riwayat_pendidikan[' + index + '][jenjang]'" x-model="rp.jenjang" class="in">
                                    <option value="">-- Pilih Jenjang --</option>
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
                                <label class="lb">Nama Sekolah / Instansi / Universitas <span class="req">*</span></label>
                                <input type="text" :name="'riwayat_pendidikan[' + index + '][nama_instansi]'" x-model="rp.nama_instansi" class="in uppercase" placeholder="Contoh: SDN 1 Kudus / Undip">
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="lb">Jurusan / Program Studi</label>
                                <input type="text" :name="'riwayat_pendidikan[' + index + '][jurusan]'" x-model="rp.jurusan" class="in uppercase" placeholder="Contoh: IPA / Teknik Mesin (Kosongkan jika SD/SMP)">
                            </div>
                            <div>
                                <label class="lb">Nilai Akhir / IPK</label>
                                <input type="text" :name="'riwayat_pendidikan[' + index + '][nilai_ipk]'" x-model="rp.nilai_ipk" class="in" placeholder="Contoh: 85.00 / 3.50">
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="no_wa" class="lb">Nomor HP / WhatsApp</label>
                    <input id="no_wa" type="text" name="no_wa" inputmode="numeric" x-model="wa" placeholder="08xxxxxxxxxx" class="in">
                </div>
                <div>
                    <label for="email" class="lb">Email</label>
                    <input id="email" type="email" name="email" x-model="emailForm" placeholder="email@gmail.com" class="in">
                </div>
            </div>

           <div class="grid gap-4 sm:grid-cols-2 items-end">
                <div>
                    <label for="disabilitas" class="lb">Disabilitas Jika ada Tulis, Jenis Disabilitasnya</label>
                    <input id="disabilitas" type="text" name="disabilitas" x-model="disabilitas" placeholder="Kosongkan jika normal" class="in">
                </div>
                <div>
                    <label for="npwp" class="lb">NPWP</label>
                    <input id="npwp" type="text" name="npwp" x-model="npwp" placeholder="Nomor NPWP" class="in">
                </div>
            </div>

            <div>
                <label for="alamat_ktp" class="lb">Alamat lengkap sesuai KTP <span class="req">*</span></label>
                <textarea id="alamat_ktp" name="alamat_ktp" rows="3" x-model="alamatKtp" placeholder="Jalan, RT/RW, desa/kelurahan, kecamatan, kabupaten" class="in"></textarea>
            </div>

            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3">
                <input type="checkbox" x-model="alamatSama" class="mt-0.5 h-4 w-4 rounded border-slate-400">
                <span class="text-sm text-slate-700">Alamat tempat tinggal saat ini sama dengan alamat KTP</span>
            </label>

            <div x-show="!alamatSama" x-cloak>
                <label for="alamat_domisili" class="lb">Alamat tempat tinggal saat ini</label>
                <textarea id="alamat_domisili" name="alamat_domisili" rows="3" x-model="alamatDom" placeholder="Diisi jika berbeda dengan KTP" class="in"></textarea>
            </div>
        </section>

        {{-- LANGKAH 2 --}}
        <section x-show="step === 2" x-cloak class="cd space-y-5">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Data Pekerjaan dan Rekening</h2>
                <p class="mt-0.5 text-sm text-slate-500">Pilih jenis karyawan, status, dan bagian kerja, lalu isi data rekening gaji.</p>
            </div>
            <div class="al" x-show="ada(2)" x-cloak x-text="pesan(2)"></div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="tipe_karyawan" class="lb">Jenis karyawan <span class="req">*</span></label>
                    <select id="tipe_karyawan" name="tipe_karyawan" x-model="tipe" class="in" 
                            @change="jabatanDipilih = ''; distribusiJabatan = ''; rmRegional = ''; amWilayah = ''; if (tipe === 'DISTRIBUSI') { unitId = ''; }">
                        <option value="BULANAN">Bulanan</option>
                        <option value="HARIAN">Harian</option>
                        <option value="BORONG">Borong</option>
                        <option value="DISTRIBUSI">Distribusi</option>
                    </select>
                </div>
                <div>
                    <label for="ket_karyawan" class="lb">Status karyawan <span class="req">*</span></label>
                    <select id="ket_karyawan" name="ket_karyawan" x-model="statusKerjaan" class="in">
                        <option value="TETAP">TETAP</option>
                        <option value="HARIAN_LEPAS">HARIAN LEPAS</option>
                        <option value="KONTRAK">KONTRAK</option>
                    </select>
                </div>
            </div>

            {{-- BAGIAN UNIT KERJA (Hanya muncul jika BUKAN DISTRIBUSI) --}}
            <div x-show="tipe !== 'DISTRIBUSI'" class="sb space-y-4">
                <div>
                    <label for="unit_organisasi_id" class="lb">Bagian / unit kerja <span class="req">*</span></label>
                    <p class="hint mb-1 mt-0">Dikelompokkan per departemen sesuai struktur perusahaan terbaru.</p>
                    
                    <select id="unit_organisasi_id" name="unit_organisasi_id" x-model="unitId"
                            :disabled="tipe === 'DISTRIBUSI'"
                            @change="const o = $event.target.selectedOptions[0]; unitNama = (o ? o.text.trim() : ''); jabatanDipilih = ''"
                            class="in mt-0">
                        <option value="">Pilih bagian / unit kerja</option>
                        
                        @if(count($departemen) === 0)
                            <option value="" disabled>⚠️ TABEL DEPARTEMEN DI DATABASE MASIH KOSONG!</option>
                        @else
                            @foreach ($departemen as $dep)
                                <optgroup label="{{ $dep->nama_departemen }}">
                                    @foreach ($unitOrganisasi->where('departemen_id', $dep->id) as $u)
                                        @php
                                            $namaUnitUp = strtoupper(trim($u->nama_unit));
                                            $isPerwakilan = str_starts_with($namaUnitUp, 'PERWAKILAN') || str_contains($namaUnitUp, 'PERWAKILAN');
                                            $isRegional = str_starts_with($namaUnitUp, 'REGIONAL') || str_contains($namaUnitUp, 'REGIONAL');
                                        @endphp
                                        
                                        @if (strtoupper($dep->nama_departemen) === 'DISTRIBUSI' && ($isPerwakilan || $isRegional))
                                            @continue
                                        @endif

                                        <option value="{{ $u->id }}">
                                            {{ $u->nama_unit }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        @endif
                    </select>
                </div>

                {{-- JABATAN (SATPAM = DROPDOWN, SELAIN SATPAM = FREETEXT) --}}
                <div x-show="tipe !== 'BORONG'">
                    <label for="nama_jabatan" class="lb">Jabatan <span class="req">*</span></label>

                    {{-- 1. JIKA SATPAM -> DROPDOWN KHUSUS SATPAM --}}
                    <div x-show="isSatpam()">
                        <select id="nama_jabatan_satpam" name="nama_jabatan" x-model="jabatanDipilih" class="in mt-1"
                                :disabled="tipe === 'DISTRIBUSI' || !unitId || !isSatpam()">
                            <option value="">-- Pilih Jabatan Satpam --</option>
                            <template x-for="jbt in ['Anggota', 'Wadanru', 'Danru', 'Danton', 'Kepala Satpam']" :key="jbt">
                                <option :value="jbt" x-text="jbt" :selected="jabatanDipilih === jbt"></option>
                            </template>
                        </select>
                    </div>

                    {{-- 2. SELAIN SATPAM -> FREETEXT (INPUT TEKS BEBAS) --}}
                    <div x-show="!isSatpam()">
                        <input type="text" id="nama_jabatan_text" name="nama_jabatan" x-model="jabatanDipilih"
                               class="in uppercase mt-1"
                               :placeholder="unitId ? 'Ketik nama jabatan Anda (Contoh: Pelaksana, Staff Admin, Trainer, CCO, MT, Manager, dll)' : 'Pilih bagian / unit kerja terlebih dahulu'"
                               :disabled="tipe === 'DISTRIBUSI' || !unitId || isSatpam()">
                        <p class="hint">Ketik nama jabatan Anda saat ini sesuai penugasan kerja.</p>
                    </div>
                </div>
            </div>

            {{-- BAGIAN BORONG --}}
            <div x-show="tipe === 'BORONG'" x-cloak class="sb">
                <label for="jabatan_borong_id" class="lb">Jenis pekerjaan borong <span class="req">*</span></label>
                <select id="jabatan_borong_id" name="jabatan_borong_id" x-model="borongId" class="in" :disabled="tipe !== 'BORONG'">
                    <option value="">Pilih pekerjaan borong</option>
                    @if(count($jabatanBorong) === 0)
                         <option value="" disabled>⚠️ TABEL JABATAN BORONG MASIH KOSONG!</option>
                    @else
                        @foreach ($jabatanBorong as $jb)
                            <option value="{{ $jb->id }}">{{ $jb->nama_jabatan_borong }}</option>
                        @endforeach
                    @endif
                </select>
            </div>

            {{-- BAGIAN DISTRIBUSI CABANG / PERWAKILAN --}}
            <div x-show="tipe === 'DISTRIBUSI'" x-cloak class="sb space-y-4">
                <input type="hidden" name="nama_jabatan" :value="finalJabatanDistribusi()" :disabled="tipe !== 'DISTRIBUSI'">

                <div>
                    <label for="nama_jabatan_distribusi" class="lb">Jabatan Distribusi Cabang <span class="req">*</span></label>
                    <select id="nama_jabatan_distribusi" x-model="distribusiJabatan" class="in"
                            @change="if (distribusiJabatan !== 'RM') rmRegional = ''; if (distribusiJabatan !== 'AM') amWilayah = '';"
                            :disabled="tipe !== 'DISTRIBUSI'">
                        <option value="">-- Pilih Jabatan Distribusi --</option>
                        @foreach (['OB', 'Sales Motoris', 'Sales Kanvas', 'Sales Dropping', 'Sales CCO', 'TL', 'TL CCO', 'SS', 'AM', 'RM'] as $jbtDist)
                            <option value="{{ $jbtDist }}">{{ $jbtDist }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- JIKA MEMILIH RM -> PILIHAN REGIONAL 1 - 3 --}}
                <div x-show="distribusiJabatan === 'RM'" x-cloak class="rounded-lg bg-blue-50 border border-blue-200 p-4">
                    <label for="rm_regional_select" class="lb text-blue-900">Pilih Wilayah Regional (RM) <span class="req">*</span></label>
                    <select id="rm_regional_select" x-model="rmRegional" class="in mt-1">
                        <option value="">-- Pilih Regional (1 - 3) --</option>
                        <option value="Regional 1">Regional 1</option>
                        <option value="Regional 2">Regional 2</option>
                        <option value="Regional 3">Regional 3</option>
                    </select>
                </div>

                {{-- JIKA MEMILIH AM -> PILIHAN WILAYAH AM --}}
                <div x-show="distribusiJabatan === 'AM'" x-cloak class="rounded-lg bg-blue-50 border border-blue-200 p-4">
                    <label for="am_wilayah_select" class="lb text-blue-900">Pilih Wilayah Area Manager (AM) <span class="req">*</span></label>
                    <select id="am_wilayah_select" x-model="amWilayah" class="in mt-1">
                        <option value="">-- Pilih Wilayah AM --</option>
                        <optgroup label="Jawa Tengah">
                            <option value="Jateng 1">Jateng 1</option>
                            <option value="Jateng 2">Jateng 2</option>
                            <option value="Jateng 3">Jateng 3</option>
                        </optgroup>
                        <optgroup label="Jawa Barat">
                            <option value="Jabar 1">Jabar 1</option>
                            <option value="Jabar 2">Jabar 2</option>
                        </optgroup>
                        <optgroup label="Jawa Timur">
                            <option value="Jatim 1">Jatim 1</option>
                            <option value="Jatim 2">Jatim 2</option>
                            <option value="Jatim 3">Jatim 3</option>
                        </optgroup>
                        <optgroup label="Luar Jawa">
                            <option value="Sumatra">Sumatra</option>
                            <option value="Kalimantan">Kalimantan</option>
                        </optgroup>
                    </select>
                </div>

                {{-- KOTA PERWAKILAN & AREA (HANYA DITAMPILKAN JIKA BUKAN RM DAN BUKAN AM) --}}
                <div x-show="distribusiJabatan !== 'RM' && distribusiJabatan !== 'AM'" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="kota_perwakilan_id" class="lb">Kota perwakilan <span class="req">*</span></label>
                        <select id="kota_perwakilan_id" name="kota_perwakilan_id" x-model="kotaId" class="in" :disabled="tipe !== 'DISTRIBUSI' || distribusiJabatan === 'RM' || distribusiJabatan === 'AM'">
                            <option value="">Pilih kota</option>
                            @if(count($kotaPerwakilan) === 0)
                                <option value="" disabled>⚠️ TABEL KOTA PERWAKILAN KOSONG!</option>
                            @else
                                @foreach ($kotaPerwakilan as $kp)
                                    <option value="{{ $kp->id }}">{{ $kp->nama_kota }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div>
                        <label for="area_id" class="lb">Area <span class="req">*</span></label>
                        <select id="area_id" name="area_id" x-model="areaId" class="in" :disabled="tipe !== 'DISTRIBUSI' || distribusiJabatan === 'RM' || distribusiJabatan === 'AM'">
                            <option value="">Pilih area</option>
                            @if(count($area) === 0)
                                <option value="" disabled>⚠️ TABEL AREA KOSONG!</option>
                            @else
                                @foreach ($area as $a)
                                    <option value="{{ $a->id }}">{{ $a->nama_area }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>
            </div>

            {{-- GOLONGAN DISEMBUNYIKAN SEMENTARA (HIDDEN) --}}
            <div style="display: none;" x-show="false">
                <label for="golongan_id" class="lb">Golongan Karyawan</label>
                <select id="golongan_id" name="golongan_id" class="in">
                    <option value="">-- Kosongkan jika belum ada / tidak tahu --</option>
                    @if(isset($golongan))
                        @foreach ($golongan as $g)
                            <option value="{{ $g->id }}" @selected(old('golongan_id') == $g->id)>
                                {{ $g->nama_golongan ?? $g->golongan ?? 'Golongan ' . $g->id }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div class="space-y-4">
                <div>
                    <label for="no_rekening" class="lb">
                        Nomor rekening (BRI 15 angka / BNI 9-10 angka)
                    </label>
                    <input id="no_rekening" type="text" name="no_rekening" x-model="rek"
                           maxlength="15" inputmode="numeric"
                           pattern="^(\d{10}|\d{15})?$"
                           title="Kosongkan, atau masukkan 10 angka (BNI) / 15 angka (BRI)"
                           placeholder="Kosongkan jika tidak ada/belum tahu"
                           class="in tracking-wide">
                    <p class="hint" :class="bank().w" x-text="bank().p"></p>
                </div>

                <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4">
                    <span class="lb text-blue-900">Jika gaji belum dilakukan via transfer, apakah bersedia untuk dilakukan penggajian via transfer? <span class="req">*</span></span>
                    <div class="mt-3 flex items-center gap-6">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                            <input type="radio" name="bersedia_transfer" value="YA" x-model="bersediaTransfer" class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                            Iya, Bersedia
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                            <input type="radio" name="bersedia_transfer" value="TIDAK" x-model="bersediaTransfer" class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                            Tidak
                        </label>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 mt-4">
                <div>
                    <label for="no_bpjs_kesehatan" class="lb">No. BPJS Kesehatan</label>
                    <input id="no_bpjs_kesehatan" type="text" name="no_bpjs_kesehatan"
                           inputmode="numeric" x-model="bpjsKes" placeholder="Nomor kartu" class="in">
                </div>
                <div>
                    <label for="no_bpjs_ketenagakerjaan" class="lb">No. BPJS Ketenagakerjaan</label>
                    <input id="no_bpjs_ketenagakerjaan" type="text" name="no_bpjs_ketenagakerjaan"
                           inputmode="numeric" x-model="bpjsKet" placeholder="Nomor BPJS Ketenagakerjaan" class="in">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="no_anggota_koperasi" class="lb">No. Anggota Koperasi KKSG</label>
                    <input id="no_anggota_koperasi" type="text" name="no_anggota_koperasi"
                           x-model="kop" placeholder="Kosongkan jika tidak ada" class="in">
                </div>
            </div>
        </section>

        {{-- LANGKAH 3 --}}
        <section x-show="step === 3" x-cloak class="cd space-y-5">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Keluarga dan Orang Tua</h2>
                <p class="mt-0.5 text-sm text-slate-500">Lengkapi data orang tua kandung, data pasangan, dan anak sesuai kartu keluarga.</p>
            </div>
            <div class="al" x-show="ada(3)" x-cloak x-text="pesan(3)"></div>

            {{-- NAMA AYAH DAN IBU KANDUNG (MANDATORY) --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nama_ayah" class="lb">Nama lengkap Ayah Kandung <span class="req">*</span></label>
                    <input id="nama_ayah" type="text" name="nama_ayah" x-model="ayahNama" placeholder="Nama ayah kandung" class="in uppercase" required>
                </div>
                <div>
                    <label for="nama_ibu" class="lb">Nama lengkap Ibu Kandung <span class="req">*</span></label>
                    <input id="nama_ibu" type="text" name="nama_ibu" x-model="ibuNama" placeholder="Nama ibu kandung" class="in uppercase" required>
                </div>
            </div>

            <div>
                <label for="status_pernikahan" class="lb">Status pernikahan <span class="req">*</span></label>
                <select id="status_pernikahan" name="status_pernikahan" x-model="nikah" class="in">
                    <option value="BELUM_KAWIN">Belum menikah</option>
                    <option value="KAWIN">Sudah menikah</option>
                    <option value="CERAI_HIDUP">Cerai hidup</option>
                    <option value="CERAI_MATI">Cerai mati</option>
                </select>
            </div>

            {{-- DATA PASANGAN (HANYA MUNCUL JIKA KAWIN) --}}
            <div x-show="nikah === 'KAWIN'" x-cloak class="sb space-y-4">
                <p class="text-sm font-semibold text-slate-700">Data suami / istri</p>
                <div>
                    <label for="nama_pasangan" class="lb">Nama lengkap <span class="req">*</span></label>
                    <input id="nama_pasangan" type="text" name="nama_pasangan"
                           x-model="pasanganNama" :disabled="nikah !== 'KAWIN'" class="in uppercase">
                </div>
                <div>
                    <label for="pekerjaan_pasangan" class="lb">Pekerjaan</label>
                    <input id="pekerjaan_pasangan" type="text" name="pekerjaan_pasangan"
                           x-model="kerjaPasangan" :disabled="nikah !== 'KAWIN'" class="in">
                </div>
            </div>

            {{-- JUMLAH ANAK (HANYA JIKA BUKAN BELUM MENIKAH) --}}
            <div x-show="nikah !== 'BELUM_KAWIN'" x-cloak>
                <label for="jumlah_anak" class="lb">Jumlah anak (maksimal 10)</label>
                <select id="jumlah_anak" name="jumlah_anak" x-model.number="anakN" class="in" :disabled="nikah === 'BELUM_KAWIN'">
                    <option value="0">Tidak ada / 0 anak</option>
                    <template x-for="n in 10" :key="n">
                        <option :value="n" x-text="n + ' anak'"></option>
                    </template>
                </select>
            </div>

            {{-- DAFTAR ANAK DINAMIS --}}
            <div class="space-y-4" x-show="nikah !== 'BELUM_KAWIN' && anakN > 0" x-cloak>
                <template x-for="i in anakN" :key="i">
                    <div class="sb space-y-3">
                        <p class="text-sm font-semibold text-blue-900" x-text="'Anak ke-' + i"></p>
                        <div>
                            <label class="lb">Nama lengkap anak <span class="req">*</span></label>
                            <input type="text" :name="'anak[' + i + '][nama_anak]'" x-model="anakList[i-1].nama_anak" class="in uppercase">
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="lb">Tanggal lahir</label>
                                <input type="date" :name="'anak[' + i + '][tgl_lahir_anak]'" x-model="anakList[i-1].tgl_lahir_anak" class="in">
                            </div>
                            <div>
                                <label class="lb">Pendidikan</label>
                                <select :name="'anak[' + i + '][pendidikan_anak]'" x-model="anakList[i-1].pendidikan_anak" class="in">
                                    @foreach (['BELUM SEKOLAH'=>'Belum sekolah','TK'=>'TK / PAUD','SD'=>'SD','SMP'=>'SMP','SMA/SMK'=>'SMA / SMK','KULIAH'=>'Kuliah','BEKERJA'=>'Sudah bekerja'] as $v => $t)
                                        <option value="{{ $v }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        {{-- LANGKAH 4 --}}
        <section x-show="step === 4" x-cloak class="cd space-y-5">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Riwayat Pekerjaan</h2>
                <p class="mt-0.5 text-sm text-slate-500">Kosongkan bagian yang tidak berlaku untuk Anda.</p>
            </div>

            {{-- REPEATER RIWAYAT LUAR --}}
            <div class="sb space-y-4">
                <div class="flex items-center justify-between border-b border-blue-200 pb-2">
                    <p class="text-sm font-semibold text-slate-700">Pengalaman kerja sebelum di Sukun</p>
                    <button type="button" @click="riwayatLuar.push({ perusahaan: '', jabatan: '', tahun_masuk: '', tahun_keluar: '' })" class="rounded-md bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-200">
                        + Tambah Riwayat
                    </button>
                </div>

                <template x-for="(rw, index) in riwayatLuar" :key="index">
                    <div class="relative rounded-lg border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400" x-text="'Pengalaman Luar #' + (index + 1)"></span>
                            <button type="button" x-show="riwayatLuar.length > 1" @click="riwayatLuar.splice(index, 1)" class="text-xs font-medium text-red-500 hover:text-red-700 hover:underline">
                                Hapus
                            </button>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="lb">Nama perusahaan sebelumnya</label>
                                <input type="text" :name="'riwayat_luar[' + index + '][perusahaan]'" x-model="rw.perusahaan" class="in" placeholder="Nama Perusahaan">
                            </div>
                            <div>
                                <label class="lb">Jabatan</label>
                                <input type="text" :name="'riwayat_luar[' + index + '][jabatan]'" x-model="rw.jabatan" class="in" placeholder="Nama Jabatan">
                            </div>
                            <div>
                                <label class="lb">Tahun Masuk</label>
                                <input type="number" min="1970" max="{{ now()->year }}" :name="'riwayat_luar[' + index + '][tahun_masuk]'" x-model="rw.tahun_masuk" class="in" placeholder="Contoh: 2018">
                            </div>
                            <div>
                                <label class="lb">Tahun Keluar</label>
                                <input type="number" min="1970" max="{{ now()->year + 5 }}" :name="'riwayat_luar[' + index + '][tahun_keluar]'" x-model="rw.tahun_keluar" class="in" placeholder="Contoh: 2021">
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- REPEATER RIWAYAT SUKUN --}}
            <div class="sb space-y-4">
                <div class="flex items-center justify-between border-b border-blue-200 pb-2">
                    <p class="text-sm font-semibold text-slate-700">Riwayat pekerjaan di Sukun</p>
                    <button type="button" @click="riwayatSukun.push({ bagian: '', jabatan: '', tahun_mulai: '', tahun_selesai: '' })" class="rounded-md bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-200">
                        + Tambah Riwayat
                    </button>
                </div>

                <template x-for="(rw, index) in riwayatSukun" :key="index">
                    <div class="relative rounded-lg border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400" x-text="'Riwayat Sukun #' + (index + 1)"></span>
                            <button type="button" x-show="riwayatSukun.length > 1" @click="riwayatSukun.splice(index, 1)" class="text-xs font-medium text-red-500 hover:text-red-700 hover:underline">
                                Hapus
                            </button>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="lb">Bagian / unit sebelumnya</label>
                                <input type="text" :name="'riwayat_sukun[' + index + '][bagian]'" x-model="rw.bagian" class="in" placeholder="Nama Bagian/Unit">
                            </div>
                            <div>
                                <label class="lb">Jabatan sebelumnya</label>
                                <input type="text" :name="'riwayat_sukun[' + index + '][jabatan]'" x-model="rw.jabatan" class="in" placeholder="Nama Jabatan">
                            </div>
                            <div>
                                <label class="lb">Tahun Mulai</label>
                                <input type="number" min="1970" max="{{ now()->year }}" :name="'riwayat_sukun[' + index + '][tahun_mulai]'" x-model="rw.tahun_mulai" class="in" placeholder="Contoh: 2019">
                            </div>
                            <div>
                                <label class="lb">Tahun Selesai</label>
                                <input type="number" min="1970" max="{{ now()->year + 5 }}" :name="'riwayat_sukun[' + index + '][tahun_selesai]'" x-model="rw.tahun_selesai" class="in" placeholder="Contoh: 2022">
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div>
                <span class="lb">Tanggal masuk kerja di Sukun <span class="req">*</span></span>
                <p class="hint">Bulan dan tahun wajib diisi. Tanggal boleh dikosongkan jika tidak ingat.</p>

                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="tgl_masuk_tanggal" class="lb">Tanggal</label>
                        <select id="tgl_masuk_tanggal" name="tgl_masuk_tanggal" x-model="masukTgl" class="in">
                            <option value="">Tidak ingat</option>
                            @for ($d = 1; $d <= 31; $d++)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label for="tgl_masuk_bulan" class="lb">Bulan <span class="req">*</span></label>
                        <select id="tgl_masuk_bulan" name="tgl_masuk_bulan" x-model="masukBulan" class="in" required>
                            <option value="">Pilih bulan</option>
                            @foreach ([1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'] as $angka => $namaBulan)
                                <option value="{{ $angka }}">{{ $namaBulan }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tgl_masuk_tahun" class="lb">Tahun <span class="req">*</span></label>
                        <select id="tgl_masuk_tahun" name="tgl_masuk_tahun" x-model="masukTahun" class="in" required>
                            <option value="">Pilih tahun</option>
                            @for ($tahun = now()->year; $tahun >= 1970; $tahun--)
                                <option value="{{ $tahun }}">{{ $tahun }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>

            <p class="text-xs leading-relaxed text-slate-500">
                Dengan menekan tombol Kirim data, Anda menyatakan bahwa data yang diisi adalah benar.
                HRD akan memverifikasi; kolom yang kosong akan ditandai untuk dilengkapi.
            </p>
        </section>

        {{-- NAVIGASI --}}
        <div class="mt-6 flex items-center justify-between gap-3">
            <button type="button" x-show="step > 1" x-cloak @click="step--" class="bt bl">Kembali</button>
            <span x-show="step === 1" class="text-xs text-slate-500">Langkah 1 dari 4</span>
            <button type="button" x-show="step < 4" @click="lanjut()" class="bt bp">Lanjut</button>
            <button type="submit" x-show="step === 4" x-cloak class="bt bp"
                    onclick="return confirm('Pastikan data sudah benar. Kirim data ke HRD?')">
                Kirim data
            </button>
        </div>
    </form>

    <footer class="mt-10 pb-8 text-center text-xs text-slate-500">
        &copy; {{ now()->year }} IT PT. Sukun Wartono Indonesia
    </footer>
</main>

@verbatim
<script>
function formKaryawan(initialData) {
    return Object.assign({}, initialData, {
        init() {
            const draftKey = 'draftKaryawanSukun';

            if (this.isSukses) {
                localStorage.removeItem(draftKey);
            } else if (!this.isError) {
                try {
                    const savedDraft = localStorage.getItem(draftKey);
                    if (savedDraft) {
                        const draft = JSON.parse(savedDraft);
                        Object.keys(draft).forEach((key) => {
                            if (this[key] !== undefined) {
                                this[key] = draft[key];
                            }
                        });
                    }
                } catch (error) {
                    localStorage.removeItem(draftKey);
                }
            }

            this.normalisasiArray();

            if (this.nikah === 'BELUM_KAWIN') {
                this.anakN = 0;
                this.anakList = [];
                this.pasanganNama = '';
                this.kerjaPasangan = '';
            } else {
                this.siapkanAnak();
            }

            this.$watch('anakN', () => {
                if (this.nikah !== 'BELUM_KAWIN') {
                    this.siapkanAnak();
                }
            });

            this.$watch('nikah', (status) => {
                if (status === 'BELUM_KAWIN') {
                    this.anakN = 0;
                    this.anakList = [];
                    this.pasanganNama = '';
                    this.kerjaPasangan = '';
                } else {
                    this.siapkanAnak();
                }
            });

            const jabatanLama = String(this.jabatanDipilih || '').trim();
            if (jabatanLama.toUpperCase().startsWith('RM')) {
                this.distribusiJabatan = 'RM';
                if (/REGIONAL\s*1/i.test(jabatanLama)) this.rmRegional = 'Regional 1';
                else if (/REGIONAL\s*2/i.test(jabatanLama)) this.rmRegional = 'Regional 2';
                else if (/REGIONAL\s*3/i.test(jabatanLama)) this.rmRegional = 'Regional 3';
            } else if (jabatanLama.toUpperCase().startsWith('AM ')) {
                this.distribusiJabatan = 'AM';
                this.amWilayah = jabatanLama.replace(/^AM\s+/i, '').trim();
            } else if (this.tipe === 'DISTRIBUSI') {
                this.distribusiJabatan = jabatanLama;
            }

            document.addEventListener('input', () => this.$nextTick(() => this.saveDraft()));
            document.addEventListener('change', () => this.$nextTick(() => this.saveDraft()));

            this.$nextTick(() => {
                const selectUnit = document.getElementById('unit_organisasi_id');
                if (selectUnit && selectUnit.selectedOptions.length > 0) {
                    this.unitNama = selectUnit.selectedOptions[0].text.trim();
                }
            });
        },

        normalisasiArray() {
            if (!Array.isArray(this.anakList)) this.anakList = Object.values(this.anakList || {});
            if (!Array.isArray(this.riwayatPendidikan)) this.riwayatPendidikan = Object.values(this.riwayatPendidikan || {});
            if (!Array.isArray(this.riwayatLuar)) this.riwayatLuar = Object.values(this.riwayatLuar || {});
            if (!Array.isArray(this.riwayatSukun)) this.riwayatSukun = Object.values(this.riwayatSukun || {});

            if (this.riwayatPendidikan.length === 0) {
                this.riwayatPendidikan = [{ jenjang: 'SD', nama_instansi: '', jurusan: '', nilai_ipk: '' }];
            }
            if (this.riwayatLuar.length === 0) {
                this.riwayatLuar = [{ perusahaan: '', jabatan: '', tahun_masuk: '', tahun_keluar: '' }];
            }
            if (this.riwayatSukun.length === 0) {
                this.riwayatSukun = [{ bagian: '', jabatan: '', tahun_mulai: '', tahun_selesai: '' }];
            }
        },

        siapkanAnak() {
            if (!Array.isArray(this.anakList)) this.anakList = [];
            const jumlah = Math.min(10, Math.max(0, Number(this.anakN) || 0));
            while (this.anakList.length < jumlah) {
                this.anakList.push({ nama_anak: '', tgl_lahir_anak: '', pendidikan_anak: 'BELUM SEKOLAH' });
            }
            if (this.anakList.length > jumlah) {
                this.anakList = this.anakList.slice(0, jumlah);
            }
        },

        saveDraft() {
            const keys = ['step', 'nik', 'nip', 'nama', 'jk', 'tglLahir', 'golDarah', 'riwayatPendidikan', 'wa', 'emailForm', 'disabilitas', 'npwp', 'alamatKtp', 'alamatSama', 'alamatDom', 'tipe', 'statusKerjaan', 'unitId', 'unitNama', 'jabatanDipilih', 'distribusiJabatan', 'rmRegional', 'amWilayah', 'borongId', 'kotaId', 'areaId', 'rek', 'bersediaTransfer', 'bpjsKes', 'bpjsKet', 'kop', 'ayahNama', 'ibuNama', 'nikah', 'pasanganNama', 'kerjaPasangan', 'anakN', 'anakList', 'riwayatLuar', 'riwayatSukun', 'masukTgl', 'masukBulan', 'masukTahun'];
            const data = {};
            keys.forEach((key) => { data[key] = this[key]; });
            try {
                localStorage.setItem('draftKaryawanSukun', JSON.stringify(data));
            } catch (error) {}
        },

        isSatpam() {
            const unit = String(this.unitNama || '').toLowerCase();
            return unit.includes('satpam') || unit.includes('security');
        },

        finalJabatanDistribusi() {
            if (this.distribusiJabatan === 'RM') {
                return this.rmRegional ? 'RM ' + this.rmRegional : 'RM';
            }
            if (this.distribusiJabatan === 'AM') {
                return this.amWilayah ? 'AM ' + this.amWilayah : 'AM';
            }
            return this.distribusiJabatan || '';
        },

        ada(stepNumber) {
            return Object.keys(this.errs[stepNumber] || {}).length > 0;
        },

        pesan(stepNumber) {
            return Object.values(this.errs[stepNumber] || {}).join(' ');
        },

        cek(stepNumber) {
            const errors = {};

            if (stepNumber === 1) {
                if (String(this.nik || '').replace(/\D/g, '').length !== 16) errors.nik = 'Nomor KTP wajib 16 angka.';
                if (!String(this.nama || '').trim()) errors.nama = 'Nama lengkap wajib diisi.';
                if (!this.jk) errors.jk = 'Pilih jenis kelamin.';
                if (!this.tglLahir) errors.tgl = 'Tanggal lahir wajib diisi.';
                if (!String(this.alamatKtp || '').trim()) errors.alamat = 'Alamat sesuai KTP wajib diisi.';

                if (!this.riwayatPendidikan || this.riwayatPendidikan.length === 0) {
                    errors.pendidikan = 'Isi riwayat pendidikan Anda minimal 1 jenjang.';
                } else {
                    for (let i = 0; i < this.riwayatPendidikan.length; i++) {
                        if (!String(this.riwayatPendidikan[i]?.jenjang || '').trim()) {
                            errors.pendidikan = `Pilih jenjang pada Pendidikan #${i + 1}.`;
                            break;
                        }
                        if (!String(this.riwayatPendidikan[i]?.nama_instansi || '').trim()) {
                            errors.pendidikan = `Nama sekolah/instansi pada Pendidikan #${i + 1} wajib diisi.`;
                            break;
                        }
                    }
                }
            }

            if (stepNumber === 2) {
                if (this.tipe !== 'DISTRIBUSI' && !this.unitId) errors.unit = 'Pilih bagian / unit kerja.';
                if (this.tipe === 'BORONG' && !this.borongId) errors.borong = 'Pilih jenis pekerjaan borong.';

                if (this.tipe === 'DISTRIBUSI') {
                    if (!this.distribusiJabatan) {
                        errors.jabatan = 'Pilih jabatan distribusi.';
                    } else if (this.distribusiJabatan === 'RM') {
                        if (!this.rmRegional) errors.rmRegional = 'Pilih wilayah Regional.';
                    } else if (this.distribusiJabatan === 'AM') {
                        if (!this.amWilayah) errors.amWilayah = 'Pilih wilayah Area Manager.';
                    } else {
                        if (!this.kotaId) errors.kota = 'Pilih kota perwakilan.';
                        if (!this.areaId) errors.area = 'Pilih area.';
                    }
                }

                if (this.tipe !== 'DISTRIBUSI' && this.tipe !== 'BORONG') {
                    if (!String(this.jabatanDipilih || '').trim()) {
                        errors.jabatan = this.isSatpam() ? 'Pilih jabatan satpam.' : 'Nama jabatan wajib diketik.';
                    }
                }

                if (!this.bersediaTransfer) errors.transfer = 'Pertanyaan kesediaan transfer gaji wajib dipilih.';

                const jumlahDigitRekening = String(this.rek || '').replace(/\D/g, '').length;
                if (jumlahDigitRekening > 0 && jumlahDigitRekening !== 10 && jumlahDigitRekening !== 15) {
                    errors.rekening = 'Jika diisi, nomor rekening harus 10 angka untuk BNI atau 15 angka untuk BRI.';
                }
            }

            if (stepNumber === 3) {
                if (!String(this.ayahNama || '').trim()) errors.ayah = 'Nama lengkap ayah kandung wajib diisi.';
                if (!String(this.ibuNama || '').trim()) errors.ibu = 'Nama lengkap ibu kandung wajib diisi.';

                if (this.nikah === 'KAWIN' && !String(this.pasanganNama || '').trim()) {
                    errors.pasangan = 'Nama suami / istri wajib diisi.';
                }

                if (this.nikah === 'BELUM_KAWIN') {
                    this.anakN = 0;
                    this.anakList = [];
                } else {
                    this.siapkanAnak();
                    const jumlahAnak = Number(this.anakN) || 0;
                    for (let index = 0; index < jumlahAnak; index++) {
                        const namaAnak = String(this.anakList[index]?.nama_anak || '').trim();
                        if (!namaAnak) {
                            errors.anak = `Nama anak ke-${index + 1} wajib diisi.`;
                            break;
                        }
                    }
                }
            }

            if (stepNumber === 4) {
                if (!this.masukBulan) errors.bulanMasuk = 'Bulan masuk kerja wajib dipilih.';
                if (!this.masukTahun) errors.tahunMasuk = 'Tahun masuk kerja wajib dipilih.';
            }

            this.errs = { ...this.errs, [stepNumber]: errors };
            return Object.keys(errors).length === 0;
        },

        lanjut() {
            const currentStep = Number(this.step);
            if (!this.cek(currentStep)) return;
            this.step = Math.min(currentStep + 1, 4);
            this.saveDraft();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        kirim(event) {
            for (let stepNumber = 1; stepNumber <= 4; stepNumber++) {
                if (!this.cek(stepNumber)) {
                    event.preventDefault();
                    this.step = stepNumber;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }
            }
            this.saveDraft();
        },

        bank() {
            const jumlahDigit = String(this.rek || '').replace(/\D/g, '').length;
            if (jumlahDigit === 15) return { w: 'text-emerald-700', p: 'Terdeteksi BRI (15 angka)' };
            if (jumlahDigit === 10) return { w: 'text-emerald-700', p: 'Terdeteksi BNI (10 angka)' };
            if (jumlahDigit === 0) return { w: 'text-slate-500', p: 'Opsional. Boleh dikosongkan.' };
            return { w: 'text-red-600', p: 'Jumlah angka saat ini ' + jumlahDigit + ' (wajib 15 untuk BRI, 10 untuk BNI)' };
        }
    });
}
</script>
@endverbatim

</body>
</html>