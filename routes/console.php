<?php

use App\Services\DataReset;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// php artisan app:clean-test-data          -> batches, purchases, invoices, adjustments, payments; stock to 0
// php artisan app:clean-test-data --all    -> also items, categories and branches (login is kept)
Artisan::command('app:clean-test-data {--all : Also delete items, categories and branches} {--force : Do not ask for confirmation}', function () {
    $what = $this->option('all')
        ? 'ALL data except the login (items, categories, branches and every transaction)'
        : 'all batches, purchases, invoices, adjustments and payments, and set stock to 0';

    if (! $this->option('force') && ! $this->confirm("This deletes {$what}. Continue?")) {
        $this->comment('Nothing deleted.');

        return;
    }

    $this->option('all') ? DataReset::everything() : DataReset::transactions();
    $this->info($this->option('all') ? 'All data cleared. Only the login is left.' : 'Test transactions cleared and stock reset to 0.');
})->purpose('Clear test data (add --all to also remove items, categories and branches)');
