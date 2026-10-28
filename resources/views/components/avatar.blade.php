@props(['user', 'size' => 'h-9 w-9 text-sm', 'tone' => 'bg-indigo-100 text-indigo-700'])

@if ($user?->photoUrl())
    <img src="{{ $user->photoUrl() }}" alt="" {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$size $tone flex shrink-0 items-center justify-center rounded-full font-semibold"]) }}>{{ $user?->initials() ?: '?' }}</span>
@endif
