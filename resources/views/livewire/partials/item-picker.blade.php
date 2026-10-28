{{-- Item search used inside a stockForm Alpine component --}}
<div class="card relative">
    <label class="label">Add item</label>
    <input x-model="query" type="search" class="input" placeholder="Type a name, e.g. chilli, oil..."
           autocomplete="off" @keydown.enter.prevent="matches.length && add(matches[0])">

    <template x-if="query.trim()">
        <div class="mt-2 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
            <template x-for="item in matches" :key="item.id">
                <button type="button" @click="add(item)"
                        class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left hover:bg-slate-50 active:bg-slate-100"
                        :class="unavailable(item) ? 'opacity-40' : ''">
                    <span class="min-w-0">
                        <span class="block truncate font-medium" x-text="item.name"></span>
                        <span class="block text-xs text-slate-500" x-text="item.category"></span>
                    </span>
                    <span class="shrink-0 text-right text-xs text-slate-500">
                        <span x-text="unavailable(item) ? 'Out of stock' : qty(item.stock) + ' ' + item.unit"></span>
                    </span>
                </button>
            </template>

            @if ($allowCreate ?? true)
                <template x-if="mode === 'purchase' && !exactMatch">
                    <div class="space-y-2 bg-slate-50 p-3">
                        <p class="text-sm">New item: <span class="font-semibold" x-text="query.trim()"></span></p>
                        <div class="grid grid-cols-2 gap-2">
                            <select x-model="newCategory" class="input px-2 text-sm">
                                <option value="">Other</option>
                                @foreach ($categories ?? [] as $cat)
                                    @if ($cat->name !== 'Other')
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <select x-model="newUnit" class="input px-2 text-sm">
                                @foreach ($units ?? [] as $u)
                                    <option value="{{ $u }}">{{ $u }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" @click="createItem" class="btn-light w-full py-2 text-sm">+ Add new item</button>
                        <p x-show="createError" x-text="createError" class="text-sm text-red-600"></p>
                    </div>
                </template>
            @endif

            <template x-if="mode !== 'purchase' && !matches.length">
                <p class="px-3 py-2.5 text-sm text-slate-500">No items found.</p>
            </template>
        </div>
    </template>
</div>
