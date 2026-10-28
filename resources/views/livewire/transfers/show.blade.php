<div class="space-y-4">
    {{-- Branch Delivery Amount & Details Card --}}
    <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-teal-800 via-teal-700 to-teal-900 p-5 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <span class="rounded-full bg-teal-600/70 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-teal-100">
                Delivery Challan / Invoice
            </span>
            <span class="text-xs font-medium text-teal-200">
                #{{ $transfer->invoice_no }}
            </span>
        </div>

        <div class="mt-4">
            <p class="text-xs font-medium text-teal-200">Branch Delivery Total Amount</p>
            <p class="mt-1 text-3xl sm:text-4xl font-black tracking-tight text-white">
                Rs. {{ number_format((float) $transfer->total_value) }}
            </p>
            <p class="mt-1 text-xs text-teal-100/90 italic">
                {{ rupees_in_words($transfer->total_value) }}
            </p>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 border-t border-teal-600/60 pt-4 text-xs">
            <div>
                <p class="text-teal-300 font-medium">Delivered to Branch</p>
                <a href="{{ route('branches.show', $transfer->branch) }}" wire:navigate class="mt-0.5 inline-block text-base font-bold text-white hover:underline">
                    {{ $transfer->branch->name }} ›
                </a>
                @if ($transfer->branch->contact_person)
                    <p class="text-[11px] text-teal-200">Attn: {{ $transfer->branch->contact_person }}</p>
                @endif
                @if ($transfer->branch->phone)
                    <p class="text-[11px] text-teal-200">Ph: {{ $transfer->branch->phone }}</p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-teal-300 font-medium">Dispatch Date</p>
                <p class="mt-0.5 text-sm font-bold text-white">{{ $transfer->transfer_date->format('d M Y') }}</p>
                @if ($transfer->po_no)
                    <p class="mt-1">
                        <span class="text-teal-300">Batch:</span>
                        <span class="font-bold text-white">{{ $transfer->po_no }}</span>
                    </p>
                @endif
            </div>
        </div>

        @if ($transfer->notes)
            <div class="mt-3 rounded-xl bg-teal-900/50 p-2.5 text-xs text-teal-100">
                <span class="font-semibold text-teal-300">Notes:</span> {{ $transfer->notes }}
            </div>
        @endif
    </div>

    {{-- Download & WhatsApp Share Action Buttons --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
        <a href="{{ route('transfers.invoice', $transfer) }}" target="_blank" class="flex items-center justify-center gap-2 rounded-xl bg-teal-800 hover:bg-teal-900 py-3.5 px-4 font-semibold text-white shadow-md active:scale-95 transition">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span>Download / Print Invoice PDF</span>
        </a>

        <a href="{{ $whatsappUrl }}" target="_blank" class="flex items-center justify-center gap-2 rounded-xl bg-[#25D366] hover:bg-[#20ba59] py-3.5 px-4 font-bold text-white shadow-md active:scale-95 transition">
            <svg class="h-5 w-5 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            <span>Share on WhatsApp</span>
        </a>
    </div>

    <div class="grid grid-cols-2 gap-2" x-data="{ copied: false }">
        <button type="button" @click="navigator.clipboard.writeText(@js($shareSummary)); copied = true; setTimeout(() => copied = false, 2500)" class="btn-light flex items-center justify-center gap-1.5 py-2.5 text-xs">
            <template x-if="!copied">
                <span class="flex items-center gap-1.5 text-slate-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Copy Text Summary
                </span>
            </template>
            <template x-if="copied">
                <span class="flex items-center gap-1.5 text-teal-700 font-bold">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Copied to clipboard!
                </span>
            </template>
        </button>

        <a href="{{ route('transfers.edit', $transfer) }}" wire:navigate class="btn-light flex items-center justify-center gap-1.5 py-2.5 text-xs text-slate-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z"/></svg>
            Edit Items / Rates
        </a>
    </div>

    {{-- Items Breakdown Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/70 px-4 py-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">Items Delivered ({{ $lines->count() }})</h3>
        </div>
        <div class="grid grid-cols-12 gap-2 border-b border-slate-100 py-2 pl-4 pr-9 text-xs font-semibold text-slate-500 bg-slate-50/40">
            <span class="col-span-5">Item</span>
            <span class="col-span-3 text-right">Kgs / Qty</span>
            <span class="col-span-2 text-right">Rate</span>
            <span class="col-span-2 text-right">Amount</span>
        </div>
        @foreach ($lines as $line)
            <a href="{{ route('items.show', $line->item_id) }}" wire:navigate class="row-link grid grid-cols-12 gap-2 border-b border-slate-100 px-4 py-3 text-sm">
                <span class="col-span-5">
                    <span class="font-semibold text-slate-900">{{ $line->item->name }}</span>
                    @if ($line->description) <span class="block text-xs text-slate-500">{{ $line->description }}</span> @endif
                </span>
                <span class="col-span-3 text-right">
                    <span class="font-bold text-slate-800">{{ qty($line->qty) }}</span> <span class="text-xs text-slate-500">{{ $line->item->unit }}</span>
                    @if ($line->pieces !== null) <span class="block text-xs text-slate-500">({{ qty($line->pieces) }} pcs)</span> @endif
                </span>
                <span class="col-span-2 text-right text-slate-700">{{ number_format($line->unit_cost, fmod((float) $line->unit_cost, 1.0) == 0.0 ? 0 : 2) }}</span>
                <span class="col-span-2 text-right font-bold text-teal-800">{{ money($line->total) }}</span>
            </a>
        @endforeach
        @php
            $kgs = $lines->sum(fn ($l) => $l->invoiceKgs() ?? 0);
            $pcs = $lines->sum(fn ($l) => $l->pieces ?? 0);
        @endphp
        <div class="rounded-b-2xl bg-teal-50/80 p-4 text-sm font-bold text-teal-900 border-t border-teal-100">
            <div class="flex items-center justify-between text-base">
                <span>Total Amount Delivered:</span>
                <span class="text-xl font-extrabold text-teal-800">Rs. {{ number_format((float) $transfer->total_value) }}</span>
            </div>
            @if ($kgs > 0 || $pcs > 0)
                <p class="mt-1 text-xs font-semibold text-teal-700">
                    Total Qty: {{ collect([$kgs > 0 ? qty($kgs).' kg' : null, $pcs > 0 ? qty($pcs).' pcs' : null])->filter()->implode(' · ') }}
                </p>
            @endif
        </div>
    </div>

    <div class="pt-2">
        <button wire:click="delete" wire:confirm="Delete transfer #{{ $transfer->invoice_no }}? Any non-batch stock will go back into warehouse." class="btn-light w-full text-xs text-red-600 hover:bg-red-50">
            Delete transfer slip
        </button>
    </div>
</div>
