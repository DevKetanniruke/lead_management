@props(['status'])

@php
    // Full class names so Tailwind's scanner picks them up.
    $classes = [
        'blue' => 'bg-blue-100 text-blue-800',
        'indigo' => 'bg-indigo-100 text-indigo-800',
        'purple' => 'bg-purple-100 text-purple-800',
        'yellow' => 'bg-yellow-100 text-yellow-800',
        'orange' => 'bg-orange-100 text-orange-800',
        'green' => 'bg-green-100 text-green-800',
        'red' => 'bg-red-100 text-red-800',
    ][$status->color] ?? 'bg-gray-100 text-gray-800';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded-full px-2 py-0.5 text-xs font-medium $classes"]) }}>{{ $status->name }}</span>
