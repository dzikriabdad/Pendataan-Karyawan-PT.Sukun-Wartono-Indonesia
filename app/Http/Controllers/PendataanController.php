<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PendataanController extends Controller
{
    private function currentRole(): string
    {
        return strtoupper(trim((string) (session('pengguna_role') ?? session('role') ?? session('user_role') ?? '')));
    }

    private function normalizedRole(): string
    {
        return str_replace(['_', '-', ' '], '', $this->currentRole());
    }

    private function isHrdPusat(): bool
    {
        return $this->normalizedRole() === 'HRDPUSAT';
    }

    private function canManageKaryawan(): bool
    {
        return in_array($this->normalizedRole(), ['HRDPUSAT', 'HRD', 'ADMINHR'], true);
    }

    private function canImportExcel(): bool
    {
        return in_array($this->normalizedRole(), ['HRDPUSAT', 'HRD'], true);
    }

    private function canDeleteKaryawan(): bool
    {
        return $this->isHrdPusat();
    }

    private function ensureCanManageKaryawan(string $message = 'Anda tidak memiliki akses ke halaman ini.'): void
    {
        if (! $this->canManageKaryawan()) {
            abort(403, $message);
        }
    }

    private function ensureCanImportExcel(string $message = 'Anda tidak memiliki akses untuk melakukan import data.'): void
    {
        if (! $this->canImportExcel()) {
            abort(403, $message);
        }
    }

    private function sanitizeNumericInputs(Request $request): void
    {
        $merged = [];

        if ($request->has('nik_ktp')) {
            $merged['nik_ktp'] = preg_replace('/[^0-9]/', '', (string) $request->input('nik_ktp'));
        }

        if ($request->has('no_rekening')) {
            $cleanRek = preg_replace('/[^0-9]/', '', (string) $request->input('no_rekening'));
            $merged['no_rekening'] = $cleanRek !== '' ? $cleanRek : null;
        }

        if ($request->has('no_bpjs_kesehatan')) {
            $cleanBpjsKes = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('no_bpjs_kesehatan')));
            $merged['no_bpjs_kesehatan'] = $cleanBpjsKes !== '' ? $cleanBpjsKes : null;
        }

        if ($request->has('no_bpjs_ketenagakerjaan')) {
            $cleanBpjsKet = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('no_bpjs_ketenagakerjaan')));
            $merged['no_bpjs_ketenagakerjaan'] = $cleanBpjsKet !== '' ? $cleanBpjsKet : null;
        }

        if (! empty($merged)) {
            $request->merge($merged);
        }
    }

    private function normalizeAnakArray(array $rawAnak): array
    {
        if (empty($rawAnak)) {
            return [];
        }

        if (! array_key_exists(1, $rawAnak) && array_key_exists(0, $rawAnak)) {
            $normalized = [];
            $idx = 1;
            foreach ($rawAnak as $item) {
                $normalized[$idx++] = $item;
            }
            return $normalized;
        }

        return $rawAnak;
    }

    private function resolveNip(?string $inputNip, ?string $existingNip = null): string
    {
        $cleanInput = trim((string) $inputNip);
        if ($cleanInput !== '') {
            return $cleanInput;
        }

        $cleanExisting = trim((string) $existingNip);
        if ($cleanExisting !== '') {
            return $cleanExisting;
        }

        do {
            $nip = 'AUTO-' . date('ym') . '-' . random_int(10000, 99999);
        } while (DB::table('karyawan')->where('nip', $nip)->exists());

        return $nip;
    }

    private function normalizeRekening(?string $noRek): string
    {
        return preg_replace('/[^0-9]/', '', (string) $noRek);
    }

    private function normalizeNpwp(?string $rawNpwp): ?string
    {
        $clean = trim((string) $rawNpwp);
        if ($clean === '' || $clean === '-') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $clean);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 14) {
            $digits = '0' . $digits;
        }

        if (strlen($digits) === 15) {
            return sprintf(
                '%s.%s.%s.%s-%s.%s',
                substr($digits, 0, 2),
                substr($digits, 2, 3),
                substr($digits, 5, 3),
                substr($digits, 8, 1),
                substr($digits, 9, 3),
                substr($digits, 12, 3)
            );
        }

        if (strlen($digits) === 16) {
            return sprintf(
                '%s.%s.%s.%s',
                substr($digits, 0, 4),
                substr($digits, 4, 4),
                substr($digits, 8, 4),
                substr($digits, 12, 4)
            );
        }

        return str_replace(',', '.', $clean);
    }

    private function resolveBankIdFromRekening(string $noRek): ?int
    {
        if (strlen($noRek) === 15) {
            return DB::table('bank')->where('nama_bank', 'BRI')->value('id');
        }
        if (strlen($noRek) === 9 || strlen($noRek) === 10) {
            return DB::table('bank')->where('nama_bank', 'BNI')->value('id');
        }

        return null;
    }

    private function resolveBankId(string $noRek, $explicitBankId = null, ?string $namaBank = null): ?int
    {
        if (filled($explicitBankId)) {
            $found = DB::table('bank')->where('id', (int) $explicitBankId)->value('id');
            if ($found) {
                return (int) $found;
            }
        }

        $cleanBank = strtoupper(trim((string) $namaBank));
        if ($cleanBank !== '' && $cleanBank !== '-') {
            $bankId = DB::table('bank')
                ->whereRaw('UPPER(TRIM(nama_bank)) = ?', [$cleanBank])
                ->value('id')
                ?: DB::table('bank')
                    ->where('nama_bank', 'like', '%' . $cleanBank . '%')
                    ->value('id');

            if ($bankId) {
                return (int) $bankId;
            }
        }

        return $this->resolveBankIdFromRekening($noRek);
    }

    private function statusVerifikasiSqlExpression(): string
    {
        return "CASE WHEN
            TRIM(COALESCE(karyawan.no_wa, '')) NOT IN ('', '-')
            AND EXISTS (
                SELECT 1 FROM alamat a
                WHERE a.karyawan_id = karyawan.id
                  AND a.jenis_alamat = 'DOMISILI'
                  AND TRIM(COALESCE(a.alamat_lengkap, '')) NOT IN ('', '-')
            )
            AND (
                UPPER(TRIM(COALESCE(departemen.nama_departemen, ''))) != 'PRODUKSI'
                OR TRIM(COALESCE(karyawan.tempat_lahir, '')) NOT IN ('', '-')
            )
        THEN 'LENGKAP' ELSE 'BELUM_LENGKAP' END";
    }

    private function syncStatusVerifikasi(int $karyawanId): void
    {
        $statusExpr = $this->statusVerifikasiSqlExpression();

        DB::table('karyawan')
            ->leftJoin('departemen', 'karyawan.departemen_id', '=', 'departemen.id')
            ->where('karyawan.id', $karyawanId)
            ->update([
                'karyawan.status_verifikasi' => DB::raw("({$statusExpr})"),
            ]);
    }

    private function parseTahunOrDate(?string $val, bool $isEnd = false): ?string
    {
        $clean = trim((string) $val);
        if ($clean === '' || $clean === '-' || strtoupper($clean) === 'SEKARANG') {
            return null;
        }
        if (preg_match('/^\d{4}$/', $clean)) {
            $year = (int) $clean;
            if ($year < 1950 || $year > (int) date('Y')) {
                return null;
            }
            return $clean . ($isEnd ? '-12-31' : '-01-01');
        }

        return $this->normalizeExcelDate($clean);
    }

    private function normalizeJenjangPendidikan(string $raw): string
    {
        $clean = strtoupper(trim($raw));
        if ($clean === '' || $clean === '-') {
            return '';
        }

        return match (true) {
            str_contains($clean, 'SD') || str_contains($clean, 'MI')                       => 'SD',
            str_contains($clean, 'SMP') || str_contains($clean, 'MTS')                     => 'SMP',
            str_contains($clean, 'SMA') || str_contains($clean, 'SMK') || $clean === 'MA'  => 'SMA/SMK',
            $clean === 'D1'                                                                => 'D1',
            $clean === 'D2'                                                                => 'D2',
            $clean === 'D3'                                                                => 'D3',
            $clean === 'D4'                                                                => 'D4',
            str_contains($clean, 'S1')                                                     => 'S1',
            str_contains($clean, 'S2')                                                     => 'S2',
            str_contains($clean, 'S3')                                                     => 'S3',
            default                                                                        => $clean,
        };
    }

    private function normalizePendidikanAnak(string $raw): ?string
    {
        $clean = strtoupper(trim($raw));
        if ($clean === '' || $clean === '-') {
            return null;
        }

        return match (true) {
            str_contains($clean, 'BELUM')                                                                          => 'Belum sekolah',
            str_contains($clean, 'TK') || str_contains($clean, 'PAUD')                                             => 'TK / PAUD',
            $clean === 'SD'                                                                                        => 'SD',
            $clean === 'SMP'                                                                                       => 'SMP',
            str_contains($clean, 'SMA') || str_contains($clean, 'SMK') || $clean === 'MA'                          => 'SMA / SMK',
            str_contains($clean, 'KULIAH') || in_array($clean, ['D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'], true)   => 'Kuliah',
            str_contains($clean, 'BEKERJA') || str_contains($clean, 'KERJA')                                       => 'Sudah bekerja',
            default                                                                                                => $raw,
        };
    }

    private function applySheetDataDropdowns(Worksheet $wsData, int $lastMasterRow, int $maxRow, int $maxPend, int $maxAnak, bool $isTemplate = false): void
    {
        $setListRange = function (string $col, string $formula) use ($wsData, $maxRow): void {
            $val = $wsData->getCell("{$col}2")->getDataValidation();
            $val->setType(DataValidation::TYPE_LIST);
            $val->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $val->setAllowBlank(true);
            $val->setShowDropDown(true);
            $val->setFormula1($formula);
            $val->setSqref("{$col}2:{$col}{$maxRow}");
        };

        $bankNames = DB::table('bank')->orderBy('nama_bank')->pluck('nama_bank')
            ->map(fn ($b) => strtoupper(trim((string) $b)))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $opsiBank     = ! empty($bankNames) ? '"' . implode(',', $bankNames) . '"' : '"BRI,BNI,MANDIRI,BCA"';
        $opsiJk       = '"L,P"';
        $opsiUnit     = "=MASTER!\$C\$2:\$C\${$lastMasterRow}";
        $opsiNikah    = '"BELUM_KAWIN,KAWIN,CERAI_HIDUP,CERAI_MATI"';
        $opsiTipe     = '"BULANAN,HARIAN,BORONG,DISTRIBUSI"';
        $opsiStatus   = '"TETAP,HARIAN_LEPAS,KONTRAK"';
        $opsiTransfer = '"YA,TIDAK"';
        $opsiGolDarah = '"A,B,AB,O,-"';
        $opsiJenjang  = '"SD,SMP,SMA/SMK,D1,D2,D3,D4,S1,S2,S3"';
        $opsiPendAnak = '"Belum sekolah,TK / PAUD,SD,SMP,SMA / SMK,Kuliah,Sudah bekerja"';

        $colGolDarah  = $isTemplate ? 'AE' : 'AG';
        $colTipe      = $isTemplate ? 'AF' : 'AH';
        $colStatus    = $isTemplate ? 'AG' : 'AI';
        $colTransfer  = $isTemplate ? 'AH' : 'AJ';
        $startPendCol = $isTemplate ? 36 : 38;
        $startAnakCol = $startPendCol + ($maxPend * 4);

        $setListRange('E', $opsiJk);
        $setListRange('H', $opsiJenjang);
        $setListRange('P', $opsiUnit);
        $setListRange('R', $opsiNikah);
        $setListRange('T', $opsiBank);
        $setListRange($colGolDarah, $opsiGolDarah);
        $setListRange($colTipe, $opsiTipe);
        $setListRange($colStatus, $opsiStatus);
        $setListRange($colTransfer, $opsiTransfer);

        for ($i = 0; $i < $maxPend; $i++) {
            $colJenjang = Coordinate::stringFromColumnIndex($startPendCol + ($i * 4));
            $setListRange($colJenjang, $opsiJenjang);
        }

        for ($i = 0; $i < $maxAnak; $i++) {
            $colPendAnak = Coordinate::stringFromColumnIndex($startAnakCol + ($i * 3) + 2);
            $setListRange($colPendAnak, $opsiPendAnak);
        }
    }

    private function buildDataHeaders(int $maxPend = 4, int $maxAnak = 5, int $maxKarir = 3, int $maxPeng = 3, bool $isTemplate = false): array
    {
        $dataHeaders = [
            'No',                                   // A (1)
            'Nama Lengkap',                         // B (2)
            'NIP',                                  // C (3)
            'NIK',                                  // D (4)
            'Jenis Kelamin',                        // E (5)
            'Tempat Lahir',                         // F (6)
            'Tgl Lahir',                            // G (7)
            'Pendidikan Terakhir',                  // H (8)
            'Nama Ayah',                            // I (9)
            'Nama Ibu',                             // J (10)
            'Alamat KTP',                           // K (11)
            'Alamat Domisili',                      // L (12)
            'No HP/WA',                             // M (13)
            'Email',                                // N (14)
            'Jabatan',                              // O (15)
            'Unit',                                 // P (16)
            'Tanggal Masuk PT Sukun (YYYY-MM-DD)',  // Q (17)
            'Pernikahan',                           // R (18)
            'Disabilitas',                          // S (19)
            'Nama Bank',                            // T (20)
            'No Rekening',                          // U (21)
            'Atas Nama Rekening',                   // V (22)
            'BPJS Kes',                             // W (23)
            'BPJS Ket',                             // X (24)
            'NPWP',                                 // Y (25)
            'Pasangan',                             // Z (26)
            'Pekerjaan Pasangan',                   // AA (27)
            'Jml Anak',                             // AB (28)
            'Divisi',                               // AC (29)
            'Departemen / Unit',                    // AD (30)
        ];

        if (! $isTemplate) {
            array_push($dataHeaders, 'Status Verifikasi', 'Waktu Input'); // AE (31), AF (32)
        }

        array_push(
            $dataHeaders,
            'Golongan Darah',    // AE (31) [template] / AG (33) [export]
            'Jenis Karyawan',    // AF (32) / AH (34)
            'Status Karyawan',   // AG (33) / AI (35)
            'Bersedia Transfer', // AH (34) / AJ (36)
            'No Koperasi KKSG'   // AI (35) / AK (37)
        );

        for ($i = 1; $i <= $maxPend; $i++) {
            array_push($dataHeaders, "Pendidikan {$i} - Jenjang", "Pendidikan {$i} - Instansi", "Pendidikan {$i} - Jurusan", "Pendidikan {$i} - Nilai");
        }
        for ($i = 1; $i <= $maxAnak; $i++) {
            array_push($dataHeaders, "Anak {$i} - Nama", "Anak {$i} - Tgl Lahir", "Anak {$i} - Pendidikan");
        }
        for ($i = 1; $i <= $maxKarir; $i++) {
            array_push($dataHeaders, "Karir Sukun {$i} - Bagian", "Karir Sukun {$i} - Jabatan", "Karir Sukun {$i} - Tahun Mulai", "Karir Sukun {$i} - Tahun Selesai");
        }
        for ($i = 1; $i <= $maxPeng; $i++) {
            array_push($dataHeaders, "Pengalaman Luar {$i} - Perusahaan", "Pengalaman Luar {$i} - Jabatan", "Pengalaman Luar {$i} - Tahun Masuk", "Pengalaman Luar {$i} - Tahun Keluar");
        }

        return $dataHeaders;
    }

    private function validatePenempatanByTipe(Request $request, ?object $existing = null): void
    {
        $tipe = $request->filled('tipe_karyawan')
            ? $request->input('tipe_karyawan')
            : ($existing->tipe_karyawan ?? null);

        if ($tipe !== 'DISTRIBUSI' && empty($request->input('unit_organisasi_id'))) {
            throw ValidationException::withMessages([
                'unit_organisasi_id' => 'Bagian / unit kerja wajib dipilih.',
            ]);
        }

        if ($tipe === 'BORONG' && empty($request->input('jabatan_borong_id'))) {
            throw ValidationException::withMessages([
                'jabatan_borong_id' => 'Jenis pekerjaan borong wajib dipilih.',
            ]);
        }

        if ($tipe === 'DISTRIBUSI') {
            $jabatan = trim((string) $request->input('nama_jabatan', ''));
            if ($jabatan === '') {
                throw ValidationException::withMessages([
                    'nama_jabatan' => 'Jabatan distribusi wajib dipilih.',
                ]);
            }

            $jabUpper = strtoupper($jabatan);
            if (! str_starts_with($jabUpper, 'RM') && ! str_starts_with($jabUpper, 'AM') && empty($request->input('kota_perwakilan_id'))) {
                throw ValidationException::withMessages([
                    'kota_perwakilan_id' => 'Kota perwakilan / cabang wajib dipilih.',
                ]);
            }
        }
    }

    private function resolvePenempatan(Request $request, ?object $existing = null): array
    {
        $tipe         = $request->filled('tipe_karyawan') ? $request->input('tipe_karyawan') : ($existing->tipe_karyawan ?? null);
        $unitId       = $request->filled('unit_organisasi_id') ? (int) $request->input('unit_organisasi_id') : ($existing->unit_organisasi_id ?? null);
        $departemenId = $existing->departemen_id ?? null;
        $namaJabatan  = $request->filled('nama_jabatan')
            ? strtoupper(trim((string) $request->input('nama_jabatan')))
            : ($existing->nama_jabatan ?? null);

        $rawKota = $request->has('kota_perwakilan_id') ? $request->input('kota_perwakilan_id') : ($existing->kota_perwakilan_id ?? null);
        $rawArea = $request->has('area_id') ? $request->input('area_id') : ($existing->area_id ?? null);
        $rawBor  = $request->has('jabatan_borong_id') ? $request->input('jabatan_borong_id') : ($existing->jabatan_borong_id ?? null);

        $kotaId          = ($tipe === 'DISTRIBUSI' && filled($rawKota)) ? (int) $rawKota : null;
        $areaId          = ($tipe === 'DISTRIBUSI' && filled($rawArea)) ? (int) $rawArea : null;
        $jabatanBorongId = ($tipe === 'BORONG' && filled($rawBor)) ? (int) $rawBor : null;

        if ($tipe === 'DISTRIBUSI') {
            $departemenId = DB::table('departemen')->where('nama_departemen', 'DISTRIBUSI')->value('id')
                ?: DB::table('departemen')->where('nama_departemen', 'like', '%DISTRIBUSI%')->value('id')
                ?: $departemenId;

            $unitId = null;

            if ($namaJabatan && str_starts_with($namaJabatan, 'RM')) {
                $kotaId = null;
                $areaId = null;
                preg_match('/REGIONAL\s*[1-3]/i', $namaJabatan, $matches);
                $regName = ! empty($matches[0]) ? strtoupper($matches[0]) : 'REGIONAL';

                $unitId = DB::table('unit_organisasi')
                    ->when($departemenId, fn ($q) => $q->where('departemen_id', $departemenId))
                    ->where('nama_unit', 'like', '%' . $regName . '%')
                    ->value('id');

                if (! $unitId && $departemenId) {
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
                    ->when($departemenId, fn ($q) => $q->where('departemen_id', $departemenId))
                    ->where(function ($q) use ($wilayahAM) {
                        $q->where('nama_unit', 'like', '%' . $wilayahAM . '%')
                            ->orWhere('nama_unit', 'like', '%AM ' . $wilayahAM . '%');
                    })
                    ->where('nama_unit', 'not like', '%PUSAT%')
                    ->value('id');

                if (! $unitId && $departemenId && $wilayahAM !== '') {
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
                    $cleanKota       = strtoupper(trim((string) $namaKota));
                    $compactKota     = str_replace(' ', '', $cleanKota);

                    $unitId = DB::table('unit_organisasi')
                        ->when($departemenId, fn ($q) => $q->where('departemen_id', $departemenId))
                        ->where(function ($q) use ($cleanKota, $compactKota) {
                            $q->whereRaw('UPPER(TRIM(nama_unit)) = ?', ['PERWAKILAN ' . $cleanKota])
                                ->orWhere('nama_unit', 'like', '%' . $cleanKota . '%')
                                ->orWhereRaw("REPLACE(UPPER(nama_unit), ' ', '') LIKE ?", ['%' . $compactKota . '%']);
                        })
                        ->where('nama_unit', 'not like', '%PUSAT%')
                        ->value('id');

                    if (! $unitId && $departemenId) {
                        try {
                            $unitId = DB::table('unit_organisasi')->insertGetId([
                                'departemen_id' => $departemenId,
                                'nama_unit'     => 'PERWAKILAN ' . $cleanKota,
                            ]);
                        } catch (\Throwable $th) {
                        }
                    }
                }
            }

            if (! $unitId && $departemenId) {
                $unitId = DB::table('unit_organisasi')
                    ->where('departemen_id', $departemenId)
                    ->where('nama_unit', 'like', 'PERWAKILAN%')
                    ->value('id')
                    ?: DB::table('unit_organisasi')
                        ->where('departemen_id', $departemenId)
                        ->where('nama_unit', 'not like', '%PUSAT%')
                        ->value('id')
                    ?: DB::table('unit_organisasi')
                        ->where('departemen_id', $departemenId)
                        ->value('id');
            }
        } elseif ($unitId) {
            $departemenId = DB::table('unit_organisasi')->where('id', $unitId)->value('departemen_id') ?: $departemenId;
        } elseif ($tipe === 'BORONG') {
            $departemenId = DB::table('departemen')->where('nama_departemen', 'like', '%PRODUKSI%')
                ->orWhere('nama_departemen', 'like', '%BORONG%')->value('id') ?: $departemenId;
            if (! $unitId && $departemenId) {
                $unitId = DB::table('unit_organisasi')->where('departemen_id', $departemenId)->value('id');
            }
        }

        if (! $departemenId && $unitId) {
            $departemenId = DB::table('unit_organisasi')->where('id', $unitId)->value('departemen_id');
        }

        return [
            'departemen_id'      => $departemenId,
            'unit_organisasi_id' => $unitId,
            'nama_jabatan'       => $namaJabatan,
            'jabatan_borong_id'  => $jabatanBorongId,
            'kota_perwakilan_id' => $kotaId,
            'area_id'            => $areaId,
        ];
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
            'bank'              => DB::table('bank')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->sanitizeNumericInputs($request);
        $today = now()->toDateString();

        $data = $request->validate([
            'nik_ktp'       => 'required|digits:16',
            'nama_lengkap'  => 'required|string|max:150',
            'jenis_kelamin' => 'required|in:L,P',
            'tempat_lahir'  => 'nullable|string|max:100',
            'tgl_lahir'     => 'required|date|date_format:Y-m-d|after_or_equal:1940-01-01|before_or_equal:' . $today,
            'alamat_ktp'    => 'required|string',
            'tipe_karyawan' => 'required|in:BULANAN,HARIAN,BORONG,DISTRIBUSI',
            'ket_karyawan'  => 'required|in:TETAP,HARIAN_LEPAS,KONTRAK',

            'tgl_masuk_tanggal' => 'nullable|integer|between:1,31',
            'tgl_masuk_bulan'   => 'required|integer|between:1,12',
            'tgl_masuk_tahun'   => 'required|integer|between:1970,' . now()->year,

            'bank_id'     => 'nullable|exists:bank,id',
            'nama_bank'   => 'nullable|string|max:100',
            'no_rekening' => [
                'nullable',
                'regex:/^(?:[0-9]{9,10}|[0-9]{15})$/',
            ],

            'bersedia_transfer' => 'required|in:YA,TIDAK',
            'alamat_domisili'   => 'nullable|string',

            'unit_organisasi_id' => 'nullable|exists:unit_organisasi,id',
            'nama_jabatan'       => 'nullable|string|max:150',
            'jabatan_borong_id'  => 'nullable|exists:jabatan_borong,id',
            'kota_perwakilan_id' => 'nullable|exists:kota_perwakilan,id',
            'area_id'            => 'nullable|exists:area,id',

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
            'anak.*.tgl_lahir_anak'  => 'nullable|date|date_format:Y-m-d|after_or_equal:1970-01-01|before_or_equal:' . $today,
            'anak.*.pendidikan_anak' => 'nullable|string|max:50',
            'no_wa'                   => 'nullable|string|max:30',
            'email'                   => 'nullable|email|max:150',
            'golongan_darah'          => 'nullable|string|max:5',
            'disabilitas'             => 'nullable|string|max:100',
            'golongan_id'             => 'nullable|exists:golongan,id',
            'no_bpjs_kesehatan'       => 'nullable|alpha_num|max:30',
            'no_bpjs_ketenagakerjaan' => 'nullable|alpha_num|max:30',
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
            'tgl_lahir.date_format'                     => 'Format tanggal lahir tidak valid (tahun harus 4 digit).',
            'tgl_lahir.after_or_equal'                  => 'Tahun lahir tidak valid (minimal tahun 1940).',
            'tgl_lahir.before_or_equal'                 => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'anak.*.tgl_lahir_anak.date_format'         => 'Format tanggal lahir anak tidak valid (tahun harus 4 digit).',
            'anak.*.tgl_lahir_anak.before_or_equal'     => 'Tanggal lahir anak tidak boleh melebihi hari ini.',
            'no_bpjs_kesehatan.alpha_num'               => 'Nomor BPJS Kesehatan hanya boleh berisi huruf dan angka.',
            'no_bpjs_ketenagakerjaan.alpha_num'         => 'Nomor BPJS Ketenagakerjaan hanya boleh berisi huruf dan angka.',
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

        $this->validatePenempatanByTipe($request);

        if (DB::table('karyawan')->where('nik_ktp', $data['nik_ktp'])->exists()) {
            return redirect()->route('pendataan.create')->withErrors([
                'nik_ktp' => 'Nomor KTP (NIK) ' . $data['nik_ktp'] . ' sudah pernah mengisi data pendataan. Silakan hubungi bagian HRD PT. Sukun Wartono Indonesia.',
            ])->withInput();
        }

        if (! empty($data['nip']) && DB::table('karyawan')->where('nip', trim($data['nip']))->exists()) {
            return redirect()->route('pendataan.create')->withErrors([
                'nip' => 'NIP ' . $data['nip'] . ' sudah terdaftar dalam sistem.',
            ])->withInput();
        }

        if ($request->input('status_pernikahan') === 'KAWIN' && trim((string) $request->input('nama_pasangan', '')) === '') {
            throw ValidationException::withMessages([
                'nama_pasangan' => 'Nama suami / istri wajib diisi.',
            ]);
        }

        $statusPernikahan = $request->input('status_pernikahan');
        $dataAnakRequest  = $this->normalizeAnakArray((array) $request->input('anak', []));
        $jumlahAnak       = ($statusPernikahan === 'BELUM_KAWIN')
            ? 0
            : (int) $request->input('jumlah_anak', count($dataAnakRequest));

        if ($statusPernikahan !== 'BELUM_KAWIN' && $jumlahAnak > 0) {
            for ($i = 1; $i <= $jumlahAnak; $i++) {
                if (trim((string) data_get($dataAnakRequest, $i . '.nama_anak', '')) === '') {
                    throw ValidationException::withMessages([
                        "anak.$i.nama_anak" => "Nama anak ke-$i wajib diisi.",
                    ]);
                }
            }
        }

        $hariDiketahui = filled($data['tgl_masuk_tanggal'] ?? null);
        $hari  = $hariDiketahui ? (int) $data['tgl_masuk_tanggal'] : 1;
        $bulan = (int) $data['tgl_masuk_bulan'];
        $tahun = (int) $data['tgl_masuk_tahun'];

        if (! checkdate($bulan, $hari, $tahun)) {
            throw ValidationException::withMessages([
                'tgl_masuk_tanggal' => 'Tanggal tidak valid untuk bulan dan tahun yang dipilih.',
            ]);
        }

        $tglMasukKerja = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);

        DB::transaction(function () use ($request, $data, $hariDiketahui, $tglMasukKerja, $jumlahAnak, $statusPernikahan, $dataAnakRequest) {
            $nip   = $this->resolveNip($request->input('nip'));
            $token = bin2hex(random_bytes(32));

            $noRek  = $this->normalizeRekening($request->input('no_rekening'));
            $bankId = $this->resolveBankId($noRek, $request->input('bank_id'), $request->input('nama_bank'));

            $penempatan = $this->resolvePenempatan($request);
            $listPend = array_values(array_filter($data['riwayat_pendidikan'] ?? [], function ($rp) {
                return ! empty($rp['jenjang']) && ! empty($rp['nama_instansi']);
            }));
            $pendidikanPalingAkhir = ! empty($listPend) ? end($listPend) : [];

            $noWa           = trim((string) $request->input('no_wa', ''));
            $alamatDomisili = trim((string) ($request->input('alamat_domisili') ?: $data['alamat_ktp']));
            $tempatLahirVal = filled($data['tempat_lahir'] ?? null)
                ? strtoupper(trim($data['tempat_lahir']))
                : null;

            $namaPemilikRek = trim((string) $request->input('nama_pemilik_rekening', '')) ?: $data['nama_lengkap'];

            $karyawanId = DB::table('karyawan')->insertGetId([
                'token'                    => $token,
                'nip'                      => $nip,
                'nik_ktp'                  => $data['nik_ktp'],
                'nama_lengkap'             => strtoupper($data['nama_lengkap']),
                'jenis_kelamin'            => $data['jenis_kelamin'],
                'tempat_lahir'             => $tempatLahirVal,
                'tgl_lahir'                => $data['tgl_lahir'],
                'golongan_darah'           => $request->input('golongan_darah', '-'),
                'pendidikan_terakhir'      => strtoupper($pendidikanPalingAkhir['jenjang'] ?? 'SD'),
                'jurusan'                  => ! empty($pendidikanPalingAkhir['jurusan']) ? strtoupper($pendidikanPalingAkhir['jurusan']) : null,
                'nama_instansi'            => ! empty($pendidikanPalingAkhir['nama_instansi']) ? strtoupper($pendidikanPalingAkhir['nama_instansi']) : null,
                'nilai'                    => $pendidikanPalingAkhir['nilai_ipk'] ?? null,
                'no_wa'                    => $noWa !== '' ? $noWa : null,
                'email'                    => $request->input('email'),
                'nama_ayah'                => strtoupper($data['nama_ayah']),
                'nama_ibu'                 => strtoupper($data['nama_ibu']),
                'status_pernikahan'        => $statusPernikahan,
                'disabilitas'              => $request->input('disabilitas'),
                'tipe_karyawan'            => $request->input('tipe_karyawan'),
                'ket_karyawan'             => $request->input('ket_karyawan', 'TETAP'),
                'departemen_id'            => $penempatan['departemen_id'],
                'unit_organisasi_id'       => $penempatan['unit_organisasi_id'],
                'golongan_id'              => $request->input('golongan_id'),
                'nama_jabatan'             => $penempatan['nama_jabatan'],
                'jabatan_borong_id'        => $penempatan['jabatan_borong_id'],
                'kota_perwakilan_id'       => $penempatan['kota_perwakilan_id'],
                'area_id'                  => $penempatan['area_id'],
                'tgl_masuk_kerja'          => $tglMasukKerja,
                'tgl_masuk_hari_diketahui' => $hariDiketahui,
                'bank_id'                  => $bankId,
                'no_rekening'              => $noRek !== '' ? $noRek : null,
                'bersedia_transfer'        => $request->input('bersedia_transfer'),
                'nama_pemilik_rekening'    => strtoupper($namaPemilikRek),
                'no_bpjs_kesehatan'        => $data['no_bpjs_kesehatan'] ?? null,
                'no_bpjs_ketenagakerjaan'  => $data['no_bpjs_ketenagakerjaan'] ?? null,
                'npwp'                     => $this->normalizeNpwp($request->input('npwp')),
                'no_anggota_koperasi'      => $request->input('no_anggota_koperasi'),
                'status_verifikasi'        => 'BELUM_LENGKAP',
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);

            foreach ($listPend as $pend) {
                DB::table('riwayat_pendidikan')->insert([
                    'karyawan_id'   => $karyawanId,
                    'jenjang'       => strtoupper($pend['jenjang']),
                    'nama_instansi' => strtoupper($pend['nama_instansi']),
                    'jurusan'       => ! empty($pend['jurusan']) ? strtoupper($pend['jurusan']) : null,
                    'nilai_ipk'     => ! empty($pend['nilai_ipk']) ? $pend['nilai_ipk'] : null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            DB::table('alamat')->insert([
                'karyawan_id'    => $karyawanId,
                'jenis_alamat'   => 'KTP',
                'alamat_lengkap' => $data['alamat_ktp'],
            ]);

            DB::table('alamat')->insert([
                'karyawan_id'    => $karyawanId,
                'jenis_alamat'   => 'DOMISILI',
                'alamat_lengkap' => $alamatDomisili,
            ]);

            if ($statusPernikahan === 'KAWIN' && trim((string) $request->input('nama_pasangan', '')) !== '') {
                DB::table('pasangan')->insert([
                    'karyawan_id'        => $karyawanId,
                    'nama_pasangan'      => strtoupper(trim((string) $request->input('nama_pasangan'))),
                    'pekerjaan_pasangan' => trim((string) $request->input('pekerjaan_pasangan', '')) ?: '-',
                ]);
            }

            if ($statusPernikahan !== 'BELUM_KAWIN' && $jumlahAnak > 0) {
                for ($i = 1; $i <= $jumlahAnak; $i++) {
                    if (! empty($dataAnakRequest[$i]['nama_anak'])) {
                        DB::table('anak')->insert([
                            'karyawan_id'     => $karyawanId,
                            'anak_ke'         => $i,
                            'nama_anak'       => strtoupper($dataAnakRequest[$i]['nama_anak']),
                            'tgl_lahir_anak'  => $dataAnakRequest[$i]['tgl_lahir_anak'] ?? null,
                            'pendidikan_anak' => $dataAnakRequest[$i]['pendidikan_anak'] ?? null,
                        ]);
                    }
                }
            }

            foreach ($request->input('riwayat_sukun', []) as $rw) {
                if (! empty($rw['bagian'])) {
                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawanId,
                        'unit_bagian' => strtoupper(trim($rw['bagian'])),
                        'jabatan'     => ! empty($rw['jabatan']) ? strtoupper(trim($rw['jabatan'])) : '-',
                        'tgl_mulai'   => $this->parseTahunOrDate($rw['tahun_mulai'] ?? null, false),
                        'tgl_selesai' => $this->parseTahunOrDate($rw['tahun_selesai'] ?? null, true),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            foreach ($request->input('riwayat_luar', []) as $rwl) {
                if (! empty($rwl['perusahaan'])) {
                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawanId,
                        'nama_perusahaan' => strtoupper(trim($rwl['perusahaan'])),
                        'jabatan'         => ! empty($rwl['jabatan']) ? strtoupper(trim($rwl['jabatan'])) : '-',
                        'tahun_masuk'     => ! empty($rwl['tahun_masuk']) ? trim($rwl['tahun_masuk']) : null,
                        'tahun_keluar'    => ! empty($rwl['tahun_keluar']) ? trim($rwl['tahun_keluar']) : null,
                    ]);
                }
            }

            $this->syncStatusVerifikasi($karyawanId);
        });

        return redirect()->route('pendataan.create')->with('sukses', 'Terima kasih! Data Anda sudah berhasil masuk ke HRD Sukun.');
    }

    public function detail($id)
    {
        $statusExpr = $this->statusVerifikasiSqlExpression();

        $karyawan = DB::table('karyawan')
            ->leftJoin('departemen', 'karyawan.departemen_id', '=', 'departemen.id')
            ->leftJoin('unit_organisasi', 'karyawan.unit_organisasi_id', '=', 'unit_organisasi.id')
            ->leftJoin('jabatan_borong', 'karyawan.jabatan_borong_id', '=', 'jabatan_borong.id')
            ->leftJoin('kota_perwakilan', 'karyawan.kota_perwakilan_id', '=', 'kota_perwakilan.id')
            ->leftJoin('area', 'karyawan.area_id', '=', 'area.id')
            ->leftJoin('bank', 'karyawan.bank_id', '=', 'bank.id')
            ->select(
                'karyawan.*',
                DB::raw("({$statusExpr}) as status_verifikasi"),
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

        $alamat       = DB::table('alamat')->where('karyawan_id', $id)->get();
        $pasangan     = DB::table('pasangan')->where('karyawan_id', $id)->first();
        $anak         = DB::table('anak')->where('karyawan_id', $id)->orderBy('anak_ke')->get();
        $pendidikan   = DB::table('riwayat_pendidikan')->where('karyawan_id', $id)->get();
        $pengalaman   = DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $id)->get();
        $riwayatSukun = DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $id)->get();

        $backUrl   = ($this->normalizedRole() === 'ATASAN') ? url('/atasan/dashboard') : url('/hrd/dashboard');
        $canDelete = $this->canDeleteKaryawan();

        return view('pendataan.detail', compact('karyawan', 'alamat', 'pasangan', 'anak', 'pendidikan', 'pengalaman', 'riwayatSukun', 'backUrl', 'canDelete'));
    }

    public function editHrd($id)
    {
        $this->ensureCanManageKaryawan('Anda tidak memiliki akses untuk mengubah data karyawan.');

        $karyawan = DB::table('karyawan')->where('id', $id)->first();
        if (! $karyawan) {
            abort(404, 'Data karyawan tidak ditemukan.');
        }

        return view('pendataan.edit-hrd', [
            'karyawan'          => $karyawan,
            'alamatKtp'         => DB::table('alamat')->where('karyawan_id', $id)->where('jenis_alamat', 'KTP')->first(),
            'alamatDom'         => DB::table('alamat')->where('karyawan_id', $id)->where('jenis_alamat', 'DOMISILI')->first(),
            'pasangan'          => DB::table('pasangan')->where('karyawan_id', $id)->first(),
            'anak'              => DB::table('anak')->where('karyawan_id', $id)->orderBy('anak_ke')->get(),
            'pendidikan'        => DB::table('riwayat_pendidikan')->where('karyawan_id', $id)->get(),
            'riwayatLuar'       => DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $id)->get(),
            'riwayatSukun'      => DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $id)->get(),
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
        $this->ensureCanManageKaryawan('Anda tidak memiliki akses untuk memperbarui data karyawan.');

        $karyawan = DB::table('karyawan')->where('id', $id)->first();
        if (! $karyawan) {
            abort(404, 'Data karyawan tidak ditemukan.');
        }

        $this->sanitizeNumericInputs($request);
        $today = now()->toDateString();

        $request->validate([
            'nik_ktp' => [
                'required',
                'digits:16',
                Rule::unique('karyawan', 'nik_ktp')->ignore($karyawan->id),
            ],
            'nip' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('karyawan', 'nip')->ignore($karyawan->id),
            ],
            'nama_lengkap'        => 'required|string|max:150',
            'jenis_kelamin'       => 'required|in:L,P',
            'tempat_lahir'        => 'nullable|string|max:100',
            'tgl_lahir'           => 'required|date|date_format:Y-m-d|after_or_equal:1940-01-01|before_or_equal:' . $today,
            'golongan_darah'      => 'nullable|string|max:5',
            'disabilitas'         => 'nullable|string|max:100',
            'no_wa'               => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:150',
            'npwp'                => 'nullable|string|max:50',
            'no_anggota_koperasi' => 'nullable|string|max:50',

            'alamat_ktp'      => 'required|string',
            'alamat_domisili' => 'nullable|string',

            'tipe_karyawan' => 'required|in:BULANAN,HARIAN,BORONG,DISTRIBUSI',
            'ket_karyawan'  => 'required|in:TETAP,HARIAN_LEPAS,KONTRAK',

            'tgl_masuk_kerja'   => 'nullable|date|date_format:Y-m-d|after_or_equal:1970-01-01|before_or_equal:' . $today,
            'tgl_masuk_tanggal' => 'nullable|integer|between:1,31',
            'tgl_masuk_bulan'   => 'nullable|integer|between:1,12',
            'tgl_masuk_tahun'   => 'nullable|integer|between:1970,' . now()->year,

            'unit_organisasi_id' => 'nullable|exists:unit_organisasi,id',
            'nama_jabatan'       => 'nullable|string|max:150',
            'jabatan_borong_id'  => 'nullable|exists:jabatan_borong,id',
            'kota_perwakilan_id' => 'nullable|exists:kota_perwakilan,id',
            'area_id'            => 'nullable|exists:area,id',
            'golongan_id'        => 'nullable|exists:golongan,id',

            'bank_id'     => 'nullable|exists:bank,id',
            'nama_bank'   => 'nullable|string|max:100',
            'no_rekening' => [
                'nullable',
                'regex:/^(?:[0-9]{9,10}|[0-9]{15})$/',
            ],
            'nama_pemilik_rekening'   => 'nullable|string|max:150',
            'bersedia_transfer'       => 'required|in:YA,TIDAK',
            'no_bpjs_kesehatan'       => 'nullable|alpha_num|max:30',
            'no_bpjs_ketenagakerjaan' => 'nullable|alpha_num|max:30',

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
            'anak.*.tgl_lahir_anak'  => 'nullable|date|date_format:Y-m-d|after_or_equal:1970-01-01|before_or_equal:' . $today,
            'anak.*.pendidikan_anak' => 'nullable|string|max:50',

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
            'nik_ktp.unique'                            => 'Nomor KTP (NIK) sudah digunakan oleh karyawan lain.',
            'nip.unique'                                => 'NIP sudah terdaftar pada karyawan lain.',
            'nama_lengkap.required'                     => 'Nama lengkap wajib diisi.',
            'jenis_kelamin.required'                    => 'Jenis kelamin wajib dipilih.',
            'tgl_lahir.required'                        => 'Tanggal lahir wajib diisi.',
            'tgl_lahir.date_format'                     => 'Format tanggal lahir tidak valid (tahun harus 4 digit).',
            'tgl_lahir.after_or_equal'                  => 'Tahun lahir tidak valid (minimal tahun 1940).',
            'tgl_lahir.before_or_equal'                 => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'anak.*.tgl_lahir_anak.date_format'         => 'Format tanggal lahir anak tidak valid (tahun harus 4 digit).',
            'anak.*.tgl_lahir_anak.before_or_equal'     => 'Tanggal lahir anak tidak boleh melebihi hari ini.',
            'no_bpjs_kesehatan.alpha_num'               => 'Nomor BPJS Kesehatan hanya boleh berisi huruf dan angka.',
            'no_bpjs_ketenagakerjaan.alpha_num'         => 'Nomor BPJS Ketenagakerjaan hanya boleh berisi huruf dan angka.',
            'alamat_ktp.required'                       => 'Alamat KTP wajib diisi.',
            'no_rekening.regex'                         => 'Jika diisi, nomor rekening harus 9-10 angka untuk BNI atau 15 angka untuk BRI.',
            'bersedia_transfer.required'                => 'Pertanyaan kesediaan transfer gaji wajib dipilih.',
            'nama_ayah.required'                        => 'Nama lengkap ayah kandung wajib diisi.',
            'nama_ibu.required'                         => 'Nama lengkap ibu kandung wajib diisi.',
            'status_pernikahan.required'                => 'Status pernikahan wajib dipilih.',
            'email.email'                               => 'Format alamat email tidak valid.',
            'riwayat_pendidikan.required'               => 'Riwayat pendidikan wajib diisi minimal 1.',
            'riwayat_pendidikan.*.jenjang.required'     => 'Jenjang pendidikan wajib dipilih.',
            'riwayat_pendidikan.*.nama_instansi.required' => 'Nama sekolah / universitas wajib diisi.',
        ]);

        $this->validatePenempatanByTipe($request, $karyawan);

        if ($request->input('status_pernikahan') === 'KAWIN' && trim((string) $request->input('nama_pasangan', '')) === '') {
            throw ValidationException::withMessages([
                'nama_pasangan' => 'Nama suami / istri wajib diisi jika status Sudah menikah.',
            ]);
        }

        $tglMasukKerja = $karyawan->tgl_masuk_kerja;
        $hariDiketahui = (bool) ($karyawan->tgl_masuk_hari_diketahui ?? true);

        if ($request->filled('tgl_masuk_kerja')) {
            $tglMasukKerja = $request->input('tgl_masuk_kerja');
            $hariDiketahui = true;
        } elseif ($request->filled('tgl_masuk_bulan') && $request->filled('tgl_masuk_tahun')) {
            $hariDiketahui = filled($request->input('tgl_masuk_tanggal'));
            $hari  = $hariDiketahui ? (int) $request->input('tgl_masuk_tanggal') : 1;
            $bulan = (int) $request->input('tgl_masuk_bulan');
            $tahun = (int) $request->input('tgl_masuk_tahun');

            if (! checkdate($bulan, $hari, $tahun)) {
                throw ValidationException::withMessages([
                    'tgl_masuk_tanggal' => 'Tanggal masuk kerja tidak valid untuk bulan dan tahun yang dipilih.',
                ]);
            }

            $tglMasukKerja = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
        }

        $statusPernikahan = $request->input('status_pernikahan');
        $dataAnakRequest  = $this->normalizeAnakArray((array) $request->input('anak', []));
        $jumlahAnak       = ($statusPernikahan === 'BELUM_KAWIN')
            ? 0
            : (int) $request->input('jumlah_anak', count($dataAnakRequest));

        if ($statusPernikahan !== 'BELUM_KAWIN' && $jumlahAnak > 0) {
            for ($i = 1; $i <= $jumlahAnak; $i++) {
                if (trim((string) data_get($dataAnakRequest, $i . '.nama_anak', '')) === '') {
                    throw ValidationException::withMessages([
                        "anak.$i.nama_anak" => "Nama anak ke-$i wajib diisi.",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($request, $karyawan, $statusPernikahan, $jumlahAnak, $dataAnakRequest, $tglMasukKerja, $hariDiketahui) {
            $penempatan = $this->resolvePenempatan($request, $karyawan);

            $finalNip = $this->resolveNip($request->input('nip'), $karyawan->nip);

            $noRek  = $this->normalizeRekening($request->has('no_rekening') ? $request->input('no_rekening') : $karyawan->no_rekening);
            $bankId = $this->resolveBankId(
                $noRek,
                $request->has('bank_id') ? $request->input('bank_id') : $karyawan->bank_id,
                $request->input('nama_bank')
            );

            $noWa = trim((string) ($request->has('no_wa') ? $request->input('no_wa', '') : $karyawan->no_wa));

            $alamatKtp = trim((string) $request->input('alamat_ktp'));

            $savedAlamatDomisili = DB::table('alamat')
                ->where('karyawan_id', $karyawan->id)
                ->where('jenis_alamat', 'DOMISILI')
                ->value('alamat_lengkap');

            $alamatDomisili = trim((string) (
                $request->filled('alamat_domisili')
                    ? $request->input('alamat_domisili')
                    : ($savedAlamatDomisili ?: $alamatKtp)
            ));
            $tempatLahirVal = $request->has('tempat_lahir')
                ? (filled($request->input('tempat_lahir')) ? strtoupper(trim($request->input('tempat_lahir'))) : null)
                : $karyawan->tempat_lahir;

            $namaLengkap    = strtoupper($request->input('nama_lengkap', $karyawan->nama_lengkap));
            $namaPemilikRek = trim((string) $request->input('nama_pemilik_rekening', ''))
                ?: ($karyawan->nama_pemilik_rekening ?: $namaLengkap);

            $updateKaryawan = [
                'nip'                      => $finalNip,
                'nik_ktp'                  => $request->input('nik_ktp', $karyawan->nik_ktp),
                'nama_lengkap'             => $namaLengkap,
                'jenis_kelamin'            => $request->input('jenis_kelamin', $karyawan->jenis_kelamin),
                'tempat_lahir'             => $tempatLahirVal,
                'tgl_lahir'                => $request->input('tgl_lahir', $karyawan->tgl_lahir),
                'golongan_darah'           => $request->input('golongan_darah', $karyawan->golongan_darah),
                'nama_ayah'                => strtoupper($request->input('nama_ayah', $karyawan->nama_ayah ?? '')),
                'nama_ibu'                 => strtoupper($request->input('nama_ibu', $karyawan->nama_ibu ?? '')),
                'no_wa'                    => $noWa !== '' ? $noWa : null,
                'email'                    => $request->input('email', $karyawan->email),
                'disabilitas'              => $request->input('disabilitas', $karyawan->disabilitas),
                'status_pernikahan'        => $statusPernikahan,
                'tipe_karyawan'            => $request->filled('tipe_karyawan') ? $request->input('tipe_karyawan') : $karyawan->tipe_karyawan,
                'ket_karyawan'             => $request->filled('ket_karyawan') ? $request->input('ket_karyawan') : $karyawan->ket_karyawan,
                'tgl_masuk_kerja'          => $tglMasukKerja,
                'tgl_masuk_hari_diketahui' => $hariDiketahui,
                'departemen_id'            => $penempatan['departemen_id'],
                'unit_organisasi_id'       => $penempatan['unit_organisasi_id'],
                'golongan_id'              => $request->input('golongan_id', $karyawan->golongan_id),
                'nama_jabatan'             => $penempatan['nama_jabatan'],
                'jabatan_borong_id'        => $penempatan['jabatan_borong_id'],
                'kota_perwakilan_id'       => $penempatan['kota_perwakilan_id'],
                'area_id'                  => $penempatan['area_id'],
                'bank_id'                  => $bankId,
                'no_rekening'              => $noRek !== '' ? $noRek : null,
                'bersedia_transfer'        => $request->input('bersedia_transfer', $karyawan->bersedia_transfer),
                'nama_pemilik_rekening'    => strtoupper($namaPemilikRek),
                'no_bpjs_kesehatan'        => $request->has('no_bpjs_kesehatan') ? $request->input('no_bpjs_kesehatan') : $karyawan->no_bpjs_kesehatan,
                'no_bpjs_ketenagakerjaan'  => $request->has('no_bpjs_ketenagakerjaan') ? $request->input('no_bpjs_ketenagakerjaan') : $karyawan->no_bpjs_ketenagakerjaan,
                'npwp'                     => $this->normalizeNpwp($request->input('npwp', $karyawan->npwp)),
                'no_anggota_koperasi'      => $request->input('no_anggota_koperasi', $karyawan->no_anggota_koperasi),
                'updated_at'               => now(),
            ];

            DB::table('riwayat_pendidikan')->where('karyawan_id', $karyawan->id)->delete();
            $listP = array_values(array_filter($request->input('riwayat_pendidikan', []), function ($rp) {
                return ! empty($rp['jenjang']) && ! empty($rp['nama_instansi']);
            }));

            foreach ($listP as $rp) {
                DB::table('riwayat_pendidikan')->insert([
                    'karyawan_id'   => $karyawan->id,
                    'jenjang'       => strtoupper($rp['jenjang']),
                    'nama_instansi' => strtoupper($rp['nama_instansi']),
                    'jurusan'       => ! empty($rp['jurusan']) ? strtoupper($rp['jurusan']) : null,
                    'nilai_ipk'     => ! empty($rp['nilai_ipk']) ? $rp['nilai_ipk'] : null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            if (! empty($listP)) {
                $lastPend = end($listP);
                $updateKaryawan['pendidikan_terakhir'] = strtoupper($lastPend['jenjang']);
                $updateKaryawan['nama_instansi']       = strtoupper($lastPend['nama_instansi']);
                $updateKaryawan['jurusan']             = ! empty($lastPend['jurusan']) ? strtoupper($lastPend['jurusan']) : null;
                $updateKaryawan['nilai']               = $lastPend['nilai_ipk'] ?? null;
            }

            DB::table('karyawan')->where('id', $karyawan->id)->update($updateKaryawan);

            DB::table('alamat')->updateOrInsert(
                ['karyawan_id' => $karyawan->id, 'jenis_alamat' => 'KTP'],
                ['alamat_lengkap' => $alamatKtp]
            );

            DB::table('alamat')->updateOrInsert(
                ['karyawan_id' => $karyawan->id, 'jenis_alamat' => 'DOMISILI'],
                ['alamat_lengkap' => $alamatDomisili]
            );

            if ($statusPernikahan === 'KAWIN' && trim((string) $request->input('nama_pasangan', '')) !== '') {
                DB::table('pasangan')->updateOrInsert(
                    ['karyawan_id' => $karyawan->id],
                    [
                        'nama_pasangan'      => strtoupper(trim((string) $request->input('nama_pasangan'))),
                        'pekerjaan_pasangan' => trim((string) $request->input('pekerjaan_pasangan', '')) ?: '-',
                    ]
                );
            } else {
                DB::table('pasangan')->where('karyawan_id', $karyawan->id)->delete();
            }

            DB::table('anak')->where('karyawan_id', $karyawan->id)->delete();
            if ($statusPernikahan !== 'BELUM_KAWIN' && $jumlahAnak > 0) {
                for ($i = 1; $i <= $jumlahAnak; $i++) {
                    if (! empty($dataAnakRequest[$i]['nama_anak'])) {
                        DB::table('anak')->insert([
                            'karyawan_id'     => $karyawan->id,
                            'anak_ke'         => $i,
                            'nama_anak'       => strtoupper($dataAnakRequest[$i]['nama_anak']),
                            'tgl_lahir_anak'  => $dataAnakRequest[$i]['tgl_lahir_anak'] ?? null,
                            'pendidikan_anak' => $dataAnakRequest[$i]['pendidikan_anak'] ?? null,
                        ]);
                    }
                }
            }

            DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $karyawan->id)->delete();
            foreach ($request->input('riwayat_sukun', []) as $rw) {
                if (! empty($rw['bagian'])) {
                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawan->id,
                        'unit_bagian' => strtoupper(trim($rw['bagian'])),
                        'jabatan'     => ! empty($rw['jabatan']) ? strtoupper(trim($rw['jabatan'])) : '-',
                        'tgl_mulai'   => $this->parseTahunOrDate($rw['tahun_mulai'] ?? null, false),
                        'tgl_selesai' => $this->parseTahunOrDate($rw['tahun_selesai'] ?? null, true),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $karyawan->id)->delete();
            foreach ($request->input('riwayat_luar', []) as $rwl) {
                if (! empty($rwl['perusahaan'])) {
                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawan->id,
                        'nama_perusahaan' => strtoupper(trim($rwl['perusahaan'])),
                        'jabatan'         => ! empty($rwl['jabatan']) ? strtoupper(trim($rwl['jabatan'])) : '-',
                        'tahun_masuk'     => ! empty($rwl['tahun_masuk']) ? trim($rwl['tahun_masuk']) : null,
                        'tahun_keluar'    => ! empty($rwl['tahun_keluar']) ? trim($rwl['tahun_keluar']) : null,
                    ]);
                }
            }

            $this->syncStatusVerifikasi($karyawan->id);
        });

        return redirect()->route('pendataan.detail', $karyawan->id)->with('sukses', 'Seluruh data karyawan berhasil diperbarui oleh HRD.');
    }

    public function destroy($id)
    {
        if (! $this->canDeleteKaryawan()) {
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

    public function showMandiriForm()
    {
        return view('pendataan.mandiri');
    }

    public function verifyMandiri(Request $request)
    {
        $throttleKey = 'mandiri-verify|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);

            return back()->withInput()->withErrors([
                'auth' => "Terlalu banyak percobaan. Silakan coba lagi dalam {$minutes} menit.",
            ]);
        }

        if ($request->has('nik')) {
            $request->merge([
                'nik' => preg_replace('/\D/', '', (string) $request->input('nik')),
            ]);
        }

        $request->validate([
            'nik'             => 'required|digits:16',
            'nama_ibu'        => 'required|string|min:2|max:150',
            'tgl_masuk_bulan' => 'required|integer|between:1,12',
            'tgl_masuk_tahun' => 'required|integer|between:1950,' . now()->year,
        ], [
            'nik.required'             => 'NIK wajib diisi.',
            'nik.digits'               => 'NIK harus 16 digit angka.',
            'nama_ibu.required'        => 'Nama ibu kandung wajib diisi.',
            'tgl_masuk_bulan.required' => 'Bulan masuk wajib dipilih.',
            'tgl_masuk_bulan.between'  => 'Bulan masuk tidak valid.',
            'tgl_masuk_tahun.required' => 'Tahun masuk wajib diisi.',
            'tgl_masuk_tahun.between'  => 'Tahun masuk tidak valid.',
        ]);

        $nik = trim((string) $request->input('nik'));
        $karyawan = DB::table('karyawan')->where('nik_ktp', $nik)->first();

        $authFailed = false;

        if (! $karyawan) {
            $authFailed = true;
        } else {
            if (empty($karyawan->tgl_masuk_kerja)) {
                $authFailed = true;
            } else {
                $tglMasukTime = strtotime($karyawan->tgl_masuk_kerja);
                $dbMonth = (int) date('n', $tglMasukTime);
                $dbYear  = (int) date('Y', $tglMasukTime);

                $reqMonth = (int) $request->input('tgl_masuk_bulan');
                $reqYear  = (int) $request->input('tgl_masuk_tahun');

                if ($dbMonth !== $reqMonth || $dbYear !== $reqYear) {
                    $authFailed = true;
                }
            }

            if (! $authFailed) {
                if (! $this->matchNamaIbu((string) $request->input('nama_ibu'), (string) ($karyawan->nama_ibu ?? ''))) {
                    $authFailed = true;
                }
            }
        }

        if ($authFailed) {
            RateLimiter::hit($throttleKey, 900);
            $attemptsLeft = RateLimiter::remaining($throttleKey, 5);

            return back()->withInput()->withErrors([
                'auth' => "Data tidak sesuai. Silakan periksa kembali data Anda. (Sisa: {$attemptsLeft})",
            ]);
        }

        RateLimiter::clear($throttleKey);

        $token = $karyawan->token;
        if (empty($token)) {
            $token = Str::random(40);
            DB::table('karyawan')->where('id', $karyawan->id)->update(['token' => $token]);
        }

        return redirect()->route('pendataan.edit.token', $token)
            ->with('sukses', 'Verifikasi identitas berhasil! Silakan periksa atau lengkapi data Anda di bawah ini.');
    }

    private function matchNamaIbu(string $input, string $db): bool
    {
        $normalize = function (string $str): string {
            $str = strtolower(trim($str));
            $str = preg_replace('/\b(hj|hajjah|hajah|ny|ibu|alm|almarhumah|st)\b\.?/i', '', $str);
            return preg_replace('/[^a-z0-9]/', '', $str);
        };

        $cleanInput = $normalize($input);
        $cleanDb    = $normalize($db);

        if ($cleanInput === '' || $cleanDb === '') {
            return false;
        }

        if ($cleanInput === $cleanDb) {
            return true;
        }

        if (strlen($cleanInput) >= 4 && (str_contains($cleanDb, $cleanInput) || str_contains($cleanInput, $cleanDb))) {
            return true;
        }

        return false;
    }

    public function editByToken($token)
    {
        $karyawan = DB::table('karyawan')->where('token', $token)->first();
        if (! $karyawan) {
            abort(404, 'Link edit tidak valid.');
        }

        return view('pendataan.edit-token', [
            'karyawan'          => $karyawan,
            'alamatKtp'         => DB::table('alamat')->where('karyawan_id', $karyawan->id)->where('jenis_alamat', 'KTP')->first(),
            'alamatDom'         => DB::table('alamat')->where('karyawan_id', $karyawan->id)->where('jenis_alamat', 'DOMISILI')->first(),
            'pasangan'          => DB::table('pasangan')->where('karyawan_id', $karyawan->id)->first(),
            'anak'              => DB::table('anak')->where('karyawan_id', $karyawan->id)->orderBy('anak_ke')->get(),
            'pendidikan'        => DB::table('riwayat_pendidikan')->where('karyawan_id', $karyawan->id)->get(),
            'pengalaman'        => DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $karyawan->id)->get(),
            'riwayatSukun'      => DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $karyawan->id)->get(),
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

    public function updateByToken(Request $request, $token)
    {
        $karyawan = DB::table('karyawan')->where('token', $token)->first();
        if (! $karyawan) {
            abort(404, 'Data tidak ditemukan.');
        }

        $this->sanitizeNumericInputs($request);
        $today = now()->toDateString();

        $request->validate([
            'nip' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('karyawan', 'nip')->ignore($karyawan->id),
            ],
            'nama_ayah'               => 'required|string|max:150',
            'nama_ibu'                => 'required|string|max:150',
            'jenis_kelamin'           => 'required|in:L,P',
            'tempat_lahir'            => 'nullable|string|max:100',
            'tgl_lahir'               => 'required|date|date_format:Y-m-d|after_or_equal:1940-01-01|before_or_equal:' . $today,
            'golongan_darah'          => 'nullable|string|max:5',
            'disabilitas'             => 'nullable|string|max:100',
            'no_wa'                   => 'nullable|string|max:30',
            'email'                   => 'nullable|email|max:150',
            'alamat_domisili'         => 'required|string',
            'tipe_karyawan'           => 'nullable|in:BULANAN,HARIAN,BORONG,DISTRIBUSI',
            'ket_karyawan'            => 'nullable|in:TETAP,HARIAN_LEPAS,KONTRAK',
            'unit_organisasi_id'      => 'nullable|exists:unit_organisasi,id',
            'nama_jabatan'            => 'nullable|string|max:150',
            'jabatan_borong_id'       => 'nullable|exists:jabatan_borong,id',
            'kota_perwakilan_id'      => 'nullable|exists:kota_perwakilan,id',
            'area_id'                 => 'nullable|exists:area,id',
            'status_pernikahan'       => 'required|in:BELUM_KAWIN,KAWIN,CERAI_HIDUP,CERAI_MATI',
            'bersedia_transfer'       => 'required|in:YA,TIDAK',
            'bank_id'                 => 'nullable|exists:bank,id',
            'nama_bank'               => 'nullable|string|max:100',
            'no_rekening'             => [
                'nullable',
                'regex:/^(?:[0-9]{9,10}|[0-9]{15})$/',
            ],
            'nama_pemilik_rekening'   => 'nullable|string|max:150',
            'no_bpjs_kesehatan'       => 'nullable|alpha_num|max:30',
            'no_bpjs_ketenagakerjaan' => 'nullable|alpha_num|max:30',
            'npwp'                    => 'nullable|string|max:50',
            'no_anggota_koperasi'     => 'nullable|string|max:50',

            'nama_pasangan'          => 'nullable|string|max:150',
            'pekerjaan_pasangan'     => 'nullable|string|max:150',
            'jumlah_anak'            => 'nullable|integer|between:0,10',
            'anak'                   => 'nullable|array|max:10',
            'anak.*.nama_anak'       => 'nullable|string|max:150',
            'anak.*.tgl_lahir_anak'  => 'nullable|date|date_format:Y-m-d|after_or_equal:1970-01-01|before_or_equal:' . $today,
            'anak.*.pendidikan_anak' => 'nullable|string|max:50',

            'riwayat_pendidikan'                 => 'required|array|min:1',
            'riwayat_pendidikan.*.jenjang'       => 'required|string|max:50',
            'riwayat_pendidikan.*.nama_instansi' => 'required|string|max:150',
            'riwayat_pendidikan.*.jurusan'       => 'nullable|string|max:150',
            'riwayat_pendidikan.*.nilai_ipk'     => 'nullable|string|max:50',

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
            'nip.unique'                                => 'NIP sudah terdaftar pada karyawan lain.',
            'alamat_domisili.required'                  => 'Alamat domisili saat ini wajib diisi.',
            'tgl_lahir.date_format'                     => 'Format tanggal lahir tidak valid (tahun harus 4 digit).',
            'tgl_lahir.after_or_equal'                  => 'Tahun lahir tidak valid (minimal tahun 1940).',
            'tgl_lahir.before_or_equal'                 => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'anak.*.tgl_lahir_anak.date_format'         => 'Format tanggal lahir anak tidak valid (tahun harus 4 digit).',
            'anak.*.tgl_lahir_anak.before_or_equal'     => 'Tanggal lahir anak tidak boleh melebihi hari ini.',
            'no_bpjs_kesehatan.alpha_num'               => 'Nomor BPJS Kesehatan hanya boleh berisi huruf dan angka.',
            'no_bpjs_ketenagakerjaan.alpha_num'         => 'Nomor BPJS Ketenagakerjaan hanya boleh berisi huruf dan angka.',
            'no_rekening.regex'                         => 'Jika diisi, nomor rekening harus 9-10 angka untuk BNI atau 15 angka untuk BRI.',
            'riwayat_pendidikan.required'               => 'Riwayat pendidikan wajib diisi minimal 1.',
            'riwayat_pendidikan.*.jenjang.required'     => 'Jenjang pendidikan wajib dipilih.',
            'riwayat_pendidikan.*.nama_instansi.required' => 'Nama sekolah / universitas wajib diisi.',
        ]);

        $this->validatePenempatanByTipe($request, $karyawan);

        if ($request->input('status_pernikahan') === 'KAWIN' && trim((string) $request->input('nama_pasangan', '')) === '') {
            throw ValidationException::withMessages([
                'nama_pasangan' => 'Nama suami / istri wajib diisi jika status Sudah menikah.',
            ]);
        }

        $statusPernikahan = $request->input('status_pernikahan');
        $dataAnakRequest  = $this->normalizeAnakArray((array) $request->input('anak', []));
        $jumlahAnak       = ($statusPernikahan === 'BELUM_KAWIN')
            ? 0
            : (int) $request->input('jumlah_anak', count($dataAnakRequest));

        if ($statusPernikahan !== 'BELUM_KAWIN' && $jumlahAnak > 0) {
            for ($i = 1; $i <= $jumlahAnak; $i++) {
                if (trim((string) data_get($dataAnakRequest, $i . '.nama_anak', '')) === '') {
                    throw ValidationException::withMessages([
                        "anak.$i.nama_anak" => "Nama anak ke-$i wajib diisi.",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($request, $karyawan, $statusPernikahan, $jumlahAnak, $dataAnakRequest) {
            $penempatan = $this->resolvePenempatan($request, $karyawan);

            $finalNip = $this->resolveNip($request->input('nip'), $karyawan->nip);

            $noRek  = $this->normalizeRekening($request->has('no_rekening') ? $request->input('no_rekening') : $karyawan->no_rekening);
            $bankId = $this->resolveBankId(
                $noRek,
                $request->has('bank_id') ? $request->input('bank_id') : $karyawan->bank_id,
                $request->input('nama_bank')
            );

            $noWa = trim((string) ($request->has('no_wa') ? $request->input('no_wa', '') : $karyawan->no_wa));

            $savedAlamatKtp = trim((string) DB::table('alamat')
                ->where('karyawan_id', $karyawan->id)
                ->where('jenis_alamat', 'KTP')
                ->value('alamat_lengkap'));

            $savedAlamatDomisili = trim((string) DB::table('alamat')
                ->where('karyawan_id', $karyawan->id)
                ->where('jenis_alamat', 'DOMISILI')
                ->value('alamat_lengkap'));

            $alamatDomisili = trim((string) (
                $request->filled('alamat_domisili')
                    ? $request->input('alamat_domisili')
                    : ($savedAlamatDomisili ?: $savedAlamatKtp)
            ));

            $tempatLahirVal = $request->has('tempat_lahir')
                ? (filled($request->input('tempat_lahir')) ? strtoupper(trim($request->input('tempat_lahir'))) : null)
                : $karyawan->tempat_lahir;

            $namaPemilikRek = trim((string) $request->input('nama_pemilik_rekening', ''))
                ?: ($karyawan->nama_pemilik_rekening ?: $karyawan->nama_lengkap);

            $updateKaryawan = [
                'nip'                     => $finalNip,
                'jenis_kelamin'           => $request->input('jenis_kelamin', $karyawan->jenis_kelamin),
                'tempat_lahir'            => $tempatLahirVal,
                'tgl_lahir'               => $request->input('tgl_lahir', $karyawan->tgl_lahir),
                'golongan_darah'          => $request->input('golongan_darah', $karyawan->golongan_darah),
                'no_wa'                   => $noWa !== '' ? $noWa : null,
                'email'                   => $request->input('email', $karyawan->email),
                'nama_ayah'               => strtoupper($request->input('nama_ayah', $karyawan->nama_ayah ?? '')),
                'nama_ibu'                => strtoupper($request->input('nama_ibu', $karyawan->nama_ibu ?? '')),
                'status_pernikahan'       => $statusPernikahan,
                'disabilitas'             => $request->input('disabilitas', $karyawan->disabilitas),
                'tipe_karyawan'           => $request->filled('tipe_karyawan') ? $request->input('tipe_karyawan') : $karyawan->tipe_karyawan,
                'ket_karyawan'            => $request->filled('ket_karyawan') ? $request->input('ket_karyawan') : $karyawan->ket_karyawan,
                'departemen_id'           => $penempatan['departemen_id'],
                'unit_organisasi_id'      => $penempatan['unit_organisasi_id'],
                'nama_jabatan'            => $penempatan['nama_jabatan'],
                'jabatan_borong_id'       => $penempatan['jabatan_borong_id'],
                'kota_perwakilan_id'      => $penempatan['kota_perwakilan_id'],
                'area_id'                 => $penempatan['area_id'],
                'bank_id'                 => $bankId,
                'no_rekening'             => $noRek !== '' ? $noRek : null,
                'bersedia_transfer'       => $request->input('bersedia_transfer', $karyawan->bersedia_transfer),
                'nama_pemilik_rekening'   => strtoupper($namaPemilikRek),
                'no_bpjs_kesehatan'       => $request->has('no_bpjs_kesehatan') ? $request->input('no_bpjs_kesehatan') : $karyawan->no_bpjs_kesehatan,
                'no_bpjs_ketenagakerjaan' => $request->has('no_bpjs_ketenagakerjaan') ? $request->input('no_bpjs_ketenagakerjaan') : $karyawan->no_bpjs_ketenagakerjaan,
                'npwp'                    => $this->normalizeNpwp($request->input('npwp', $karyawan->npwp)),
                'no_anggota_koperasi'     => $request->input('no_anggota_koperasi', $karyawan->no_anggota_koperasi),
                'updated_at'              => now(),
            ];

            DB::table('riwayat_pendidikan')->where('karyawan_id', $karyawan->id)->delete();
            $listP = array_values(array_filter($request->input('riwayat_pendidikan', []), function ($rp) {
                return ! empty($rp['jenjang']) && ! empty($rp['nama_instansi']);
            }));

            foreach ($listP as $rp) {
                DB::table('riwayat_pendidikan')->insert([
                    'karyawan_id'   => $karyawan->id,
                    'jenjang'       => strtoupper($rp['jenjang']),
                    'nama_instansi' => strtoupper($rp['nama_instansi']),
                    'jurusan'       => ! empty($rp['jurusan']) ? strtoupper($rp['jurusan']) : null,
                    'nilai_ipk'     => ! empty($rp['nilai_ipk']) ? $rp['nilai_ipk'] : null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            if (! empty($listP)) {
                $lastPend = end($listP);
                $updateKaryawan['pendidikan_terakhir'] = strtoupper($lastPend['jenjang']);
                $updateKaryawan['nama_instansi']       = strtoupper($lastPend['nama_instansi']);
                $updateKaryawan['jurusan']             = ! empty($lastPend['jurusan']) ? strtoupper($lastPend['jurusan']) : null;
                $updateKaryawan['nilai']               = $lastPend['nilai_ipk'] ?? null;
            }

            DB::table('karyawan')->where('id', $karyawan->id)->update($updateKaryawan);

            if ($alamatDomisili !== '') {
                DB::table('alamat')->updateOrInsert(
                    ['karyawan_id' => $karyawan->id, 'jenis_alamat' => 'DOMISILI'],
                    ['alamat_lengkap' => $alamatDomisili]
                );
            }

            if ($statusPernikahan === 'KAWIN' && trim((string) $request->input('nama_pasangan', '')) !== '') {
                DB::table('pasangan')->updateOrInsert(
                    ['karyawan_id' => $karyawan->id],
                    [
                        'nama_pasangan'      => strtoupper(trim((string) $request->input('nama_pasangan'))),
                        'pekerjaan_pasangan' => trim((string) $request->input('pekerjaan_pasangan', '')) ?: '-',
                    ]
                );
            } else {
                DB::table('pasangan')->where('karyawan_id', $karyawan->id)->delete();
            }

            DB::table('anak')->where('karyawan_id', $karyawan->id)->delete();
            if ($statusPernikahan !== 'BELUM_KAWIN' && $jumlahAnak > 0) {
                for ($i = 1; $i <= $jumlahAnak; $i++) {
                    if (! empty($dataAnakRequest[$i]['nama_anak'])) {
                        DB::table('anak')->insert([
                            'karyawan_id'     => $karyawan->id,
                            'anak_ke'         => $i,
                            'nama_anak'       => strtoupper($dataAnakRequest[$i]['nama_anak']),
                            'tgl_lahir_anak'  => $dataAnakRequest[$i]['tgl_lahir_anak'] ?? null,
                            'pendidikan_anak' => $dataAnakRequest[$i]['pendidikan_anak'] ?? null,
                        ]);
                    }
                }
            }

            DB::table('riwayat_pekerjaan_sukun')->where('karyawan_id', $karyawan->id)->delete();
            foreach ($request->input('riwayat_sukun', []) as $rw) {
                if (! empty($rw['bagian'])) {
                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawan->id,
                        'unit_bagian' => strtoupper(trim($rw['bagian'])),
                        'jabatan'     => ! empty($rw['jabatan']) ? strtoupper(trim($rw['jabatan'])) : '-',
                        'tgl_mulai'   => $this->parseTahunOrDate($rw['tahun_mulai'] ?? null, false),
                        'tgl_selesai' => $this->parseTahunOrDate($rw['tahun_selesai'] ?? null, true),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            DB::table('pengalaman_kerja_sebelum')->where('karyawan_id', $karyawan->id)->delete();
            foreach ($request->input('riwayat_luar', []) as $rwl) {
                if (! empty($rwl['perusahaan'])) {
                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawan->id,
                        'nama_perusahaan' => strtoupper(trim($rwl['perusahaan'])),
                        'jabatan'         => ! empty($rwl['jabatan']) ? strtoupper(trim($rwl['jabatan'])) : '-',
                        'tahun_masuk'     => ! empty($rwl['tahun_masuk']) ? trim($rwl['tahun_masuk']) : null,
                        'tahun_keluar'    => ! empty($rwl['tahun_keluar']) ? trim($rwl['tahun_keluar']) : null,
                    ]);
                }
            }

            $this->syncStatusVerifikasi($karyawan->id);
        });

        return redirect()->route('pendataan.edit.token', $token)->with('sukses', 'Semua data berhasil diperbarui!');
    }

    public function downloadTemplate()
    {
        $this->ensureCanManageKaryawan('Anda tidak memiliki akses untuk mengunduh template import.');

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

        $maxPend  = 4;
        $maxAnak  = 5;
        $maxKarir = 3;
        $maxPeng  = 3;

        $allDivisi   = DB::table('departemen')->orderBy('id')->get()->keyBy('id');
        $masterUnits = DB::table('unit_organisasi')->orderBy('nama_unit')->get();

        $wsData = $spreadsheet->getActiveSheet();
        $wsData->setTitle('DATA');

        $dataHeaders   = $this->buildDataHeaders($maxPend, $maxAnak, $maxKarir, $maxPeng, true);
        $lastColLetter = Coordinate::stringFromColumnIndex(count($dataHeaders));

        $wsData->fromArray($dataHeaders, null, 'A1');
        $wsData->getStyle("A1:{$lastColLetter}1")->applyFromArray($headerStyle);
        $wsData->getRowDimension(1)->setRowHeight(28);
        $wsData->freezePane('C2');

        $wsMaster = $spreadsheet->createSheet();
        $wsMaster->setTitle('MASTER');
        $wsMaster->fromArray(['No', 'Divisi', 'Departemen / Unit Kerja'], null, 'A1');
        $wsMaster->getStyle('A1:C1')->applyFromArray($headerStyle);
        $wsMaster->getRowDimension(1)->setRowHeight(24);

        $rMaster = 2;
        $noM     = 1;
        foreach ($masterUnits as $u) {
            $divName = $allDivisi->get($u->departemen_id)->nama_departemen ?? '-';
            $wsMaster->setCellValue("A{$rMaster}", $noM++);
            $wsMaster->setCellValue("B{$rMaster}", $divName);
            $wsMaster->setCellValue("C{$rMaster}", $u->nama_unit);
            $rMaster++;
        }
        $lastMasterRow = max(2, $rMaster - 1);
        $wsMaster->getStyle("A2:C{$lastMasterRow}")->applyFromArray($cellBorder);
        foreach (['A' => 6, 'B' => 22, 'C' => 42] as $col => $w) {
            $wsMaster->getColumnDimension($col)->setWidth($w);
        }

        $wsInfo = $spreadsheet->createSheet();
        $wsInfo->setTitle('PETUNJUK');
        $wsInfo->setCellValue('A1', 'PETUNJUK PENGISIAN TEMPLATE IMPORT KARYAWAN');
        $wsInfo->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1F4E78');

        $petunjuk = [
            ['1. Sheet DATA', 'Isi data karyawan mulai dari baris ke-2 di sheet DATA. Jangan mengubah nama header pada baris ke-1.'],
            ['2. Kolom Wajib', 'Kolom NIK (harus 16 digit angka), Nama Lengkap, Jenis Kelamin, Tgl Lahir, Alamat KTP, Unit (untuk non-DISTRIBUSI), Tanggal Masuk PT Sukun (YYYY-MM-DD), Pernikahan (+ Pasangan jika KAWIN), Nama Ayah, Nama Ibu, Jenis Karyawan, Status Karyawan, Bersedia Transfer, dan minimal 1 Riwayat Pendidikan wajib diisi. Jika kosong/tidak valid, baris ditolak saat import.'],
            ['3. Kolom Angka & Teks', 'Kolom NIK, NIP, No HP/WA, No Rekening, BPJS, NPWP, dan No Koperasi sudah diformat sebagai TEXT (@) agar angka 0 di depan tidak hilang dan NIK tidak berubah menjadi E+15.'],
            ['4. Format Tanggal', 'Untuk Tgl Lahir dan Tanggal Masuk PT Sukun (YYYY-MM-DD), gunakan format YYYY-MM-DD (contoh: 1995-08-17) atau DD/MM/YYYY (contoh: 17/08/1995).'],
            ['5. Bank & Rekening', 'Isi Nama Bank (misal BRI / BNI), No Rekening (angka saja), dan Atas Nama Rekening. Jika Nama Bank dikosongkan, sistem mendeteksi otomatis dari panjang digit rekening (15 digit = BRI, 9-10 digit = BNI).'],
            ['6. Riwayat Pendidikan', 'Khusus jenjang pendidikan dari SD sampai SMA/SMK, kolom Jurusan dan Nilai/IPK tidak wajib diisi (boleh dikosongkan atau diisi "-").'],
            ['7. Dropdown Otomatis', 'Kolom Jenis Kelamin, Unit, Pernikahan, Nama Bank, Jenis Karyawan, Status Karyawan, Jenjang Pendidikan, dan Pendidikan Anak sudah dilengkapi dropdown.'],
        ];
        $rP = 3;
        foreach ($petunjuk as $p) {
            $wsInfo->setCellValue("A{$rP}", $p[0]);
            $wsInfo->setCellValue("B{$rP}", $p[1]);
            $wsInfo->getStyle("A{$rP}")->getFont()->setBold(true);
            $wsInfo->getStyle("B{$rP}")->getAlignment()->setWrapText(true);
            $wsInfo->getRowDimension($rP)->setRowHeight(32);
            $rP++;
        }
        $wsInfo->getColumnDimension('A')->setWidth(24);
        $wsInfo->getColumnDimension('B')->setWidth(115);

        $maxTemplateRow = 500;

        foreach (['C', 'D', 'M', 'U', 'W', 'X', 'Y', 'AI'] as $textCol) {
            $wsData->getStyle("{$textCol}2:{$textCol}{$maxTemplateRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        foreach (['G', 'Q'] as $dateCol) {
            $wsData->getStyle("{$dateCol}2:{$dateCol}{$maxTemplateRow}")
                ->getNumberFormat()
                ->setFormatCode('yyyy-mm-dd');
        }

        for ($r = 2; $r <= $maxTemplateRow; $r++) {
            $wsData->setCellValue("AC{$r}", "=IF(\$P{$r}=\"\",\"\",IFERROR(INDEX(MASTER!\$B\$2:\$B\${$lastMasterRow},MATCH(\$P{$r},MASTER!\$C\$2:\$C\${$lastMasterRow},0)),\"PERLU DICEK\"))");
            $wsData->setCellValue("AD{$r}", "=IF(\$P{$r}=\"\",\"\",IFERROR(INDEX(MASTER!\$C\$2:\$C\${$lastMasterRow},MATCH(\$P{$r},MASTER!\$C\$2:\$C\${$lastMasterRow},0)),\$P{$r}))");
        }

        $this->applySheetDataDropdowns($wsData, $lastMasterRow, $maxTemplateRow, $maxPend, $maxAnak, true);

        $baseWidths = [
            'A' => 5, 'B' => 28, 'C' => 18, 'D' => 22, 'E' => 14, 'F' => 20, 'G' => 14,
            'H' => 18, 'I' => 18, 'J' => 18, 'K' => 35, 'L' => 35, 'M' => 18,
            'N' => 28, 'O' => 25, 'P' => 28, 'Q' => 28, 'R' => 16, 'S' => 18,
            'T' => 16, 'U' => 20, 'V' => 26, 'W' => 18, 'X' => 18, 'Y' => 22,
            'Z' => 24, 'AA' => 20, 'AB' => 10, 'AC' => 18, 'AD' => 28,
            'AE' => 15, 'AF' => 16, 'AG' => 16, 'AH' => 16, 'AI' => 18,
        ];
        foreach ($baseWidths as $col => $w) {
            $wsData->getColumnDimension($col)->setWidth($w);
        }
        for ($c = 36; $c <= count($dataHeaders); $c++) {
            $wsData->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(22);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'TEMPLATE_IMPORT_KARYAWAN_PT_SWI.xlsx';
        $writer   = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = tempnam($tempDir, 'swi_tpl_');
        $writer->save($tempFile);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request)
    {
        $this->ensureCanImportExcel('Anda tidak memiliki akses untuk melakukan import data.');

        $request->validate([
            'file_excel' => 'required|file|mimes:xlsx,xls|max:10240',
        ], [
            'file_excel.required' => 'Pilih file Excel terlebih dahulu.',
            'file_excel.mimes'    => 'Format file harus .xlsx atau .xls.',
        ]);

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $spreadsheet = IOFactory::load($request->file('file_excel')->getRealPath());
        $sheet       = $spreadsheet->getSheetByName('DATA') ?: $spreadsheet->getActiveSheet();

        $highestRow    = $sheet->getHighestDataRow();
        $highestColIdx = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        if ($highestRow < 2) {
            return back()->with('import_errors', ['Sheet DATA kosong, tidak ada baris data untuk diimport.']);
        }

        $headerMap = [];
        for ($col = 1; $col <= $highestColIdx; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $rawHeader = strtoupper(trim((string) $sheet->getCell($colLetter . '1')->getValue()));
            if ($rawHeader !== '') {
                $headerMap[$rawHeader] = $colLetter;
            }
        }

        $getVal = function (int $row, array $aliases) use ($sheet, $headerMap): string {
            foreach ($aliases as $alias) {
                $key = strtoupper($alias);
                if (! isset($headerMap[$key])) {
                    continue;
                }

                $cell = $sheet->getCell($headerMap[$key] . $row);
                $raw  = $cell->getValue();

                if ($raw === null) {
                    return '';
                }

                if ($cell->isFormula()) {
                    $str = (string) $cell->getCalculatedValue();
                } elseif (is_float($raw) || is_int($raw)) {
                    $str = sprintf('%.0f', $raw);
                } else {
                    $str = (string) $raw;
                }

                $str = trim(ltrim($str, "'"));
                return ($str === '-') ? '' : $str;
            }
            return '';
        };

        $unitMap = [];
        foreach (DB::table('unit_organisasi')->get() as $u) {
            $unitMap[strtoupper(trim($u->nama_unit))] = [
                'id'            => $u->id,
                'departemen_id' => $u->departemen_id,
            ];
        }

        $deptMap = [];
        foreach (DB::table('departemen')->get() as $d) {
            $deptMap[strtoupper(trim($d->nama_departemen))] = $d->id;
        }

        $kotaMap = [];
        foreach (DB::table('kota_perwakilan')->get() as $k) {
            $kotaMap[strtoupper(trim($k->nama_kota))] = [
                'id'      => $k->id,
                'area_id' => $k->area_id ?? null,
            ];
        }

        $insertedCount = 0;
        $duplicates    = [];
        $errors        = [];

        for ($r = 2; $r <= $highestRow; $r++) {
            $nikRaw = preg_replace('/[^0-9]/', '', $getVal($r, ['NIK', 'NIK KTP']));
            $nama   = strtoupper($getVal($r, ['NAMA LENGKAP']));

            if ($nikRaw === '' && $nama === '') {
                continue;
            }

            if (strlen($nikRaw) !== 16) {
                $errors[] = "Baris {$r} ({$nama}): NIK '{$nikRaw}' tidak valid (wajib 16 digit).";
                continue;
            }

            $missingFields = [];

            if ($nama === '') {
                $missingFields[] = 'Nama Lengkap';
            }

            $jkRaw = strtoupper(trim($getVal($r, ['JENIS KELAMIN'])));
            if (! in_array($jkRaw, ['L', 'P'], true)) {
                $missingFields[] = 'Jenis Kelamin (wajib diisi L atau P)';
            }

            $tglLahirParsed = $this->normalizeExcelDate($getVal($r, ['TGL LAHIR', 'TANGGAL LAHIR']));
            if (! $tglLahirParsed) {
                $missingFields[] = 'Tgl Lahir (wajib format YYYY-MM-DD, tahun 4 digit valid)';
            }

            $alamatKtpCheck = trim($getVal($r, ['ALAMAT KTP']));
            if ($alamatKtpCheck === '') {
                $missingFields[] = 'Alamat KTP';
            }

            $namaAyahCheck = trim($getVal($r, ['NAMA AYAH']));
            if ($namaAyahCheck === '') {
                $missingFields[] = 'Nama Ayah';
            }

            $namaIbuCheck = trim($getVal($r, ['NAMA IBU']));
            if ($namaIbuCheck === '') {
                $missingFields[] = 'Nama Ibu';
            }

            $tglMasukRaw = $getVal($r, [
                'TANGGAL MASUK PT SUKUN (YYYY-MM-DD)',
                'MASUK PT SUKUN',
                'TGL MASUK',
                'TANGGAL MASUK',
            ]);
            $tglMasukHariDiketahui = true;
            $tglMasukParsed        = null;

            if (preg_match('/^\d{4}$/', $tglMasukRaw)) {
                $yr = (int) $tglMasukRaw;
                if ($yr >= 1970 && $yr <= (int) date('Y')) {
                    $tglMasukParsed        = $tglMasukRaw . '-01-01';
                    $tglMasukHariDiketahui = false;
                }
            } elseif (preg_match('/^(\d{4})-(\d{2})$/', $tglMasukRaw, $ym)) {
                $yr = (int) $ym[1];
                $mo = (int) $ym[2];
                if ($yr >= 1970 && $yr <= (int) date('Y') && $mo >= 1 && $mo <= 12) {
                    $tglMasukParsed        = $tglMasukRaw . '-01';
                    $tglMasukHariDiketahui = false;
                }
            } else {
                $tglMasukParsed = $this->normalizeExcelDate($tglMasukRaw);
            }

            if (! $tglMasukParsed) {
                $missingFields[] = 'Tanggal Masuk PT Sukun (YYYY-MM-DD)';
            }

            $tipeKaryawan = strtoupper(trim($getVal($r, ['JENIS KARYAWAN'])));
            if (! in_array($tipeKaryawan, ['BULANAN', 'HARIAN', 'BORONG', 'DISTRIBUSI'], true)) {
                $missingFields[] = 'Jenis Karyawan (wajib diisi BULANAN / HARIAN / BORONG / DISTRIBUSI)';
            }

            $ketKaryawan = str_replace(' ', '_', strtoupper(trim($getVal($r, ['STATUS KARYAWAN']))));
            if (! in_array($ketKaryawan, ['TETAP', 'HARIAN_LEPAS', 'KONTRAK'], true)) {
                $missingFields[] = 'Status Karyawan (wajib diisi TETAP / HARIAN_LEPAS / KONTRAK)';
            }

            $unitNama = strtoupper($getVal($r, ['UNIT', 'DEPARTEMEN / UNIT']));
            $divNama  = strtoupper($getVal($r, ['DIVISI']));
            $unitId   = $unitMap[$unitNama]['id'] ?? null;
            $deptId   = $unitMap[$unitNama]['departemen_id'] ?? ($deptMap[$divNama] ?? null);

            if ($tipeKaryawan !== 'DISTRIBUSI' && ! $unitId) {
                $missingFields[] = $unitNama === ''
                    ? 'Unit (wajib diisi untuk karyawan non-DISTRIBUSI)'
                    : "Unit '{$unitNama}' tidak ditemukan di sheet MASTER";
            }

            $statusNikah = str_replace(' ', '_', strtoupper(trim($getVal($r, ['PERNIKAHAN', 'STATUS PERNIKAHAN']))));
            if (! in_array($statusNikah, ['BELUM_KAWIN', 'KAWIN', 'CERAI_HIDUP', 'CERAI_MATI'], true)) {
                $missingFields[] = 'Pernikahan (wajib diisi BELUM_KAWIN / KAWIN / CERAI_HIDUP / CERAI_MATI)';
            }

            $namaPasangan = strtoupper($getVal($r, ['PASANGAN', 'NAMA PASANGAN']));
            if ($statusNikah === 'KAWIN' && $namaPasangan === '') {
                $missingFields[] = 'Pasangan (wajib diisi jika status KAWIN)';
            }

            if ($statusNikah !== '' && $statusNikah !== 'BELUM_KAWIN') {
                $jmlAnakExcel = (int) preg_replace('/[^0-9]/', '', $getVal($r, ['JML ANAK', 'JUMLAH ANAK']));
                for ($ai = 1; $ai <= max($jmlAnakExcel, 10); $ai++) {
                    $namaAnakCheck = trim($getVal($r, ["ANAK {$ai} - NAMA", "NAMA ANAK {$ai}"]));
                    $tglAnakCheck  = trim($getVal($r, ["ANAK {$ai} - TGL LAHIR", "TGL LAHIR ANAK {$ai}"]));
                    $pendAnakCheck = trim($getVal($r, ["ANAK {$ai} - PENDIDIKAN", "PENDIDIKAN ANAK {$ai}"]));

                    if (($ai <= $jmlAnakExcel || $tglAnakCheck !== '' || $pendAnakCheck !== '') && $namaAnakCheck === '') {
                        $missingFields[] = "Anak {$ai} - Nama (wajib diisi)";
                    }
                }
            }

            $bersediaTransfer = strtoupper(trim($getVal($r, ['BERSEDIA TRANSFER'])));
            if (! in_array($bersediaTransfer, ['YA', 'TIDAK'], true)) {
                $missingFields[] = 'Bersedia Transfer (wajib diisi YA / TIDAK)';
            }

            $noRekCheck = $this->normalizeRekening($getVal($r, ['NO REKENING']));
            if ($noRekCheck !== '' && preg_match('/^(?:[0-9]{9,10}|[0-9]{15})$/', $noRekCheck) !== 1) {
                $missingFields[] = 'No Rekening (jika diisi harus 9-10 digit untuk BNI atau 15 digit untuk BRI)';
            }

            $bpjsKesCheck = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $getVal($r, ['BPJS KES', 'NO BPJS KESEHATAN'])));
            $bpjsKetCheck = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $getVal($r, ['BPJS KET', 'NO BPJS KETENAGAKERJAAN'])));

            $hasPendidikanValid = false;
            for ($pi = 1; $pi <= 10; $pi++) {
                $jenjangCheck  = $this->normalizeJenjangPendidikan($getVal($r, ["PENDIDIKAN {$pi} - JENJANG"]));
                $instansiCheck = trim($getVal($r, ["PENDIDIKAN {$pi} - INSTANSI"]));
                if ($jenjangCheck !== '' && $instansiCheck !== '') {
                    $hasPendidikanValid = true;
                    break;
                }
            }
            if (! $hasPendidikanValid) {
                $missingFields[] = 'Riwayat Pendidikan (minimal 1: Jenjang + Instansi)';
            }

            if (! empty($missingFields)) {
                $errors[] = "Baris {$r} ({$nama}): data wajib belum valid/lengkap - " . implode(', ', $missingFields) . '.';
                continue;
            }

            $sudahAda = DB::table('karyawan')
                ->where('nik_ktp', $nikRaw)
                ->select('nama_lengkap', 'nip')
                ->first();

            if ($sudahAda) {
                $duplicates[] = [
                    'baris' => $r,
                    'nik'   => $nikRaw,
                    'nama'  => $nama ?: $sudahAda->nama_lengkap,
                ];
                continue;
            }

            DB::beginTransaction();
            try {
                $nipInput = $getVal($r, ['NIP']);
                if ($nipInput !== '' && DB::table('karyawan')->where('nip', $nipInput)->exists()) {
                    $nipInput = '';
                }
                $finalNip = $this->resolveNip($nipInput);

                $jk          = $jkRaw;
                $tempatLahir = strtoupper($getVal($r, ['TEMPAT LAHIR'])) ?: null;
                $tglLahir    = $tglLahirParsed;
                $golDarah    = strtoupper($getVal($r, ['GOLONGAN DARAH'])) ?: '-';

                $namaAyah       = strtoupper($getVal($r, ['NAMA AYAH']));
                $namaIbu        = strtoupper($getVal($r, ['NAMA IBU']));
                $alamatKtp      = $alamatKtpCheck;
                $alamatDomisili = $getVal($r, ['ALAMAT DOMISILI']) ?: $alamatKtp;

                $noWaRaw = preg_replace('/[^0-9+]/', '', $getVal($r, ['NO HP/WA', 'NO WA']));
                if ($noWaRaw !== '' && str_starts_with($noWaRaw, '8')) {
                    $noWaRaw = '0' . $noWaRaw;
                } elseif ($noWaRaw !== '' && str_starts_with($noWaRaw, '628')) {
                    $noWaRaw = '0' . substr($noWaRaw, 2);
                } elseif ($noWaRaw !== '' && str_starts_with($noWaRaw, '+628')) {
                    $noWaRaw = '0' . substr($noWaRaw, 3);
                }
                $noWa = $noWaRaw;

                $email       = $getVal($r, ['EMAIL']) ?: null;
                $disabilitas = $getVal($r, ['DISABILITAS']) ?: null;
                $npwp        = $this->normalizeNpwp($getVal($r, ['NPWP']));
                $jabatan     = strtoupper($getVal($r, ['JABATAN'])) ?: null;

                $kotaId = null;
                $areaId = null;
                if ($tipeKaryawan === 'DISTRIBUSI' && $unitNama !== '') {
                    $cleanKotaKey = trim(str_replace('PERWAKILAN', '', $unitNama));
                    if (isset($kotaMap[$cleanKotaKey])) {
                        $kotaId = $kotaMap[$cleanKotaKey]['id'];
                        $areaId = $kotaMap[$cleanKotaKey]['area_id'];
                    }
                }

                $subReq = new Request([
                    'tipe_karyawan'      => $tipeKaryawan,
                    'unit_organisasi_id' => $unitId,
                    'nama_jabatan'       => $jabatan,
                    'kota_perwakilan_id' => $kotaId,
                    'area_id'            => $areaId,
                ]);
                $penempatan = $this->resolvePenempatan($subReq, (object) [
                    'departemen_id'      => $deptId,
                    'unit_organisasi_id' => $unitId,
                    'nama_jabatan'       => $jabatan,
                ]);

                $noRek          = $noRekCheck;
                $namaBankRaw    = $getVal($r, ['NAMA BANK', 'BANK']);
                $bankId         = $this->resolveBankId($noRek, null, $namaBankRaw);
                $namaPemilikRek = strtoupper($getVal($r, ['ATAS NAMA REKENING', 'NAMA PEMILIK REKENING'])) ?: $nama;

                $bpjsKes = $bpjsKesCheck !== '' ? $bpjsKesCheck : null;
                $bpjsKet = $bpjsKetCheck !== '' ? $bpjsKetCheck : null;
                $noKop   = trim($getVal($r, ['NO KOPERASI KKSG', 'NO ANGGOTA KOPERASI'])) ?: null;

                $parsedPend = [];
                for ($i = 1; $i <= 10; $i++) {
                    $jenjangRaw = $getVal($r, ["PENDIDIKAN {$i} - JENJANG"]);
                    $jenjang    = $this->normalizeJenjangPendidikan($jenjangRaw);
                    $instansi   = strtoupper($getVal($r, ["PENDIDIKAN {$i} - INSTANSI"]));
                    $jurusan    = strtoupper($getVal($r, ["PENDIDIKAN {$i} - JURUSAN"])) ?: null;
                    $nilai      = $getVal($r, ["PENDIDIKAN {$i} - NILAI"]) ?: null;

                    if ($jenjang !== '' && $instansi !== '') {
                        $parsedPend[] = [
                            'jenjang'       => $jenjang,
                            'nama_instansi' => $instansi,
                            'jurusan'       => $jurusan,
                            'nilai_ipk'     => $nilai,
                        ];
                    }
                }

                $pendTerakhir = $this->normalizeJenjangPendidikan($getVal($r, ['PENDIDIKAN TERAKHIR']));
                $lastPend     = ! empty($parsedPend) ? end($parsedPend) : null;

                $karyawanId = DB::table('karyawan')->insertGetId([
                    'token'                    => bin2hex(random_bytes(32)),
                    'nip'                      => $finalNip,
                    'nik_ktp'                  => $nikRaw,
                    'nama_lengkap'             => $nama,
                    'jenis_kelamin'            => $jk,
                    'tempat_lahir'             => $tempatLahir,
                    'tgl_lahir'                => $tglLahir,
                    'golongan_darah'           => $golDarah,
                    'pendidikan_terakhir'      => $lastPend['jenjang'] ?? ($pendTerakhir ?: 'SD'),
                    'jurusan'                  => $lastPend['jurusan'] ?? null,
                    'nama_instansi'            => $lastPend['nama_instansi'] ?? null,
                    'nilai'                    => $lastPend['nilai_ipk'] ?? null,
                    'no_wa'                    => $noWa !== '' ? $noWa : null,
                    'email'                    => $email,
                    'nama_ayah'                => $namaAyah,
                    'nama_ibu'                 => $namaIbu,
                    'status_pernikahan'        => $statusNikah,
                    'disabilitas'              => $disabilitas,
                    'tipe_karyawan'            => $tipeKaryawan,
                    'ket_karyawan'             => $ketKaryawan,
                    'departemen_id'            => $penempatan['departemen_id'],
                    'unit_organisasi_id'       => $penempatan['unit_organisasi_id'],
                    'nama_jabatan'             => $penempatan['nama_jabatan'],
                    'jabatan_borong_id'        => $penempatan['jabatan_borong_id'],
                    'kota_perwakilan_id'       => $penempatan['kota_perwakilan_id'],
                    'area_id'                  => $penempatan['area_id'],
                    'tgl_masuk_kerja'          => $tglMasukParsed,
                    'tgl_masuk_hari_diketahui' => $tglMasukHariDiketahui,
                    'bank_id'                  => $bankId,
                    'no_rekening'              => $noRek !== '' ? $noRek : null,
                    'bersedia_transfer'        => $bersediaTransfer,
                    'nama_pemilik_rekening'    => $namaPemilikRek,
                    'no_bpjs_kesehatan'        => $bpjsKes,
                    'no_bpjs_ketenagakerjaan'  => $bpjsKet,
                    'npwp'                     => $npwp,
                    'no_anggota_koperasi'      => $noKop,
                    'status_verifikasi'        => 'BELUM_LENGKAP',
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ]);

                foreach ($parsedPend as $p) {
                    DB::table('riwayat_pendidikan')->insert([
                        'karyawan_id'   => $karyawanId,
                        'jenjang'       => $p['jenjang'],
                        'nama_instansi' => $p['nama_instansi'],
                        'jurusan'       => $p['jurusan'],
                        'nilai_ipk'     => $p['nilai_ipk'],
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }

                DB::table('alamat')->insert([
                    ['karyawan_id' => $karyawanId, 'jenis_alamat' => 'KTP', 'alamat_lengkap' => $alamatKtp],
                    ['karyawan_id' => $karyawanId, 'jenis_alamat' => 'DOMISILI', 'alamat_lengkap' => $alamatDomisili],
                ]);

                if ($statusNikah === 'KAWIN' && $namaPasangan !== '') {
                    DB::table('pasangan')->insert([
                        'karyawan_id'        => $karyawanId,
                        'nama_pasangan'      => $namaPasangan,
                        'pekerjaan_pasangan' => $getVal($r, ['PEKERJAAN PASANGAN']) ?: '-',
                    ]);
                }

                if ($statusNikah !== 'BELUM_KAWIN') {
                    $anakKe = 1;
                    for ($i = 1; $i <= 10; $i++) {
                        $namaAnak = strtoupper($getVal($r, ["ANAK {$i} - NAMA", "NAMA ANAK {$i}"]));
                        if ($namaAnak === '') {
                            continue;
                        }
                        $tglAnak  = $this->normalizeExcelDate($getVal($r, ["ANAK {$i} - TGL LAHIR", "TGL LAHIR ANAK {$i}"]));
                        $pendAnak = $this->normalizePendidikanAnak($getVal($r, ["ANAK {$i} - PENDIDIKAN", "PENDIDIKAN ANAK {$i}"]));

                        DB::table('anak')->insert([
                            'karyawan_id'     => $karyawanId,
                            'anak_ke'         => $anakKe++,
                            'nama_anak'       => $namaAnak,
                            'tgl_lahir_anak'  => $tglAnak,
                            'pendidikan_anak' => $pendAnak,
                        ]);
                    }
                }

                for ($i = 1; $i <= 10; $i++) {
                    $bagian = strtoupper($getVal($r, ["KARIR SUKUN {$i} - BAGIAN"]));
                    if ($bagian === '') {
                        continue;
                    }
                    $jabatanKarir = strtoupper($getVal($r, ["KARIR SUKUN {$i} - JABATAN"])) ?: '-';
                    $thnMulai     = $getVal($r, ["KARIR SUKUN {$i} - TAHUN MULAI"]);
                    $thnSelesai   = $getVal($r, ["KARIR SUKUN {$i} - TAHUN SELESAI"]);

                    DB::table('riwayat_pekerjaan_sukun')->insert([
                        'karyawan_id' => $karyawanId,
                        'unit_bagian' => $bagian,
                        'jabatan'     => $jabatanKarir,
                        'tgl_mulai'   => $this->parseTahunOrDate($thnMulai, false),
                        'tgl_selesai' => $this->parseTahunOrDate($thnSelesai, true),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                for ($i = 1; $i <= 10; $i++) {
                    $perusahaan = strtoupper($getVal($r, [
                        "PENGALAMAN LUAR {$i} - PERUSAHAAN",
                        "PENGALAMAN {$i} - PERUSAHAAN",
                    ]));
                    if ($perusahaan === '') {
                        continue;
                    }
                    $jabatanPeng = strtoupper($getVal($r, [
                        "PENGALAMAN LUAR {$i} - JABATAN",
                        "PENGALAMAN {$i} - JABATAN",
                    ])) ?: '-';
                    $thnMasuk = $getVal($r, [
                        "PENGALAMAN LUAR {$i} - TAHUN MASUK",
                        "PENGALAMAN {$i} - TAHUN MASUK",
                    ]) ?: null;
                    $thnKeluar = $getVal($r, [
                        "PENGALAMAN LUAR {$i} - TAHUN KELUAR",
                        "PENGALAMAN {$i} - TAHUN KELUAR",
                    ]) ?: null;

                    DB::table('pengalaman_kerja_sebelum')->insert([
                        'karyawan_id'     => $karyawanId,
                        'nama_perusahaan' => $perusahaan,
                        'jabatan'         => $jabatanPeng,
                        'tahun_masuk'     => $thnMasuk,
                        'tahun_keluar'    => $thnKeluar,
                    ]);
                }

                $this->syncStatusVerifikasi($karyawanId);

                DB::commit();
                $insertedCount++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = "Baris {$r} ({$nama}): Gagal disimpan ({$e->getMessage()}).";
            }
        }

        unset($spreadsheet);

        return back()->with([
            'import_sukses'     => "Import selesai: {$insertedCount} data karyawan baru berhasil masuk.",
            'import_duplicates' => $duplicates,
            'import_errors'     => $errors,
        ]);
    }

    private function normalizeExcelDate(string $raw): ?string
    {
        $clean = trim($raw);
        if ($clean === '' || $clean === '-') {
            return null;
        }

        $parsed = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
            $parsed = $clean;
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $clean, $m)) {
            $parsed = "{$m[3]}-{$m[2]}-{$m[1]}";
        } elseif (is_numeric($clean)) {
            try {
                $parsed = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $clean)->format('Y-m-d');
            } catch (\Throwable $th) {
                return null;
            }
        } else {
            $ts     = strtotime($clean);
            $parsed = $ts ? date('Y-m-d', $ts) : null;
        }

        if (! $parsed || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $parsed, $parts)) {
            return null;
        }

        $year  = (int) $parts[1];
        $month = (int) $parts[2];
        $day   = (int) $parts[3];

        if ($year < 1940 || $year > (int) date('Y') || ! checkdate($month, $day, $year)) {
            return null;
        }

        return $parsed;
    }

    private function exportExcel($dataExport, array $filterMeta = [])
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

        $allDivisi    = DB::table('departemen')->orderBy('id')->get();
        $allUnits     = DB::table('unit_organisasi')->orderBy('nama_unit')->get();
        $sortedExport = $dataExport->sortBy('id')->values();

        $masterUnits = $allUnits;
        $divisiList  = $allDivisi;

        if ($filterMeta['allowed_unit_ids'] !== null) {
            $effectiveUnitIds = collect($filterMeta['allowed_unit_ids'])
                ->merge($sortedExport->pluck('unit_organisasi_id')->filter())
                ->unique()
                ->values()
                ->toArray();

            $masterUnits = $allUnits->whereIn('id', $effectiveUnitIds)->values();
            $divisiList  = $allDivisi->whereIn('id', $masterUnits->pluck('departemen_id')->unique())->values();
        }

        if (! empty($filterMeta['departemen'])) {
            $divisiList  = $divisiList->where('nama_departemen', $filterMeta['departemen'])->values();
            $masterUnits = $masterUnits->whereIn('departemen_id', $divisiList->pluck('id'))->values();
        }

        if (! empty($filterMeta['unit_id'])) {
            $masterUnits = $masterUnits->where('id', $filterMeta['unit_id'])->values();
            $divisiList  = $divisiList->whereIn('id', $masterUnits->pluck('departemen_id'))->values();
        } elseif (! empty($filterMeta['unit_name'])) {
            $masterUnits = $masterUnits->where('nama_unit', $filterMeta['unit_name'])->values();
            $divisiList  = $divisiList->whereIn('id', $masterUnits->pluck('departemen_id'))->values();
        }

        if (! empty($filterMeta['has_filter'])
            && empty($filterMeta['departemen'])
            && empty($filterMeta['unit_id'])
            && empty($filterMeta['unit_name'])) {
            $filteredUnitIds = $sortedExport->pluck('unit_organisasi_id')->filter()->unique();
            $masterUnits     = $masterUnits->whereIn('id', $filteredUnitIds)->values();
            $divisiList      = $divisiList->whereIn('id', $masterUnits->pluck('departemen_id')->unique())->values();
        }

        $lastMasterRow = max(2, $masterUnits->count() + 1);
        $lastDataRow   = max(2, $sortedExport->count() + 1);

        $ids = $sortedExport->pluck('id')->toArray();

        $alamatMap = DB::table('alamat')
            ->whereIn('karyawan_id', $ids)
            ->select('karyawan_id', 'jenis_alamat', 'alamat_lengkap')
            ->get()
            ->groupBy('karyawan_id');

        $pasanganMap = DB::table('pasangan')
            ->whereIn('karyawan_id', $ids)
            ->select('karyawan_id', 'nama_pasangan', 'pekerjaan_pasangan')
            ->get()
            ->keyBy('karyawan_id');

        $anakMap = DB::table('anak')
            ->whereIn('karyawan_id', $ids)
            ->orderBy('anak_ke')
            ->get()
            ->groupBy('karyawan_id');

        $pendidikanMap = DB::table('riwayat_pendidikan')
            ->whereIn('karyawan_id', $ids)
            ->orderBy('id')
            ->get()
            ->groupBy('karyawan_id');

        $sukunMap = DB::table('riwayat_pekerjaan_sukun')
            ->whereIn('karyawan_id', $ids)
            ->orderBy('id')
            ->get()
            ->groupBy('karyawan_id');

        $pengalamanMap = DB::table('pengalaman_kerja_sebelum')
            ->whereIn('karyawan_id', $ids)
            ->orderBy('id')
            ->get()
            ->groupBy('karyawan_id');

        $wsDash = $spreadsheet->getActiveSheet();
        $wsDash->setTitle('DASHBOARD');

        $wsDash->setCellValue('A1', 'DASHBOARD REKAP PENDATAAN KARYAWAN PT SUKUN KUDUS');
        $wsDash->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1F4E78');
        $wsDash->getRowDimension(1)->setRowHeight(18.5);

        $filterDesc = ! empty($filterMeta['labels'])
            ? '  |  Filter aktif: ' . implode(', ', $filterMeta['labels'])
            : '';
        $wsDash->setCellValue('A2', 'Update terakhir: ' . now()->format('d-m-Y') . '  |  Sumber: export Sistem Pendataan HRD (' . $sortedExport->count() . ' respons)' . $filterDesc);
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

        $rowDash    = 7;
        $noDiv      = 1;
        $divCount   = $divisiList->count();
        $totDashRow = 7 + $divCount;

        foreach ($divisiList as $div) {
            $wsDash->setCellValue("A{$rowDash}", $noDiv++);
            $wsDash->setCellValue("B{$rowDash}", $div->nama_departemen);
            $wsDash->setCellValue("C{$rowDash}", "=COUNTIF(MASTER!\$B\$2:\$B\${$lastMasterRow},\$B{$rowDash})");
            $wsDash->setCellValue("D{$rowDash}", "=COUNTIF(DATA!\$AC\$2:\$AC\${$lastDataRow},\$B{$rowDash})");
            $wsDash->setCellValue("E{$rowDash}", "=IF(\$D\${$totDashRow}=0,0,\$D{$rowDash}/\$D\${$totDashRow})");
            $wsDash->setCellValue("F{$rowDash}", "=COUNTIFS(MASTER!\$B\$2:\$B\${$lastMasterRow},\$B{$rowDash},MASTER!\$E\$2:\$E\${$lastMasterRow},\">0\")");
            $wsDash->setCellValue("G{$rowDash}", "=COUNTIFS(MASTER!\$B\$2:\$B\${$lastMasterRow},\$B{$rowDash},MASTER!\$E\$2:\$E\${$lastMasterRow},0)");
            $rowDash++;
        }

        $lastDivRow = max(7, $rowDash - 1);
        $wsDash->setCellValue("B{$totDashRow}", 'TOTAL');
        if ($divCount > 0) {
            $wsDash->setCellValue("C{$totDashRow}", "=SUM(C7:C{$lastDivRow})");
            $wsDash->setCellValue("D{$totDashRow}", "=SUM(D7:D{$lastDivRow})");
            $wsDash->setCellValue("E{$totDashRow}", "=SUM(E7:E{$lastDivRow})");
            $wsDash->setCellValue("F{$totDashRow}", "=SUM(F7:F{$lastDivRow})");
            $wsDash->setCellValue("G{$totDashRow}", "=SUM(G7:G{$lastDivRow})");
        } else {
            $wsDash->fromArray([0, 0, 0, 0, 0], null, "C{$totDashRow}");
        }

        $wsDash->getStyle("E7:E{$totDashRow}")->getNumberFormat()->setFormatCode('0.0%');
        $wsDash->getStyle("A7:G{$totDashRow}")->applyFromArray($cellBorder);
        $wsDash->getStyle("B{$totDashRow}:D{$totDashRow}")->getFont()->setBold(true);
        $wsDash->getStyle("F{$totDashRow}:G{$totDashRow}")->getFont()->setBold(true);

        foreach (['A' => 5, 'B' => 20, 'C' => 16, 'D' => 12, 'E' => 16, 'F' => 18, 'G' => 22, 'H' => 3, 'I' => 28, 'J' => 14] as $col => $w) {
            $wsDash->getColumnDimension($col)->setWidth($w);
        }

        $wsMaster = $spreadsheet->createSheet();
        $wsMaster->setTitle('MASTER');
        $masterHeaders = ['No', 'Divisi', 'Departemen / Unit Kerja', 'Target Karyawan (isi manual)', 'Sudah Isi', 'Belum Isi', '% Isi', 'Catatan'];
        $wsMaster->fromArray($masterHeaders, null, 'A1');
        $wsMaster->getStyle('A1:H1')->applyFromArray($headerStyle);
        $wsMaster->getRowDimension(1)->setRowHeight(29);

        $rowMaster = 2;
        $noMaster  = 1;
        foreach ($divisiList as $div) {
            $unitsInDiv = $masterUnits->where('departemen_id', $div->id);
            foreach ($unitsInDiv as $u) {
                $wsMaster->setCellValue("A{$rowMaster}", $noMaster++);
                $wsMaster->setCellValue("B{$rowMaster}", $div->nama_departemen);
                $wsMaster->setCellValue("C{$rowMaster}", $u->nama_unit);
                $wsMaster->setCellValue("D{$rowMaster}", null);
                $wsMaster->setCellValue("E{$rowMaster}", "=COUNTIF(DATA!\$P\$2:\$P\${$lastDataRow},\$C{$rowMaster})");
                $wsMaster->setCellValue("F{$rowMaster}", "=IF(\$D{$rowMaster}=\"\",\"\",\$D{$rowMaster}-\$E{$rowMaster})");
                $wsMaster->setCellValue("G{$rowMaster}", "=IF(OR(\$D{$rowMaster}=\"\",\$D{$rowMaster}=0),\"\",\$E{$rowMaster}/\$D{$rowMaster})");
                $rowMaster++;
            }
        }

        if ($rowMaster > 2) {
            $endMasterRow = $rowMaster - 1;
            $wsMaster->getStyle("G2:G{$endMasterRow}")->getNumberFormat()->setFormatCode('0.0%');
            $wsMaster->getStyle("A2:H{$endMasterRow}")->applyFromArray($cellBorder);
        }

        foreach (['A' => 6, 'B' => 18, 'C' => 40, 'D' => 18, 'E' => 10, 'F' => 12, 'G' => 8, 'H' => 40] as $col => $w) {
            $wsMaster->getColumnDimension($col)->setWidth($w);
        }

        $kpiItems = [
            ['Total respons masuk', "=COUNTA(DATA!\$B\$2:\$B\${$lastDataRow})"],
            ['Total departemen (master)', "=COUNTA(MASTER!\$C\$2:\$C\${$lastMasterRow})"],
            ['Departemen sudah ada isian', "=COUNTIF(MASTER!\$E\$2:\$E\${$lastMasterRow},\">0\")"],
            ['Departemen belum ada isian', "=COUNTIF(MASTER!\$E\$2:\$E\${$lastMasterRow},0)"],
            ['% departemen terisi', "=IF(J6=0,0,J7/J6)"],
        ];
        $rKpi = 5;
        foreach ($kpiItems as $kpi) {
            $wsDash->setCellValue("I{$rKpi}", $kpi[0]);
            $wsDash->setCellValue("J{$rKpi}", $kpi[1]);
            $rKpi++;
        }
        $wsDash->getStyle('I5:J9')->applyFromArray($cellBorder);
        $wsDash->getStyle('J5:J9')->getFont()->setBold(true);
        $wsDash->getStyle('J9')->getNumberFormat()->setFormatCode('0.0%');

        if ($divCount > 0) {
            $chartStartRow = $totDashRow + 3;
            $chartEndRow   = $chartStartRow + 15;

            $barLabels     = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'DASHBOARD!$D$6', null, 1)];
            $barCategories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'DASHBOARD!$B$7:$B$' . $lastDivRow, null, $divCount)];
            $barValues     = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'DASHBOARD!$D$7:$D$' . $lastDivRow, null, $divCount)];

            $barSeries = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($barValues) - 1), $barLabels, $barCategories, $barValues);
            $barSeries->setPlotDirection(DataSeries::DIRECTION_COL);

            $barChart = new Chart('chart_bar_divisi', new Title('Jumlah Karyawan Sudah Isi per Divisi'), null, new PlotArea(null, [$barSeries]), true, DataSeries::EMPTY_AS_GAP, new Title('Divisi'), new Title('Jumlah'));
            $barChart->setTopLeftPosition('A' . $chartStartRow);
            $barChart->setBottomRightPosition('H' . $chartEndRow);
            $wsDash->addChart($barChart);

            $pieCategories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'DASHBOARD!$B$7:$B$' . $lastDivRow, null, $divCount)];
            $pieValues     = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'DASHBOARD!$D$7:$D$' . $lastDivRow, null, $divCount)];

            $pieSeries = new DataSeries(DataSeries::TYPE_PIECHART, null, range(0, count($pieValues) - 1), [], $pieCategories, $pieValues);
            $pieChart  = new Chart('chart_pie_divisi', new Title('Porsi Isian per Divisi'), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, [$pieSeries]), true, DataSeries::EMPTY_AS_GAP);
            $pieChart->setTopLeftPosition('I' . $chartStartRow);
            $pieChart->setBottomRightPosition('N' . $chartEndRow);
            $wsDash->addChart($pieChart);
        }

        $wsData = $spreadsheet->createSheet();
        $wsData->setTitle('DATA');

        $maxPend  = 4;
        $maxAnak  = 5;
        $maxKarir = 3;
        $maxPeng  = 3;

        $dataHeaders   = $this->buildDataHeaders($maxPend, $maxAnak, $maxKarir, $maxPeng, false);
        $lastColLetter = Coordinate::stringFromColumnIndex(count($dataHeaders));

        $wsData->fromArray($dataHeaders, null, 'A1');
        $wsData->getStyle("A1:{$lastColLetter}1")->applyFromArray($headerStyle);
        $wsData->getRowDimension(1)->setRowHeight(28);

        $rowData = 2;
        $noData  = 1;
        foreach ($sortedExport as $emp) {
            $alList      = $alamatMap->get($emp->id) ?? collect();
            $alKtp       = optional($alList->firstWhere('jenis_alamat', 'KTP'))->alamat_lengkap ?? '-';
            $alDom       = optional($alList->firstWhere('jenis_alamat', 'DOMISILI'))->alamat_lengkap ?? '-';
            $pas         = $pasanganMap->get($emp->id);
            $tempatLahir = trim((string) ($emp->tempat_lahir ?? '')) ?: '-';

            $pendList  = ($pendidikanMap->get($emp->id) ?? collect())->values();
            $anakList  = ($anakMap->get($emp->id) ?? collect())->values();
            $sukunList = ($sukunMap->get($emp->id) ?? collect())->values();
            $pengList  = ($pengalamanMap->get($emp->id) ?? collect())->values();

            $wsData->setCellValue("A{$rowData}", (string) $noData++);
            $wsData->setCellValue("B{$rowData}", $emp->nama_lengkap);
            $wsData->setCellValueExplicit("C{$rowData}", (string) ($emp->nip ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValueExplicit("D{$rowData}", (string) $emp->nik_ktp, DataType::TYPE_STRING);
            $wsData->setCellValue("E{$rowData}", $emp->jenis_kelamin);
            $wsData->setCellValue("F{$rowData}", $tempatLahir);
            $wsData->setCellValue("G{$rowData}", $emp->tgl_lahir ?? '-');
            $wsData->setCellValue("H{$rowData}", $emp->pendidikan_terakhir ?? '-');
            $wsData->setCellValue("I{$rowData}", $emp->nama_ayah ?? '-');
            $wsData->setCellValue("J{$rowData}", $emp->nama_ibu ?? '-');
            $wsData->setCellValue("K{$rowData}", $alKtp);
            $wsData->setCellValue("L{$rowData}", $alDom);
            $wsData->setCellValueExplicit("M{$rowData}", (string) ($emp->no_wa ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValue("N{$rowData}", $emp->email ?? '-');
            $wsData->setCellValue("O{$rowData}", $emp->nama_jabatan ?? '-');
            $wsData->setCellValue("P{$rowData}", $emp->nama_unit ?? '-');
            $wsData->setCellValue("Q{$rowData}", $emp->tgl_masuk_kerja);
            $wsData->setCellValue("R{$rowData}", $emp->status_pernikahan);
            $wsData->setCellValue("S{$rowData}", $emp->disabilitas ?? '-');
            $wsData->setCellValue("T{$rowData}", $emp->nama_bank ?? '-');
            $wsData->setCellValueExplicit("U{$rowData}", (string) ($emp->no_rekening ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValue("V{$rowData}", $emp->nama_pemilik_rekening ?? '-');
            $wsData->setCellValueExplicit("W{$rowData}", (string) ($emp->no_bpjs_kesehatan ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValueExplicit("X{$rowData}", (string) ($emp->no_bpjs_ketenagakerjaan ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValueExplicit("Y{$rowData}", (string) ($emp->npwp ?? '-'), DataType::TYPE_STRING);
            $wsData->setCellValue("Z{$rowData}", $pas->nama_pasangan ?? '-');
            $wsData->setCellValue("AA{$rowData}", $pas->pekerjaan_pasangan ?? '-');
            $wsData->setCellValue("AB{$rowData}", (string) $anakList->count());
            $wsData->setCellValue("AC{$rowData}", "=IF(\$P{$rowData}=\"\",\"\",IFERROR(INDEX(MASTER!\$B\$2:\$B\${$lastMasterRow},MATCH(\$P{$rowData},MASTER!\$C\$2:\$C\${$lastMasterRow},0)),\"PERLU DICEK\"))");
            $wsData->setCellValue("AD{$rowData}", "=IF(\$P{$rowData}=\"\",\"\",IFERROR(INDEX(MASTER!\$C\$2:\$C\${$lastMasterRow},MATCH(\$P{$rowData},MASTER!\$C\$2:\$C\${$lastMasterRow},0)),\$P{$rowData}))");
            $wsData->setCellValue("AE{$rowData}", $emp->status_verifikasi ?? '-');
            $wsData->setCellValue("AF{$rowData}", $emp->created_at ?? '-');
            $wsData->setCellValue("AG{$rowData}", $emp->golongan_darah ?? '-');
            $wsData->setCellValue("AH{$rowData}", $emp->tipe_karyawan ?? '-');
            $wsData->setCellValue("AI{$rowData}", $emp->ket_karyawan ?? '-');
            $wsData->setCellValue("AJ{$rowData}", $emp->bersedia_transfer ?? '-');
            $wsData->setCellValueExplicit("AK{$rowData}", (string) ($emp->no_anggota_koperasi ?? '-'), DataType::TYPE_STRING);

            $colIdx = 38;

            for ($i = 0; $i < $maxPend; $i++) {
                $p = $pendList->get($i);
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $p->jenjang ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $p->nama_instansi ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $p->jurusan ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $p->nilai_ipk ?? '-');
            }

            for ($i = 0; $i < $maxAnak; $i++) {
                $a = $anakList->get($i);
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $a->nama_anak ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $a->tgl_lahir_anak ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $a->pendidikan_anak ?? '-');
            }

            for ($i = 0; $i < $maxKarir; $i++) {
                $s = $sukunList->get($i);
                $mulai   = ($s && $s->tgl_mulai) ? substr($s->tgl_mulai, 0, 4) : '-';
                $selesai = ($s && $s->tgl_selesai) ? substr($s->tgl_selesai, 0, 4) : ($s ? 'Sekarang' : '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $s->unit_bagian ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $s->jabatan ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $mulai);
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $selesai);
            }

            for ($i = 0; $i < $maxPeng; $i++) {
                $pg = $pengList->get($i);
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $pg->nama_perusahaan ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $pg->jabatan ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $pg->tahun_masuk ?? '-');
                $wsData->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowData, $pg->tahun_keluar ?? '-');
            }

            $rowData++;
        }

        unset($alamatMap, $pasanganMap, $anakMap, $pendidikanMap, $sukunMap, $pengalamanMap);

        $maxExportDropdownRow = max(500, $rowData + 100);
        foreach (['C', 'D', 'M', 'U', 'W', 'X', 'Y', 'AK'] as $textCol) {
            $wsData->getStyle("{$textCol}2:{$textCol}{$maxExportDropdownRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $this->applySheetDataDropdowns($wsData, $lastMasterRow, $maxExportDropdownRow, $maxPend, $maxAnak, false);

        $baseWidths = [
            'A' => 5, 'B' => 28, 'C' => 18, 'D' => 22, 'E' => 14, 'F' => 20, 'G' => 14,
            'H' => 18, 'I' => 18, 'J' => 18, 'K' => 35, 'L' => 35, 'M' => 18,
            'N' => 28, 'O' => 30, 'P' => 28, 'Q' => 28, 'R' => 16, 'S' => 20,
            'T' => 16, 'U' => 20, 'V' => 26, 'W' => 18, 'X' => 18, 'Y' => 22,
            'Z' => 24, 'AA' => 20, 'AB' => 10, 'AC' => 18, 'AD' => 28,
            'AE' => 18, 'AF' => 20, 'AG' => 15, 'AH' => 16, 'AI' => 16, 'AJ' => 16, 'AK' => 18,
        ];
        foreach ($baseWidths as $col => $w) {
            $wsData->getColumnDimension($col)->setWidth($w);
        }
        for ($c = 38; $c <= count($dataHeaders); $c++) {
            $wsData->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(22);
        }

        $wsCat = $spreadsheet->createSheet();
        $wsCat->setTitle('CATATAN');
        $wsCat->setCellValue('A1', 'PETUNJUK PENGGUNAAN & TEMPLATE IMPORT');
        $wsCat->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1F4E78');
        $wsCat->getRowDimension(1)->setRowHeight(15.5);

        $notes = [
            ['1', "Jika menambah data baru secara manual di sheet DATA, salin (drag) rumus pada kolom 'Divisi' (AC) & 'Departemen / Unit' (AD) dari baris terakhir agar pemetaan unit otomatis."],
            ['2', 'Sheet DATA pada file ini dapat langsung digunakan sebagai template Import Excel. Setiap data pendidikan, anak, karir Sukun, dan pengalaman luar sudah dipisah per kolom dan dilengkapi dropdown.'],
            ['3', 'Khusus jenjang pendidikan dari SD sampai SMA/SMK, kolom Jurusan dan Nilai/IPK tidak wajib diisi (boleh dikosongkan atau diisi "-").'],
            ['4', 'Data dengan NIK yang sudah terdaftar akan otomatis dilewati dengan notifikasi duplikat saat proses Import Excel.'],
            ['5', "Isi kolom 'Target Karyawan' di sheet MASTER (khususnya PRODUKSI) agar kolom Belum Isi & % Isi terisi."],
            ['6', 'File ini di-export langsung dari sistem pendataan karyawan PT. Sukun Wartono Indonesia.'],
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

        $usedSheetNames = ['DASHBOARD', 'MASTER', 'DATA', 'CATATAN'];
        $exportByDiv    = $sortedExport->groupBy('departemen_id');

        $divisiSheetList = $divisiList->values();
        if (! empty($filterMeta['has_filter'])
            && empty($filterMeta['departemen'])
            && empty($filterMeta['unit_id'])
            && empty($filterMeta['unit_name'])) {
            $activeDeptIds   = $sortedExport->pluck('departemen_id')->filter()->unique();
            $divisiSheetList = $divisiSheetList->whereIn('id', $activeDeptIds)->values();
        }

        foreach ($divisiSheetList as $div) {
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

            $unitsInDiv = $masterUnits->where('departemen_id', $div->id)->values();
            if (! empty($filterMeta['unit_id'])) {
                $unitsInDiv = $unitsInDiv->where('id', $filterMeta['unit_id'])->values();
            } elseif (! empty($filterMeta['unit_name'])) {
                $unitsInDiv = $unitsInDiv->where('nama_unit', $filterMeta['unit_name'])->values();
            }

            $rUnit = 5;
            $noU   = 1;
            foreach ($unitsInDiv as $u) {
                $wsDiv->setCellValue("A{$rUnit}", $noU++);
                $wsDiv->setCellValue("B{$rUnit}", $u->nama_unit);
                $wsDiv->setCellValue("C{$rUnit}", "=IFERROR(INDEX(MASTER!\$D\$2:\$D\${$lastMasterRow},MATCH(\$B{$rUnit},MASTER!\$C\$2:\$C\${$lastMasterRow},0)),\"\")");
                $wsDiv->setCellValue("D{$rUnit}", "=COUNTIF(DATA!\$P\$2:\$P\${$lastDataRow},\$B{$rUnit})");
                $wsDiv->setCellValue("E{$rUnit}", "=IF(OR(\$C{$rUnit}=\"\",\$C{$rUnit}=0),\"\",\$C{$rUnit}-\$D{$rUnit})");
                $wsDiv->setCellValue("F{$rUnit}", "=IF(OR(\$C{$rUnit}=\"\",\$C{$rUnit}=0),\"\",\$D{$rUnit}/\$C{$rUnit})");
                $wsDiv->setCellValue("G{$rUnit}", "=IF(\$D{$rUnit}=0,\"BELUM ADA YANG ISI\",IF(\$E{$rUnit}=\"\",\"SUDAH ADA ISIAN\",IF(\$E{$rUnit}>0,\"ADA YANG BELUM ISI\",\"LENGKAP\")))");
                $rUnit++;
            }

            $endUnitRow = max(5, $rUnit - 1);
            if ($unitsInDiv->count() > 0) {
                $wsDiv->getStyle("F5:F{$endUnitRow}")->getNumberFormat()->setFormatCode('0.0%');
                $wsDiv->getStyle("A5:G{$endUnitRow}")->applyFromArray($cellBorder);
            }

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

            $empsInDiv  = ($exportByDiv->get($div->id) ?? collect())->sortBy(['nama_unit', 'nama_lengkap'])->values();
            $rListTitle = $totUnitRow + 2;
            $wsDiv->setCellValue("A{$rListTitle}", 'Daftar yang sudah isi (' . $empsInDiv->count() . ' karyawan)');
            $wsDiv->getStyle("A{$rListTitle}")->getFont()->setBold(true)->setSize(12);

            $rListHead = $rListTitle + 1;
            $divListHeaders = ['No', 'Nama Lengkap', 'NIP', 'NIK', 'Tempat Lahir', 'Tgl Lahir', 'Jabatan', 'Unit (form)', 'Tanggal Masuk PT Sukun (YYYY-MM-DD)', 'Pernikahan', 'No HP/WA'];
            $wsDiv->fromArray($divListHeaders, null, "A{$rListHead}");
            $wsDiv->getStyle("A{$rListHead}:K{$rListHead}")->applyFromArray($headerStyle);
            $wsDiv->getRowDimension($rListHead)->setRowHeight(24);

            $rEmpStart = $rListHead + 1;
            $rEmp      = $rEmpStart;
            $noEmp     = 1;
            foreach ($empsInDiv as $e) {
                $tempatLahirEmp = trim((string) ($e->tempat_lahir ?? '')) ?: '-';

                $wsDiv->setCellValue("A{$rEmp}", $noEmp++);
                $wsDiv->setCellValue("B{$rEmp}", $e->nama_lengkap);
                $wsDiv->setCellValueExplicit("C{$rEmp}", (string) ($e->nip ?? '-'), DataType::TYPE_STRING);
                $wsDiv->setCellValueExplicit("D{$rEmp}", (string) $e->nik_ktp, DataType::TYPE_STRING);
                $wsDiv->setCellValue("E{$rEmp}", $tempatLahirEmp);
                $wsDiv->setCellValue("F{$rEmp}", $e->tgl_lahir ?? '-');
                $wsDiv->setCellValue("G{$rEmp}", $e->nama_jabatan ?? '-');
                $wsDiv->setCellValue("H{$rEmp}", $e->nama_unit ?? '-');
                $wsDiv->setCellValue("I{$rEmp}", $e->tgl_masuk_kerja);
                $wsDiv->setCellValue("J{$rEmp}", $e->status_pernikahan);
                $wsDiv->setCellValueExplicit("K{$rEmp}", (string) ($e->no_wa ?? '-'), DataType::TYPE_STRING);
                $rEmp++;
            }

            if ($rEmp > $rEmpStart) {
                $lastEmpRow = $rEmp - 1;
                $wsDiv->getStyle("A{$rEmpStart}:K{$lastEmpRow}")->applyFromArray($cellBorder);
            }

            foreach (['A' => 5, 'B' => 32, 'C' => 18, 'D' => 20, 'E' => 20, 'F' => 14, 'G' => 30, 'H' => 28, 'I' => 28, 'J' => 13, 'K' => 16] as $col => $w) {
                $wsDiv->getColumnDimension($col)->setWidth($w);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'REKAP_PENDATAAN_PT_SWI_' . date('Ymd_His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $writer->setPreCalculateFormulas(false);

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = tempnam($tempDir, 'swi_excel_');
        $writer->save($tempFile);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);

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
                'Jabatan Borong', 'Kota Perwakilan', 'Area', 'Tanggal Masuk PT Sukun (YYYY-MM-DD)',
                'Status Pernikahan', 'Pasangan', 'Pekerjaan Pasangan', 'Jml Anak', 'Detail Anak',
                'Disabilitas', 'Nama Bank', 'No Rekening', 'Atas Nama Rekening', 'Bersedia Transfer',
                'BPJS Kes', 'BPJS Ket', 'NPWP', 'No Anggota Koperasi',
                'Riwayat Pekerjaan Sukun', 'Pengalaman Luar',
                'Status Verifikasi', 'Waktu Input'
            ]);

            $no = 1;
            foreach ($sortedExport as $row) {
                $alList      = $alamatMap->get($row->id) ?? collect();
                $alamatKtp   = optional($alList->firstWhere('jenis_alamat', 'KTP'))->alamat_lengkap ?? '-';
                $alamatDom   = optional($alList->firstWhere('jenis_alamat', 'DOMISILI'))->alamat_lengkap ?? '-';
                $pasangan    = $pasanganMap->get($row->id);
                $tempatLahir = trim((string) ($row->tempat_lahir ?? '')) ?: '-';

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
                    $tempatLahir,
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

    private function buildDashboardQuery(Request $request, ?array $allowedUnitIds = null, array $extraKaryawanIds = []): array
    {
        $statusExpr = $this->statusVerifikasiSqlExpression();

        $query = DB::table('karyawan')
            ->leftJoin('departemen', 'karyawan.departemen_id', '=', 'departemen.id')
            ->leftJoin('unit_organisasi', 'karyawan.unit_organisasi_id', '=', 'unit_organisasi.id')
            ->leftJoin('bank', 'karyawan.bank_id', '=', 'bank.id')
            ->leftJoin('jabatan_borong', 'karyawan.jabatan_borong_id', '=', 'jabatan_borong.id')
            ->leftJoin('kota_perwakilan', 'karyawan.kota_perwakilan_id', '=', 'kota_perwakilan.id')
            ->leftJoin('area', 'karyawan.area_id', '=', 'area.id')
            ->select(
                'karyawan.*',
                DB::raw("({$statusExpr}) as status_verifikasi"),
                'departemen.nama_departemen',
                'unit_organisasi.nama_unit',
                'bank.nama_bank',
                'jabatan_borong.nama_jabatan_borong',
                'kota_perwakilan.nama_kota',
                'area.nama_area'
            )
            ->orderByDesc('karyawan.id');

        $filterMeta = [
            'has_filter'       => false,
            'departemen'       => null,
            'unit_id'          => null,
            'unit_name'        => null,
            'allowed_unit_ids' => $allowedUnitIds,
            'labels'           => [],
        ];

        if ($allowedUnitIds !== null) {
            if (empty($allowedUnitIds) && empty($extraKaryawanIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($allowedUnitIds, $extraKaryawanIds) {
                    if (! empty($allowedUnitIds)) {
                        $q->whereIn('karyawan.unit_organisasi_id', $allowedUnitIds);
                    }
                    if (! empty($extraKaryawanIds)) {
                        $q->orWhereIn('karyawan.id', $extraKaryawanIds);
                    }
                });
            }
        }

        if ($request->filled('search')) {
            $search      = trim((string) $request->input('search'));
            $phoneDigits = preg_replace('/[^0-9]/', '', $search);

            $query->where(function ($q) use ($search, $phoneDigits) {
                $q->where('karyawan.nama_lengkap', 'like', '%' . $search . '%')
                    ->orWhere('karyawan.nik_ktp', 'like', '%' . $search . '%')
                    ->orWhere('karyawan.nip', 'like', '%' . $search . '%')
                    ->orWhere('karyawan.no_wa', 'like', '%' . $search . '%')
                    ->orWhere('unit_organisasi.nama_unit', 'like', '%' . $search . '%');

                if ($phoneDigits !== '') {
                    $normalizedPhone = $phoneDigits;
                    if (str_starts_with($normalizedPhone, '62')) {
                        $normalizedPhone = '0' . substr($normalizedPhone, 2);
                    } elseif (str_starts_with($normalizedPhone, '8')) {
                        $normalizedPhone = '0' . $normalizedPhone;
                    }

                    $q->orWhere('karyawan.no_wa', 'like', '%' . $phoneDigits . '%')
                        ->orWhere('karyawan.no_wa', 'like', '%' . $normalizedPhone . '%');
                }
            });
            $filterMeta['has_filter'] = true;
            $filterMeta['labels'][]   = 'Cari: "' . $search . '"';
        }

        if ($request->filled('departemen')) {
            $dept = trim((string) $request->input('departemen'));
            $query->where('departemen.nama_departemen', $dept);
            $filterMeta['has_filter'] = true;
            $filterMeta['departemen'] = $dept;
            $filterMeta['labels'][]   = 'Divisi: ' . $dept;
        }

        if ($request->filled('unit_organisasi_id')) {
            $unitId = (int) $request->input('unit_organisasi_id');
            $query->where('karyawan.unit_organisasi_id', $unitId);
            $unitName = DB::table('unit_organisasi')->where('id', $unitId)->value('nama_unit') ?? (string) $unitId;

            $filterMeta['has_filter'] = true;
            $filterMeta['unit_id']    = $unitId;
            $filterMeta['unit_name']  = $unitName;
            $filterMeta['labels'][]   = 'Unit: ' . $unitName;
        } elseif ($request->filled('unit')) {
            $unitName = trim((string) $request->input('unit'));
            $query->where('unit_organisasi.nama_unit', $unitName);

            $filterMeta['has_filter'] = true;
            $filterMeta['unit_name']  = $unitName;
            $filterMeta['labels'][]   = 'Unit: ' . $unitName;
        }

        if ($request->filled('status_verifikasi')) {
            $status = strtoupper(trim((string) $request->input('status_verifikasi')));
            $query->whereRaw("({$statusExpr}) = ?", [$status]);
            $filterMeta['has_filter'] = true;
            $filterMeta['labels'][]   = 'Status: ' . $status;
        }

        if ($request->filled('tipe_karyawan')) {
            $tipe = strtoupper(trim((string) $request->input('tipe_karyawan')));
            $query->where('karyawan.tipe_karyawan', $tipe);
            $filterMeta['has_filter'] = true;
            $filterMeta['labels'][]   = 'Tipe: ' . $tipe;
        }

        return [$query, $filterMeta];
    }

    public function dashboard(Request $request)
    {
        [$query, $filterMeta] = $this->buildDashboardQuery($request);

        if ($request->input('export') === 'excel') {
            return $this->exportExcel($query->get(), $filterMeta);
        }

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query->get());
        }

        $totalData = (clone $query)->reorder()->count('karyawan.id');

        return view('pendataan.dashboard', [
            'karyawan'       => $query->paginate(10)->withQueryString(),
            'judul'          => 'Dashboard HRD',
            'mode'           => 'HRD',
            'canDelete'      => $this->canDeleteKaryawan(),
            'canImport'      => $this->canImportExcel(),
            'totalData'      => $totalData,
            'listDepartemen' => DB::table('departemen')->orderBy('nama_departemen')->pluck('nama_departemen'),
        ]);
    }

    public function dashboardAtasan(Request $request)
    {
        $penggunaId = (int) session('pengguna_id');
        $unitIds    = DB::table('atasan_unit')->where('pengguna_id', $penggunaId)->pluck('unit_organisasi_id')->toArray();

        $extraKaryawanIds = [];
        $username = strtolower(trim((string) (
            session('pengguna_username')
            ?? session('username')
            ?? DB::table('pengguna')->where('id', $penggunaId)->value('username')
            ?? ''
        )));

        if ($penggunaId === 236 || $username === 'kepala_teknik') {
            $extraKaryawanIds = [
                453,  // SUBAGYO (SKM)
                449,  // SUMARJONO (SKM)
                1366, // SOFWAN SUTIKNO (SKM)
                514,  // ABDUL AZIS (SKM)
                443,  // MUHAMMAD ZULFA RATHOMI (SKM)
                103,  // SUMARYANTO (SKM)
                227,  // ABDUL MAKIS (SKM)
                441,  // SUBANDI (SKM)
                1040, // NOOR FALIH (SKM)
                100,  // REZA DWI RAHMAN (SKM)
            ];
        }

        [$query, $filterMeta] = $this->buildDashboardQuery($request, $unitIds, $extraKaryawanIds);

        if ($request->input('export') === 'excel') {
            return $this->exportExcel($query->get(), $filterMeta);
        }

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query->get());
        }

        $totalData = (clone $query)->reorder()->count('karyawan.id');

        return view('pendataan.dashboard', [
            'karyawan'       => $query->paginate(10)->withQueryString(),
            'judul'          => 'Dashboard Kepala Bagian',
            'mode'           => 'ATASAN',
            'canDelete'      => false,
            'canImport'      => false,
            'totalData'      => $totalData,
            'listDepartemen' => DB::table('departemen')->orderBy('nama_departemen')->pluck('nama_departemen'),
        ]);
    }
}