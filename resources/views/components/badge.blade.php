@props(['color' => 'gray'])

@php
    // Full class names so Tailwind's scanner picks them up.
    $classes = [
        'gray' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'purple' => 'bg-purple-50 text-purple-700 ring-purple-200',
        'yellow' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'red' => 'bg-red-50 text-red-700 ring-red-200',
    ][$color] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset $classes"]) }}>{{ $slot }}</span>
