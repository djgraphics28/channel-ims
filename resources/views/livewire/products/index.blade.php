<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use Livewire\Attributes\Title;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $showAddStockModal = false;
    public $product;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $productToDelete;
    public $pageRows = 10;

    public $category_id;
    public $code;
    public $name;
    public $description;
    public $unit_id;
    public $stock;
    public $buying_price;
    public $selling_price;
    public $created_by;
    public $updated_by;
    public $stock_quantity = '';
    public $addStockProduct = '';
    public $stockProductCurrentStock = 0;

    public $selectedCategory = '';
    public $selectedUnit = '';

    public function rules()
    {
        return [
            'category_id' => 'required|string',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_id' => 'required|string',
            'stock' => 'required|integer|min:0',
            'buying_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'created_by' => 'nullable|string',
            'updated_by' => 'nullable|string',
        ];
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(Product $product)
    {
        $this->product = $product;
        $this->category_id = $product->category_id;
        $this->code = $product->code;
        $this->name = $product->name;
        $this->description = $product->description;
        $this->unit_id = $product->unit_id;
        $this->stock = $product->stock;
        $this->buying_price = $product->buying_price;
        $this->selling_price = $product->selling_price;
        $this->created_by = $product->created_by;
        $this->updated_by = $product->updated_by;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function addStock($productId)
    {
        $this->stock_quantity = '';
        $this->showAddStockModal = true;
        $product = Product::find($productId);
        $this->product = $product;
        $this->addStockProduct = $product->name;
        $this->stockProductCurrentStock = $product->stock;
    }

    public function saveStock()
    {
        $this->product->increment('stock', $this->stock_quantity);
        $this->product->stocks()->create([
            'quantity' => $this->stock_quantity,
        ]);

        flash()->success('Product stocks added successfully!');

        $this->showAddStockModal = false;
    }

    public function save()
    {
        $validatedData = $this->validate();

        if ($this->isEditing) {
            $this->product->update($validatedData);
            flash()->success('Product updated successfully!');
        } else {
            Product::create($validatedData);
            flash()->success('Product created successfully!');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete($productId)
    {
        $this->productToDelete = $productId;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        $product = Product::find($this->productToDelete);
        if ($product) {
            $product->delete();
            flash()->success('Product deleted successfully!');
        }

        $this->confirmingDelete = false;
        $this->productToDelete = null;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingPageRows()
    {
        $this->resetPage();
    }

    private function resetForm()
    {
        $this->category_id = '';
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->unit_id = '';
        $this->stock = 0;
        $this->buying_price = 0;
        $this->selling_price = 0;
        $this->created_by = '';
        $this->updated_by = '';
        $this->product = null;
    }

    #[Title('Products')]
    public function with(): array
    {
        $query = Product::query()->where('name', 'like', '%' . $this->search . '%');

        if ($this->selectedCategory) {
            $query->where('category_id', $this->selectedCategory);
        }

        if ($this->selectedUnit) {
            $query->where('unit_id', $this->selectedUnit);
        }

        return [
            'products' => $query->paginate($this->pageRows),
            'categories' => Category::all(),
            'units' => Unit::all(),
        ];
    }
}; ?>

<div>
    <div class="mb-4">
        <nav class="flex justify-end" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('dashboard') }}"
                        class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400">
                        Dashboard
                    </a>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-3 h-3 mx-1 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 9 4-4-4-4" />
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">Products</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between">
            <div class="w-1/3">
                <input wire:model.live="search" type="search" placeholder="Search products..."
                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
            </div>
            <div class="flex items-center space-x-2">
                <select wire:model.live="selectedCategory"
                    class="rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="selectedUnit"
                    class="rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                    <option value="">All Units</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="pageRows"
                    class="rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
        </div>
        @if ($products->isEmpty())
            <div class="flex flex-col items-center justify-center p-8">
                <p class="mb-4 text-gray-500 dark:text-gray-400">No products found</p>
                <button wire:click="create"
                    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-3 text-sm font-medium text-white transition-all duration-200 ease-in-out hover:bg-green-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 active:bg-green-800 dark:bg-green-500 dark:hover:bg-green-600 dark:focus:ring-green-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="my-auto mr-2 h-5 w-5" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                            clip-rule="evenodd" />
                    </svg>
                    Add Product
                </button>
            </div>
        @else
            <div class="flex justify-end">
                <button wire:click="create"
                    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 dark:bg-green-500 dark:hover:bg-green-600">
                    Add Product
                </button>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-4 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Code</th>
                                <th
                                    class="px-4 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Name</th>
                                <th
                                    class="px-4 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Category</th>
                                <th
                                    class="px-4 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Unit</th>
                                <th
                                    class="hidden sm:table-cell px-4 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Stock</th>
                                <th
                                    class="hidden sm:table-cell px-4 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Buying Price</th>
                                <th
                                    class="hidden sm:table-cell px-4 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Selling Price</th>
                                <th
                                    class="px-4 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @foreach ($products as $product)
                                <tr x-data="{ open: false }" class="dark:hover:bg-gray-800">
                                    <td class="px-4 sm:px-6 py-2 sm:py-4 dark:text-gray-300 text-sm">
                                        {{ $product->code }}</td>
                                    <td class="px-4 sm:px-6 py-2 sm:py-4 dark:text-gray-300 text-sm">
                                        <button @click="open = !open"
                                            class="sm:hidden text-left w-full">{{ $product->name }}</button>
                                        <span class="hidden sm:inline">{{ $product->name }}</span>
                                        <div x-show="open" class="sm:hidden mt-2 space-y-2">
                                            <p><span class="font-medium">Stock:</span> {{ $product->stock }}</p>
                                            <p><span class="font-medium">Buying Price:</span>
                                                <strong>₱{{ $product->buying_price }}</strong>
                                            </p>
                                            <p><span class="font-medium">Selling Price:</span>
                                                <strong>₱{{ $product->selling_price }}</strong>
                                            </p>
                                        </div>
                                    </td>
                                    <td
                                        class="hidden sm:table-cell px-4 sm:px-6 py-2 sm:py-4 dark:text-gray-300 text-sm">
                                        {{ $product->category->name ?? 'not yet set' }}</td>
                                    <td
                                        class="hidden sm:table-cell px-4 sm:px-6 py-2 sm:py-4 dark:text-gray-300 text-sm">
                                        {{ $product->unit->name  ?? 'not yet set' }}</td>
                                    <td
                                        class="hidden sm:table-cell px-4 sm:px-6 py-2 sm:py-4 dark:text-gray-300 text-sm">
                                        {{ $product->stock }}</td>
                                    <td
                                        class="hidden sm:table-cell px-4 sm:px-6 py-2 sm:py-4 dark:text-gray-300 text-sm">
                                        <strong>₱{{ $product->buying_price }}</strong>
                                    </td>
                                    <td
                                        class="hidden sm:table-cell px-4 sm:px-6 py-2 sm:py-4 dark:text-gray-300 text-sm">
                                        <strong>₱{{ $product->selling_price }}</strong>
                                    </td>
                                    <td class="px-4 sm:px-6 py-2 sm:py-4 space-x-2 text-sm">
                                        <button wire:click="edit({{ $product->id }})"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 cursor-pointer">Edit</button>
                                        <button wire:click="confirmDelete({{ $product->id }})"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 cursor-pointer">Delete</button>
                                        <button wire:click="addStock({{ $product->id }})"
                                            class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 cursor-pointer">Add
                                            Stock</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    @if ($showAddStockModal)
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 dark:bg-gray-800 opacity-75"></div>
                </div>
                <div
                    class="inline-block transform overflow-hidden rounded-lg bg-white dark:bg-gray-900 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form wire:submit="saveStock">
                        <div class="bg-white dark:bg-gray-900 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="mb-4">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Additional
                                    Stock for {{ $addStockProduct }} | Current Stock:
                                    {{ $stockProductCurrentStock }}</label>
                                <input wire:model="stock_quantity" type="number" required
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                @error('stock_quantity')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="submit"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-blue-400 sm:ml-3 sm:w-auto sm:text-sm">
                                Add Stock
                            </button>
                            <button type="button" wire:click="$set('showAddStockModal', false)"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    @if ($showModal)
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 dark:bg-gray-800 opacity-75"></div>
                </div>
                <div
                    class="inline-block transform overflow-hidden rounded-lg bg-white dark:bg-gray-900 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-900 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="mb-4">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                                <select wire:model="category_id"
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code</label>
                                <input wire:model="code" type="text"
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                @error('code')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                <input wire:model="name" type="text"
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                @error('name')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea wire:model="description"
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100"></textarea>
                                @error('description')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unit</label>
                                <select wire:model="unit_id"
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    <option value="">Select Unit</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                    @endforeach
                                </select>
                                @error('unit_id')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stock</label>
                                <input wire:model="stock" type="number" required
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                @error('stock')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buying
                                    Price</label>
                                <input wire:model="buying_price" type="number" step="0.01" required
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                @error('buying_price')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Selling
                                    Price</label>
                                <input wire:model="selling_price" type="number" step="0.01" required
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                @error('selling_price')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="submit"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-blue-400 sm:ml-3 sm:w-auto sm:text-sm">
                                {{ $isEditing ? 'Update' : 'Create' }}
                            </button>
                            <button type="button" wire:click="$set('showModal', false)"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmingDelete)
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 dark:bg-gray-800 opacity-75"></div>
                </div>
                <div
                    class="inline-block transform overflow-hidden rounded-lg bg-white dark:bg-gray-900 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <div class="bg-white dark:bg-gray-900 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                                    Delete Product
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to delete this product? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button wire:click="delete"
                            class="inline-flex w-full justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-red-400 sm:ml-3 sm:w-auto sm:text-sm">
                            Delete
                        </button>
                        <button wire:click="$set('confirmingDelete', false)"
                            class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div x-data="{ show: false, message: '', type: '' }" x-show="show" x-transition x-init="Livewire.on('notify', (msg, type) => {
        message = msg;
        type = type;
        show = true;
        setTimeout(() => show = false, 3000);
    })"
        class="fixed top-5 right-5 px-4 py-2 rounded-lg shadow-md text-white"
        :class="type === 'success' ? 'bg-green-500' : 'bg-red-500'">
        <span x-text="message"></span>
    </div>
</div>
