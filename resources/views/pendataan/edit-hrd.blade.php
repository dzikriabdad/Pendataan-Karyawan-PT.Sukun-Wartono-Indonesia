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

    @php
        $riwayatLuarData = $riwayatLuar ?? $pengalaman ?? collect();
    @endphp

    <form action="{{ url('/hrd/karyawan/update/' . $karyawan->id) }}" method="POST" class="space-y-6"
        x-data="editHrdForm({
            isSubmitting: false,
            tipe: @js(old('tipe_karyawan', $karyawan->tipe_karyawan ?? 'BULANAN')),
            unitId: @js((string) old('unit_organisasi_id', $karyawan->unit_organisasi_id ?? '')),
            unitNama: '',
            jabatanDipilih: @js(old('nama_jabatan', $karyawan->nama_jabatan ?? '')),
            distribusiJabatan: '',
            customDistribusiJabatan: '',
            rmRegional: '',
            amWilayah: '',
            borongId: @js((string) old('jabatan_borong_id', $karyawan->jabatan_borong_id ?? '')),
            kotaId: @js((string) old('kota_perwakilan_id', $karyawan->kota_perwakilan_id ?? '')),
            areaId: @js((string) old('area_id', $karyawan->area_id ?? '')),
            rek: @js(old('no_rekening', $karyawan->no_rekening ?? '')),
            nikah: @js(old('status_pernikahan', $karyawan->status_pernikahan ?? 'BELUM_KAWIN')),
            anakN: {{ old('status_pernikahan', $karyawan->status_pernikahan) === 'BELUM_KAWIN' ? 0 : (int) old('jumlah_anak', count($anak ?? [])) }},
            anakList: @js(array_values(old('anak', count($anak ?? []) > 0 ? $anak->map(fn($a) => [
                'nama_anak' => $a->nama_anak,
                'tgl_lahir_anak' => $a->tgl_lahir_anak,
                'pendidikan_anak' => $a->pendidikan_anak ?? 'BELUM SEKOLAH',
            ])->toArray() : []))),
            pendList: @js(array_values(old('riwayat_pendidikan', count($pendidikan ?? []) > 0 ? $pendidikan->map(fn($p) => [
                'jenjang' => $p->jenjang,
                'nama_instansi' => $p->nama_instansi,
                'jurusan' => $p->jurusan,
                'nilai_ipk' => $p->nilai_ipk,
            ])->toArray() : [['jenjang' => 'SD', 'nama_instansi' => '', 'jurusan' => '', 'nilai_ipk' => '']]))),
            sukunList: @js(array_values(old('riwayat_sukun', count($riwayatSukun ?? []) > 0 ? $riwayatSukun->map(fn($r) => [
                'bagian' => $r->unit_bagian,
                'jabatan' => $r->jabatan,
                'tahun_mulai' => $r->tgl_mulai ? substr($r->tgl_mulai, 0, 4) : ($r->tahun_mulai ?? ''),
                'tahun_selesai' => $r->tgl_selesai ? substr($r->tgl_selesai, 0, 4) : ($r->tahun_selesai ?? ''),
            ])->toArray() : [['bagian' => '', 'jabatan' => '', 'tahun_mulai' => '', 'tahun_selesai' => '']]))),
            luarList: @js(array_values(old('riwayat_luar', count($riwayatLuarData) > 0 ? $riwayatLuarData->map(fn($p) => [
                'perusahaan' => $p->nama_perusahaan,
                'jabatan' => $p->jabatan,
                'tahun_masuk' => $p->tahun_masuk,
                'tahun_keluar' => $p->tahun_keluar,
            ])->toArray() : [['perusahaan' => '', 'jabatan' => '', 'tahun_masuk' => '', 'tahun_keluar' => '']])))
        })"
        @submit="if (isSubmitting) { $event.preventDefault(); return; } isSubmitting = true;">
        @csrf
        @method('PUT')

        {{-- 1. PEKERJAAN, BAGIAN & JABATAN --}}
        <div class="cd space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b pb-2">1. Pekerjaan, Bagian & Jabatan</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="lb">Jenis Karyawan <span class="req">*</span></label>
                    <select name="tipe_karyawan" x-model="tipe" class="in" required
                            @change="jabatanDipilih = ''; distribusiJabatan = ''; customDistribusiJabatan = ''; rmRegional = ''; amWilayah = ''; if (tipe === 'DISTRIBUSI') { unitId = ''; unitNama = ''; }">
                        <option value="BULANAN">Bulanan</option>
                        <option value="HARIAN">Harian</option>
                        <option value="BORONG">Borong</option>
                        <option value="DISTRIBUSI">Distribusi</option>
                    </select>
                </div>
                <div>
                    <label class="lb">Status Karyawan <span class="req">*</span></label>
                    <select name="ket_karyawan" class="in" required>
                        <option value="TETAP" @selected(old('ket_karyawan', $karyawan->ket_karyawan) === 'TETAP')>TETAP</option>
                        <option value="HARIAN_LEPAS" @selected(in_array(old('ket_karyawan', $karyawan->ket_karyawan), ['HARIAN_LEPAS', 'TIDAK_TETAP'], true))>HARIAN LEPAS</option>
                        <option value="KONTRAK" @selected(old('ket_karyawan', $karyawan->ket_karyawan) === 'KONTRAK')>KONTRAK</option>
                    </select>
                </div>
            </div>

            {{-- BAGIAN UNIT KERJA (SELAIN DISTRIBUSI) --}}
            <div x-show="tipe !== 'DISTRIBUSI'" class="sb space-y-4">
                <div>
                    <label class="lb">Bagian / Unit Kerja <span class="req">*</span></label>
                    <select id="unit_organisasi_id" name="unit_organisasi_id" x-model="unitId"
                            :disabled="tipe === 'DISTRIBUSI'"
                            :required="tipe !== 'DISTRIBUSI'"
                            @change="const o = $event.target.selectedOptions[0]; unitNama = (o ? o.text.trim() : ''); jabatanDipilih = ''"
                            class="in">
                        <option value="">-- Pilih Unit Kerja --</option>
                        @foreach ($departemen as $dep)
                            <optgroup label="{{ $dep->nama_departemen }}">
                                @foreach ($unitOrganisasi->where('departemen_id', $dep->id) as $u)
                                    @php
                                        $namaUnitUp = strtoupper(trim($u->nama_unit));
                                        $isPerwakilan = str_contains($namaUnitUp, 'PERWAKILAN');
                                        $isRegional = str_contains($namaUnitUp, 'REGIONAL');
                                    @endphp
                                    @if (strtoupper($dep->nama_departemen) === 'DISTRIBUSI' && ($isPerwakilan || $isRegional))
                                        @continue
                                    @endif
                                    <option value="{{ $u->id }}" @selected((string) old('unit_organisasi_id', $karyawan->unit_organisasi_id) === (string) $u->id)>
                                        {{ $u->nama_unit }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                {{-- JABATAN (SATPAM = DROPDOWN, SELAIN SATPAM & BORONG = FREETEXT) --}}
                <div x-show="tipe !== 'BORONG'">
                    <label class="lb">Nama Jabatan <span class="req">*</span></label>
                    <div x-show="isSatpam()">
                        <select name="nama_jabatan" x-model="jabatanDipilih" class="in"
                                :disabled="tipe === 'DISTRIBUSI' || tipe === 'BORONG' || !unitId || !isSatpam()"
                                :required="tipe !== 'DISTRIBUSI' && tipe !== 'BORONG' && isSatpam()">
                            <option value="">-- Pilih Jabatan Satpam --</option>
                            <template x-for="jbt in ['Anggota', 'Wadanru', 'Danru', 'Danton', 'Kepala Satpam']" :key="jbt">
                                <option :value="jbt" x-text="jbt" :selected="jabatanDipilih === jbt"></option>
                            </template>
                        </select>
                    </div>
                    <div x-show="!isSatpam()">
                        <input type="text" name="nama_jabatan" x-model="jabatanDipilih"
                               class="in uppercase"
                               placeholder="Contoh: AM JATIM 2, RM REGIONAL 1, SALES KANVAS, PELAKSANA"
                               :disabled="tipe === 'DISTRIBUSI' || tipe === 'BORONG' || !unitId || isSatpam()"
                               :required="tipe !== 'DISTRIBUSI' && tipe !== 'BORONG' && !isSatpam()">
                    </div>
                </div>
            </div>

            {{-- BAGIAN BORONG --}}
            @if(isset($jabatanBorong))
            <div x-show="tipe === 'BORONG'" x-cloak class="sb">
                <label class="lb">Jenis Pekerjaan Borong <span class="req">*</span></label>
                <select name="jabatan_borong_id" x-model="borongId" class="in"
                        :disabled="tipe !== 'BORONG'"
                        :required="tipe === 'BORONG'">
                    <option value="">-- Pilih Pekerjaan Borong --</option>
                    @foreach ($jabatanBorong as $jb)
                        <option value="{{ $jb->id }}" @selected((string) old('jabatan_borong_id', $karyawan->jabatan_borong_id) === (string) $jb->id)>
                            {{ $jb->nama_jabatan_borong }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- BAGIAN DISTRIBUSI --}}
            <div x-show="tipe === 'DISTRIBUSI'" x-cloak class="sb space-y-4">
                <input type="hidden" name="nama_jabatan" :value="finalJabatanDistribusi()" :disabled="tipe !== 'DISTRIBUSI'">

                <div>
                    <label class="lb">Jabatan Distribusi Cabang <span class="req">*</span></label>
                    <select x-model="distribusiJabatan" class="in"
                            @change="if (distribusiJabatan !== 'RM') rmRegional = ''; if (distribusiJabatan !== 'AM') amWilayah = '';"
                            :disabled="tipe !== 'DISTRIBUSI'"
                            :required="tipe === 'DISTRIBUSI'">
                        <option value="">-- Pilih Jabatan Distribusi --</option>
                        @foreach (['OB', 'Sales Motoris', 'Sales Kanvas', 'Sales Dropping', 'Sales CCO', 'TL', 'TL CCO', 'ADMIN', 'SS', 'AM', 'RM'] as $jbtDist)
                            <option value="{{ $jbtDist }}">{{ $jbtDist }}</option>
                        @endforeach
                        <template x-if="customDistribusiJabatan">
                            <option :value="customDistribusiJabatan" x-text="customDistribusiJabatan"></option>
                        </template>
                    </select>
                </div>

                <div x-show="distribusiJabatan === 'RM'" x-cloak class="rounded-lg bg-blue-50 border border-blue-200 p-4">
                    <label class="lb text-blue-900">Pilih Wilayah Regional (RM) <span class="req">*</span></label>
                    <select x-model="rmRegional" class="in" :required="tipe === 'DISTRIBUSI' && distribusiJabatan === 'RM'">
                        <option value="">-- Pilih Regional (1 - 3) --</option>
                        <option value="Regional 1">Regional 1</option>
                        <option value="Regional 2">Regional 2</option>
                        <option value="Regional 3">Regional 3</option>
                    </select>
                </div>

                <div x-show="distribusiJabatan === 'AM'" x-cloak class="rounded-lg bg-blue-50 border border-blue-200 p-4">
                    <label class="lb text-blue-900">Pilih Wilayah Area Manager (AM) <span class="req">*</span></label>
                    <select x-model="amWilayah" class="in" :required="tipe === 'DISTRIBUSI' && distribusiJabatan === 'AM'">
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

                @if(isset($kotaPerwakilan) && isset($area))
                <div x-show="distribusiJabatan !== 'RM' && distribusiJabatan !== 'AM'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="lb">Kota Perwakilan <span class="req">*</span></label>
                        <select name="kota_perwakilan_id" x-model="kotaId" class="in"
                                :disabled="tipe !== 'DISTRIBUSI' || distribusiJabatan === 'RM' || distribusiJabatan === 'AM'"
                                :required="tipe === 'DISTRIBUSI' && distribusiJabatan !== 'RM' && distribusiJabatan !== 'AM'">
                            <option value="">-- Pilih Kota --</option>
                            @foreach ($kotaPerwakilan as $kp)
                                <option value="{{ $kp->id }}" @selected((string) old('kota_perwakilan_id', $karyawan->kota_perwakilan_id) === (string) $kp->id)>
                                    {{ $kp->nama_kota }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="lb">Area <span class="req">*</span></label>
                        <select name="area_id" x-model="areaId" class="in"
                                :disabled="tipe !== 'DISTRIBUSI' || distribusiJabatan === 'RM' || distribusiJabatan === 'AM'"
                                :required="tipe === 'DISTRIBUSI' && distribusiJabatan !== 'RM' && distribusiJabatan !== 'AM'">
                            <option value="">-- Pilih Area --</option>
                            @foreach ($area as $a)
                                <option value="{{ $a->id }}" @selected((string) old('area_id', $karyawan->area_id) === (string) $a->id)>
                                    {{ $a->nama_area }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- 2. DATA DIRI, ORANG TUA & PENDIDIKAN --}}
        <div class="cd space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b pb-2">2. Data Diri, Orang Tua & Pendidikan</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="lb">Nama Lengkap Sesuai KTP <span class="req">*</span></label>
                    <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap', $karyawan->nama_lengkap) }}" class="in uppercase" required>
                </div>
                <div>
                    <label class="lb">NIK KTP (16 Angka) <span class="req">*</span></label>
                    <input type="text" name="nik_ktp"
                           value="{{ old('nik_ktp', $karyawan->nik_ktp) }}"
                           oninput="this.value = this.value.replace(/\D/g, '').slice(0, 16)"
                           maxlength="16" inputmode="numeric" class="in font-mono" required>
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

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="lb">Jenis Kelamin <span class="req">*</span></label>
                    <select name="jenis_kelamin" class="in" required>
                        <option value="L" @selected(old('jenis_kelamin', $karyawan->jenis_kelamin) === 'L')>Laki-laki</option>
                        <option value="P" @selected(old('jenis_kelamin', $karyawan->jenis_kelamin) === 'P')>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="lb">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $karyawan->tempat_lahir) }}" class="in uppercase" placeholder="Contoh: KUDUS">
                </div>
                <div>
                    <label class="lb">Tanggal Lahir <span class="req">*</span></label>
                    <input type="date" name="tgl_lahir" value="{{ old('tgl_lahir', $karyawan->tgl_lahir) }}" class="in" required>
                </div>
                <div>
                    <label class="lb">Golongan Darah</label>
                    <select name="golongan_darah" class="in">
                        @foreach(['-'=>'Tidak diketahui', 'A'=>'A', 'B'=>'B', 'AB'=>'AB', 'O'=>'O'] as $gV => $gLabel)
                            <option value="{{ $gV }}" @selected(old('golongan_darah', $karyawan->golongan_darah ?? '-') === $gV)>{{ $gLabel }}</option>
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
                    <input type="text" name="no_wa" inputmode="numeric" value="{{ old('no_wa', $karyawan->no_wa) }}" class="in" placeholder="08xxxxxxxxxx">
                </div>
                <div>
                    <label class="lb">Email</label>
                    <input type="email" name="email" value="{{ old('email', $karyawan->email) }}" class="in" placeholder="email@gmail.com">
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

        {{-- 3. REPEATER RIWAYAT PENDIDIKAN --}}
        <div class="cd space-y-4">
            <div class="flex items-center justify-between border-b pb-2">
                <div>
                    <h3 class="text-base font-bold text-slate-900">3. Riwayat Pendidikan (SD s/d Terakhir) <span class="req">*</span></h3>
                    <p class="text-xs text-slate-500">Isi riwayat jenjang pendidikan dari SD hingga pendidikan terakhir, atau pilih Tidak Sekolah.</p>
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
                            <label class="lb">Jenjang Pendidikan <span class="req">*</span></label>
                            <select :name="'riwayat_pendidikan[' + index + '][jenjang]'" x-model="rp.jenjang" @change="onJenjangChange(rp)" class="in" required>
                                <option value="">-- Pilih Jenjang --</option>
                                <option value="TIDAK SEKOLAH">Tidak Sekolah</option>
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
                            <input type="text"
                                   :name="'riwayat_pendidikan[' + index + '][nama_instansi]'"
                                   x-model="rp.nama_instansi"
                                   :readonly="rp.jenjang === 'TIDAK SEKOLAH'"
                                   :class="rp.jenjang === 'TIDAK SEKOLAH' ? 'in uppercase bg-slate-100 text-slate-500 cursor-not-allowed' : 'in uppercase'"
                                   placeholder="Nama Sekolah / Kampus" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="lb">Jurusan / Program Studi</label>
                            <input type="text"
                                   :name="'riwayat_pendidikan[' + index + '][jurusan]'"
                                   x-model="rp.jurusan"
                                   :readonly="rp.jenjang === 'TIDAK SEKOLAH'"
                                   :class="rp.jenjang === 'TIDAK SEKOLAH' ? 'in uppercase bg-slate-100 text-slate-500 cursor-not-allowed' : 'in uppercase'"
                                   placeholder="Jurusan (Kosongkan jika SD/SMP)">
                        </div>
                        <div>
                            <label class="lb">Nilai Akhir / IPK</label>
                            <input type="text"
                                   :name="'riwayat_pendidikan[' + index + '][nilai_ipk]'"
                                   x-model="rp.nilai_ipk"
                                   :readonly="rp.jenjang === 'TIDAK SEKOLAH'"
                                   :class="rp.jenjang === 'TIDAK SEKOLAH' ? 'in bg-slate-100 text-slate-500 cursor-not-allowed' : 'in'"
                                   placeholder="Nilai / IPK">
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
                    <input type="text" name="no_rekening"
                           x-model="rek"
                           @input="rek = rek.replace(/\D/g, '').slice(0, 15)"
                           maxlength="15" inputmode="numeric"
                           class="in font-mono" placeholder="BNI (9-10) / BRI (15)"
                           pattern="^(\d{9,10}|\d{15})?$"
                           title="Kosongkan, atau masukkan 9-10 angka (BNI) / 15 angka (BRI)">
                    <p class="hint" :class="bank().w" x-text="bank().p"></p>
                </div>
                <div>
                    <label class="lb">Atas Nama Rekening</label>
                    <input type="text" name="nama_pemilik_rekening" value="{{ old('nama_pemilik_rekening', $karyawan->nama_pemilik_rekening) }}" class="in uppercase">
                </div>
                <div>
                    <label class="lb">Kesediaan Transfer Gaji <span class="req">*</span></label>
                    <select name="bersedia_transfer" class="in" required>
                        <option value="YA" @selected(old('bersedia_transfer', $karyawan->bersedia_transfer) === 'YA')>Iya, Bersedia</option>
                        <option value="TIDAK" @selected(old('bersedia_transfer', $karyawan->bersedia_transfer) === 'TIDAK')>Tidak</option>
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

        {{-- 5. RIWAYAT PEKERJAAN DI SUKUN --}}
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
                        <button type="button" @click="hapusItem(sukunList, index, { bagian: '', jabatan: '', tahun_mulai: '', tahun_selesai: '' })" class="text-xs text-red-500 hover:underline">Hapus</button>
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

        {{-- 6. PENGALAMAN KERJA SEBELUM --}}
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
                        <button type="button" @click="hapusItem(luarList, index, { perusahaan: '', jabatan: '', tahun_masuk: '', tahun_keluar: '' })" class="text-xs text-red-500 hover:underline">Hapus</button>
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
                            <input type="number" min="1970" max="{{ now()->year }}" :name="'riwayat_luar[' + index + '][tahun_masuk]'" x-model="rwl.tahun_masuk" class="in" placeholder="Contoh: 2017">
                        </div>
                        <div>
                            <label class="lb">Tahun Keluar</label>
                            <input type="number" min="1970" max="{{ now()->year + 5 }}" :name="'riwayat_luar[' + index + '][tahun_keluar]'" x-model="rwl.tahun_keluar" class="in" placeholder="Contoh: 2020">
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
                    <label class="lb">Status Pernikahan <span class="req">*</span></label>
                    <select name="status_pernikahan" x-model="nikah" class="in" required>
                        <option value="BELUM_KAWIN">Belum menikah</option>
                        <option value="KAWIN">Sudah menikah</option>
                        <option value="CERAI_HIDUP">Cerai hidup</option>
                        <option value="CERAI_MATI">Cerai mati</option>
                    </select>
                </div>
                <div x-show="nikah === 'KAWIN'" x-cloak class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="lb">Nama Suami / Istri <span class="req">*</span></label>
                        <input type="text" name="nama_pasangan"
                               value="{{ old('nama_pasangan', $pasangan->nama_pasangan ?? '') }}"
                               :disabled="nikah !== 'KAWIN'"
                               :required="nikah === 'KAWIN'"
                               class="in uppercase">
                    </div>
                    <div>
                        <label class="lb">Pekerjaan Pasangan</label>
                        <input type="text" name="pekerjaan_pasangan"
                               value="{{ old('pekerjaan_pasangan', $pasangan->pekerjaan_pasangan ?? '') }}"
                               :disabled="nikah !== 'KAWIN'"
                               class="in">
                    </div>
                </div>
            </div>

            <div x-show="nikah !== 'BELUM_KAWIN'" x-cloak class="pt-2">
                <label class="lb">Jumlah Anak (Maksimal 10)</label>
                <select name="jumlah_anak" x-model.number="anakN" :disabled="nikah === 'BELUM_KAWIN'" class="in">
                    <option value="0">Tidak ada / 0 anak</option>
                    <template x-for="n in 10" :key="n">
                        <option :value="n" x-text="n + ' anak'"></option>
                    </template>
                </select>
            </div>

            <div class="space-y-4 pt-2" x-show="nikah !== 'BELUM_KAWIN' && anakN > 0" x-cloak>
                <template x-for="i in anakN" :key="i">
                    <div class="sb space-y-3 bg-white p-4 rounded-lg border border-slate-200">
                        <p class="text-sm font-semibold text-blue-900" x-text="'Anak ke-' + i"></p>
                        <div>
                            <label class="lb">Nama Lengkap Anak <span class="req">*</span></label>
                            <input type="text" :name="'anak[' + i + '][nama_anak]'" x-model="anakList[i-1].nama_anak" required class="in uppercase" placeholder="Nama Lengkap Anak">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="lb">Tanggal Lahir</label>
                                <input type="date" :name="'anak[' + i + '][tgl_lahir_anak]'" x-model="anakList[i-1].tgl_lahir_anak" class="in">
                            </div>
                            <div>
                                <label class="lb">Pendidikan</label>
                                <select :name="'anak[' + i + '][pendidikan_anak]'" class="in" x-model="anakList[i-1].pendidikan_anak">
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
            <button type="submit" :disabled="isSubmitting"
                    class="w-full rounded-lg bg-blue-600 px-5 py-3.5 text-base font-bold text-white hover:bg-blue-700 transition shadow disabled:opacity-50 disabled:cursor-not-allowed"
                    x-text="isSubmitting ? 'Sedang Menyimpan...' : 'Simpan Seluruh Perubahan Karyawan'">
            </button>
        </div>
    </form>
</div>

@verbatim
<script>
function editHrdForm(initialData) {
    return Object.assign({}, initialData, {
        init() {
            this.rek = String(this.rek || '').replace(/\D/g, '').slice(0, 15);
            this.normalisasiArray();

            if (this.nikah === 'BELUM_KAWIN') {
                this.anakN = 0;
                this.anakList = [];
            } else {
                this.siapkanAnak();
            }

            this.$watch('anakN', () => {
                if (this.nikah !== 'BELUM_KAWIN') {
                    this.siapkanAnak();
                }
            });

            this.$watch('nikah', (val) => {
                if (val === 'BELUM_KAWIN') {
                    this.anakN = 0;
                    this.anakList = [];
                } else {
                    this.siapkanAnak();
                }
            });

            const opsiDistribusi = ['OB', 'Sales Motoris', 'Sales Kanvas', 'Sales Dropping', 'Sales CCO', 'TL', 'TL CCO', 'ADMIN', 'SS', 'AM', 'RM'];
            const opsiAmWilayah  = ['Jateng 1', 'Jateng 2', 'Jateng 3', 'Jabar 1', 'Jabar 2', 'Jatim 1', 'Jatim 2', 'Jatim 3', 'Sumatra', 'Kalimantan'];
            const jabatanLama    = String(this.jabatanDipilih || '').trim();
            const jabatanUpper   = jabatanLama.toUpperCase();

            if (jabatanUpper.startsWith('RM')) {
                this.distribusiJabatan = 'RM';
                if (/REGIONAL\s*1/i.test(jabatanLama)) this.rmRegional = 'Regional 1';
                else if (/REGIONAL\s*2/i.test(jabatanLama)) this.rmRegional = 'Regional 2';
                else if (/REGIONAL\s*3/i.test(jabatanLama)) this.rmRegional = 'Regional 3';
            } else if (jabatanUpper === 'AM' || jabatanUpper.startsWith('AM ')) {
                this.distribusiJabatan = 'AM';
                const rawWilayah = jabatanLama.replace(/^AM\s*/i, '').trim();
                const matchAm = opsiAmWilayah.find(w => w.toUpperCase() === rawWilayah.toUpperCase());
                this.amWilayah = matchAm || rawWilayah;
            } else if (this.tipe === 'DISTRIBUSI' && jabatanLama !== '') {
                const matchDist = opsiDistribusi.find(j => j.toUpperCase() === jabatanUpper);
                if (matchDist) {
                    this.distribusiJabatan = matchDist;
                } else {
                    this.customDistribusiJabatan = jabatanLama;
                    this.$nextTick(() => {
                        this.distribusiJabatan = jabatanLama;
                    });
                }
            }

            this.$nextTick(() => {
                const selectUnit = document.getElementById('unit_organisasi_id');
                if (selectUnit && selectUnit.selectedOptions.length > 0) {
                    this.unitNama = selectUnit.selectedOptions[0].text.trim();
                }

                if (this.isSatpam() && this.jabatanDipilih) {
                    const opsiSatpam = ['Anggota', 'Wadanru', 'Danru', 'Danton', 'Kepala Satpam'];
                    const matchSatpam = opsiSatpam.find(s => s.toUpperCase() === String(this.jabatanDipilih).trim().toUpperCase());
                    if (matchSatpam) {
                        this.jabatanDipilih = matchSatpam;
                    }
                }
            });
        },

        normalisasiArray() {
            if (!Array.isArray(this.anakList)) this.anakList = Object.values(this.anakList || {});
            if (!Array.isArray(this.pendList)) this.pendList = Object.values(this.pendList || {});
            if (!Array.isArray(this.sukunList)) this.sukunList = Object.values(this.sukunList || {});
            if (!Array.isArray(this.luarList)) this.luarList = Object.values(this.luarList || {});

            if (this.pendList.length === 0) {
                this.pendList = [{ jenjang: 'SD', nama_instansi: '', jurusan: '', nilai_ipk: '' }];
            }
            this.pendList.forEach((rp) => {
                if (rp.jenjang === 'TIDAK SEKOLAH') {
                    rp.nama_instansi = 'TIDAK SEKOLAH';
                    rp.jurusan = '';
                    rp.nilai_ipk = '';
                }
            });
            if (this.sukunList.length === 0) {
                this.sukunList = [{ bagian: '', jabatan: '', tahun_mulai: '', tahun_selesai: '' }];
            }
            if (this.luarList.length === 0) {
                this.luarList = [{ perusahaan: '', jabatan: '', tahun_masuk: '', tahun_keluar: '' }];
            }
        },

        onJenjangChange(rp) {
            if (rp.jenjang === 'TIDAK SEKOLAH') {
                rp.nama_instansi = 'TIDAK SEKOLAH';
                rp.jurusan = '';
                rp.nilai_ipk = '';
            } else if (rp.nama_instansi === 'TIDAK SEKOLAH') {
                rp.nama_instansi = '';
            }
        },

        hapusItem(list, index, emptyTemplate) {
            if (list.length > 1) {
                list.splice(index, 1);
            } else {
                Object.assign(list[0], emptyTemplate);
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

        bank() {
            const jumlahDigit = String(this.rek || '').replace(/\D/g, '').length;
            if (jumlahDigit === 15) return { w: 'text-emerald-700', p: 'Terdeteksi BRI (15 angka)' };
            if (jumlahDigit === 10) return { w: 'text-emerald-700', p: 'Terdeteksi BNI (10 angka)' };
            if (jumlahDigit === 9) return { w: 'text-emerald-700', p: 'Terdeteksi BNI Taplus Lama (9 angka)' };
            if (jumlahDigit === 0) return { w: 'text-slate-500', p: 'Opsional. Boleh dikosongkan.' };
            return { w: 'text-red-600', p: 'Jumlah angka saat ini ' + jumlahDigit + ' (wajib 15 untuk BRI, 9-10 untuk BNI)' };
        }
    });
}
</script>
@endverbatim

</body>
</html>