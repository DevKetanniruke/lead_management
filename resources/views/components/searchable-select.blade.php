@props([
    'name',
    'id' => null,
    'options' => [],          // [value => label]
    'selected' => null,
    'placeholder' => 'Select…',
    'required' => false,
])

@php
    $id ??= $name;
    $items = collect($options)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])->values();
@endphp

<div
    x-data="{
        open: false,
        search: '',
        value: @js((string) $selected),
        active: 0,
        items: @js($items),
        placeholder: @js($placeholder),
        init() {
            // Lets a parent react to the choice, e.g. x-on:selected with $event.detail.
            this.$watch('value', value => this.$dispatch('selected', value));
        },
        get filtered() {
            const term = this.search.trim().toLowerCase();
            const matches = this.items.filter(i => i.label.toLowerCase().includes(term));
            return term ? matches : [{ value: '', label: this.placeholder }, ...matches];
        },
        get label() {
            return this.items.find(i => i.value === this.value)?.label ?? this.placeholder;
        },
        toggle() {
            this.open ? this.close() : this.show();
        },
        show() {
            this.open = true;
            this.search = '';
            const index = this.filtered.findIndex(i => i.value === this.value);
            this.active = index < 0 ? 0 : index;
            this.$nextTick(() => { this.$refs.search.focus(); this.scrollToActive(); });
        },
        close(refocus = true) {
            this.open = false;
            if (refocus) this.$refs.button.focus();
        },
        choose(item) {
            if (! item) return;
            this.value = item.value;
            this.close();
        },
        move(step) {
            const count = this.filtered.length;
            if (! count) return;
            this.active = (this.active + step + count) % count;
            this.$nextTick(() => this.scrollToActive());
        },
        scrollToActive() {
            this.$refs.list.querySelector('[data-active=true]')?.scrollIntoView({ block: 'nearest' });
        },
    }"
    x-on:click.outside="open && close(false)"
    x-on:keydown.escape.prevent.stop="open && close()"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    {{-- Native select keeps form submission and browser "required" validation working. --}}
    <select name="{{ $name }}" x-model="value" @required($required) tabindex="-1" aria-hidden="true"
            class="sr-only" x-on:focus="$refs.button.focus()">
        <option value=""></option>
        @foreach ($items as $item)
            <option value="{{ $item['value'] }}">{{ $item['label'] }}</option>
        @endforeach
    </select>

    <button type="button" id="{{ $id }}" x-ref="button"
            x-on:click="toggle()"
            x-on:keydown.arrow-down.prevent="show()"
            x-bind:aria-expanded="open"
            aria-haspopup="listbox"
            class="mt-1 flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        <span x-text="label" x-bind:class="value === '' ? 'text-gray-500' : 'text-gray-900'" class="truncate">{{ $placeholder }}</span>
        <svg class="ml-2 h-4 w-4 shrink-0 text-gray-400 transition" x-bind:class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M5.25 7.5 10 12.5l4.75-5h-9.5Z" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
        <div class="p-2">
            <input type="text" x-ref="search" x-model="search" autocomplete="off"
                   x-on:input="active = 0"
                   x-on:keydown.arrow-down.prevent="move(1)"
                   x-on:keydown.arrow-up.prevent="move(-1)"
                   x-on:keydown.enter.prevent="choose(filtered[active])"
                   x-on:keydown.tab="close(false)"
                   aria-label="Search"
                   class="block w-full rounded border-gray-300 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <ul x-ref="list" role="listbox" class="max-h-56 overflow-y-auto pb-1">
            <template x-for="(item, index) in filtered" :key="item.value">
                <li role="option"
                    x-text="item.label"
                    x-bind:data-active="index === active"
                    x-bind:aria-selected="item.value === value"
                    x-on:mouseenter="active = index"
                    x-on:click="choose(item)"
                    x-bind:class="index === active ? 'bg-indigo-600 text-white' : (item.value === value ? 'bg-gray-100 text-gray-900' : 'text-gray-700')"
                    class="cursor-pointer px-3 py-2 text-sm"></li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-500">No results found</li>
        </ul>
    </div>
</div>
