<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');
});

// User administration — founders only.
Route::middleware(['auth', 'founder'])->group(function () {
    Route::get('settings/users', [UserController::class, 'index'])->name('users.index');
    Route::post('settings/users', [UserController::class, 'store'])->name('users.store');
    Route::post('settings/users/{user}/invite', [UserController::class, 'invite'])->name('users.invite');
});
