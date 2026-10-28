{{--
    Grouped bar chart as inline SVG with a hover/tap tooltip.
    $groups: [['label' => 'Oct', 'long' => 'October 2026', 'values' => [123, 456]], ...]
    $series: [['name' => 'Investment', 'color' => '#eb6834'], ...]
    $negativeColor: optional colour for values below zero (single-series signed charts)
--}}
@php
    $W = 340; $H = 190; $padL = 46; $padR = 6; $padT = 10; $padB = 24;
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $negativeColor ??= null;

    $nice = function (float $v): float {
        if ($v <= 0) return 0;
        $p = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) if ($m * $p >= $v) return $m * $p;
        return 10 * $p;
    };
    $compact = function (float $v): string {
        $a = abs($v); $s = $v < 0 ? '-' : '';
        if ($a >= 1_000_000) return $s.rtrim(rtrim(number_format($a / 1_000_000, 1), '0'), '.').'M';
        if ($a >= 1_000) return $s.rtrim(rtrim(number_format($a / 1_000, 1), '0'), '.').'k';
        return $s.number_format($a);
    };

    $all = collect($groups)->pluck('values')->flatten();
    $top = $nice((float) max(0, $all->max() ?? 0));
    $bottom = -$nice((float) -min(0, $all->min() ?? 0));
    if ($top == 0 && $bottom == 0) $top = 1000;
    $y = fn (float $v) => $padT + ($top - $v) / ($top - $bottom) * $plotH;
    $ticks = $bottom < 0 ? [$bottom, 0, $top] : [0, $top / 2, $top];

    $n = max(count($groups), 1);
    $slot = $plotW / $n;
    $barGap = 2;
    $groupW = min($slot * 0.7, 46);
    $barW = ($groupW - $barGap * (count($series) - 1)) / count($series);
    $labelEvery = $slot < 26 ? 2 : 1;

    // Bar with rounded corners on the end away from the zero line
    $bar = function (float $x, float $w, float $v) use ($y): string {
        $y0 = $y(0); $y1 = $y($v); $h = abs($y0 - $y1);
        if ($h < 0.5) return '';
        $r = min(3, $w / 2, $h);
        if ($v >= 0) {
            return "M$x,$y0 V".($y1 + $r)." Q$x,$y1 ".($x + $r).",$y1 H".($x + $w - $r)." Q".($x + $w).",$y1 ".($x + $w).",".($y1 + $r)." V$y0 Z";
        }
        return "M$x,$y0 V".($y1 - $r)." Q$x,$y1 ".($x + $r).",$y1 H".($x + $w - $r)." Q".($x + $w).",$y1 ".($x + $w).",".($y1 - $r)." V$y0 Z";
    };

    $tips = collect($groups)->map(fn ($g, $i) => [
        'title' => $g['long'],
        'left' => round(($padL + $slot * $i + $slot / 2) / $W * 100, 2),
        'rows' => collect($series)->map(fn ($s, $k) => ['name' => $s['name'], 'color' => ($negativeColor && $g['values'][$k] < 0) ? $negativeColor : $s['color'], 'value' => money($g['values'][$k])])->all(),
    ])->all();
@endphp

<div x-data="{ tip: null, tips: @js($tips) }" class="relative" @mouseleave="tip = null">
    @if (count($series) > 1)
        <div class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
            @foreach ($series as $s)
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}</span>
            @endforeach
        </div>
    @endif

    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="block w-full" role="img" aria-label="{{ $ariaLabel ?? 'Chart' }}">
        @foreach ($ticks as $t)
            <line x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y($t) }}" y2="{{ $y($t) }}" stroke="{{ $t == 0 ? '#c3c2b7' : '#e1e0d9' }}" stroke-width="1" />
            <text x="{{ $padL - 6 }}" y="{{ $y($t) + 3.5 }}" text-anchor="end" font-size="10" fill="#898781">{{ $compact($t) }}</text>
        @endforeach

        @foreach ($groups as $i => $g)
            @php $gx = $padL + $slot * $i + ($slot - $groupW) / 2; @endphp
            <rect x="{{ $padL + $slot * $i }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" fill="#f1f5f9" :opacity="tip === {{ $i }} ? 1 : 0" />
            @foreach ($series as $k => $s)
                @php $v = (float) $g['values'][$k]; @endphp
                <path d="{{ $bar($gx + $k * ($barW + $barGap), $barW, $v) }}" fill="{{ $negativeColor && $v < 0 ? $negativeColor : $s['color'] }}" />
            @endforeach
            @if ($i % $labelEvery === ($n - 1) % $labelEvery)
                <text x="{{ $padL + $slot * $i + $slot / 2 }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="10" fill="#898781">{{ $g['label'] }}</text>
            @endif
            {{-- Hit area bigger than the bars --}}
            <rect x="{{ $padL + $slot * $i }}" y="0" width="{{ $slot }}" height="{{ $H }}" fill="transparent" class="cursor-pointer"
                  @mouseenter="tip = {{ $i }}" @click="tip = tip === {{ $i }} ? null : {{ $i }}" />
        @endforeach
    </svg>

    <template x-if="tip !== null">
        <div class="pointer-events-none absolute top-6 z-10 w-max min-w-36 -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-2 text-xs text-white shadow-lg"
             :style="`left: clamp(4.5rem, ${tips[tip].left}%, calc(100% - 4.5rem))`">
            <p class="mb-1 font-semibold" x-text="tips[tip].title"></p>
            <template x-for="row in tips[tip].rows" :key="row.name">
                <p class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" :style="`background: ${row.color}`"></span><span x-text="row.name"></span></span>
                    <span class="font-semibold tabular-nums" x-text="row.value"></span>
                </p>
            </template>
        </div>
    </template>
</div>
