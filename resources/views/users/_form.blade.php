@php $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6"
      x-data="{ setupLink: {{ old('send_setup_link', $user->exists ? 0 : 1) ? 'true' : 'false' }} }">
    @csrf
    @isset($method) @method($method) @endisset

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="name" value="Full name *" />
            <input id="name" name="name" type="text" required maxlength="255" class="{{ $input }}" value="{{ old('name', $user->name) }}">
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" value="Email address *" />
            <input id="email" name="email" type="email" required maxlength="255" class="{{ $input }}" value="{{ old('email', $user->email) }}">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="phone" value="Phone number" />
            <input id="phone" name="phone" type="tel" maxlength="30" class="{{ $input }}" value="{{ old('phone', $user->phone) }}">
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="department" value="Department" />
            <input id="department" name="department" type="text" maxlength="100" class="{{ $input }}" value="{{ old('department', $user->department) }}" placeholder="e.g. Inside Sales">
            <x-input-error :messages="$errors->get('department')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="role" value="Role *" />
            <select id="role" name="role" required class="{{ $input }}">
                @foreach ($roles as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $user->roleName() ?? 'sales_rep') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="photo" value="Profile photo" />
            <div class="mt-1 flex items-center gap-3">
                @if ($user->exists)<x-avatar :user="$user" size="h-10 w-10 text-sm" />@endif
                <input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
            </div>
            @if ($user->profile_photo_path)
                <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300 text-indigo-600"> Remove current photo
                </label>
            @endif
            <p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP, up to 2 MB.</p>
            <x-input-error :messages="$errors->get('photo')" class="mt-2" />
        </div>
    </div>

    <fieldset class="rounded-lg border border-slate-200 p-4">
        <legend class="px-1 text-sm font-medium text-slate-700">Password</legend>
        @unless ($user->exists)
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="send_setup_link" value="1" x-model="setupLink" class="mt-0.5 rounded border-slate-300 text-indigo-600">
                <span><span class="font-medium text-slate-800">Email a secure password setup link</span><br>
                    <span class="text-slate-500">Recommended. The user chooses their own password; nobody else ever sees it.</span></span>
            </label>
        @endunless
        <div class="mt-3" x-show="!setupLink" @unless($user->exists) x-cloak @endunless>
            <x-input-label for="password" :value="$user->exists ? 'New password (leave blank to keep the current one)' : 'Password *'" />
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" class="{{ $input }}" x-bind:disabled="setupLink">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
    </fieldset>

    <div class="flex items-center gap-4">
        <x-primary-button>Save user</x-primary-button>
        <a href="{{ $user->exists ? route('users.show', $user) : route('users.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
    </div>
</form>
