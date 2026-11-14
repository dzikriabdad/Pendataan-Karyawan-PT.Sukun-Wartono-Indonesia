<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PendataanController extends Controller
{
    private function currentRole(): string
    {
        return strtoupper((string) (session('pengguna_role') ?? session('role') ?? session('user_role') ?? ''));
    }

    private function isHrdPusat(): bool
    {
        return $this->currentRole() === 'HRDPUSAT';
    }

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
            'tempat_lahir'  => 'required|string|max:100',
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

            'riwayat_pendidikan'                 => 'required|array|min:1',
            'riwayat_pendidikan.*.jenjang'       => 'required|string|max:50',
            'riwayat_pendidikan.*.nama_instansi' => 'required|string|max:150',
            'riwayat_pendidikan.*.jurusan'       => 'nullable|string|max:150',
            'riwayat_pendidikan.*.nilai_ipk'     => 'nullable|string|max:50',

            'nama_ayah'         => 'required|string|max:150',
            'nama_ibu'          => 'required|string|max:150',
            'status_pernikahan' => 'required|in:BELUM_KAWIN,KAWIN,CERAI_HIDUP,CERAI_MATI',

            'nama_pasangan'          => 'nullable|string|max:150',
            'pekerjaan_pasangan'     => 'nullable|string|max:150',
            'jumlah_anak'            => 'nullable|integer|between:0,10',
            'anak'                   => 'nullable|array|max:10',
            'anak.*.nama_anak'       => 'nullable|string|max:150',
            'anak.*.tgl_lahir_anak'  => 'nullable|date',
            'anak.*.pendidikan_anak' => 'nullable|string|max:50',

            'no_wa'                   => 'nullable|string|max:30',
            'email'                   => 'nullable|email|max:150',
            'golongan_darah'          => 'nullable|string|max:5',
            'disabilitas'             => 'nullable|string|max:100',
            'golongan_id'             => 'nullable|exists:golongan,id',
            'no_bpjs_kesehatan'       => 'nullable|string|max:50',
            'no_bpjs_ketenagakerjaan' => 'nullable|string|max:50',
            'npwp'                    => 'nullable|string|max:50',
            'no_anggota_koperasi'     => 'nullable|string|max:50',
            'nama_pemilik_rekening'   => 'nullable|string|max:150',
            'nip'                     => 'nullable|string|max:50',

            'riwayat_luar'                => 'nullable|array',
            'riwayat_luar.*.perusahaan'   => 'nullable|string|max:150',
            'riwayat_luar.*.jabatan'      => 'nullable|string|max:150',
            'riwayat_luar.*.tahun_masuk'  => 'nullable|string|max:10',
            'riwayat_luar.*.tahun_keluar' => 'nullable|string|max:10',

            'riwayat_sukun'                 => 'nullable|array',
            'riwayat_sukun.*.bagian'        => 'nullable|string|max:150',
            'riwayat_sukun.*.jabatan'       => 'nullable|string|max:150',
            'riwayat_sukun.*.tahun_mulai'   => 'nullable|string|max:10',
            'riwayat_sukun.*.tahun_selesai' => 'nullable|string|max:10',
        ], [
            'nik_ktp.required'                          => 'Nomor KTP (NIK) wajib diisi.',
            'nik_ktp.digits'                            => 'Nomor KTP (NIK) harus pas 16 angka.',
            'tempat_lahir.required'                     => 'Tempat lahir wajib diisi.',
            'no_rekening.regex'                         => 'Jika diisi, nomor rekening harus 9-10 angka untuk BNI atau 15 angka untuk BRI.',
            'bersedia_transfer.required'                => 'Pertanyaan kesediaan transfer gaji wajib dipilih.',
            'nama_ayah.required'                        => 'Nama lengkap ayah kandung wajib diisi.',
            'nama_ibu.required'                         => 'Nama lengkap ibu kandung wajib diisi.',
            'tgl_masuk_bulan.required'                  => 'Bulan masuk kerja wajib dipilih.',
            'tgl_masuk_tahun.required'                  => 'Tahun masuk kerja wajib dipilih.',
            'riwayat_pendidikan.required'               => 'Riwayat pendidikan wajib diisi minimal 1.',
            'riwayat_pendidikan.*.jenjang.required'     => 'Jenjang pendidikan wajib dipilih.',
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
                        } catch (\Throwable $th) {
                        }
                    }
                } elseif ($namaJabatan && str_starts_with($namaJabatan, 'AM')) {
                    $kotaId = null;
                    $areaId = null;
                    $wilayahAM = trim(str_replace('AM', '', $namaJabatan));

                    $unitId = DB::table('unit_organisasi')
                        ->where('departemen_id', $departemenId)
                        ->where(function ($q) use ($wilayahAM) {
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
                        } catch (\Throwable $th) {
                        }
                    }
                } elseif ($kotaId) {
                    $namaKota = DB::table('kota_perwakilan')->where('id', $kotaId)->value('nama_kota');
                    if ($namaKota) {
                        $unitId = DB::table('unit_organisasi')
                            ->where('departemen_id', $departemenId)
                            ->where(function ($q) use ($namaKota) {
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
                            } catch (\Throwable $th) {
                            }
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

            $listPend = $data['riwayat_pendidikan'] ?? [];
            $pendidikanPalingAkhir = end($listPend);

            $statusVerifikasi = ($inputNip !== '' && !empty($noRek)) ? 'LENGKAP' : 'BELUM_LENGKAP';

            $karyawanId = DB::table('karyawan')->insertGetId([
                'token'                     => $token,
                'nip'                       => $nip,
                'nik_ktp'                   => $data['nik_ktp'],
                'nama_lengkap'              => strtoupper($data['nama_lengkap']),
                'jenis_kelamin'             => $data['jenis_kelamin'],
                'tempat_lahir'              => strtoupper(trim($data['tempat_lahir'])),
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

            $riwayatSukun = $request->input('riwayat_sukun', []);
            foreach ($riwayatSukun as $rw) {
                if (!empty($rw['bagian'])) {
                    $thnMulai = trim((string)($rw['tahun_mulai'] ?? ''));
                    $thnSelesai = trim((string)($rw['tahun_selesai'] ?? ''));

                    $valMulai = $thnMulai ? (strlen($thnMulai) === 4 ? $thnMulai . '-01-01' : $thnMulai) : null;
                    $valSelesai = $thnSelesai ? (strlen($thnSelesai) === 4 ? $thnSelesai . '-12-31' : $thnSelesai) : null;

                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawanId,
                        'unit_bagian' => strtoupper(trim($rw['bagian'])),
                        'jabatan'     => !empty($rw['jabatan']) ? strtoupper(trim($rw['jabatan'])) : '-',
                        'tgl_mulai'   => $valMulai,
                        'tgl_selesai' => $valSelesai,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            $riwayatLuar = $request->input('riwayat_luar', []);
            foreach ($riwayatLuar as $rwl) {
                if (!empty($rwl['perusahaan'])) {
                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawanId,
                        'nama_perusahaan' => strtoupper(trim($rwl['perusahaan'])),
                        'jabatan'         => !empty($rwl['jabatan']) ? strtoupper(trim($rwl['jabatan'])) : '-',
                        'tahun_masuk'     => !empty($rwl['tahun_masuk']) ? trim($rwl['tahun_masuk']) : null,
                        'tahun_keluar'    => !empty($rwl['tahun_keluar']) ? trim($rwl['tahun_keluar']) : null,
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

        $role = $this->currentRole() ?: 'ADMIN_HR';
        $backUrl = ($role === 'ATASAN') ? url('/atasan/dashboard') : url('/hrd/dashboard');
        $canDelete = $this->isHrdPusat();

        return view('pendataan.detail', compact('karyawan', 'alamat', 'pasangan', 'anak', 'pendidikan', 'pengalaman', 'riwayatSukun', 'backUrl', 'canDelete'));
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
                'tempat_lahir'              => $request->filled('tempat_lahir') ? strtoupper(trim($request->input('tempat_lahir'))) : $karyawan->tempat_lahir,
                'tgl_lahir'                 => $request->input('tgl_lahir', $karyawan->tgl_lahir),
                'golongan_darah'            => $request->input('golongan_darah', $karyawan->golongan_darah),
                'nama_ayah'                 => strtoupper($request->input('nama_ayah', $karyawan->nama_ayah ?? '')),
                'nama_ibu'                  => strtoupper($request->input('nama_ibu', $karyawan->nama_ibu ?? '')),
                'no_wa'                     => $request->input('no_wa', $karyawan->no_wa),
                'email'                     => $request->input('email', $karyawan->email),
                'disabilitas'               => $request->input('disabilitas', $karyawan->disabilitas),
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
                'no_bpjs_kesehatan'         => $request->input('no_bpjs_kesehatan', $karyawan->no_bpjs_kesehatan),
                'no_bpjs_ketenagakerjaan'   => $request->input('no_bpjs_ketenagakerjaan', $karyawan->no_bpjs_ketenagakerjaan),
                'npwp'                      => $request->input('npwp', $karyawan->npwp),
                'no_anggota_koperasi'       => $request->input('no_anggota_koperasi', $karyawan->no_anggota_koperasi),
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
                if (!empty($rw['bagian'])) {
                    $thnMulai = trim((string)($rw['tahun_mulai'] ?? ''));
                    $thnSelesai = trim((string)($rw['tahun_selesai'] ?? ''));

                    $valMulai = $thnMulai ? (strlen($thnMulai) === 4 ? $thnMulai . '-01-01' : $thnMulai) : null;
                    $valSelesai = $thnSelesai ? (strlen($thnSelesai) === 4 ? $thnSelesai . '-12-31' : $thnSelesai) : null;

                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawan->id,
                        'unit_bagian' => strtoupper(trim($rw['bagian'])),
                        'jabatan'     => !empty($rw['jabatan']) ? strtoupper(trim($rw['jabatan'])) : '-',
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
                if (!empty($rwl['perusahaan'])) {
                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawan->id,
                        'nama_perusahaan' => strtoupper(trim($rwl['perusahaan'])),
                        'jabatan'         => !empty($rwl['jabatan']) ? strtoupper(trim($rwl['jabatan'])) : '-',
                        'tahun_masuk'     => !empty($rwl['tahun_masuk']) ? trim($rwl['tahun_masuk']) : null,
                        'tahun_keluar'    => !empty($rwl['tahun_keluar']) ? trim($rwl['tahun_keluar']) : null,
                    ]);
                }
            }
        });

        return redirect()->route('pendataan.detail', $karyawan->id)->with('sukses', 'Seluruh data karyawan berhasil diperbarui oleh HRD.');
    }

    public function destroy($id)
    {
        if (! $this->isHrdPusat()) {
            abort(403, 'Hanya HRD Pusat yang memiliki akses menghapus data karyawan.');
        }

        DB::transaction(function () use ($id) {
            DB::table('alamat')->where('karyawan_id', $id)->delete();
            DB::table('pasangan')->where('karyawan_id', $id)->delete();
            DB::table('anak')->where('karyawan_id', $id)->delete();
            DB::table('riwayat_pendidikan')->where('karyawan_id', $id)->delete();
            DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $id)->delete();
            DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $id)->delete();
            DB::table('karyawan')->where('id', $id)->delete();
        });

        return redirect('/hrd/dashboard')->with('sukses', 'Data karyawan berhasil dihapus.');
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
            'nama_ayah'         => 'required|string|max:150',
            'nama_ibu'          => 'required|string|max:150',
            'jenis_kelamin'     => 'required|in:L,P',
            'tempat_lahir'      => 'required|string|max:100',
            'tgl_lahir'         => 'required|date',
            'no_wa'             => 'required|string|max:30',
            'alamat_domisili'   => 'required|string',
            'status_pernikahan' => 'required|in:BELUM_KAWIN,KAWIN,CERAI_HIDUP,CERAI_MATI',
            'bersedia_transfer' => 'required|in:YA,TIDAK',
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
                'tempat_lahir'              => strtoupper(trim($request->input('tempat_lahir'))),
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
                if (!empty($rw['bagian'])) {
                    $thnMulai = trim((string)($rw['tahun_mulai'] ?? ''));
                    $thnSelesai = trim((string)($rw['tahun_selesai'] ?? ''));

                    $valMulai = $thnMulai ? (strlen($thnMulai) === 4 ? $thnMulai . '-01-01' : $thnMulai) : null;
                    $valSelesai = $thnSelesai ? (strlen($thnSelesai) === 4 ? $thnSelesai . '-12-31' : $thnSelesai) : null;

                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawan->id,
                        'unit_bagian' => strtoupper(trim($rw['bagian'])),
                        'jabatan'     => !empty($rw['jabatan']) ? strtoupper(trim($rw['jabatan'])) : '-',
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
                if (!empty($rwl['perusahaan'])) {
                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawan->id,
                        'nama_perusahaan' => strtoupper(trim($rwl['perusahaan'])),
                        'jabatan'         => !empty($rwl['jabatan']) ? strtoupper(trim($rwl['jabatan'])) : '-',
                        'tahun_masuk'     => !empty($rwl['tahun_masuk']) ? trim($rwl['tahun_masuk']) : null,
                        'tahun_keluar'    => !empty($rwl['tahun_keluar']) ? trim($rwl['tahun_keluar']) : null,
                    ]);
                }
            }
        });

        return redirect()->route('pendataan.edit.token', $token)->with('sukses', 'Semua data berhasil diperbarui!');
    }

    private function exportExcel($dataExport)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $headerStyle = [
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
        ];

        $cellBorder = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
        ];

        $divisiList = DB::table('departemen')->orderBy('id')->get();
        $allUnits   = DB::table('unit_organisasi')->orderBy('nama_unit')->get();

        $ids = $dataExport->pluck('id')->toArray();
        $alamatMap   = DB::table('alamat')->whereIn('karyawan_id', $ids)->get()->groupBy('karyawan_id');
        $pasanganMap = DB::table('pasangan')->whereIn('karyawan_id', $ids)->get()->keyBy('karyawan_id');
        $anakCounts  = DB::table('anak')->whereIn('karyawan_id', $ids)
            ->select('karyawan_id', DB::raw('COUNT(*) as total'))
            ->groupBy('karyawan_id')->pluck('total', 'karyawan_id');

        // ==========================================
        // 1. SHEET DASHBOARD
        // ==========================================
        $wsDash = $spreadsheet->getActiveSheet();
        $wsDash->setTitle('DASHBOARD');

        $wsDash->setCellValue('A1', 'DASHBOARD REKAP PENDATAAN KARYAWAN PT SUKUN KUDUS');
        $wsDash->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1F4E78');
        $wsDash->getRowDimension(1)->setRowHeight(18.5);

        $totalResp = $dataExport->count();
        $wsDash->setCellValue('A2', 'Update terakhir: ' . now()->format('d-m-Y') . '  |  Sumber: export Sistem Pendataan HRD (' . $totalResp . ' respons)  |  Tempel data baru di sheet DATA, semua angka & grafik otomatis ter-update');
        $wsDash->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('555555');

        $wsDash->setCellValue('A4', 'Ringkasan per Divisi');
        $wsDash->getStyle('A4')->getFont()->setBold(true)->setSize(12);
        $wsDash->setCellValue('I4', 'KPI');
        $wsDash->getStyle('I4')->getFont()->setBold(true)->setSize(12);
        $wsDash->getRowDimension(4)->setRowHeight(15.5);

        $dashHeaders = ['No', 'Divisi', 'Jml Departemen', 'Sudah Isi', '% dari Total Isi', 'Departemen Terisi', 'Departemen Belum Terisi'];
        $wsDash->fromArray($dashHeaders, null, 'A6');
        $wsDash->getStyle('A6:G6')->applyFromArray($headerStyle);
        $wsDash->getRowDimension(6)->setRowHeight(29);

        $rowDash = 7;
        $noDiv = 1;
        $divCount = $divisiList->count();
        $totDashRow = 7 + $divCount;

        foreach ($divisiList as $div) {
            $wsDash->setCellValue("A{$rowDash}", $noDiv++);
            $wsDash->setCellValue("B{$rowDash}", $div->nama_departemen);
            $wsDash->setCellValue("C{$rowDash}", "=COUNTIF(MASTER!\$B:\$B,\$B{$rowDash})");
            $wsDash->setCellValue("D{$rowDash}", "=COUNTIF(DATA!\$Z:\$Z,\$B{$rowDash})");
            $wsDash->setCellValue("E{$rowDash}", "=IF(\$D\${$totDashRow}=0,0,\$D{$rowDash}/\$D\${$totDashRow})");
            $wsDash->setCellValue("F{$rowDash}", "=COUNTIFS(MASTER!\$B:\$B,\$B{$rowDash},MASTER!\$E:\$E,\">0\")");
            $wsDash->setCellValue("G{$rowDash}", "=COUNTIFS(MASTER!\$B:\$B,\$B{$rowDash},MASTER!\$E:\$E,0)");
            $wsDash->getStyle("E{$rowDash}")->getNumberFormat()->setFormatCode('0.0%');
            $wsDash->getStyle("A{$rowDash}:G{$rowDash}")->applyFromArray($cellBorder);
            $rowDash++;
        }

        $lastDivRow = max(7, $rowDash - 1);
        $wsDash->setCellValue("B{$totDashRow}", 'TOTAL');
        $wsDash->setCellValue("C{$totDashRow}", "=SUM(C7:C{$lastDivRow})");
        $wsDash->setCellValue("D{$totDashRow}", "=SUM(D7:D{$lastDivRow})");
        $wsDash->setCellValue("E{$totDashRow}", "=SUM(E7:E{$lastDivRow})");
        $wsDash->setCellValue("F{$totDashRow}", "=SUM(F7:F{$lastDivRow})");
        $wsDash->setCellValue("G{$totDashRow}", "=SUM(G7:G{$lastDivRow})");
        $wsDash->getStyle("E{$totDashRow}")->getNumberFormat()->setFormatCode('0.0%');
        $wsDash->getStyle("A{$totDashRow}:G{$totDashRow}")->applyFromArray($cellBorder);
        $wsDash->getStyle("B{$totDashRow}:D{$totDashRow}")->getFont()->setBold(true);
        $wsDash->getStyle("F{$totDashRow}:G{$totDashRow}")->getFont()->setBold(true);

        foreach (['A' => 5, 'B' => 20, 'C' => 16, 'D' => 12, 'E' => 16, 'F' => 18, 'G' => 22, 'H' => 3, 'I' => 28, 'J' => 14] as $col => $w) {
            $wsDash->getColumnDimension($col)->setWidth($w);
        }

        // ==========================================
        // 2. SHEET MASTER
        // ==========================================
        $wsMaster = $spreadsheet->createSheet();
        $wsMaster->setTitle('MASTER');
        $masterHeaders = ['No', 'Divisi', 'Departemen / Unit Kerja', 'Target Karyawan (isi manual)', 'Sudah Isi', 'Belum Isi', '% Isi', 'Catatan'];
        $wsMaster->fromArray($masterHeaders, null, 'A1');
        $wsMaster->getStyle('A1:H1')->applyFromArray($headerStyle);
        $wsMaster->getRowDimension(1)->setRowHeight(29);

        $rowMaster = 2;
        $noMaster = 1;
        foreach ($divisiList as $div) {
            $unitsInDiv = $allUnits->where('departemen_id', $div->id);
            foreach ($unitsInDiv as $u) {
                $wsMaster->setCellValue("A{$rowMaster}", $noMaster++);
                $wsMaster->setCellValue("B{$rowMaster}", $div->nama_departemen);
                $wsMaster->setCellValue("C{$rowMaster}", $u->nama_unit);
                $wsMaster->setCellValue("D{$rowMaster}", null);
                $wsMaster->setCellValue("E{$rowMaster}", "=COUNTIF(DATA!\$O:\$O,\$C{$rowMaster})");
                $wsMaster->setCellValue("F{$rowMaster}", "=IF(\$D{$rowMaster}=\"\",\"\",\$D{$rowMaster}-\$E{$rowMaster})");
                $wsMaster->setCellValue("G{$rowMaster}", "=IF(OR(\$D{$rowMaster}=\"\",\$D{$rowMaster}=0),\"\",\$E{$rowMaster}/\$D{$rowMaster})");
                $wsMaster->getStyle("G{$rowMaster}")->getNumberFormat()->setFormatCode('0.0%');
                $wsMaster->getStyle("A{$rowMaster}:H{$rowMaster}")->applyFromArray($cellBorder);
                $rowMaster++;
            }
        }
        $lastMasterRow = max(2, $rowMaster - 1);

        foreach (['A' => 6, 'B' => 18, 'C' => 40, 'D' => 18, 'E' => 10, 'F' => 12, 'G' => 8, 'H' => 40] as $col => $w) {
            $wsMaster->getColumnDimension($col)->setWidth($w);
        }

        // KPI Box di DASHBOARD (I5:J9)
        $kpiItems = [
            ['Total respons masuk', "=COUNTA(DATA!\$B\$2:\$B\$5000)"],
            ['Total departemen (master)', "=COUNTA(MASTER!\$C\$2:\$C\${$lastMasterRow})"],
            ['Departemen sudah ada isian', "=COUNTIF(MASTER!\$E\$2:\$E\${$lastMasterRow},\">0\")"],
            ['Departemen belum ada isian', "=COUNTIF(MASTER!\$E\$2:\$E\${$lastMasterRow},0)"],
            ['% departemen terisi', "=IF(J6=0,0,J7/J6)"],
        ];
        $rKpi = 5;
        foreach ($kpiItems as $kpi) {
            $wsDash->setCellValue("I{$rKpi}", $kpi[0]);
            $wsDash->setCellValue("J{$rKpi}", $kpi[1]);
            $wsDash->getStyle("I{$rKpi}:J{$rKpi}")->applyFromArray($cellBorder);
            $wsDash->getStyle("J{$rKpi}")->getFont()->setBold(true);
            if ($rKpi === 9) {
                $wsDash->getStyle("J{$rKpi}")->getNumberFormat()->setFormatCode('0.0%');
            }
            $rKpi++;
        }

        // ==========================================
        // DIAGRAM / CHART DI DASHBOARD (BarChart & PieChart)
        // ==========================================
        if ($divCount > 0) {
            $chartStartRow = $totDashRow + 3;
            $chartEndRow   = $chartStartRow + 15;

            // 1. BarChart: Jumlah Karyawan Sudah Isi per Divisi (A26:H41)
            $barLabels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'DASHBOARD!$D$6', null, 1),
            ];
            $barCategories = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'DASHBOARD!$B$7:$B$' . $lastDivRow, null, $divCount),
            ];
            $barValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'DASHBOARD!$D$7:$D$' . $lastDivRow, null, $divCount),
            ];

            $barSeries = new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_CLUSTERED,
                range(0, count($barValues) - 1),
                $barLabels,
                $barCategories,
                $barValues
            );
            $barSeries->setPlotDirection(DataSeries::DIRECTION_COL);

            $barPlotArea = new PlotArea(null, [$barSeries]);
            $barChart = new Chart(
                'chart_bar_divisi',
                new Title('Jumlah Karyawan Sudah Isi per Divisi'),
                null,
                $barPlotArea,
                true,
                DataSeries::EMPTY_AS_GAP,
                new Title('Divisi'),
                new Title('Jumlah')
            );
            $barChart->setTopLeftPosition('A' . $chartStartRow);
            $barChart->setBottomRightPosition('H' . $chartEndRow);
            $wsDash->addChart($barChart);

            // 2. PieChart: Porsi Isian per Divisi (I26:N41)
            $pieCategories = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'DASHBOARD!$B$7:$B$' . $lastDivRow, null, $divCount),
            ];
            $pieValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'DASHBOARD!$D$7:$D$' . $lastDivRow, null, $divCount),
            ];

            $pieSeries = new DataSeries(
                DataSeries::TYPE_PIECHART,
                null,
                range(0, count($pieValues) - 1),
                [],
                $pieCategories,
                $pieValues
            );

            $piePlotArea = new PlotArea(null, [$pieSeries]);
            $pieLegend = new Legend(Legend::POSITION_RIGHT, null, false);
            $pieChart = new Chart(
                'chart_pie_divisi',
                new Title('Porsi Isian per Divisi'),
                $pieLegend,
                $piePlotArea,
                true,
                DataSeries::EMPTY_AS_GAP
            );
            $pieChart->setTopLeftPosition('I' . $chartStartRow);
            $pieChart->setBottomRightPosition('N' . $chartEndRow);
            $wsDash->addChart($pieChart);
        }

        // ==========================================
        // 3. SHEET DATA (29 Kolom A..AC)
        // ==========================================
        $wsData = $spreadsheet->createSheet();
        $wsData->setTitle('DATA');
        $dataHeaders = [
            'No', 'Nama Lengkap', 'NIP', 'NIK', 'Jenis Kelamin', 'Pendidikan Terakhir',
            'Tgl Lahir', 'Nama Ayah', 'Nama Ibu', 'Alamat KTP', 'Alamat Domisili',
            'No HP/WA', 'Email', 'Jabatan', 'Unit', 'Tgl Masuk', 'Pernikahan', 'Disabilitas',
            'No Rekening', 'BPJS Kes', 'BPJS Ket', 'NPWP', 'Pasangan', 'Pekerjaan Pasangan',
            'Jml Anak', 'Divisi', 'Departemen', 'Catatan Validasi', 'Departemen (auto)'
        ];
        $wsData->fromArray($dataHeaders, null, 'A1');
        $wsData->getStyle('A1:AC1')->applyFromArray($headerStyle);
        $wsData->getRowDimension(1)->setRowHeight(24);

        $sortedExport = $dataExport->sortBy('id')->values();
        $rowData = 2;
        $noData = 1;
        foreach ($sortedExport as $emp) {
            $alList = $alamatMap->get($emp->id) ?? collect();
            $alKtp  = optional($alList->firstWhere('jenis_alamat', 'KTP'))->alamat_lengkap ?? '-';
            $alDom  = optional($alList->firstWhere('jenis_alamat', 'DOMISILI'))->alamat_lengkap ?? '-';
            $pas    = $pasanganMap->get($emp->id);
            $jmlAn  = $anakCounts[$emp->id] ?? 0;

            $wsData->setCellValue("A{$rowData}", (string)$noData++);
            $wsData->setCellValue("B{$rowData}", $emp->nama_lengkap);
            $wsData->setCellValueExplicit("C{$rowData}", (string)($emp->nip ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValueExplicit("D{$rowData}", (string)$emp->nik_ktp, DataType::TYPE_STRING);
            $wsData->setCellValue("E{$rowData}", $emp->jenis_kelamin);
            $wsData->setCellValue("F{$rowData}", $emp->pendidikan_terakhir ?? '-');
            $wsData->setCellValue("G{$rowData}", $emp->tgl_lahir);
            $wsData->setCellValue("H{$rowData}", $emp->nama_ayah ?? '-');
            $wsData->setCellValue("I{$rowData}", $emp->nama_ibu ?? '-');
            $wsData->setCellValue("J{$rowData}", $alKtp);
            $wsData->setCellValue("K{$rowData}", $alDom);
            $wsData->setCellValueExplicit("L{$rowData}", (string)($emp->no_wa ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValue("M{$rowData}", $emp->email ?? '-');
            $wsData->setCellValue("N{$rowData}", $emp->nama_jabatan ?? '-');
            $wsData->setCellValue("O{$rowData}", $emp->nama_unit ?? '-');
            $wsData->setCellValue("P{$rowData}", $emp->tgl_masuk_kerja);
            $wsData->setCellValue("Q{$rowData}", $emp->status_pernikahan);
            $wsData->setCellValue("R{$rowData}", $emp->disabilitas ?? '-');
            $wsData->setCellValueExplicit("S{$rowData}", (string)($emp->no_rekening ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValueExplicit("T{$rowData}", (string)($emp->no_bpjs_kesehatan ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValueExplicit("U{$rowData}", (string)($emp->no_bpjs_ketenagakerjaan ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValueExplicit("V{$rowData}", (string)($emp->npwp ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValue("W{$rowData}", $pas->nama_pasangan ?? '-');
            $wsData->setCellValue("X{$rowData}", $pas->pekerjaan_pasangan ?? '-');
            $wsData->setCellValue("Y{$rowData}", (string)$jmlAn);
            $wsData->setCellValue("Z{$rowData}", "=IF(\$O{$rowData}=\"\",\"\",IFERROR(INDEX(MASTER!\$B:\$B,MATCH(\$O{$rowData},MASTER!\$C:\$C,0)),\"PERLU DICEK\"))");
            $wsData->setCellValue("AA{$rowData}", "=IF(\$O{$rowData}=\"\",\"\",IFERROR(INDEX(MASTER!\$C:\$C,MATCH(\$O{$rowData},MASTER!\$C:\$C,0)),\$O{$rowData}))");

            $rowData++;
        }

        $maxTemplateRow = max($rowData + 200, 5000);
        for ($rEmpty = $rowData; $rEmpty <= $maxTemplateRow; $rEmpty++) {
            $wsData->setCellValue("Z{$rEmpty}", "=IF(\$O{$rEmpty}=\"\",\"\",IFERROR(INDEX(MASTER!\$B:\$B,MATCH(\$O{$rEmpty},MASTER!\$C:\$C,0)),\"PERLU DICEK\"))");
            $wsData->setCellValue("AA{$rEmpty}", "=IF(\$O{$rEmpty}=\"\",\"\",IFERROR(INDEX(MASTER!\$C:\$C,MATCH(\$O{$rEmpty},MASTER!\$C:\$C,0)),\$O{$rEmpty}))");
        }

        $dataWidths = [
            'A' => 5, 'B' => 28, 'C' => 18, 'D' => 20, 'E' => 6, 'F' => 12,
            'G' => 14, 'H' => 18, 'I' => 18, 'J' => 35, 'K' => 35, 'L' => 16,
            'M' => 28, 'N' => 30, 'O' => 28, 'P' => 12, 'Q' => 14, 'R' => 20,
            'S' => 18, 'T' => 16, 'U' => 16, 'V' => 20, 'W' => 24, 'X' => 20,
            'Y' => 8, 'Z' => 16, 'AA' => 28, 'AB' => 22, 'AC' => 22
        ];
        foreach ($dataWidths as $col => $w) {
            $wsData->getColumnDimension($col)->setWidth($w);
        }

        // ==========================================
        // 4. SHEET CATATAN
        // ==========================================
        $wsCat = $spreadsheet->createSheet();
        $wsCat->setTitle('CATATAN');
        $wsCat->setCellValue('A1', 'PETUNJUK PENGGUNAAN');
        $wsCat->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1F4E78');
        $wsCat->getRowDimension(1)->setRowHeight(15.5);

        $notes = [
            ['1', "Buka sheet DATA, tempel (paste) data baru di bawah data lama (mulai kolom A). Kolom 'Divisi' & 'Departemen' sudah ada rumus sampai baris 5000 — pemetaan unit otomatis."],
            ['2', 'Semua angka di DASHBOARD, MASTER, dan sheet per divisi memakai rumus (COUNTIF/INDEX-MATCH) sehingga otomatis ter-update saat DATA bertambah. Grafik di DASHBOARD ikut berubah.'],
            ['3', "Isi kolom 'Target Karyawan' di sheet MASTER (khususnya PRODUKSI) agar kolom Belum Isi & % Isi terisi — ini kunci laporan 'siapa yang belum isi' per departemen."],
            ['4', 'Kirim sheet divisi terkait ke kepala bagian: tabel atas = rekap per departemen, tabel bawah = daftar nama yang sudah isi untuk dicek silang dengan daftar hadir karyawan.'],
            ['5', 'File ini di-export langsung dari sistem pendataan karyawan PT. Sukun Wartono Indonesia.'],
        ];
        $rNote = 2;
        foreach ($notes as $n) {
            $wsCat->setCellValue("A{$rNote}", $n[0]);
            $wsCat->setCellValue("B{$rNote}", $n[1]);
            $wsCat->getStyle("B{$rNote}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $wsCat->getRowDimension($rNote)->setRowHeight(29);
            $rNote++;
        }
        $wsCat->getColumnDimension('A')->setWidth(28);
        $wsCat->getColumnDimension('B')->setWidth(120);

        // ==========================================
        // 5. SHEET PER DIVISI
        // ==========================================
        $usedSheetNames = ['DASHBOARD', 'MASTER', 'DATA', 'CATATAN'];

        foreach ($divisiList as $div) {
            $baseName = mb_substr(trim(preg_replace('/[\\\\\/\?\*\[\]\:]/', ' ', $div->nama_departemen)), 0, 28);
            if ($baseName === '') {
                $baseName = 'DIVISI_' . $div->id;
            }
            $sheetName = $baseName;
            $suffix = 2;
            while (in_array(strtoupper($sheetName), $usedSheetNames, true)) {
                $sheetName = mb_substr($baseName, 0, 27) . '_' . $suffix++;
            }
            $usedSheetNames[] = strtoupper($sheetName);

            $wsDiv = $spreadsheet->createSheet();
            $wsDiv->setTitle($sheetName);

            $wsDiv->setCellValue('A1', 'REKAP DIVISI ' . strtoupper($div->nama_departemen) . ' — Pendataan Karyawan PT Sukun');
            $wsDiv->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1F4E78');

            $wsDiv->setCellValue('A2', 'Update: ' . now()->format('d-m-Y') . ' | Isi kolom Target di sheet MASTER untuk melihat jumlah & % yang belum isi');
            $wsDiv->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('555555');

            $divTopHeaders = ['No', 'Departemen / Unit Kerja', 'Target', 'Sudah Isi', 'Belum Isi', '% Isi', 'Status'];
            $wsDiv->fromArray($divTopHeaders, null, 'A4');
            $wsDiv->getStyle('A4:G4')->applyFromArray($headerStyle);
            $wsDiv->getRowDimension(4)->setRowHeight(24);

            $unitsInDiv = $allUnits->where('departemen_id', $div->id)->values();
            $rUnit = 5;
            $noU = 1;
            foreach ($unitsInDiv as $u) {
                $wsDiv->setCellValue("A{$rUnit}", $noU++);
                $wsDiv->setCellValue("B{$rUnit}", $u->nama_unit);
                $wsDiv->setCellValue("C{$rUnit}", "=IFERROR(INDEX(MASTER!\$D:\$D,MATCH(\$B{$rUnit},MASTER!\$C:\$C,0)),\"\")");
                $wsDiv->setCellValue("D{$rUnit}", "=COUNTIF(DATA!\$O:\$O,\$B{$rUnit})");
                $wsDiv->setCellValue("E{$rUnit}", "=IF(OR(\$C{$rUnit}=\"\",\$C{$rUnit}=0),\"\",\$C{$rUnit}-\$D{$rUnit})");
                $wsDiv->setCellValue("F{$rUnit}", "=IF(OR(\$C{$rUnit}=\"\",\$C{$rUnit}=0),\"\",\$D{$rUnit}/\$C{$rUnit})");
                $wsDiv->setCellValue("G{$rUnit}", "=IF(\$D{$rUnit}=0,\"BELUM ADA YANG ISI\",IF(\$E{$rUnit}=\"\",\"SUDAH ADA ISIAN\",IF(\$E{$rUnit}>0,\"ADA YANG BELUM ISI\",\"LENGKAP\")))");
                $wsDiv->getStyle("F{$rUnit}")->getNumberFormat()->setFormatCode('0.0%');
                $wsDiv->getStyle("A{$rUnit}:G{$rUnit}")->applyFromArray($cellBorder);
                $rUnit++;
            }

            $endUnitRow = max(5, $rUnit - 1);
            $totUnitRow = $rUnit;
            $wsDiv->setCellValue("B{$totUnitRow}", 'TOTAL');
            if ($unitsInDiv->count() > 0) {
                $wsDiv->setCellValue("D{$totUnitRow}", "=SUM(D5:D{$endUnitRow})");
                $wsDiv->setCellValue("E{$totUnitRow}", "=IF(SUM(\$C\$5:\$C\${$endUnitRow})=0,\"\",SUM(\$C\$5:\$C\${$endUnitRow})-SUM(\$D\$5:\$D\${$endUnitRow}))");
                $wsDiv->setCellValue("F{$totUnitRow}", "=IF(SUM(\$C\$5:\$C\${$endUnitRow})=0,\"\",SUM(\$D\$5:\$D\${$endUnitRow})/SUM(\$C\$5:\$C\${$endUnitRow}))");
            } else {
                $wsDiv->setCellValue("D{$totUnitRow}", 0);
            }
            $wsDiv->getStyle("F{$totUnitRow}")->getNumberFormat()->setFormatCode('0.0%');
            $wsDiv->getStyle("A{$totUnitRow}:G{$totUnitRow}")->applyFromArray($cellBorder);
            $wsDiv->getStyle("B{$totUnitRow}")->getFont()->setBold(true);
            $wsDiv->getStyle("D{$totUnitRow}:F{$totUnitRow}")->getFont()->setBold(true);

            $empsInDiv = $sortedExport->where('departemen_id', $div->id)->sortBy(['nama_unit', 'nama_lengkap'])->values();
            $rListTitle = $totUnitRow + 2;
            $wsDiv->setCellValue("A{$rListTitle}", 'Daftar yang sudah isi (' . $empsInDiv->count() . ' karyawan)');
            $wsDiv->getStyle("A{$rListTitle}")->getFont()->setBold(true)->setSize(12);

            $rListHead = $rListTitle + 1;
            $divListHeaders = ['No', 'Nama Lengkap', 'NIP', 'NIK', 'Jabatan', 'Unit (form)', 'Tgl Masuk', 'Pernikahan', 'No HP/WA'];
            $wsDiv->fromArray($divListHeaders, null, "A{$rListHead}");
            $wsDiv->getStyle("A{$rListHead}:I{$rListHead}")->applyFromArray($headerStyle);
            $wsDiv->getRowDimension($rListHead)->setRowHeight(24);

            $rEmp = $rListHead + 1;
            $noEmp = 1;
            foreach ($empsInDiv as $e) {
                $wsDiv->setCellValue("A{$rEmp}", $noEmp++);
                $wsDiv->setCellValue("B{$rEmp}", $e->nama_lengkap);
                $wsDiv->setCellValueExplicit("C{$rEmp}", (string)($e->nip ?? '-'), DataType::TYPE_STRING);
                $wsDiv->setCellValueExplicit("D{$rEmp}", (string)$e->nik_ktp, DataType::TYPE_STRING);
                $wsDiv->setCellValue("E{$rEmp}", $e->nama_jabatan ?? '-');
                $wsDiv->setCellValue("F{$rEmp}", $e->nama_unit ?? '-');
                $wsDiv->setCellValue("G{$rEmp}", $e->tgl_masuk_kerja);
                $wsDiv->setCellValue("H{$rEmp}", $e->status_pernikahan);
                $wsDiv->setCellValueExplicit("I{$rEmp}", (string)($e->no_wa ?? '-'), DataType::TYPE_STRING);
                $wsDiv->getStyle("A{$rEmp}:I{$rEmp}")->applyFromArray($cellBorder);
                $rEmp++;
            }

            foreach (['A' => 5, 'B' => 32, 'C' => 18, 'D' => 20, 'E' => 32, 'F' => 28, 'G' => 12, 'H' => 13, 'I' => 16] as $col => $w) {
                $wsDiv->getColumnDimension($col)->setWidth($w);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'REKAP_PENDATAAN_PT_SWI_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $writer->setPreCalculateFormulas(false);

        $tempFile = tempnam(sys_get_temp_dir(), 'swi_excel_');
        $writer->save($tempFile);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function exportCsv($dataExport)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $ids = $dataExport->pluck('id')->toArray();

        $alamatMap     = DB::table('alamat')->whereIn('karyawan_id', $ids)->get()->groupBy('karyawan_id');
        $pasanganMap   = DB::table('pasangan')->whereIn('karyawan_id', $ids)->get()->keyBy('karyawan_id');
        $anakMap       = DB::table('anak')->whereIn('karyawan_id', $ids)->orderBy('anak_ke')->get()->groupBy('karyawan_id');
        $pendidikanMap = DB::table('riwayat_pendidikan')->whereIn('karyawan_id', $ids)->get()->groupBy('karyawan_id');
        $sukunMap      = DB::table('riwayat_pekerjaan_sukun')->whereIn('karyawan_id', $ids)->get()->groupBy('karyawan_id');
        $pengalamanMap = DB::table('pengalaman_kerja_sebelum')->whereIn('karyawan_id', $ids)->get()->groupBy('karyawan_id');

        $sortedExport = $dataExport->sortBy('id')->values();

        $response = new StreamedResponse(function () use ($sortedExport, $alamatMap, $pasanganMap, $anakMap, $pendidikanMap, $sukunMap, $pengalamanMap) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No', 'Nama Lengkap', 'NIP', 'NIK', 'Jenis Kelamin', 'Tempat Lahir', 'Tgl Lahir', 'Golongan Darah',
                'Pendidikan Terakhir', 'Jurusan', 'Nama Instansi', 'Nilai/IPK', 'Riwayat Pendidikan Lengkap',
                'Nama Ayah', 'Nama Ibu', 'Alamat KTP', 'Alamat Domisili', 'No HP/WA', 'Email',
                'Divisi / Departemen', 'Unit Kerja', 'Tipe Karyawan', 'Status Karyawan', 'Jabatan',
                'Jabatan Borong', 'Kota Perwakilan', 'Area', 'Tgl Masuk',
                'Status Pernikahan', 'Pasangan', 'Pekerjaan Pasangan', 'Jml Anak', 'Detail Anak',
                'Disabilitas', 'Bank', 'No Rekening', 'Nama Pemilik Rekening', 'Bersedia Transfer',
                'BPJS Kes', 'BPJS Ket', 'NPWP', 'No Anggota Koperasi',
                'Riwayat Pekerjaan Sukun', 'Pengalaman Kerja Sebelumnya',
                'Status Verifikasi', 'Waktu Input'
            ]);

            $no = 1;
            foreach ($sortedExport as $row) {
                $alList    = $alamatMap->get($row->id) ?? collect();
                $alamatKtp = optional($alList->firstWhere('jenis_alamat', 'KTP'))->alamat_lengkap ?? '-';
                $alamatDom = optional($alList->firstWhere('jenis_alamat', 'DOMISILI'))->alamat_lengkap ?? '-';
                $pasangan  = $pasanganMap->get($row->id);

                $anakList   = $anakMap->get($row->id) ?? collect();
                $anakCount  = $anakList->count();
                $detailAnak = $anakList->map(function ($a) {
                    return "{$a->anak_ke}. {$a->nama_anak}" . ($a->tgl_lahir_anak ? " ({$a->tgl_lahir_anak})" : '') . ($a->pendidikan_anak ? " - {$a->pendidikan_anak}" : '');
                })->implode(' | ') ?: '-';

                $pendList   = $pendidikanMap->get($row->id) ?? collect();
                $detailPend = $pendList->map(function ($p) {
                    return "{$p->jenjang} - {$p->nama_instansi}" . ($p->jurusan ? " ({$p->jurusan})" : '');
                })->implode(' | ') ?: '-';

                $sukunList   = $sukunMap->get($row->id) ?? collect();
                $detailSukun = $sukunList->map(function ($s) {
                    $mulai   = $s->tgl_mulai ? substr($s->tgl_mulai, 0, 4) : '?';
                    $selesai = $s->tgl_selesai ? substr($s->tgl_selesai, 0, 4) : 'Sekarang';
                    return "{$s->unit_bagian} ({$s->jabatan}: {$mulai}-{$selesai})";
                })->implode(' | ') ?: '-';

                $pengList   = $pengalamanMap->get($row->id) ?? collect();
                $detailPeng = $pengList->map(function ($pg) {
                    return "{$pg->nama_perusahaan} ({$pg->jabatan}: {$pg->tahun_masuk}-{$pg->tahun_keluar})";
                })->implode(' | ') ?: '-';

                fputcsv($handle, [
                    $no++,
                    $row->nama_lengkap,
                    $row->nip ?? '-',
                    $row->nik_ktp,
                    $row->jenis_kelamin,
                    $row->tempat_lahir ?? '-',
                    $row->tgl_lahir,
                    $row->golongan_darah ?? '-',
                    $row->pendidikan_terakhir ?? '-',
                    $row->jurusan ?? '-',
                    $row->nama_instansi ?? '-',
                    $row->nilai ?? '-',
                    $detailPend,
                    $row->nama_ayah ?? '-',
                    $row->nama_ibu ?? '-',
                    $alamatKtp,
                    $alamatDom,
                    $row->no_wa ?? '-',
                    $row->email ?? '-',
                    $row->nama_departemen ?? '-',
                    $row->nama_unit ?? '-',
                    $row->tipe_karyawan ?? '-',
                    $row->ket_karyawan ?? '-',
                    $row->nama_jabatan ?? '-',
                    $row->nama_jabatan_borong ?? '-',
                    $row->nama_kota ?? '-',
                    $row->nama_area ?? '-',
                    $row->tgl_masuk_kerja,
                    $row->status_pernikahan,
                    $pasangan?->nama_pasangan ?? '-',
                    $pasangan?->pekerjaan_pasangan ?? '-',
                    $anakCount,
                    $detailAnak,
                    $row->disabilitas ?? '-',
                    $row->nama_bank ?? '-',
                    $row->no_rekening ?? '-',
                    $row->nama_pemilik_rekening ?? '-',
                    $row->bersedia_transfer ?? '-',
                    $row->no_bpjs_kesehatan ?? '-',
                    $row->no_bpjs_ketenagakerjaan ?? '-',
                    $row->npwp ?? '-',
                    $row->no_anggota_koperasi ?? '-',
                    $detailSukun,
                    $detailPeng,
                    $row->status_verifikasi ?? '-',
                    $row->created_at ?? '-',
                ]);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="Data_Karyawan_Lengkap_' . date('Ymd_His') . '.csv"');
        return $response;
    }

    public function dashboard(Request $request)
    {
        $baseQuery = DB::table('karyawan')
            ->leftJoin('departemen', 'karyawan.departemen_id', '=', 'departemen.id')
            ->leftJoin('unit_organisasi', 'karyawan.unit_organisasi_id', '=', 'unit_organisasi.id')
            ->leftJoin('bank', 'karyawan.bank_id', '=', 'bank.id')
            ->leftJoin('jabatan_borong', 'karyawan.jabatan_borong_id', '=', 'jabatan_borong.id')
            ->leftJoin('kota_perwakilan', 'karyawan.kota_perwakilan_id', '=', 'kota_perwakilan.id')
            ->leftJoin('area', 'karyawan.area_id', '=', 'area.id')
            ->select(
                'karyawan.*',
                'departemen.nama_departemen',
                'unit_organisasi.nama_unit',
                'bank.nama_bank',
                'jabatan_borong.nama_jabatan_borong',
                'kota_perwakilan.nama_kota',
                'area.nama_area'
            )
            ->orderByDesc('karyawan.id');

        if ($request->has('export') && $request->export === 'excel') {
            return $this->exportExcel((clone $baseQuery)->get());
        }

        $query = clone $baseQuery;

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('karyawan.nama_lengkap', 'like', '%' . $search . '%')
                    ->orWhere('karyawan.nik_ktp', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('departemen')) {
            $query->where('departemen.nama_departemen', $request->departemen);
        }

        if ($request->has('export') && $request->export === 'csv') {
            return $this->exportCsv($query->get());
        }

        $totalData = (clone $query)->count();

        return view('pendataan.dashboard', [
            'karyawan'       => $query->paginate(10)->withQueryString(),
            'judul'          => 'Dashboard HRD',
            'mode'           => 'HRD',
            'canDelete'      => $this->isHrdPusat(),
            'totalData'      => $totalData,
            'listDepartemen' => DB::table('departemen')->orderBy('nama_departemen')->pluck('nama_departemen'),
        ]);
    }

    public function dashboardAtasan(Request $request)
    {
        $penggunaId = session('pengguna_id');
        $unitIds = DB::table('atasan_unit')->where('pengguna_id', $penggunaId)->pluck('unit_organisasi_id')->toArray();

        $baseQuery = DB::table('karyawan')
            ->leftJoin('departemen', 'karyawan.departemen_id', '=', 'departemen.id')
            ->leftJoin('unit_organisasi', 'karyawan.unit_organisasi_id', '=', 'unit_organisasi.id')
            ->leftJoin('bank', 'karyawan.bank_id', '=', 'bank.id')
            ->leftJoin('jabatan_borong', 'karyawan.jabatan_borong_id', '=', 'jabatan_borong.id')
            ->leftJoin('kota_perwakilan', 'karyawan.kota_perwakilan_id', '=', 'kota_perwakilan.id')
            ->leftJoin('area', 'karyawan.area_id', '=', 'area.id')
            ->select(
                'karyawan.*',
                'departemen.nama_departemen',
                'unit_organisasi.nama_unit',
                'bank.nama_bank',
                'jabatan_borong.nama_jabatan_borong',
                'kota_perwakilan.nama_kota',
                'area.nama_area'
            )
            ->orderByDesc('karyawan.id');

        if (empty($unitIds)) {
            $baseQuery->whereRaw('1 = 0');
        } else {
            $baseQuery->whereIn('karyawan.unit_organisasi_id', $unitIds);
        }

        if ($request->has('export') && $request->export === 'excel') {
            return $this->exportExcel((clone $baseQuery)->get());
        }

        $query = clone $baseQuery;

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('karyawan.nama_lengkap', 'like', '%' . $search . '%')
                    ->orWhere('karyawan.nik_ktp', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('departemen')) {
            $query->where('departemen.nama_departemen', $request->departemen);
        }

        if ($request->has('export') && $request->export === 'csv') {
            return $this->exportCsv($query->get());
        }

        $totalData = (clone $query)->count();

        return view('pendataan.dashboard', [
            'karyawan'       => $query->paginate(10)->withQueryString(),
            'judul'          => 'Dashboard Kepala Bagian',
            'mode'           => 'ATASAN',
            'canDelete'      => $this->isHrdPusat(),
            'totalData'      => $totalData,
            'listDepartemen' => DB::table('departemen')->orderBy('nama_departemen')->pluck('nama_departemen'),
        ]);
    }
}