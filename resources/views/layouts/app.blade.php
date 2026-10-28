<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Laravel') }}</title>

        @include('partials.assets')
    </head>
    <body class="h-full bg-slate-50 font-sans text-slate-900 antialiased" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
        <div class="min-h-screen lg:flex">
            {{-- Mobile overlay --}}
            <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden" @click="sidebar = false"></div>

            @include('layouts.navigation')

            <div class="flex min-w-0 flex-1 flex-col">
                {{-- Top bar --}}
                <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                    <button type="button" class="-ms-1 rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden" @click="sidebar = true" aria-label="Open sidebar">
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>
                    <div class="min-w-0 flex-1">
                        @isset($header)
                            {{ $header }}
                        @endisset
                    </div>
                    @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
                    <a href="{{ route('notifications.index') }}" class="relative shrink-0 rounded-full p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                       aria-label="Notifications{{ $unread ? " ($unread unread)" : '' }}">
                        <x-icon name="bell" class="h-6 w-6" />
                        @if ($unread)
                            <span class="absolute right-1 top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </a>
                </header>

                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @include('partials.ajax-table')
        @include('partials.ui')
    </body>
</html>
