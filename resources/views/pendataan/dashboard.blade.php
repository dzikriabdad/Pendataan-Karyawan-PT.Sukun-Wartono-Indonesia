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
    
    <!-- Bagian Filter & Counter -->
    <div class="mb-6 flex flex-col md:flex-row gap-4 md:items-end justify-between rounded-xl bg-white p-5 shadow">
        
        <!-- Counter -->
        <div>
            <p class="text-sm text-slate-500">Jumlah data ditemukan</p>
            <p class="mt-1 text-3xl font-bold text-blue-800">
                {{ $totalData ?? 0 }}
            </p>
        </div>

        <!-- Form Filter -->
        <form method="GET" action="{{ url()->current() }}" class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
            <!-- Pencarian NIK/Nama -->
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari NIK / Nama..." class="w-full sm:w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            </div>

            <!-- Filter Departemen: HANYA MUNCUL UNTUK ADMIN HRD -->
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

            <!-- Tombol Aksi -->
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 transition">
                    Filter
                </button>
                <a href="{{ url()->current() }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition">
                    Reset
                </a>
                
                <!-- Tombol Unduh CSV hanya untuk Admin HRD -->
                @if(isset($mode) && $mode === 'HRD')
                    <button type="submit" name="export" value="csv" class="rounded-lg bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700 transition">
                        Unduh CSV
                    </button>
                @endif
            </div>
        </form>
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
                        <th class="px-4 py-3">Departemen</th>
                        <th class="px-4 py-3">Unit</th>
                        
                        {{-- Kolom Tipe HANYA untuk HRD --}}
                        @if(isset($mode) && $mode === 'HRD')
                            <th class="px-4 py-3">Tipe</th>
                        @endif

                        {{-- Kolom Status Data kini MUNCUL untuk HRD maupun ATASAN --}}
                        <th class="px-4 py-3">Status Data</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200">
                    @forelse ($karyawan as $index => $item)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3">{{ $karyawan->firstItem() + $index }}</td>
                            <td class="px-4 py-3">{{ $item->nik_ktp }}</td>
                            <td class="px-4 py-3 font-semibold">
                                {{-- HANYA Admin HR yang bisa klik nama untuk buka detail. Kepala Bagian hanya teks biasa --}}
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ (isset($mode) && $mode === 'HRD') ? '7' : '6' }}" class="px-4 py-8 text-center text-slate-500">
                                Belum ada data karyawan atau data tidak ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagging UI -->
        @if($karyawan->hasPages())
            <div class="border-t border-slate-200 bg-white px-4 py-3">
                {{ $karyawan->links() }}
            </div>
        @endif
    </div>
</main>

</body>
</html>