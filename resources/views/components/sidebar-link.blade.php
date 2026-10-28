@props(['active' => false, 'icon'])

<a {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition '.($active
        ? 'bg-indigo-500/15 text-white'
        : 'text-slate-400 hover:bg-slate-800 hover:text-white')]) }}
   @if ($active) aria-current="page" @endif>
    <x-icon :name="$icon" @class(['h-5 w-5 shrink-0', 'text-indigo-400' => $active, 'text-slate-500 group-hover:text-slate-300' => ! $active]) />
    {{ $slot }}
</a>
