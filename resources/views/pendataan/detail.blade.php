<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Karyawan - {{ $karyawan->nama_lengkap }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak]{display:none!important}
        body{background:#eef2f8}
        .cd{background:#fff;border-radius:.75rem;border:1px solid #e2e8f0;padding:1.5rem;box-shadow:0 1px 3px rgba(10,42,94,.06)}
        .lb{font-size:.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.025em}
        .val{font-size:.925rem;font-weight:600;color:#1e293b;margin-top:.15rem}
    </style>
</head>

<body class="text-slate-800 antialiased py-8 px-4 sm:px-6">
<div class="max-w-4xl mx-auto space-y-6">

    {{-- HEADER & TOMBOL KEMBALI AMAN --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Detail Lengkap Karyawan</h1>
            <p class="text-xs text-slate-500 mt-0.5">Informasi pendataan resmi PT. Sukun Wartono Indonesia</p>
        </div>
        <a href="{{ $backUrl ?? url()->previous() }}" class="rounded-lg bg-slate-800 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-700 transition">
            &larr; Kembali
        </a>
    </div>

    {{-- PANEL KONTROL ADMIN HR --}}
    <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-5 shadow-sm space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold text-blue-900 tracking-wide uppercase">Panel Kontrol Admin HR</p>
                <p class="text-xs text-blue-700 mt-0.5">Anda dapat mengedit langsung seluruh data ini atau membagikan link pengisian ke karyawan:</p>
            </div>
            <a href="{{ url('/hrd/karyawan/edit/' . $karyawan->id) }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold text-white shadow hover:bg-amber-600 transition shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit Langsung Data Ini
            </a>
        </div>
        
        {{-- FITUR SALIN LINK --}}
        <div class="flex items-center gap-2 pt-1" x-data="{ copied: false, link: '{{ url('/pendataan/edit/' . $karyawan->token) }}' }">
            <input type="text" readonly :value="link" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-mono text-slate-600">
            <button type="button" 
                    @click="
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(link).then(() => {
                                copied = true; 
                                setTimeout(() => copied = false, 2500);
                            });
                        } else {
                            let textArea = document.createElement('textarea');
                            textArea.value = link;
                            textArea.style.position = 'fixed';
                            textArea.style.opacity = '0';
                            document.body.appendChild(textArea);
                            textArea.focus(); 
                            textArea.select();
                            try {
                                document.execCommand('copy');
                                copied = true; 
                                setTimeout(() => copied = false, 2500);
                            } catch (err) {}
                            document.body.removeChild(textArea);
                        }
                    " 
                    class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition shrink-0">
                <span x-show="!copied">Salin Link</span>
                <span x-show="copied" x-cloak class="text-emerald-300">Tersalin!</span>
            </button>
        </div>
    </div>

    {{-- KARTU 1: INFORMASI DATA DIRI --}}
    <div class="cd space-y-4">
        <div class="flex items-start justify-between border-b pb-3">
            <div>
                <h2 class="text-2xl font-bold uppercase tracking-tight text-slate-900">{{ $karyawan->nama_lengkap }}</h2>
                <p class="text-xs text-slate-500 mt-1">
                    NIK KTP: <span class="font-mono font-bold text-slate-700">{{ $karyawan->nik_ktp }}</span> &bull; 
                    NIP: <span class="font-mono font-bold text-slate-700">{{ $karyawan->nip ?: '-' }}</span>
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $karyawan->status_verifikasi === 'LENGKAP' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                {{ $karyawan->status_verifikasi === 'LENGKAP' ? 'Lengkap' : 'Belum Lengkap' }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <span class="lb">Jenis Kelamin</span>
                <p class="val">{{ $karyawan->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</p>
            </div>
            <div>
                <span class="lb">Tempat, Tanggal Lahir</span>
                <p class="val uppercase">
                    {{ ($karyawan->tempat_lahir ? $karyawan->tempat_lahir . ', ' : '') . ($karyawan->tgl_lahir ? date('d-m-Y', strtotime($karyawan->tgl_lahir)) : '-') }}
                </p>
            </div>
            <div>
                <span class="lb">Golongan Darah</span>
                <p class="val">{{ $karyawan->golongan_darah ?: '-' }}</p>
            </div>
            <div>
                <span class="lb">Disabilitas</span>
                <p class="val">{{ $karyawan->disabilitas ?: 'Tidak Ada' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2 border-t">
            <div>
                <span class="lb">No. WhatsApp / HP</span>
                <p class="val font-mono">{{ $karyawan->no_wa ?: '-' }}</p>
            </div>
            <div>
                <span class="lb">Email</span>
                <p class="val">{{ $karyawan->email ?: '-' }}</p>
            </div>
            <div>
                <span class="lb">NPWP</span>
                <p class="val font-mono">{{ $karyawan->npwp ?: '-' }}</p>
            </div>
            <div>
                <span class="lb">Koperasi KKSG</span>
                <p class="val font-mono">{{ $karyawan->no_anggota_koperasi ?: '-' }}</p>
            </div>
        </div>

        {{-- ORANG TUA KANDUNG --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t bg-slate-50 p-3 rounded-lg">
            <div>
                <span class="lb">Nama Ayah Kandung</span>
                <p class="val uppercase text-blue-900">{{ $karyawan->nama_ayah ?: '-' }}</p>
            </div>
            <div>
                <span class="lb">Nama Ibu Kandung</span>
                <p class="val uppercase text-blue-900">{{ $karyawan->nama_ibu ?: '-' }}</p>
            </div>
        </div>
    </div>

    {{-- KARTU BARU: RIWAYAT PENDIDIKAN DARI SD S/D TERAKHIR --}}
    <div class="cd space-y-4">
        <h3 class="text-base font-bold text-slate-900 border-b pb-2 flex items-center justify-between">
            <span>Riwayat Pendidikan (SD s/d Terakhir)</span>
            <span class="text-xs font-normal text-slate-500">{{ count($pendidikan ?? []) }} Jenjang</span>
        </h3>

        @if(count($pendidikan ?? []) === 0)
            <p class="text-sm text-slate-500 italic py-2">Belum ada riwayat pendidikan yang tersimpan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b bg-slate-50 text-slate-600 font-semibold uppercase">
                            <th class="py-2.5 px-3">No</th>
                            <th class="py-2.5 px-3">Jenjang</th>
                            <th class="py-2.5 px-3">Nama Sekolah / Instansi / Universitas</th>
                            <th class="py-2.5 px-3">Jurusan / Program Studi</th>
                            <th class="py-2.5 px-3">Nilai / IPK</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($pendidikan as $index => $pd)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-3 font-medium text-slate-500">{{ $index + 1 }}</td>
                                <td class="py-2.5 px-3 font-bold text-blue-700 uppercase">{{ $pd->jenjang }}</td>
                                <td class="py-2.5 px-3 font-semibold text-slate-800 uppercase">{{ $pd->nama_instansi }}</td>
                                <td class="py-2.5 px-3 text-slate-600 uppercase">{{ $pd->jurusan ?: '-' }}</td>
                                <td class="py-2.5 px-3 font-mono font-medium text-slate-700">{{ $pd->nilai_ipk ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- KARTU 2: INFORMASI PEKERJAAN & PENGGAJIAN --}}
    <div class="cd space-y-4">
        <h3 class="text-base font-bold text-slate-900 border-b pb-2">Informasi Pekerjaan, Penggajian & Jaminan</h3>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <span class="lb">Tipe & Status Karyawan</span>
                <p class="val">{{ $karyawan->tipe_karyawan }} ({{ $karyawan->ket_karyawan }})</p>
            </div>
            <div>
                <span class="lb">Departemen / Unit</span>
                <p class="val">{{ $karyawan->nama_departemen ?? '-' }} / {{ $karyawan->nama_unit ?? '-' }}</p>
            </div>
            <div>
                <span class="lb">Jabatan Saat Ini</span>
                <p class="val text-blue-700">{{ $karyawan->nama_jabatan ?: ($karyawan->nama_jabatan_borong ?: '-') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t">
            <div>
                <span class="lb">Tanggal Masuk Kerja</span>
                <p class="val">{{ $karyawan->tgl_masuk_kerja ? date('d-m-Y', strtotime($karyawan->tgl_masuk_kerja)) : '-' }}</p>
            </div>
            <div>
                <span class="lb">Bank & No. Rekening</span>
                <p class="val font-mono">
                    {{ $karyawan->nama_bank ?: 'Bank' }} - {{ $karyawan->no_rekening ?: 'Belum Ada' }}
                </p>
                <p class="text-xs text-slate-500 mt-0.5">Atas Nama: {{ $karyawan->nama_pemilik_rekening ?: $karyawan->nama_lengkap }}</p>
            </div>
            <div>
                <span class="lb">Kesediaan Transfer Gaji</span>
                <p class="val">{{ $karyawan->bersedia_transfer === 'YA' ? 'Iya, Bersedia' : 'Tidak' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t">
            <div>
                <span class="lb">No. BPJS Kesehatan</span>
                <p class="val font-mono">{{ $karyawan->no_bpjs_kesehatan ?: '-' }}</p>
            </div>
            <div>
                <span class="lb">No. BPJS Ketenagakerjaan</span>
                <p class="val font-mono">{{ $karyawan->no_bpjs_ketenagakerjaan ?: '-' }}</p>
            </div>
        </div>
    </div>

    {{-- KARTU 3: ALAMAT & KELUARGA --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="cd space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b pb-2">Alamat</h3>
            <div>
                <span class="lb">Alamat Sesuai KTP</span>
                @php $alamatKtp = $alamat->firstWhere('jenis_alamat', 'KTP'); @endphp
                <p class="val text-sm leading-relaxed">{{ $alamatKtp?->alamat_lengkap ?? '-' }}</p>
            </div>
            <div class="pt-2 border-t">
                <span class="lb">Alamat Domisili Saat Ini</span>
                @php $alamatDom = $alamat->firstWhere('jenis_alamat', 'DOMISILI'); @endphp
                <p class="val text-sm leading-relaxed">{{ $alamatDom?->alamat_lengkap ?? '-' }}</p>
            </div>
        </div>

        <div class="cd space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b pb-2">Keluarga (Pernikahan & Anak)</h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <span class="lb">Status Pernikahan</span>
                    <p class="val">{{ str_replace('_', ' ', $karyawan->status_pernikahan) }}</p>
                </div>
                <div>
                    <span class="lb">Pasangan (Suami/Istri)</span>
                    <p class="val uppercase">{{ $pasangan?->nama_pasangan ?? '-' }}</p>
                    <p class="text-xs text-slate-500">{{ !empty($pasangan?->pekerjaan_pasangan) ? 'Pekerjaan: ' . $pasangan->pekerjaan_pasangan : '' }}</p>
                </div>
            </div>

            <div class="pt-2 border-t">
                <span class="lb">Daftar Anak ({{ count($anak) }} Orang)</span>
                @if(count($anak) === 0)
                    <p class="text-xs text-slate-500 mt-1 italic">Tidak ada data anak / belum memiliki anak.</p>
                @else
                    <ul class="mt-2 divide-y divide-slate-100 text-xs">
                        @foreach($anak as $a)
                            <li class="py-2 flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-slate-800 uppercase">{{ $a->anak_ke }}. {{ $a->nama_anak }}</p>
                                    <p class="text-slate-500">Lahir: {{ $a->tgl_lahir_anak ? date('d-m-Y', strtotime($a->tgl_lahir_anak)) : '-' }}</p>
                                </div>
                                <span class="rounded bg-blue-100 px-2 py-0.5 font-medium text-blue-800">{{ $a->pendidikan_anak ?: 'Belum Sekolah' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- KARTU 4: RIWAYAT PEKERJAAN SELAMA DI SUKUN (JENJANG KARIR) --}}
    <div class="cd space-y-4">
        <h3 class="text-base font-bold text-slate-900 border-b pb-2 flex items-center justify-between">
            <span>Riwayat Pekerjaan Selama di PT. Sukun (Jenjang Karir)</span>
            <span class="text-xs font-normal text-slate-500">{{ count($riwayatSukun) }} Riwayat</span>
        </h3>

        @if(count($riwayatSukun) === 0)
            <p class="text-sm text-slate-500 italic py-2">Belum ada riwayat mutasi / jenjang karir sebelumnya di PT. Sukun.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b bg-slate-50 text-slate-600 font-semibold uppercase">
                            <th class="py-2.5 px-3">No</th>
                            <th class="py-2.5 px-3">Bagian / Unit Kerja</th>
                            <th class="py-2.5 px-3">Jabatan</th>
                            <th class="py-2.5 px-3">Tahun Mulai</th>
                            <th class="py-2.5 px-3">Tahun Selesai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($riwayatSukun as $index => $rw)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-3 font-medium text-slate-500">{{ $index + 1 }}</td>
                                <td class="py-2.5 px-3 font-semibold text-slate-800 uppercase">{{ $rw->unit_bagian ?: '-' }}</td>
                                <td class="py-2.5 px-3 font-semibold text-blue-700 uppercase">{{ $rw->jabatan ?: '-' }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-600">{{ $rw->tgl_mulai ? substr($rw->tgl_mulai, 0, 4) : '-' }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-600">{{ $rw->tgl_selesai ? substr($rw->tgl_selesai, 0, 4) : 'Sekarang' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- KARTU 5: PENGALAMAN KERJA SEBELUM DI SUKUN --}}
    <div class="cd space-y-4">
        <h3 class="text-base font-bold text-slate-900 border-b pb-2 flex items-center justify-between">
            <span>Pengalaman Kerja Sebelum di PT. Sukun</span>
            <span class="text-xs font-normal text-slate-500">{{ count($pengalaman) }} Pengalaman</span>
        </h3>

        @if(count($pengalaman) === 0)
            <p class="text-sm text-slate-500 italic py-2">Tidak ada riwayat pengalaman kerja di perusahaan luar.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b bg-slate-50 text-slate-600 font-semibold uppercase">
                            <th class="py-2.5 px-3">No</th>
                            <th class="py-2.5 px-3">Nama Perusahaan Sebelumnya</th>
                            <th class="py-2.5 px-3">Jabatan</th>
                            <th class="py-2.5 px-3">Tahun Masuk</th>
                            <th class="py-2.5 px-3">Tahun Keluar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($pengalaman as $index => $pg)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-3 font-medium text-slate-500">{{ $index + 1 }}</td>
                                <td class="py-2.5 px-3 font-semibold text-slate-800 uppercase">{{ $pg->nama_perusahaan ?: '-' }}</td>
                                <td class="py-2.5 px-3 font-semibold text-slate-700 uppercase">{{ $pg->jabatan ?: '-' }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-600">{{ $pg->tahun_masuk ?: '-' }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-600">{{ $pg->tahun_keluar ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
</body>
</html>