<?php

use App\Http\Controllers\MappingColumnController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\TabController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/sources');
Route::redirect('/dashboard', '/sources')->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('sources', SourceController::class)->except(['create', 'edit']);
    Route::post('sources/{source}/import', [SourceController::class, 'import'])->name('sources.import');

    Route::post('sources/{source}/tabs', [TabController::class, 'store'])->name('tabs.store');
    Route::patch('tabs/{tab}', [TabController::class, 'update'])->name('tabs.update');
    Route::delete('tabs/{tab}', [TabController::class, 'destroy'])->name('tabs.destroy');
    Route::get('tabs/{tab}/headers', [TabController::class, 'headers'])->name('tabs.headers');

    Route::post('tabs/{tab}/mappings', [MappingController::class, 'store'])->name('mappings.store');
    Route::patch('mappings/{mapping}', [MappingController::class, 'update'])->name('mappings.update');
    Route::delete('mappings/{mapping}', [MappingController::class, 'destroy'])->name('mappings.destroy');

    Route::post('mappings/{mapping}/columns', [MappingColumnController::class, 'store'])->name('columns.store');
    Route::patch('columns/{column}', [MappingColumnController::class, 'update'])->name('columns.update');
    Route::delete('columns/{column}', [MappingColumnController::class, 'destroy'])->name('columns.destroy');

    Route::get('runs', [RunController::class, 'index'])->name('runs.index');
    Route::get('runs/{run}', [RunController::class, 'show'])->name('runs.show');
});

require __DIR__.'/auth.php';
