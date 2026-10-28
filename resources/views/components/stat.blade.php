@props(['label', 'value', 'sub' => null, 'icon' => null, 'tone' => 'bg-indigo-50 text-indigo-600', 'href' => null, 'alert' => false])

@php
    $classes = 'block rounded-xl border bg-white p-5 shadow-sm '.($alert ? 'border-red-200' : 'border-slate-200')
        .($href ? ' transition hover:border-indigo-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500' : '');
@endphp

@if ($href)<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>@else<div {{ $attributes->merge(['class' => $classes]) }}>@endif
    <div class="flex items-center justify-between gap-2">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $alert ? 'bg-red-50 text-red-600' : $tone }}"><x-icon :name="$icon" class="h-5 w-5" /></span>
        @endif
    </div>
    <p class="mt-3 truncate text-2xl font-semibold tracking-tight {{ $alert ? 'text-red-700' : 'text-slate-900' }}">{{ $value }}</p>
    @if ($sub)<p class="mt-1 text-xs text-slate-500">{{ $sub }}</p>@endif
@if ($href)</a>@else</div>@endif
