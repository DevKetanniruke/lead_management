{{-- Page-level success / error banners. Field errors are shown next to their inputs. --}}
@props(['errorKeys' => null])

@if (session('status'))
    <div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800']) }} role="status">
        <x-icon name="check" class="h-5 w-5 shrink-0 text-emerald-600" />
        <span>{{ session('status') }}</span>
    </div>
@endif
@if (session('error') || ($errorKeys === null ? $errors->any() : collect($errorKeys)->contains(fn ($k) => $errors->has($k))))
    <div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800']) }} role="alert">
        <x-icon name="alert" class="h-5 w-5 shrink-0 text-red-600" />
        <span>{{ session('error') ?? ($errorKeys === null ? $errors->first() : collect($errorKeys)->map(fn ($k) => $errors->first($k))->filter()->first()) }}</span>
    </div>
@endif
