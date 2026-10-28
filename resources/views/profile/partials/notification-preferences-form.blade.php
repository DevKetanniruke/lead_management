<section>
    <header>
        <h2 class="text-lg font-medium text-slate-900">Email notifications</h2>
        <p class="mt-1 text-sm text-slate-600">Choose what you're emailed about. Everything still appears under the bell icon.</p>
    </header>

    <form method="POST" action="{{ route('profile.notifications') }}" class="mt-6 space-y-4">
        @csrf @method('PATCH')

        <div class="space-y-3">
            @foreach (App\Models\User::NOTIFICATION_TYPES as $type => $label)
                <label class="flex items-center gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="email[{{ $type }}]" value="1" @checked($user->wantsMail($type))
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Save preferences</x-primary-button>
            @if (session('status') === 'notifications-updated')
                <p class="text-sm text-slate-600">Saved.</p>
            @endif
        </div>
    </form>
</section>
