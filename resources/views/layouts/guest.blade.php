<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @include('partials.assets')
    </head>
    <body class="h-full bg-slate-50 font-sans text-slate-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-950 px-4 py-10">
            <a href="/" class="mb-8 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500 text-white shadow-lg shadow-indigo-500/30">
                    <x-icon name="leads" class="h-6 w-6" />
                </span>
                <span class="text-2xl font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
            </a>

            <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
