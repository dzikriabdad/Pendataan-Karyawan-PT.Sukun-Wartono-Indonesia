<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PendataanController extends Controller
{
    public function create()
    {
        return view('pendataan.form', [
            'departemen'        => DB::table('departemen')->get(),
            'unitOrganisasi'    => DB::table('unit_organisasi')->orderBy('nama_unit')->get(),
            'golongan'          => DB::table('golongan')->get(),
            'jabatan'           => DB::table('jabatan')->get(),
            'jabatanBorong'     => DB::table('jabatan_borong')->get(),
            'jabatanDistribusi' => DB::table('jabatan_distribusi')->orderBy('kode_jabatan')->get(),
            'kotaPerwakilan'    => DB::table('kota_perwakilan')->orderBy('nama_kota')->get(),
            'area'              => DB::table('area')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nik_ktp'       => 'required|digits:16',
            'nama_lengkap'  => 'required|string|max:150',
            'jenis_kelamin' => 'required|in:L,P',
            'tgl_lahir'     => 'required|date',
            'alamat_ktp'    => 'required|string',
            'tipe_karyawan' => 'required|in:BULANAN,HARIAN,BORONG,DISTRIBUSI',
            'ket_karyawan'  => 'required|in:TETAP,HARIAN_LEPAS,TIDAK_TETAP,KONTRAK',

            'tgl_masuk_tanggal' => 'nullable|integer|between:1,31',
            'tgl_masuk_bulan'   => 'required|integer|between:1,12',
            'tgl_masuk_tahun'   => 'required|integer|between:1970,' . now()->year,

            'no_rekening' => [
             'nullable',
              'regex:/^(?:[0-9]{9,10}|[0-9]{15})$/',
            ],  
            
            'bersedia_transfer' => 'required|in:YA,TIDAK',
            'alamat_domisili'   => 'nullable|string',

            'unit_organisasi_id'    => 'nullable',
            'nama_jabatan'          => 'nullable|string|max:150',
            'jabatan_borong_id'     => 'nullable|exists:jabatan_borong,id',
            'kota_perwakilan_id'    => 'nullable|exists:kota_perwakilan,id',
            'area_id'               => 'nullable|exists:area,id',

            // Validasi Riwayat Pendidikan (Repeater Tanpa Tahun)
            'riwayat_pendidikan'                 => 'required|array|min:1',
            'riwayat_pendidikan.*.jenjang'       => 'required|string|max:50',
            'riwayat_pendidikan.*.nama_instansi' => 'required|string|max:150',
            'riwayat_pendidikan.*.jurusan'       => 'nullable|string|max:150',
            'riwayat_pendidikan.*.nilai_ipk'     => 'nullable|string|max:50',

            // Nama Orang Tua (Mandatory)
            'nama_ayah'         => 'required|string|max:150',
            'nama_ibu'          => 'required|string|max:150',
            'status_pernikahan' => 'required|in:BELUM_KAWIN,KAWIN,CERAI_HIDUP,CERAI_MATI',

            'nama_pasangan'      => 'nullable|string|max:150',
            'pekerjaan_pasangan' => 'nullable|string|max:150',
            'jumlah_anak'        => 'nullable|integer|between:0,10',
            'anak'               => 'nullable|array|max:10',
            'anak.*.nama_anak'   => 'nullable|string|max:150',
            'anak.*.tgl_lahir_anak' => 'nullable|date',
            'anak.*.pendidikan_anak' => 'nullable|string|max:50',

            'no_wa'                     => 'nullable|string|max:30',
            'email'                     => 'nullable|email|max:150',
            'golongan_darah'            => 'nullable|string|max:5',
            'disabilitas'               => 'nullable|string|max:100',
            'golongan_id'               => 'nullable|exists:golongan,id',
            'no_bpjs_kesehatan'         => 'nullable|string|max:50',
            'no_bpjs_ketenagakerjaan'   => 'nullable|string|max:50',
            'npwp'                      => 'nullable|string|max:50',
            'no_anggota_koperasi'       => 'nullable|string|max:50',
            'nama_pemilik_rekening'     => 'nullable|string|max:150',
            'nip'                       => 'nullable|string|max:50',

            // Validasi Riwayat Luar & Riwayat Sukun
            'riwayat_luar'                => 'nullable|array',
            'riwayat_luar.*.perusahaan'   => 'nullable|string|max:150',
            'riwayat_luar.*.jabatan'      => 'nullable|string|max:150',
            'riwayat_luar.*.tahun_masuk'  => 'nullable|string|max:10',
            'riwayat_luar.*.tahun_keluar' => 'nullable|string|max:10',

            'riwayat_sukun'                => 'nullable|array',
            'riwayat_sukun.*.bagian'       => 'nullable|string|max:150',
            'riwayat_sukun.*.jabatan'      => 'nullable|string|max:150',
            'riwayat_sukun.*.tahun_mulai'  => 'nullable|string|max:10',
            'riwayat_sukun.*.tahun_selesai' => 'nullable|string|max:10',
        ], [
            'nik_ktp.required'                     => 'Nomor KTP (NIK) wajib diisi.',
            'nik_ktp.digits'                       => 'Nomor KTP (NIK) harus pas 16 angka.',
            'no_rekening.regex'                    => 'Jika diisi, nomor rekening harus 9-10 angka untuk BNI atau 15 angka untuk BRI.',
            'bersedia_transfer.required'           => 'Pertanyaan kesediaan transfer gaji wajib dipilih.',
            'nama_ayah.required'                   => 'Nama lengkap ayah kandung wajib diisi.',
            'nama_ibu.required'                    => 'Nama lengkap ibu kandung wajib diisi.',
            'tgl_masuk_bulan.required'             => 'Bulan masuk kerja wajib dipilih.',
            'tgl_masuk_tahun.required'             => 'Tahun masuk kerja wajib dipilih.',
            'riwayat_pendidikan.required'          => 'Riwayat pendidikan wajib diisi minimal 1.',
            'riwayat_pendidikan.*.jenjang.required'      => 'Jenjang pendidikan wajib dipilih.',
            'riwayat_pendidikan.*.nama_instansi.required' => 'Nama sekolah / universitas wajib diisi.',
        ]);

        $karyawanExisting = DB::table('karyawan')->where('nik_ktp', $data['nik_ktp'])->first();
        if ($karyawanExisting) {
            return redirect()->route('pendataan.create')->withErrors([
                'nik_ktp' => 'Nomor KTP (NIK) ' . $data['nik_ktp'] . ' sudah pernah mengisi data pendataan. Silakan hubungi bagian HRD PT. Sukun Wartono Indonesia.'
            ])->withInput();
        }

        if (!empty($data['nip'])) {
            $nipExisting = DB::table('karyawan')->where('nip', $data['nip'])->first();
            if ($nipExisting) {
                return redirect()->route('pendataan.create')->withErrors([
                    'nip' => 'NIP ' . $data['nip'] . ' sudah terdaftar dalam sistem.'
                ])->withInput();
            }
        }

        if ($request->input('status_pernikahan') === 'KAWIN' && ! $request->filled('nama_pasangan')) {
            throw ValidationException::withMessages([
                'nama_pasangan' => 'Nama suami / istri wajib diisi.',
            ]);
        }

        $statusPernikahan = $request->input('status_pernikahan');
        $jumlahAnak = ($statusPernikahan === 'BELUM_KAWIN') ? 0 : (int) $request->input('jumlah_anak', 0);
        $dataAnakRequest = $request->input('anak', []);

        if ($statusPernikahan !== 'BELUM_KAWIN' && $jumlahAnak > 0) {
            for ($i = 1; $i <= $jumlahAnak; $i++) {
                $namaAnak = trim((string) data_get($dataAnakRequest, $i . '.nama_anak', ''));
                if ($namaAnak === '') {
                    throw ValidationException::withMessages([
                        "anak.$i.nama_anak" => "Nama anak ke-$i wajib diisi.",
                    ]);
                }
            }
        }

        $hariDiketahui = filled($data['tgl_masuk_tanggal'] ?? null);
        $hari = $hariDiketahui ? (int) $data['tgl_masuk_tanggal'] : 1;
        $bulan = (int) $data['tgl_masuk_bulan'];
        $tahun = (int) $data['tgl_masuk_tahun'];

        if (! checkdate($bulan, $hari, $tahun)) {
            throw ValidationException::withMessages([
                'tgl_masuk_tanggal' => 'Tanggal tidak valid untuk bulan dan tahun yang dipilih.',
            ]);
        }

        $tglMasukKerja = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);

        DB::transaction(function () use ($request, $data, $hariDiketahui, $tglMasukKerja, $jumlahAnak, $statusPernikahan) {
            $inputNip = trim((string) $request->input('nip', ''));
            $nip = ($inputNip !== '') ? $inputNip : ('AUTO-' . date('ym') . '-' . rand(10000, 99999));
            $token = bin2hex(random_bytes(32));

            $noRek = preg_replace('/[^0-9]/', '', $request->input('no_rekening', ''));
            $bankId = null;

            if (strlen($noRek) === 15) {
               $bankId = DB::table('bank')->where('nama_bank', 'BRI')->value('id');
            } elseif (strlen($noRek) === 9 || strlen($noRek) === 10) {
                $bankId = DB::table('bank')->where('nama_bank', 'BNI')->value('id');
            }

            $unitId = $request->input('unit_organisasi_id');
            $departemenId = null;
            $namaJabatan = $request->filled('nama_jabatan') ? strtoupper(trim($request->input('nama_jabatan'))) : null;
            $kotaId = $request->input('kota_perwakilan_id');
            $areaId = $request->input('area_id');

            // LOGIKA KHUSUS DISTRIBUSI
            if ($request->input('tipe_karyawan') === 'DISTRIBUSI') {
                $departemenId = DB::table('departemen')->where('nama_departemen', 'DISTRIBUSI')->value('id') 
                           ?: DB::table('departemen')->where('nama_departemen', 'like', '%DISTRIBUSI%')->value('id');

                $unitId = null;

                if ($namaJabatan && str_starts_with($namaJabatan, 'RM')) {
                    $kotaId = null;
                    $areaId = null;
                    preg_match('/REGIONAL\s*[1-3]/i', $namaJabatan, $matches);
                    $regName = !empty($matches[0]) ? strtoupper($matches[0]) : 'REGIONAL';
                    
                    $unitId = DB::table('unit_organisasi')
                        ->where('departemen_id', $departemenId)
                        ->where('nama_unit', 'like', '%' . $regName . '%')
                        ->value('id');

                    if (! $unitId) {
                        try {
                            $unitId = DB::table('unit_organisasi')->insertGetId([
                                'departemen_id' => $departemenId,
                                'nama_unit'     => $regName,
                            ]);
                        } catch (\Throwable $th) {}
                    }
                } elseif ($namaJabatan && str_starts_with($namaJabatan, 'AM')) {
                    $kotaId = null;
                    $areaId = null;
                    $wilayahAM = trim(str_replace('AM', '', $namaJabatan));
                    
                    $unitId = DB::table('unit_organisasi')
                        ->where('departemen_id', $departemenId)
                        ->where(function($q) use ($wilayahAM) {
                            $q->where('nama_unit', 'like', '%' . $wilayahAM . '%')
                              ->orWhere('nama_unit', 'like', '%AM ' . $wilayahAM . '%');
                        })
                        ->where('nama_unit', 'not like', '%PUSAT%')
                        ->value('id');

                    if (! $unitId && !empty($wilayahAM)) {
                        try {
                            $unitId = DB::table('unit_organisasi')->insertGetId([
                                'departemen_id' => $departemenId,
                                'nama_unit'     => 'AM ' . strtoupper($wilayahAM),
                            ]);
                        } catch (\Throwable $th) {}
                    }
                } elseif ($kotaId) {
                    $namaKota = DB::table('kota_perwakilan')->where('id', $kotaId)->value('nama_kota');
                    if ($namaKota) {
                        $unitId = DB::table('unit_organisasi')
                            ->where('departemen_id', $departemenId)
                            ->where(function($q) use ($namaKota) {
                                $q->where('nama_unit', 'like', 'PERWAKILAN ' . $namaKota)
                                  ->orWhere('nama_unit', 'like', '%' . $namaKota . '%');
                            })
                            ->where('nama_unit', 'not like', '%PUSAT%')
                            ->value('id');

                        if (! $unitId) {
                            try {
                                $unitId = DB::table('unit_organisasi')->insertGetId([
                                    'departemen_id' => $departemenId,
                                    'nama_unit'     => 'PERWAKILAN ' . strtoupper($namaKota),
                                ]);
                            } catch (\Throwable $th) {}
                        }
                    }
                }

                if (! $unitId) {
                    $unitId = DB::table('unit_organisasi')
                        ->where('departemen_id', $departemenId)
                        ->where('nama_unit', 'like', 'PERWAKILAN%')
                        ->value('id');
                }

                if (! $unitId) {
                    $unitId = DB::table('unit_organisasi')
                        ->where('departemen_id', $departemenId)
                        ->where('nama_unit', 'not like', '%PUSAT%')
                        ->value('id');
                }

                if (! $unitId) {
                    $unitId = DB::table('unit_organisasi')->where('departemen_id', $departemenId)->value('id');
                }
            } elseif ($unitId) {
                $departemenId = DB::table('unit_organisasi')->where('id', $unitId)->value('departemen_id');
            } elseif ($request->input('tipe_karyawan') === 'BORONG') {
                $departemenId = DB::table('departemen')->where('nama_departemen', 'like', '%PRODUKSI%')
                           ->orWhere('nama_departemen', 'like', '%BORONG%')->value('id');
                if (!$unitId && $departemenId) {
                    $unitId = DB::table('unit_organisasi')->where('departemen_id', $departemenId)->value('id');
                }
            }

            if (!$departemenId && $unitId) {
                $departemenId = DB::table('unit_organisasi')->where('id', $unitId)->value('departemen_id');
            }

            // Ambil pendidikan terakhir untuk rangkuman di tabel karyawan
            $listPend = $data['riwayat_pendidikan'] ?? [];
            $pendidikanPalingAkhir = end($listPend);

            $statusVerifikasi = ($inputNip !== '' && !empty($noRek)) ? 'LENGKAP' : 'BELUM_LENGKAP';

            $karyawanId = DB::table('karyawan')->insertGetId([
                'token'                     => $token,
                'nip'                       => $nip,
                'nik_ktp'                   => $data['nik_ktp'],
                'nama_lengkap'              => strtoupper($data['nama_lengkap']),
                'jenis_kelamin'             => $data['jenis_kelamin'],
                'tgl_lahir'                 => $data['tgl_lahir'],
                'golongan_darah'            => $request->input('golongan_darah', '-'),
                'pendidikan_terakhir'       => $pendidikanPalingAkhir['jenjang'] ?? 'SD',
                'jurusan'                   => !empty($pendidikanPalingAkhir['jurusan']) ? strtoupper($pendidikanPalingAkhir['jurusan']) : null,
                'nama_instansi'             => !empty($pendidikanPalingAkhir['nama_instansi']) ? strtoupper($pendidikanPalingAkhir['nama_instansi']) : null,
                'nilai'                     => $pendidikanPalingAkhir['nilai_ipk'] ?? null,
                'no_wa'                     => $request->input('no_wa'),
                'email'                     => $request->input('email'),
                'nama_ayah'                 => strtoupper($data['nama_ayah']),
                'nama_ibu'                  => strtoupper($data['nama_ibu']),
                'status_pernikahan'         => $statusPernikahan,
                'disabilitas'               => $request->input('disabilitas'),
                'tipe_karyawan'             => $request->input('tipe_karyawan'),
                'ket_karyawan'              => $request->input('ket_karyawan', 'TETAP'),
                'departemen_id'             => $departemenId,
                'unit_organisasi_id'        => $unitId,
                'golongan_id'               => $request->input('golongan_id'),
                'nama_jabatan'              => $namaJabatan,
                'jabatan_borong_id'         => $request->input('tipe_karyawan') === 'BORONG' ? $request->input('jabatan_borong_id') : null,
                'kota_perwakilan_id'        => $request->input('tipe_karyawan') === 'DISTRIBUSI' ? $kotaId : null,
                'area_id'                   => $request->input('tipe_karyawan') === 'DISTRIBUSI' ? $areaId : null,
                'tgl_masuk_kerja'           => $tglMasukKerja,
                'tgl_masuk_hari_diketahui'  => $hariDiketahui,
                'bank_id'                   => $bankId,
                'no_rekening'               => $noRek,
                'bersedia_transfer'         => $request->input('bersedia_transfer'),
                'nama_pemilik_rekening'     => strtoupper($request->input('nama_pemilik_rekening', $data['nama_lengkap'])),
                'no_bpjs_kesehatan'         => $request->input('no_bpjs_kesehatan'),
                'no_bpjs_ketenagakerjaan'   => $request->input('no_bpjs_ketenagakerjaan'),
                'npwp'                      => $request->input('npwp'),
                'no_anggota_koperasi'       => $request->input('no_anggota_koperasi'),
                'status_verifikasi'         => $statusVerifikasi,
                'created_at'                => now(),
                'updated_at'                => now(),
            ]);

            // SIMPAN RIWAYAT PENDIDIKAN KE TABEL riwayat_pendidikan (TANPA TAHUN)
            foreach ($data['riwayat_pendidikan'] as $pend) {
                if (!empty($pend['jenjang']) && !empty($pend['nama_instansi'])) {
                    DB::table('riwayat_pendidikan')->insert([
                        'karyawan_id'   => $karyawanId,
                        'jenjang'       => strtoupper($pend['jenjang']),
                        'nama_instansi' => strtoupper($pend['nama_instansi']),
                        'jurusan'       => !empty($pend['jurusan']) ? strtoupper($pend['jurusan']) : null,
                        'nilai_ipk'     => !empty($pend['nilai_ipk']) ? $pend['nilai_ipk'] : null,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }

            DB::table('alamat')->insert([
                'karyawan_id'    => $karyawanId,
                'jenis_alamat'   => 'KTP',
                'alamat_lengkap' => $data['alamat_ktp'],
            ]);

            DB::table('alamat')->insert([
                'karyawan_id'    => $karyawanId,
                'jenis_alamat'   => 'DOMISILI',
                'alamat_lengkap' => $request->input('alamat_domisili') ?: $data['alamat_ktp'],
            ]);

            if ($statusPernikahan === 'KAWIN' && $request->filled('nama_pasangan')) {
                DB::table('pasangan')->insert([
                    'karyawan_id'        => $karyawanId,
                    'nama_pasangan'      => strtoupper($request->input('nama_pasangan')),
                    'pekerjaan_pasangan' => $request->input('pekerjaan_pasangan'),
                ]);
            }

            if ($statusPernikahan !== 'BELUM_KAWIN' && $jumlahAnak > 0) {
                $dataAnak = $request->input('anak', []);
                for ($i = 1; $i <= $jumlahAnak; $i++) {
                    if (!empty($dataAnak[$i]['nama_anak'])) {
                        DB::table('anak')->insert([
                            'karyawan_id'     => $karyawanId,
                            'anak_ke'         => $i,
                            'nama_anak'       => strtoupper($dataAnak[$i]['nama_anak']),
                            'tgl_lahir_anak'  => $dataAnak[$i]['tgl_lahir_anak'] ?? null,
                            'pendidikan_anak' => $dataAnak[$i]['pendidikan_anak'] ?? null,
                        ]);
                    }
                }
            }

            // SIMPAN RIWAYAT PEKERJAAN SUKUN
            $colSukunType = 'varchar';
            try {
                $colInfo = DB::selectOne("SHOW COLUMNS FROM riwayat_pekerjaan_sukun WHERE Field = 'tgl_mulai'");
                if ($colInfo && str_contains(strtolower($colInfo->Type), 'date')) {
                    $colSukunType = 'date';
                }
            } catch (\Throwable $th) {}

            $riwayatSukun = $request->input('riwayat_sukun', []);
            foreach ($riwayatSukun as $rw) {
                $bagian = trim((string)($rw['bagian'] ?? ''));
                $jabatan = trim((string)($rw['jabatan'] ?? ''));
                $thnMulai = trim((string)($rw['tahun_mulai'] ?? ''));
                $thnSelesai = trim((string)($rw['tahun_selesai'] ?? ''));

                if ($bagian !== '' || $jabatan !== '' || $thnMulai !== '' || $thnSelesai !== '') {
                    $valMulai = $thnMulai ?: null;
                    $valSelesai = $thnSelesai ?: null;

                    if ($colSukunType === 'date') {
                        $valMulai = $thnMulai ? (strlen($thnMulai) === 4 ? $thnMulai . '-01-01' : $thnMulai) : null;
                        $valSelesai = $thnSelesai ? (strlen($thnSelesai) === 4 ? $thnSelesai . '-12-31' : $thnSelesai) : null;
                    }

                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawanId,
                        'unit_bagian' => $bagian ?: null,
                        'jabatan'     => $jabatan ?: null,
                        'tgl_mulai'   => $valMulai,
                        'tgl_selesai' => $valSelesai,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            // SIMPAN PENGALAMAN KERJA SEBELUM
            $riwayatLuar = $request->input('riwayat_luar', []);
            foreach ($riwayatLuar as $rwl) {
                $perusahaan = trim((string)($rwl['perusahaan'] ?? ''));
                $jabatan = trim((string)($rwl['jabatan'] ?? ''));
                $thnMasuk = trim((string)($rwl['tahun_masuk'] ?? ''));
                $thnKeluar = trim((string)($rwl['tahun_keluar'] ?? ''));

                if ($perusahaan !== '' || $jabatan !== '' || $thnMasuk !== '' || $thnKeluar !== '') {
                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawanId,
                        'nama_perusahaan' => $perusahaan ?: null,
                        'jabatan'         => $jabatan ?: null,
                        'tahun_masuk'     => $thnMasuk ?: null,
                        'tahun_keluar'    => $thnKeluar ?: null,
                    ]);
                }
            }
        });

        return redirect()->route('pendataan.create')->with('sukses', 'Terima kasih! Data Anda sudah berhasil masuk ke HRD Sukun.');
    }

    public function detail($id)
    {
        $karyawan = DB::table('karyawan')
            ->leftJoin('departemen', 'karyawan.departemen_id', '=', 'departemen.id')
            ->leftJoin('unit_organisasi', 'karyawan.unit_organisasi_id', '=', 'unit_organisasi.id')
            ->leftJoin('jabatan_borong', 'karyawan.jabatan_borong_id', '=', 'jabatan_borong.id')
            ->leftJoin('kota_perwakilan', 'karyawan.kota_perwakilan_id', '=', 'kota_perwakilan.id')
            ->leftJoin('area', 'karyawan.area_id', '=', 'area.id')
            ->leftJoin('bank', 'karyawan.bank_id', '=', 'bank.id')
            ->select(
                'karyawan.*', 
                'departemen.nama_departemen', 
                'unit_organisasi.nama_unit',
                'jabatan_borong.nama_jabatan_borong',
                'kota_perwakilan.nama_kota',
                'area.nama_area',
                'bank.nama_bank'
            )
            ->where('karyawan.id', $id)
            ->first();

        if (! $karyawan) {
            abort(404, 'Data karyawan tidak ditemukan.');
        }

        $alamat = DB::table('alamat')->where('karyawan_id', $id)->get();
        $pasangan = DB::table('pasangan')->where('karyawan_id', $id)->first();
        $anak = DB::table('anak')->where('karyawan_id', $id)->orderBy('anak_ke')->get();
        $pendidikan = DB::table('riwayat_pendidikan')->where('karyawan_id', $id)->get();
        $pengalaman = DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $id)->get();
        $riwayatSukun = DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $id)->get();

        $role = strtoupper((string) (session('role') ?? session('pengguna_role') ?? session('user_role') ?? 'HRD'));
        $backUrl = in_array($role, ['ATASAN', 'KEPALA_BAGIAN']) ? url('/atasan/dashboard') : url('/hrd/dashboard');

        return view('pendataan.detail', compact('karyawan', 'alamat', 'pasangan', 'anak', 'pendidikan', 'pengalaman', 'riwayatSukun', 'backUrl'));
    }

    public function editHrd($id)
    {
        $karyawan = DB::table('karyawan')->where('id', $id)->first();
        if (! $karyawan) {
            abort(404, 'Data karyawan tidak ditemukan.');
        }

        $alamatKtp = DB::table('alamat')->where('karyawan_id', $id)->where('jenis_alamat', 'KTP')->first();
        $alamatDom = DB::table('alamat')->where('karyawan_id', $id)->where('jenis_alamat', 'DOMISILI')->first();
        $pasangan = DB::table('pasangan')->where('karyawan_id', $id)->first();
        $anak = DB::table('anak')->where('karyawan_id', $id)->orderBy('anak_ke')->get();
        $pendidikan = DB::table('riwayat_pendidikan')->where('karyawan_id', $id)->get();
        $riwayatLuar = DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $id)->get();
        $riwayatSukun = DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $id)->get();

        return view('pendataan.edit-hrd', [
            'karyawan'          => $karyawan,
            'alamatKtp'         => $alamatKtp,
            'alamatDom'         => $alamatDom,
            'pasangan'          => $pasangan,
            'anak'              => $anak,
            'pendidikan'        => $pendidikan,
            'riwayatLuar'       => $riwayatLuar,
            'riwayatSukun'      => $riwayatSukun,
            'departemen'        => DB::table('departemen')->get(),
            'unitOrganisasi'    => DB::table('unit_organisasi')->orderBy('nama_unit')->get(),
            'golongan'          => DB::table('golongan')->get(),
            'jabatan'           => DB::table('jabatan')->get(),
            'jabatanBorong'     => DB::table('jabatan_borong')->get(),
            'jabatanDistribusi' => DB::table('jabatan_distribusi')->orderBy('kode_jabatan')->get(),
            'kotaPerwakilan'    => DB::table('kota_perwakilan')->orderBy('nama_kota')->get(),
            'area'              => DB::table('area')->get(),
            'bank'              => DB::table('bank')->get(),
        ]);
    }

    public function updateHrd(Request $request, $id)
    {
        $karyawan = DB::table('karyawan')->where('id', $id)->first();
        if (! $karyawan) {
            abort(404, 'Data karyawan tidak ditemukan.');
        }

        $noRek = preg_replace('/[^0-9]/', '', $request->input('no_rekening', ''));
        $bankId = null;
        if (strlen($noRek) === 15) {
            $bankId = DB::table('bank')->where('nama_bank', 'BRI')->value('id');
        } elseif (strlen($noRek) === 9 || strlen($noRek) === 10) {
            $bankId = DB::table('bank')->where('nama_bank', 'BNI')->value('id');
        }

        $inputNip = trim((string) $request->input('nip', ''));

        DB::transaction(function () use ($request, $karyawan, $noRek, $bankId, $inputNip) {
            $unitId = $request->input('unit_organisasi_id', $karyawan->unit_organisasi_id);
            $departemenId = DB::table('unit_organisasi')->where('id', $unitId)->value('departemen_id') ?: $karyawan->departemen_id;
            $namaJabatan = $request->filled('nama_jabatan') ? strtoupper(trim($request->input('nama_jabatan'))) : $karyawan->nama_jabatan;

            $statusPernikahan = $request->input('status_pernikahan', $karyawan->status_pernikahan);
            $jumlahAnak = ($statusPernikahan === 'BELUM_KAWIN') ? 0 : (int) $request->input('jumlah_anak', 0);

            $isLengkap = ($inputNip !== '' && !empty($noRek));

            DB::table('karyawan')->where('id', $karyawan->id)->update([
                'nip'                       => $inputNip ?: $karyawan->nip,
                'nik_ktp'                   => $request->input('nik_ktp', $karyawan->nik_ktp),
                'nama_lengkap'              => strtoupper($request->input('nama_lengkap', $karyawan->nama_lengkap)),
                'jenis_kelamin'             => $request->input('jenis_kelamin', $karyawan->jenis_kelamin),
                'tgl_lahir'                 => $request->input('tgl_lahir', $karyawan->tgl_lahir),
                'golongan_darah'            => $request->input('golongan_darah', $karyawan->golongan_darah),
                'nama_ayah'                 => strtoupper($request->input('nama_ayah', $karyawan->nama_ayah ?? '')),
                'nama_ibu'                  => strtoupper($request->input('nama_ibu', $karyawan->nama_ibu ?? '')),
                'status_pernikahan'         => $statusPernikahan,
                'tipe_karyawan'             => $request->input('tipe_karyawan', $karyawan->tipe_karyawan),
                'ket_karyawan'              => $request->input('ket_karyawan', $karyawan->ket_karyawan),
                'departemen_id'             => $departemenId,
                'unit_organisasi_id'        => $unitId,
                'nama_jabatan'              => $namaJabatan,
                'bank_id'                   => $bankId,
                'no_rekening'               => $noRek,
                'bersedia_transfer'         => $request->input('bersedia_transfer', $karyawan->bersedia_transfer),
                'nama_pemilik_rekening'     => strtoupper($request->input('nama_pemilik_rekening', $karyawan->nama_pemilik_rekening)),
                'no_bpjs_kesehatan'         => $request->input('no_bpjs_kesehatan'),
                'no_bpjs_ketenagakerjaan'   => $request->input('no_bpjs_ketenagakerjaan'),
                'no_anggota_koperasi'       => $request->input('no_anggota_koperasi'),
                'status_verifikasi'         => $isLengkap ? 'LENGKAP' : 'BELUM_LENGKAP',
                'updated_at'                => now(),
            ]);

            if ($request->filled('alamat_ktp')) {
                DB::table('alamat')->updateOrInsert(
                    ['karyawan_id' => $karyawan->id, 'jenis_alamat' => 'KTP'],
                    ['alamat_lengkap' => $request->input('alamat_ktp')]
                );
            }
            if ($request->filled('alamat_domisili')) {
                DB::table('alamat')->updateOrInsert(
                    ['karyawan_id' => $karyawan->id, 'jenis_alamat' => 'DOMISILI'],
                    ['alamat_lengkap' => $request->input('alamat_domisili')]
                );
            }

            if ($request->has('riwayat_pendidikan')) {
                DB::table('riwayat_pendidikan')->where('karyawan_id', $karyawan->id)->delete();
                $listP = $request->input('riwayat_pendidikan', []);
                foreach ($listP as $rp) {
                    if (!empty($rp['jenjang']) && !empty($rp['nama_instansi'])) {
                        DB::table('riwayat_pendidikan')->insert([
                            'karyawan_id'   => $karyawan->id,
                            'jenjang'       => strtoupper($rp['jenjang']),
                            'nama_instansi' => strtoupper($rp['nama_instansi']),
                            'jurusan'       => !empty($rp['jurusan']) ? strtoupper($rp['jurusan']) : null,
                            'nilai_ipk'     => !empty($rp['nilai_ipk']) ? $rp['nilai_ipk'] : null,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                    }
                }
            }

            if ($statusPernikahan === 'KAWIN' && $request->filled('nama_pasangan')) {
                DB::table('pasangan')->updateOrInsert(
                    ['karyawan_id' => $karyawan->id],
                    [
                        'nama_pasangan'      => strtoupper($request->input('nama_pasangan')),
                        'pekerjaan_pasangan' => $request->input('pekerjaan_pasangan'),
                    ]
                );
            } else {
                DB::table('pasangan')->where('karyawan_id', $karyawan->id)->delete();
            }

            DB::table('anak')->where('karyawan_id', $karyawan->id)->delete();
            if ($statusPernikahan !== 'BELUM_KAWIN' && $request->has('anak')) {
                $dataAnak = $request->input('anak', []);
                for ($i = 1; $i <= $jumlahAnak; $i++) {
                    if (!empty($dataAnak[$i]['nama_anak'])) {
                        DB::table('anak')->insert([
                            'karyawan_id'     => $karyawan->id,
                            'anak_ke'         => $i,
                            'nama_anak'       => strtoupper($dataAnak[$i]['nama_anak']),
                            'tgl_lahir_anak'  => $dataAnak[$i]['tgl_lahir_anak'] ?? null,
                            'pendidikan_anak' => $dataAnak[$i]['pendidikan_anak'] ?? null,
                        ]);
                    }
                }
            }

            DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $karyawan->id)->delete();
            $riwayatSukun = $request->input('riwayat_sukun', []);
            foreach ($riwayatSukun as $rw) {
                if (!empty($rw['bagian']) || !empty($rw['jabatan']) || !empty($rw['tahun_mulai'])) {
                    $thnMulai = trim((string)($rw['tahun_mulai'] ?? ''));
                    $thnSelesai = trim((string)($rw['tahun_selesai'] ?? ''));
                    
                    $valMulai = $thnMulai ? (strlen($thnMulai) === 4 ? $thnMulai . '-01-01' : $thnMulai) : null;
                    $valSelesai = $thnSelesai ? (strlen($thnSelesai) === 4 ? $thnSelesai . '-12-31' : $thnSelesai) : null;

                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawan->id,
                        'unit_bagian' => $rw['bagian'] ?? null,
                        'jabatan'     => $rw['jabatan'] ?? null,
                        'tgl_mulai'   => $valMulai,
                        'tgl_selesai' => $valSelesai,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $karyawan->id)->delete();
            $riwayatLuar = $request->input('riwayat_luar', []);
            foreach ($riwayatLuar as $rwl) {
                if (!empty($rwl['perusahaan']) || !empty($rwl['jabatan']) || !empty($rwl['tahun_masuk'])) {
                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawan->id,
                        'nama_perusahaan' => $rwl['perusahaan'] ?? null,
                        'jabatan'         => $rwl['jabatan'] ?? null,
                        'tahun_masuk'     => $rwl['tahun_masuk'] ?? null,
                        'tahun_keluar'    => $rwl['tahun_keluar'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('pendataan.detail', $karyawan->id)->with('sukses', 'Seluruh data karyawan berhasil diperbarui oleh HRD.');
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            DB::table('alamat')->where('karyawan_id', $id)->delete();
            DB::table('pasangan')->where('karyawan_id', $id)->delete();
            DB::table('anak')->where('karyawan_id', $id)->delete();
            DB::table('riwayat_pendidikan')->where('karyawan_id', $id)->delete();
            DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $id)->delete();
            DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $id)->delete();
            DB::table('karyawan')->where('id', $id)->delete();
        });

        if (\Illuminate\Support\Facades\Route::has('pendataan.dashboard')) {
            return redirect()->route('pendataan.dashboard')->with('sukses', 'Data karyawan berhasil dihapus.');
        }
        return redirect('/dashboard')->with('sukses', 'Data karyawan berhasil dihapus.');
    }

    public function editByToken($token)
    {
        $karyawan = DB::table('karyawan')->where('token', $token)->first();
        if (! $karyawan) {
            abort(404, 'Link edit tidak valid.');
        }

        $alamatKtp = DB::table('alamat')->where('karyawan_id', $karyawan->id)->where('jenis_alamat', 'KTP')->first();
        $alamatDom = DB::table('alamat')->where('karyawan_id', $karyawan->id)->where('jenis_alamat', 'DOMISILI')->first();
        $pasangan = DB::table('pasangan')->where('karyawan_id', $karyawan->id)->first();
        $anak = DB::table('anak')->where('karyawan_id', $karyawan->id)->orderBy('anak_ke')->get();
        $pendidikan = DB::table('riwayat_pendidikan')->where('karyawan_id', $karyawan->id)->get();
        $pengalaman = DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $karyawan->id)->get();
        $riwayatSukun = DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $karyawan->id)->get();

        return view('pendataan.edit-token', [
            'karyawan'       => $karyawan,
            'alamatKtp'      => $alamatKtp,
            'alamatDom'      => $alamatDom,
            'pasangan'       => $pasangan,
            'anak'           => $anak,
            'pendidikan'     => $pendidikan,
            'pengalaman'     => $pengalaman,
            'riwayatSukun'   => $riwayatSukun,
            'departemen'     => DB::table('departemen')->get(),
            'unitOrganisasi' => DB::table('unit_organisasi')->orderBy('nama_unit')->get(),
        ]);
    }

    public function updateByToken(Request $request, $token)
    {
        $karyawan = DB::table('karyawan')->where('token', $token)->first();
        if (! $karyawan) {
            abort(404, 'Data tidak ditemukan.');
        }

        $request->validate([
            'nama_ayah'           => 'required|string|max:150',
            'nama_ibu'            => 'required|string|max:150',
            'jenis_kelamin'       => 'required|in:L,P',
            'tgl_lahir'           => 'required|date',
            'no_wa'               => 'required|string|max:30',
            'alamat_domisili'     => 'required|string',
            'status_pernikahan'   => 'required|in:BELUM_KAWIN,KAWIN,CERAI_HIDUP,CERAI_MATI',
            'bersedia_transfer'   => 'required|in:YA,TIDAK',
        ]);

        $noRek = preg_replace('/[^0-9]/', '', $request->input('no_rekening', ''));
        $bankId = null;
        if (strlen($noRek) === 15) {
            $bankId = DB::table('bank')->where('nama_bank', 'BRI')->value('id');
        } elseif (strlen($noRek) === 9 || strlen($noRek) === 10) {
            $bankId = DB::table('bank')->where('nama_bank', 'BNI')->value('id');
        }

        $statusPernikahan = $request->input('status_pernikahan');
        $jumlahAnak = ($statusPernikahan === 'BELUM_KAWIN') ? 0 : (int) $request->input('jumlah_anak', 0);

        $isLengkap = !empty($noRek) && !empty($request->no_wa) && !empty($request->npwp) && !empty($request->alamat_domisili);

        DB::transaction(function () use ($request, $karyawan, $noRek, $bankId, $isLengkap, $statusPernikahan, $jumlahAnak) {
            $unitId = $request->input('unit_organisasi_id', $karyawan->unit_organisasi_id);
            $departemenId = DB::table('unit_organisasi')->where('id', $unitId)->value('departemen_id') ?: $karyawan->departemen_id;
            $namaJabatan = $request->filled('nama_jabatan') ? strtoupper(trim($request->input('nama_jabatan'))) : $karyawan->nama_jabatan;

            DB::table('karyawan')->where('id', $karyawan->id)->update([
                'nip'                       => $request->input('nip') ?: $karyawan->nip,
                'jenis_kelamin'             => $request->input('jenis_kelamin'),
                'tgl_lahir'                 => $request->input('tgl_lahir'),
                'golongan_darah'            => $request->input('golongan_darah'),
                'no_wa'                     => $request->input('no_wa'),
                'email'                     => $request->input('email'),
                'nama_ayah'                 => strtoupper($request->input('nama_ayah')),
                'nama_ibu'                  => strtoupper($request->input('nama_ibu')),
                'status_pernikahan'         => $statusPernikahan,
                'disabilitas'               => $request->input('disabilitas'),
                'tipe_karyawan'             => $request->input('tipe_karyawan', $karyawan->tipe_karyawan),
                'ket_karyawan'              => $request->input('ket_karyawan', $karyawan->ket_karyawan),
                'departemen_id'             => $departemenId,
                'unit_organisasi_id'        => $unitId,
                'nama_jabatan'              => $namaJabatan,
                'bank_id'                   => $bankId,
                'no_rekening'               => $noRek,
                'bersedia_transfer'         => $request->input('bersedia_transfer'),
                'nama_pemilik_rekening'     => strtoupper($request->input('nama_pemilik_rekening', $karyawan->nama_lengkap)),
                'no_bpjs_kesehatan'         => $request->input('no_bpjs_kesehatan'),
                'no_bpjs_ketenagakerjaan'   => $request->input('no_bpjs_ketenagakerjaan'),
                'npwp'                      => $request->input('npwp'),
                'no_anggota_koperasi'       => $request->input('no_anggota_koperasi'),
                'status_verifikasi'         => $isLengkap ? 'LENGKAP' : 'BELUM_LENGKAP',
                'updated_at'                => now(),
            ]);

            DB::table('alamat')->updateOrInsert(
                ['karyawan_id' => $karyawan->id, 'jenis_alamat' => 'DOMISILI'],
                ['alamat_lengkap' => $request->input('alamat_domisili')]
            );

            if ($request->has('riwayat_pendidikan')) {
                DB::table('riwayat_pendidikan')->where('karyawan_id', $karyawan->id)->delete();
                $listP = $request->input('riwayat_pendidikan', []);
                foreach ($listP as $rp) {
                    if (!empty($rp['jenjang']) && !empty($rp['nama_instansi'])) {
                        DB::table('riwayat_pendidikan')->insert([
                            'karyawan_id'   => $karyawan->id,
                            'jenjang'       => strtoupper($rp['jenjang']),
                            'nama_instansi' => strtoupper($rp['nama_instansi']),
                            'jurusan'       => !empty($rp['jurusan']) ? strtoupper($rp['jurusan']) : null,
                            'nilai_ipk'     => !empty($rp['nilai_ipk']) ? $rp['nilai_ipk'] : null,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                    }
                }
            }

            if ($statusPernikahan === 'KAWIN' && $request->filled('nama_pasangan')) {
                DB::table('pasangan')->updateOrInsert(
                    ['karyawan_id' => $karyawan->id],
                    [
                        'nama_pasangan'      => strtoupper($request->input('nama_pasangan')),
                        'pekerjaan_pasangan' => $request->input('pekerjaan_pasangan'),
                    ]
                );
            } else {
                DB::table('pasangan')->where('karyawan_id', $karyawan->id)->delete();
            }

            DB::table('anak')->where('karyawan_id', $karyawan->id)->delete();
            if ($statusPernikahan !== 'BELUM_KAWIN') {
                $dataAnak = $request->input('anak', []);
                for ($i = 1; $i <= $jumlahAnak; $i++) {
                    if (!empty($dataAnak[$i]['nama_anak'])) {
                        DB::table('anak')->insert([
                            'karyawan_id'     => $karyawan->id,
                            'anak_ke'         => $i,
                            'nama_anak'       => strtoupper($dataAnak[$i]['nama_anak']),
                            'tgl_lahir_anak'  => $dataAnak[$i]['tgl_lahir_anak'] ?? null,
                            'pendidikan_anak' => $dataAnak[$i]['pendidikan_anak'] ?? null,
                        ]);
                    }
                }
            }

            DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $karyawan->id)->delete();
            $riwayatSukun = $request->input('riwayat_sukun', []);
            foreach ($riwayatSukun as $rw) {
                if (!empty($rw['bagian']) || !empty($rw['jabatan']) || !empty($rw['tahun_mulai'])) {
                    $thnMulai = trim((string)($rw['tahun_mulai'] ?? ''));
                    $thnSelesai = trim((string)($rw['tahun_selesai'] ?? ''));
                    
                    $valMulai = $thnMulai ? (strlen($thnMulai) === 4 ? $thnMulai . '-01-01' : $thnMulai) : null;
                    $valSelesai = $thnSelesai ? (strlen($thnSelesai) === 4 ? $thnSelesai . '-12-31' : $thnSelesai) : null;

                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawan->id,
                        'unit_bagian' => $rw['bagian'] ?? null,
                        'jabatan'     => $rw['jabatan'] ?? null,
                        'tgl_mulai'   => $valMulai,
                        'tgl_selesai' => $valSelesai,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $karyawan->id)->delete();
            $riwayatLuar = $request->input('riwayat_luar', []);
            foreach ($riwayatLuar as $rwl) {
                if (!empty($rwl['perusahaan']) || !empty($rwl['jabatan']) || !empty($rwl['tahun_masuk'])) {
                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawan->id,
                        'nama_perusahaan' => $rwl['perusahaan'] ?? null,
                        'jabatan'         => $rwl['jabatan'] ?? null,
                        'tahun_masuk'     => $rwl['tahun_masuk'] ?? null,
                        'tahun_keluar'    => $rwl['tahun_keluar'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('pendataan.edit.token', $token)->with('sukses', 'Semua data berhasil diperbarui!');
    }

    private function exportCsv($dataExport)
    {
        $response = new StreamedResponse(function() use ($dataExport) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'No', 'Nama Lengkap', 'NIP', 'NIK', 'Jenis Kelamin', 'Pendidikan Terakhir', 'Tgl Lahir', 
                'Nama Ayah', 'Nama Ibu', 'Alamat KTP', 'Alamat Domisili', 'No HP/WA', 'Email', 'Jabatan', 
                'Unit', 'Tgl Masuk', 'Pernikahan', 'Disabilitas', 'No Rekening', 'BPJS Kes', 'BPJS Ket', 'NPWP', 'Pasangan', 'Pekerjaan Pasangan', 'Jml Anak'
            ]);
            $no = 1;
            foreach ($dataExport as $row) {
                $alamatKtp = DB::table('alamat')->where('karyawan_id', $row->id)->where('jenis_alamat', 'KTP')->value('alamat_lengkap') ?? '-';
                $alamatDom = DB::table('alamat')->where('karyawan_id', $row->id)->where('jenis_alamat', 'DOMISILI')->value('alamat_lengkap') ?? '-';
                $pasangan = DB::table('pasangan')->where('karyawan_id', $row->id)->first();
                $anakCount = DB::table('anak')->where('karyawan_id', $row->id)->count();

                fputcsv($handle, [
                    $no++, $row->nama_lengkap, $row->nip ?? '-', $row->nik_ktp, $row->jenis_kelamin,
                    $row->pendidikan_terakhir, $row->tgl_lahir,
                    $row->nama_ayah ?? '-', $row->nama_ibu ?? '-',
                    $alamatKtp, $alamatDom, $row->no_wa ?? '-', $row->email ?? '-', $row->nama_jabatan ?? '-', $row->nama_unit ?? '-',
                    $row->tgl_masuk_kerja, $row->status_pernikahan, $row->disabilitas ?? '-', $row->no_rekening ?? '-',
                    $row->no_bpjs_kesehatan ?? '-', $row->no_bpjs_ketenagakerjaan ?? '-', $row->npwp ?? '-',
                    $pasangan?->nama_pasangan ?? '-', $pasangan?->pekerjaan_pasangan ?? '-', $anakCount
                ]);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="Data_Karyawan_'.date('Ymd').'.csv"');
        return $response;
    }

    public function dashboard(Request $request)
    {
        $query = DB::table('karyawan')
            ->leftJoin('departemen', 'karyawan.departemen_id', '=', 'departemen.id')
            ->leftJoin('unit_organisasi', 'karyawan.unit_organisasi_id', '=', 'unit_organisasi.id')
            ->select('karyawan.*', 'departemen.nama_departemen', 'unit_organisasi.nama_unit')
            ->orderByDesc('karyawan.id');

        // PENCARIAN NIK / NAMA
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('karyawan.nama_lengkap', 'like', '%' . $search . '%')
                  ->orWhere('karyawan.nik_ktp', 'like', '%' . $search . '%');
            });
        }

        // FILTER DEPARTEMEN (SUDAH DIPERBAIKI SUPAYA BISA NANGKEP INPUTAN DARI VIEW)
        if ($request->filled('departemen')) {
            $query->where('departemen.nama_departemen', $request->departemen);
        }

        // Kloning query untuk hitung total data akurat sesuai filter
        $totalData = (clone $query)->count();

        if ($request->has('export') && $request->export === 'csv') {
            return $this->exportCsv($query->get());
        }

        return view('pendataan.dashboard', [
            'karyawan'       => $query->paginate(10)->withQueryString(),
            'judul'          => 'Dashboard HRD',
            'mode'           => 'HRD',
            'totalData'      => $totalData,
            'listDepartemen' => DB::table('departemen')->orderBy('nama_departemen')->pluck('nama_departemen'),
        ]);
    }

    public function dashboardAtasan(Request $request)
    {
        $penggunaId = session('pengguna_id');
        $unitIds = DB::table('atasan_unit')->where('pengguna_id', $penggunaId)->pluck('unit_organisasi_id')->toArray();

        $query = DB::table('karyawan')
            ->leftJoin('departemen', 'karyawan.departemen_id', '=', 'departemen.id')
            ->leftJoin('unit_organisasi', 'karyawan.unit_organisasi_id', '=', 'unit_organisasi.id')
            ->select('karyawan.*', 'departemen.nama_departemen', 'unit_organisasi.nama_unit')
            ->orderByDesc('karyawan.id');

        if (empty($unitIds)) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('karyawan.unit_organisasi_id', $unitIds);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('karyawan.nama_lengkap', 'like', '%' . $search . '%')
                  ->orWhere('karyawan.nik_ktp', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('departemen')) {
            $query->where('departemen.nama_departemen', $request->departemen);
        }

        $totalData = (clone $query)->count();

        if ($request->has('export') && $request->export === 'csv') {
            return $this->exportCsv($query->get());
        }

        return view('pendataan.dashboard', [
            'karyawan'       => $query->paginate(10)->withQueryString(),
            'judul'          => 'Dashboard Kepala Bagian',
            'mode'           => 'ATASAN',
            'totalData'      => $totalData,
            'listDepartemen' => DB::table('departemen')->orderBy('nama_departemen')->pluck('nama_departemen'),
        ]);
    }
}