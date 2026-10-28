<x-app-layout>
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="p-4 sm:p-8 bg-white border border-slate-200 shadow-sm rounded-xl">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white border border-slate-200 shadow-sm rounded-xl">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white border border-slate-200 shadow-sm rounded-xl">
                <div class="max-w-xl">
                    @include('profile.partials.notification-preferences-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white border border-slate-200 shadow-sm rounded-xl">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
