<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $judul ?? 'Dashboard' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">

<header class="bg-blue-900 text-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
        <div>
            <h1 class="text-xl font-bold">
                {{ $judul ?? 'Dashboard' }}
            </h1>
            <p class="text-sm text-blue-200">
                Login sebagai: {{ session('pengguna_nama') ?? auth()->user()->nama_pengguna ?? 'User' }}
            </p>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="rounded-lg bg-white/10 px-4 py-2 text-sm hover:bg-white/20 transition">
                Logout
            </button>
        </form>
    </div>
</header>

<main class="mx-auto max-w-7xl px-6 py-8">
    
    <!-- BLOK NOTIFIKASI (SUKSES & ERROR) -->
    @if(session('sukses'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-sm">
            {{ session('sukses') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800 shadow-sm">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800 shadow-sm">
            <p>Terdapat kesalahan saat memproses data:</p>
            <ul class="list-inside list-disc font-normal mt-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Notifikasi Hasil Import Excel --}}
    @if(session('import_sukses'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 shadow-sm">
            <p class="font-bold">✅ {{ session('import_sukses') }}</p>
        </div>
    @endif

    @php
        $importDuplicates = session('import_duplicates', []);
        $importErrors     = session('import_errors', []);
    @endphp

    @if(!empty($importDuplicates))
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm">
            <p class="font-bold">⚠️ {{ count($importDuplicates) }} Data Duplikat Ditemukan (NIK sudah pernah terisi & otomatis dilewati):</p>
            <ul class="mt-2 max-h-40 overflow-y-auto list-disc list-inside text-xs space-y-1">
                @foreach($importDuplicates as $dup)
                    <li>Baris {{ $dup['baris'] }} — NIK <strong>{{ $dup['nik'] }}</strong> ({{ $dup['nama'] }})</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($importErrors))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900 shadow-sm">
            <p class="font-bold">❌ Baris gagal diimport:</p>
            <ul class="mt-2 max-h-40 overflow-y-auto list-disc list-inside text-xs space-y-1">
                @foreach($importErrors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <!-- END BLOK NOTIFIKASI -->

    <!-- Bagian Filter, Counter & Import/Export -->
    <div class="mb-6 rounded-xl bg-white p-5 shadow space-y-4">
        <div class="flex flex-col md:flex-row gap-4 md:items-end justify-between">
            <!-- Counter -->
            <div>
                <p class="text-sm text-slate-500">Jumlah data ditemukan</p>
                <p class="mt-1 text-3xl font-bold text-blue-800">
                    {{ $totalData ?? 0 }}
                </p>
                @if(request('search') || request('departemen'))
                    <div class="flex flex-wrap items-center gap-1.5 mt-2 text-sm text-blue-700 font-medium">
                        <span>🔍 Filter aktif:</span>
                        @if(request('search'))
                            <span class="bg-blue-50 border border-blue-200 px-2.5 py-0.5 rounded-full">
                                Pencarian: "{{ request('search') }}"
                            </span>
                        @endif
                        @if(request('departemen'))
                            <span class="bg-blue-50 border border-blue-200 px-2.5 py-0.5 rounded-full">
                                Departemen: "{{ request('departemen') }}"
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Form Filter & Export (GET) -->
            <form method="GET" action="{{ url()->current() }}" class="flex flex-col sm:flex-row flex-wrap gap-3 w-full md:w-auto">
                <div>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Cari NIK / Nama / No. HP..."
                           class="w-full sm:w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                </div>

                @if(isset($mode) && $mode === 'HRD')
                    <div>
                        <select name="departemen" class="w-full sm:w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="">Semua Departemen</option>
                            @foreach($listDepartemen ?? [] as $dep)
                                <option value="{{ $dep }}" {{ request('departemen') == $dep ? 'selected' : '' }}>
                                    {{ $dep }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 transition">
                        Filter
                    </button>
                    <a href="{{ url()->current() }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition flex items-center justify-center">
                        Reset
                    </a>
                    
                    @if(isset($mode) && $mode === 'HRD')
                        <button type="submit" name="export" value="csv" class="rounded-lg bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700 transition">
                            Unduh CSV
                        </button>
                        <button type="submit" name="export" value="excel" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition">
                            Export Excel
                        </button>
                    @endif
                </div>
            </form>
        </div>

        {{-- Form Upload Import Excel & Unduh Template --}}
        @if(isset($mode) && $mode === 'HRD')
            <div class="border-t border-slate-200 pt-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="text-xs text-slate-500">
                    @if(!empty($canImport))
                        Import massal menggunakan template Excel (Sheet <code class="bg-slate-100 px-1 py-0.5 rounded font-semibold text-slate-700">DATA</code>). NIK yang sudah terdaftar akan dilewati otomatis.
                    @else
                        Unduh template Excel (Sheet <code class="bg-slate-100 px-1 py-0.5 rounded font-semibold text-slate-700">DATA</code>) sebagai acuan format pengisian data karyawan.
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('pendataan.template') }}" class="rounded-lg border border-indigo-300 bg-indigo-50 px-3.5 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition">
                        Unduh Template Import
                    </a>

                    @if(!empty($canImport))
                        <form action="{{ route('pendataan.import') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="file" name="file_excel" accept=".xlsx,.xls" required
                                   class="text-xs text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition">
                                Import Excel
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Tabel Data -->
    <div class="overflow-hidden rounded-xl bg-white shadow">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100 text-slate-700 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">NIK</th>
                        <th class="px-4 py-3">Nama Lengkap</th>
                        <th class="px-4 py-3">No. HP / WA</th>
                        <th class="px-4 py-3">Departemen</th>
                        <th class="px-4 py-3">Unit</th>
                        
                        @if(isset($mode) && $mode === 'HRD')
                            <th class="px-4 py-3">Tipe</th>
                        @endif

                        <th class="px-4 py-3">Status Data</th>

                        @if(!empty($canDelete))
                            <th class="px-4 py-3 text-center">Aksi</th>
                        @endif
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200">
                    @forelse ($karyawan as $index => $item)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3">{{ $karyawan->firstItem() + $index }}</td>
                            <td class="px-4 py-3">{{ $item->nik_ktp }}</td>
                            <td class="px-4 py-3 font-semibold">
                                @if(isset($mode) && $mode === 'HRD')
                                    <a href="{{ route('pendataan.detail', $item->id) }}" class="text-blue-600 hover:underline hover:text-blue-800">
                                        {{ $item->nama_lengkap }}
                                    </a>
                                @else
                                    <span class="text-slate-800">
                                        {{ $item->nama_lengkap }}
                                    </span>
                                @endif
                                <div class="text-xs font-normal text-slate-500">Lahir: {{ $item->tgl_lahir ?? '-' }}</div>
                            </td>
                            {{-- Sesuaikan nama kolom nomor telepon jika di DB berbeda (misal: no_telepon / no_wa) --}}
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">
                                {{ $item->no_hp ?? $item->no_telepon ?? '-' }}
                            </td>
                            <td class="px-4 py-3">{{ $item->nama_departemen ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $item->nama_unit ?? '-' }}</td>
                            
                            @if(isset($mode) && $mode === 'HRD')
                                <td class="px-4 py-3">
                                    <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">
                                        {{ $item->tipe_karyawan ?? 'UMUM' }}
                                    </span>
                                </td>
                            @endif

                            <td class="px-4 py-3">
                                @if($item->status_verifikasi == 'LENGKAP')
                                    <span class="text-green-600 font-semibold">Lengkap</span>
                                @else
                                    <span class="text-amber-600 font-semibold">Belum Lengkap</span>
                                @endif
                            </td>

                            @if(!empty($canDelete))
                                <td class="px-4 py-3 text-center">
                                    <form action="{{ route('pendataan.destroy', $item->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Yakin ingin menghapus permanen data {{ $item->nama_lengkap }} (NIK: {{ $item->nik_ktp }})?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded bg-red-600 px-3 py-1 text-xs font-semibold text-white hover:bg-red-700 transition shadow-sm">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            @php
                                $colSpan = 7;
                                if(isset($mode) && $mode === 'HRD') $colSpan++;
                                if(!empty($canDelete)) $colSpan++;
                            @endphp
                            <td colspan="{{ $colSpan }}" class="px-4 py-8 text-center text-slate-500">
                                Belum ada data karyawan atau data tidak ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($karyawan->hasPages())
            <div class="border-t border-slate-200 bg-white px-4 py-3">
                {{ $karyawan->withQueryString()->links() }}
            </div>
        @endif
    </div>
</main>

</body>
</html>