<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Internal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">

<div class="mx-auto flex min-h-screen max-w-md items-center px-4">
    <div class="w-full rounded-xl bg-white p-8 shadow">
        <h1 class="text-2xl font-bold text-slate-900">
            Login Internal
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            Sistem Pendataan Karyawan
        </p>

        @if ($errors->any())
            <div class="mt-5 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if (session('sukses'))
            <div class="mt-5 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">
                {{ session('sukses') }}
            </div>
        @endif

        <form action="{{ route('login.proses') }}" method="POST" class="mt-6 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Username
                </label>
                <input
                    type="text"
                    name="username"
                    value="{{ old('username') }}"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-4 py-3"
                    required
                    autofocus
                >
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Password
                </label>
                <input
                    type="password"
                    name="password"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-4 py-3"
                    required
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white hover:bg-blue-800"
            >
                Login
            </button>
        </form>
    </div>
</div>

</body>
</html>