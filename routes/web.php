<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StorageController;

Route::get('/', [StorageController::class, 'index'])->name('storage.index');

Route::match(['GET', 'HEAD'], '/{path}', [StorageController::class, 'handleGetOrHead'])
    ->where('path', '.*')
    ->name('storage.get');

Route::put('/{path}', [StorageController::class, 'put'])
    ->where('path', '.*')
    ->name('storage.put');

Route::delete('/{path}', [StorageController::class, 'delete'])
    ->where('path', '.*')
    ->name('storage.delete');
