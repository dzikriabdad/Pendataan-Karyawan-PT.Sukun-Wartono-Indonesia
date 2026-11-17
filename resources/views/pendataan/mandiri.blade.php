<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Edit Data Mandiri - PT Sukun Wartono Indonesia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(135deg, #eef2f8 0%, #dbeafe 100%);
        }
        .in:focus {
            outline: none;
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800 antialiased flex items-center justify-center p-4">

    <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-slate-200/80 p-6 md:p-8 space-y-5">
        
        <!-- Header -->
        <div class="flex items-center space-x-3 pb-2 border-b border-slate-100">
            <div class="p-2.5 bg-blue-100 text-blue-700 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-900">Akses Ubah Data</h1>
                <p class="text-xs text-slate-500">PT. Sukun Wartono Indonesia</p>
            </div>
        </div>

        <!-- Info Notice -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 flex items-start space-x-2.5 text-xs text-blue-800">
            <svg class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Data untuk verifikasi harus sesuai dengan data yang telah dilaporkan/diinput sebelumnya.</span>
        </div>

        <!-- Error Message -->
        @if (isset($errors) && $errors->any())
            <div class="bg-red-50 border border-red-200 rounded-xl p-3.5 text-xs text-red-700 space-y-1">
                @foreach ($errors->all() as $err)
                    <p>{{ $err }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('pendataan.ubah-data.verify') }}" method="POST" class="space-y-4">
            @csrf

            <!-- NIK KTP -->
            <div>
                <label for="nik" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                    Nomor Induk Kependudukan (NIK KTP) <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="nik"
                    name="nik"
                    value="{{ old('nik') }}"
                    inputmode="numeric"
                    maxlength="16"
                    placeholder="16 digit NIK KTP"
                    class="in w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm text-slate-800 font-mono tracking-wider focus:bg-white"
                    required
                    autofocus
                >
            </div>

            <!-- Nama Ibu Kandung -->
            <div>
                <label for="nama_ibu" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                    Nama Ibu Kandung <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="nama_ibu"
                    name="nama_ibu"
                    value="{{ old('nama_ibu') }}"
                    placeholder="Nama ibu kandung"
                    class="in w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm text-slate-800 focus:bg-white uppercase"
                    required
                >
            </div>

            <!-- Waktu Masuk Kerja -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                    Waktu Mulai Bekerja di Sukun <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <!-- Bulan Masuk -->
                    <div>
                        <select
                            name="tgl_masuk_bulan"
                            class="in w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm text-slate-800 bg-white"
                            required
                        >
                            <option value="">-- Pilih Bulan --</option>
                            @php
                                $daftarBulan = [
                                    1 => 'Januari',
                                    2 => 'Februari',
                                    3 => 'Maret',
                                    4 => 'April',
                                    5 => 'Mei',
                                    6 => 'Juni',
                                    7 => 'Juli',
                                    8 => 'Agustus',
                                    9 => 'September',
                                    10 => 'Oktober',
                                    11 => 'November',
                                    12 => 'Desember',
                                ];
                            @endphp
                            @foreach ($daftarBulan as $num => $namaBulan)
                                <option value="{{ $num }}" {{ (int) old('tgl_masuk_bulan') === $num ? 'selected' : '' }}>
                                    {{ $namaBulan }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Tahun Masuk -->
                    <div>
                        <input
                            type="number"
                            name="tgl_masuk_tahun"
                            value="{{ old('tgl_masuk_tahun') }}"
                            min="1950"
                            max="{{ date('Y') }}"
                            placeholder="Tahun (cth: 2018)"
                            class="in w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm text-slate-800"
                            required
                        >
                    </div>
                </div>
            </div>

            <!-- Submit Button & Nav Link -->
            <div class="pt-2 space-y-3">
                <button
                    type="submit"
                    class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 px-4 rounded-xl shadow-md transition duration-150 flex items-center justify-center space-x-2"
                >
                    <span>Verifikasi & Buka Formulir</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>

                <div class="text-center pt-1">
                    <a href="{{ route('pendataan.create') }}" class="text-xs text-blue-600 hover:text-blue-800 hover:underline">
                        Input Data Baru
                    </a>
                </div>
            </div>
        </form>

    </div>

</body>
</html>
