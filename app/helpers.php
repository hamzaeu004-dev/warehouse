<?php

if (! function_exists('qty')) {
    // 2.500 -> "2.5", 10.000 -> "10"
    function qty($value): string
    {
        $formatted = number_format((float) $value, 3, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}

if (! function_exists('rupees_in_words')) {
    // 157578 -> "Rupees One Hundred Fifty-Seven Thousand Five Hundred Seventy-Eight Only"
    function rupees_in_words($amount): string
    {
        $n = (int) round(abs((float) $amount));
        if ($n === 0) {
            return 'Rupees Zero Only';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $belowThousand = function (int $n) use ($ones, $tens): string {
            $words = [];
            if ($n >= 100) {
                $words[] = $ones[intdiv($n, 100)].' Hundred';
                $n %= 100;
            }
            if ($n >= 20) {
                $words[] = $tens[intdiv($n, 10)].($n % 10 ? '-'.$ones[$n % 10] : '');
            } elseif ($n > 0) {
                $words[] = $ones[$n];
            }

            return implode(' ', $words);
        };

        $parts = [];
        foreach ([1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1_000 => 'Thousand', 1 => ''] as $size => $name) {
            if ($n >= $size) {
                $parts[] = trim($belowThousand(intdiv($n, $size)).' '.$name);
                $n %= $size;
            }
        }

        return 'Rupees '.implode(' ', $parts).' Only';
    }
}

if (! function_exists('money')) {
    function money($value): string
    {
        $value = (float) $value;
        $decimals = fmod($value, 1.0) == 0.0 ? 0 : 2;

        return ($value < 0 ? '-' : '').'Rs '.number_format(abs($value), $decimals);
    }
}
