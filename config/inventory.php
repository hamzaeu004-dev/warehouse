<?php

return [
    // Stock arrives once a week; weekly reports start on this day.
    // One of: monday, tuesday, wednesday, thursday, friday, saturday, sunday
    'week_starts_on' => env('WEEK_STARTS_ON', 'monday'),
];
