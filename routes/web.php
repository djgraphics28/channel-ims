<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Route::view('dashboard', 'dashboard')
//     ->middleware(['auth', 'verified'])
//     ->name('dashboard');

Route::middleware(['auth', 'check.active'])->group(function () {
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
    Volt::route('incentives','incentives')->name('incentives');

    Volt::route('sales-report', 'reports.sales')->name('sales.report');
    Volt::route('expenses-report', 'reports.expenses')->name('expenses.report');

});

//product updater
Route::get('product-updater', function () {
    $oldProducts = \App\Models\OldProduct::all();
    foreach ($oldProducts as $oldProduct) {
        //check if product already exists
        $product = \App\Models\Product::where('code', $oldProduct->code)->first();
        if ($product) {
            //update product
            $product->update([
                'stock' => $oldProduct->stock,
                'buying_price' => $oldProduct->buyingPrice,
                'selling_price' => $oldProduct->sellingPrice,
            ]);

            $product->product_stock()->where('branch_id', 1)->update([
                'stock' => $oldProduct->stock
            ]);
        } else {
            //create product
            $product = \App\Models\Product::create([
                'name' => $oldProduct->name,
                'code' => $oldProduct->code,
                'stock' => $oldProduct->stock,
                'buying_price' => $oldProduct->buyingPrice,
                'selling_price' => $oldProduct->sellingPrice,
                'category_id' => 1,
                'unit_id' => 1,
            ]);

            $product->product_stock()->create([
                'branch_id' => 1,
                'stock' => $oldProduct->stock
            ]);
        }
    }

})->name('product-updater');

Route::get('/down01', function () {
    Artisan::call('down');
    return 'Application is now in maintenance mode';
});

Route::get('/up00', function () {
    Artisan::call('up');
    return 'Application is now live';
});

require __DIR__ . '/auth.php';
