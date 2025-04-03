<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Route::view('dashboard', 'dashboard')
//     ->middleware(['auth', 'verified'])
//     ->name('dashboard');

Route::middleware(['auth','check.active'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Volt::route('users', 'users.index')->name('users');
    Volt::route('roles', 'roles.index')->name('roles');
    Volt::route('branches', 'branches.index')->name('branches');
    Volt::route('categories', 'categories.index')->name('categories');
    Volt::route('units', 'units.index')->name('units');
    Volt::route('customers', 'customers.index')->name('customers');
    Volt::route('products', 'products.index')->name('products');
    Volt::route('quotations', 'pos.index')->name('quotations');
    Volt::route('pos', 'pos.create')->name('pos');
    Volt::route('pos/{orderId}', 'pos.edit')->name('pos.edit');
    Volt::route('cashflows', 'cashflow.index')->name('cashflows');
    Volt::route('employees', 'employees.index')->name('employees');

});

require __DIR__.'/auth.php';
