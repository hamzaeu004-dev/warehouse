@php $s = $report['summary']; @endphp

<div class="space-y-4">
    <div class="card space-y-3">
        <div>
            <h2 class="card-title">Report for the owner</h2>
            <p class="text-xs text-slate-500">Purchases, stock delivered to branches and wastage. Download it as a PDF or share it straight to WhatsApp.</p>
        </div>
        <div class="-mx-1 flex flex-wrap gap-1.5">
            @foreach ($periods as $key => $label)
                <button wire:click="setPeriod('{{ $key }}')"
                        class="chip rounded-full px-3 py-1.5 text-sm font-medium {{ $period === $key ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ $label }}</button>
            @endforeach
        </div>
        @if ($period === 'custom')
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="from">From</label>
                    <input id="from" wire:model.live="from" type="date" max="{{ now()->toDateString() }}" class="input">
                </div>
                <div>
                    <label class="label" for="to">To</label>
                    <input id="to" wire:model.live="to" type="date" max="{{ now()->toDateString() }}" class="input">
                </div>
            </div>
        @endif
        <p class="text-sm font-semibold text-slate-700">{{ $report['from']->format('d M Y') }} – {{ $report['to']->format('d M Y') }}</p>
    </div>

    {{-- The three numbers the owner asks about --}}
    <div class="grid grid-cols-1 gap-3">
        @foreach ([
            ['Purchased', $s['purchased'], $s['purchase_count'].' '.Str::plural('purchase', $s['purchase_count']), 'bg-teal-100 text-teal-700', 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z', 'text-slate-900'],
            ['Delivered to branches', $s['delivered'], $s['invoice_count'].' '.Str::plural('invoice', $s['invoice_count']).' · stock cost '.money($s['delivered_cost']), 'bg-sky-100 text-sky-700', 'M9 17h6M9 13h6M9 9h1m-4 12h12a2 2 0 002-2V7.4a2 2 0 00-.6-1.4l-3.4-3.4a2 2 0 00-1.4-.6H6a2 2 0 00-2 2v15a2 2 0 002 2z', 'text-slate-900'],
            ['Wastage', $s['wastage'], 'Wasted, expired or damaged', 'bg-red-100 text-red-700', 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z', 'text-red-700'],
        ] as [$label, $value, $hint, $color, $icon, $valueColor])
            <div class="card flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $color }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="eyebrow">{{ $label }}</p>
                    <p class="text-xl font-bold tracking-tight {{ $valueColor }}">{{ money($value) }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $hint }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Download / share --}}
    <div class="card space-y-2"
         x-data="{
            sharing: false,
            canShare: !!(navigator.canShare && navigator.share),
            async share() {
                this.sharing = true;
                try {
                    const res = await fetch(@js($pdfUrl), { credentials: 'same-origin' });
                    const file = new File([await res.blob()], @js($fileName), { type: 'application/pdf' });
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        await navigator.share({ files: [file], title: 'Warehouse report' });
                    } else {
                        window.location.href = @js($pdfUrl);
                    }
                } catch (e) {
                    // Share sheet closed or not supported
                } finally {
                    this.sharing = false;
                }
            },
         }">
        <a href="{{ $pdfUrl }}" class="btn-primary w-full" download="{{ $fileName }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M7 21h10a2 2 0 002-2V9.4a1 1 0 00-.3-.7l-5.4-5.4a1 1 0 00-.7-.3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            Download PDF
        </a>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" @click="share" :disabled="sharing" x-show="canShare" class="btn-light">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.7 13.3l6.6 3.4m0-9.4l-6.6 3.4M21 5a3 3 0 11-6 0 3 3 0 016 0zM9 12a3 3 0 11-6 0 3 3 0 016 0zm12 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span x-text="sharing ? 'Preparing…' : 'Share'"></span>
            </button>
            <a href="{{ $viewUrl }}" target="_blank" class="btn-light" :class="canShare ? '' : 'col-span-2'">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12z"/><circle cx="12" cy="12" r="3"/></svg>
                View
            </a>
        </div>
        <p class="text-xs text-slate-500">"Share" opens your phone's share menu so you can send the PDF on WhatsApp or email.</p>
    </div>

    {{-- Quick look at what is in the PDF --}}
    <div class="card p-0">
        <h2 class="card-title px-4 pb-1 pt-4">Delivered by branch</h2>
        @forelse ($report['deliveredByBranch'] as $b)
            <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <div>
                    <p class="text-sm font-medium">{{ $b->name }}</p>
                    <p class="text-xs text-slate-500">{{ $b->invoices }} {{ Str::plural('invoice', $b->invoices) }}</p>
                </div>
                <span class="font-semibold">{{ money($b->amount) }}</span>
            </div>
        @empty
            <p class="px-4 pb-4 text-sm text-slate-500">Nothing delivered in this period.</p>
        @endforelse
    </div>

    <div class="card p-0">
        <h2 class="card-title px-4 pb-1 pt-4">Wastage</h2>
        @forelse ($report['wastage'] as $w)
            <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">{{ $w->name }}</p>
                    <p class="text-xs text-slate-500">{{ $w->reason_label }} · {{ qty($w->qty) }} {{ $w->unit }}</p>
                </div>
                <span class="shrink-0 font-semibold text-red-700">{{ money($w->value) }}</span>
            </div>
        @empty
            <p class="px-4 pb-4 text-sm text-slate-500">No wastage in this period.</p>
        @endforelse
    </div>
</div>
