<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ReportPdfController;
use App\Http\Controllers\TransferInvoiceController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', Livewire\Dashboard::class)->name('dashboard');

    Route::get('/items', Livewire\Items\Index::class)->name('items.index');
    Route::get('/items/{item}', Livewire\Items\Show::class)->name('items.show');

    Route::get('/purchases', Livewire\Purchases\Index::class)->name('purchases.index');
    Route::get('/purchases/new', Livewire\Purchases\Create::class)->name('purchases.create');
    Route::get('/purchases/{purchase}', Livewire\Purchases\Show::class)->name('purchases.show');
    Route::get('/purchases/{purchase}/edit', Livewire\Purchases\Create::class)->name('purchases.edit');

    Route::get('/transfers', Livewire\Transfers\Index::class)->name('transfers.index');
    Route::get('/transfers/new', Livewire\Transfers\Create::class)->name('transfers.create');
    Route::get('/transfers/{transfer}', Livewire\Transfers\Show::class)->name('transfers.show');
    Route::get('/transfers/{transfer}/edit', Livewire\Transfers\Create::class)->name('transfers.edit');
    Route::get('/transfers/{transfer}/invoice', TransferInvoiceController::class)->name('transfers.invoice');

    Route::get('/adjustments', Livewire\Adjustments\Index::class)->name('adjustments.index');
    Route::get('/adjustments/new', Livewire\Adjustments\Create::class)->name('adjustments.create');
    Route::get('/adjustments/{adjustment}', Livewire\Adjustments\Show::class)->name('adjustments.show');

    Route::get('/batches', Livewire\Batches\Index::class)->name('batches.index');
    Route::get('/batches/new', Livewire\Batches\Create::class)->name('batches.create');
    Route::get('/batches/{batch}', Livewire\Batches\Show::class)->name('batches.show');

    Route::get('/reports', Livewire\Reports::class)->name('reports');
    Route::get('/reports/pdf', ReportPdfController::class)->name('reports.pdf');
    Route::get('/analysis', Livewire\Analysis::class)->name('analysis');
    Route::get('/order-list', Livewire\OrderList::class)->name('order-list');
    Route::get('/activity', Livewire\ActivityLogPage::class)->name('activity');

    Route::get('/categories', Livewire\Categories::class)->name('categories');
    Route::get('/branches', Livewire\Branches::class)->name('branches');
    Route::get('/branches/{branch}', Livewire\BranchShow::class)->name('branches.show');
    Route::get('/account', Livewire\Account::class)->name('account');
    Route::get('/more', Livewire\More::class)->name('more');
});
