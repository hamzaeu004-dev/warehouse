@props(['status'])
{{-- Paid / Partly paid / Payment pending / Overdue, with an icon so it isn't colour alone --}}
@php
    $icon = match (true) {
        $status->status === 'paid' => 'M5 13l4 4L19 7',
        $status->overdue => 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
        default => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    };
@endphp
<span {{ $attributes->merge(['class' => \App\Services\InvoiceStatus::badgeClass($status).' whitespace-nowrap']) }}>
    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
    {{ \App\Services\InvoiceStatus::label($status) }}
</span>
