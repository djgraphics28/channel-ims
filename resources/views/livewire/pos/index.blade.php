<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Employee;
use Livewire\Attributes\Title;

new class extends Component {
    use WithPagination;

    public $perPage = 10;
    public $search = '';
    public $showModal = false;
    public $quotation;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $confirmingVoid = false;
    public $quotationToDelete;
    public $quotationToVoid;
    public $statusFilter = 'all';
    public $schemeFilter = 'all';
    public $methodFilter = 'all';
    public $voidFilter = 'active'; // Added void filter
    public $startDate = null;
    public $endDate = null;

    public $receiptModal = false;

    public $cart = [];
    public $customerSelected;
    public $tax;
    public $discount;
    public $paymentMethod;
    public $paymentScheme;
    public $paymentStatus;
    public $server;
    public $notes;
    public $receiptNumber;
    public $total;
    public $date;
    public $partialPaymentAmount;

    public function mount()
    {
        $this->startDate = now()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function confirmDelete($quotationId)
    {
        $this->quotationToDelete = $quotationId;
        $this->confirmingDelete = true;
    }

    public function confirmVoid($quotationId)
    {
        $this->quotationToVoid = $quotationId;
        $this->confirmingVoid = true;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function delete()
    {
        $quotation = Order::find($this->quotationToDelete);
        if ($quotation) {
            $quotation->delete();
            $this->dispatch('notify', 'Quotation deleted successfully!', 'success');
        }
        $this->confirmingDelete = false;
        $this->quotationToDelete = null;
    }

    public function void()
    {
        $quotation = Order::find($this->quotationToVoid);
        if ($quotation) {
            $quotation->update(['is_void' => true, 'order_number' => 'VOID-' . $quotation->order_number]); // Fixed the update syntax
            $this->dispatch('notify', 'Quotation voided successfully!', 'success');
        }
        $this->confirmingVoid = false;
        $this->quotationToVoid = null;
    }

    // public function printReceipt($orderId)
    // {
    //     $this->receiptModal = true;
    //     $order = Order::with(['order_items', 'order_items.product', 'customer', 'payment'])->findOrFail($orderId);

    //     $this->customerSelected = $order->customer_id;
    //     $this->tax = $order->tax;
    //     $this->discount = $order->discount;
    //     $this->paymentMethod = $order->payment->payment_method;
    //     $this->paymentScheme = $order->payment->payment_scheme;
    //     $this->paymentStatus = $order->payment->payment_status;
    //     $this->server = $order->assisted_by;
    //     $this->notes = $order->notes;
    //     $this->receiptNumber = $order->order_number;
    //     $this->date = $order->created_at;
    //     $this->partialPaymentAmount = $order->payment->amount_paid;

    //     foreach ($order->order_items as $item) {
    //         $this->cart[$item->product_id] = [
    //             'name' => $item->product->name,
    //             'unit' => $item->product->unit->name ?? 'pc',
    //             'price' => $item->price,
    //             'quantity' => $item->quantity,
    //         ];
    //     }

    //     $this->calculateTotal();
    // }

    public function printReceipt($orderId)
    {
        // Reset all receipt-related properties first
        $this->reset(['cart', 'customerSelected', 'tax', 'discount', 'paymentMethod', 'paymentScheme', 'paymentStatus', 'server', 'notes', 'receiptNumber', 'total', 'date', 'partialPaymentAmount']);

        $this->receiptModal = true;
        $order = Order::with(['order_items', 'order_items.product', 'customer', 'payment'])->findOrFail($orderId);

        $this->customerSelected = $order->customer_id;
        $this->tax = $order->tax;
        $this->discount = $order->discount;
        $this->paymentMethod = $order->payment->payment_method;
        $this->paymentScheme = $order->payment->payment_scheme;
        $this->paymentStatus = $order->payment->payment_status;
        $this->server = $order->assisted_by;
        $this->notes = $order->notes;
        $this->receiptNumber = $order->order_number;
        $this->date = $order->created_at;
        $this->partialPaymentAmount = $order->payment->amount_paid;

        // Reset cart before adding new items
        $this->cart = [];

        foreach ($order->order_items as $item) {
            $this->cart[$item->product_id] = [
                'name' => $item->product->name,
                'unit' => $item->product->unit->name ?? 'pc',
                'price' => $item->price,
                'quantity' => $item->quantity,
            ];
        }

        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $this->total = collect($this->cart)->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });
    }

    public function confirmAndPrint()
    {
        $this->dispatch('print-receipt');
        $this->receiptModal = false;
        $this->cart = [];
    }

    public function resetFilters()
    {
        $this->reset(['statusFilter', 'schemeFilter', 'methodFilter', 'voidFilter', 'startDate', 'endDate']);
        $this->resetPage();
    }

    #[Title('Quotations')]
    public function with(): array
    {
        $query = Order::query()
            ->withCount('order_items')
            ->with(['customer', 'payment', 'order_items.product'])
            ->where(function ($q) {
                $q->where('order_number', 'like', '%' . $this->search . '%')
                    ->orWhereHas('customer', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('order_items.product', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%')->orWhere('description', 'like', '%' . $this->search . '%');
                    });
            })
            ->where('branch_id', auth()->user()->branch_id);

        // Apply void filter
        if ($this->voidFilter === 'active') {
            $query->where('is_void', false);
        } elseif ($this->voidFilter === 'voided') {
            $query->where('is_void', true);
        }

        if ($this->statusFilter !== 'all') {
            $query->whereHas('payment', function ($q) {
                $q->where('payment_status', $this->statusFilter);
            });
        }

        if ($this->schemeFilter !== 'all') {
            $query->whereHas('payment', function ($q) {
                $q->where('payment_scheme', $this->schemeFilter);
            });
        }

        if ($this->methodFilter !== 'all') {
            $query->whereHas('payment', function ($q) {
                $q->where('payment_method', $this->methodFilter);
            });
        }

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        $dateQuery = clone $query;

        return [
            'quotations' => $query->latest()->paginate($this->perPage),
            'statusCounts' => [
                'all' => $dateQuery->count(),
                'paid' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_status', 'paid'))->count(),
                'not-paid' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_status', 'not-paid'))->count(),
            ],
            'schemeCounts' => [
                'all' => $dateQuery->count(),
                'full-payment' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_scheme', 'full-payment'))->count(),
                'partial-payment' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_scheme', 'partial-payment'))->count(),
            ],
            'methodCounts' => [
                'all' => $dateQuery->count(),
                'cash' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_method', 'cash'))->count(),
                'cod' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_method', 'cod'))->count(),
                'sign' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_method', 'sign'))->count(),
                'refund' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_method', 'refund'))->count(),
                'returned' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_method', 'returned'))->count(),
                'sales-only' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_method', 'sales-only'))->count(),
                'delivery-only' => (clone $dateQuery)->whereHas('payment', fn($q) => $q->where('payment_method', 'delivery-only'))->count(),
            ],
            'voidCounts' => [
                'all' => $dateQuery->count(),
                'active' => (clone $dateQuery)->where('is_void', false)->count(),
                'voided' => (clone $dateQuery)->where('is_void', true)->count(),
            ],
        ];
    }
};

?>

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
                        <span
                            class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">Quotations</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between">
            <div class="w-1/3">
                <input wire:model.live="search" type="search" placeholder="Search quotations..."
                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
            </div>

            <div class="flex items-center space-x-2">
                <select wire:model.live="perPage"
                    class="rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
                    <option value="5">5 rows</option>
                    <option value="10">10 rows</option>
                    <option value="25">25 rows</option>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                    <option value="500">500 rows</option>
                </select>
                <button wire:click="resetFilters"
                    class="inline-flex items-center justify-center rounded-lg bg-gray-200 dark:bg-gray-700 px-4 py-2 text-sm font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600">
                    Reset Filters
                </button>
                <a href="{{ route('pos') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 dark:bg-green-500 dark:hover:bg-green-600">
                    Create Quotation
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Status Filter -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Status</h3>
                <div class="flex flex-wrap gap-1">
                    <button wire:click="$set('statusFilter', 'all'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $statusFilter === 'all' ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        All ({{ $statusCounts['all'] }})
                    </button>
                    <button wire:click="$set('statusFilter', 'paid'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $statusFilter === 'paid' ? 'bg-green-100 dark:bg-green-900 border-green-300 dark:border-green-700 text-green-800 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Paid ({{ $statusCounts['paid'] }})
                    </button>
                    <button wire:click="$set('statusFilter', 'not-paid'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $statusFilter === 'not-paid' ? 'bg-yellow-100 dark:bg-yellow-900 border-yellow-300 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Not-Paid ({{ $statusCounts['not-paid'] }})
                    </button>
                </div>
            </div>

            <!-- Payment Scheme Filter -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Scheme</h3>
                <div class="flex flex-wrap gap-1">
                    <button wire:click="$set('schemeFilter', 'all'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $schemeFilter === 'all' ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        All ({{ $schemeCounts['all'] }})
                    </button>
                    <button wire:click="$set('schemeFilter', 'full-payment'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $schemeFilter === 'full-payment' ? 'bg-green-100 dark:bg-green-900 border-green-300 dark:border-green-700 text-green-800 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Full Payment ({{ $schemeCounts['full-payment'] }})
                    </button>
                    <button wire:click="$set('schemeFilter', 'partial-payment'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $schemeFilter === 'partial-payment' ? 'bg-purple-100 dark:bg-purple-900 border-purple-300 dark:border-purple-700 text-purple-800 dark:text-purple-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Partial Payment ({{ $schemeCounts['partial-payment'] }})
                    </button>
                </div>
            </div>

            <!-- Payment Method Filter -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Method</h3>
                <div class="flex flex-wrap gap-1">
                    <button wire:click="$set('methodFilter', 'all'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $methodFilter === 'all' ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        All ({{ $methodCounts['all'] }})
                    </button>
                    <button wire:click="$set('methodFilter', 'cash'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $methodFilter === 'cash' ? 'bg-green-100 dark:bg-green-900 border-green-300 dark:border-green-700 text-green-800 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Cash ({{ $methodCounts['cash'] }})
                    </button>
                    <button wire:click="$set('methodFilter', 'cod'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $methodFilter === 'cod' ? 'bg-yellow-100 dark:bg-yellow-900 border-yellow-300 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        COD ({{ $methodCounts['cod'] }})
                    </button>
                    <button wire:click="$set('methodFilter', 'sign'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $methodFilter === 'sign' ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Sign ({{ $methodCounts['sign'] }})
                    </button>
                    <button wire:click="$set('methodFilter', 'refund'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $methodFilter === 'refund' ? 'bg-indigo-100 dark:bg-indigo-900 border-indigo-300 dark:border-indigo-700 text-indigo-800 dark:text-indigo-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Refund ({{ $methodCounts['refund'] }})
                    </button>
                    <button wire:click="$set('methodFilter', 'returned'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $methodFilter === 'returned' ? 'bg-red-100 dark:bg-red-900 border-red-300 dark:border-red-700 text-red-800 dark:text-red-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Returned ({{ $methodCounts['returned'] }})
                    </button>
                    <button wire:click="$set('methodFilter', 'sales-only'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $methodFilter === 'sales-only' ? 'bg-green-100 dark:bg-green-900 border-green-300 dark:border-green-700 text-green-800 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Sales Only ({{ $methodCounts['sales-only'] }})
                    </button>
                    <button wire:click="$set('methodFilter', 'delivery-only'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $methodFilter === 'delivery-only' ? 'bg-green-100 dark:bg-green-900 border-green-300 dark:border-green-700 text-green-800 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Delivery Only ({{ $methodCounts['delivery-only'] }})
                    </button>
                </div>
            </div>

            <!-- Date Range and Void Filter -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Order Status</h3>
                <div class="flex flex-wrap gap-1 mb-2">
                    <button wire:click="$set('voidFilter', 'all'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $voidFilter === 'all' ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        All ({{ $voidCounts['all'] }})
                    </button>
                    <button wire:click="$set('voidFilter', 'active'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $voidFilter === 'active' ? 'bg-green-100 dark:bg-green-900 border-green-300 dark:border-green-700 text-green-800 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Active ({{ $voidCounts['active'] }})
                    </button>
                    <button wire:click="$set('voidFilter', 'voided'), $resetPage()"
                        class="px-3 py-1 text-xs rounded-full border {{ $voidFilter === 'voided' ? 'bg-red-100 dark:bg-red-900 border-red-300 dark:border-red-700 text-red-800 dark:text-red-200' : 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200' }}">
                        Voided ({{ $voidCounts['voided'] }})
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-2">
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">From</label>
                        <input type="date" wire:model.live="startDate"
                            class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">To</label>
                        <input type="date" wire:model.live="endDate"
                            class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
                    </div>
                </div>
            </div>
        </div>

        @if ($quotations->isEmpty())
            <div class="flex flex-col items-center justify-center p-8">
                <p class="mb-4 text-gray-500 dark:text-gray-400">No quotations found</p>
                <a href="{{ route('pos') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-3 text-sm font-medium text-white transition-all duration-200 ease-in-out hover:bg-green-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 active:bg-green-800 dark:bg-green-500 dark:hover:bg-green-600 dark:focus:ring-green-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="my-auto mr-2 h-5 w-5" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                            clip-rule="evenodd" />
                    </svg>
                    Create Quotation
                </a>
            </div>
        @else
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Quotation #</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Customer Name</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Amount</th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Amount Paid</th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Partial Payment</th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Balance</th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Payment Method</th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Payment Scheme</th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Status</th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Order Items</th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Created At</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @foreach ($quotations as $quotation)
                                <tr
                                    class="{{ $quotation->is_void ? 'bg-red-50 dark:bg-red-900/20' : 'dark:hover:bg-gray-800' }}">
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                        {{ $quotation->order_number }}
                                        @if ($quotation->is_void)
                                            <span class="ml-1 text-xs text-red-500 dark:text-red-400">(VOIDED)</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                        {{ strtoupper($quotation->customer->name ?? '') }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                        ₱{{ number_format($quotation->total_amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                        ₱{{ number_format($quotation->payment->amount_paid, 2) ?? '' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                        ₱{{ $quotation->payment->payment_scheme == 'full-payment' ? '0.00' : number_format($quotation->payment->amount_paid, 2) ?? '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                        ₱{{ number_format($quotation->total_amount - $quotation->payment->amount_paid, 2) ?? 0 }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                        {{ strtoupper($quotation->payment->payment_method ?? '') }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                        {{ strtoupper($quotation->payment->payment_scheme ?? '') }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-center">
                                        @php
                                            $status = strtolower($quotation->payment->payment_status ?? '');
                                            $statusClasses = [
                                                'paid' =>
                                                    'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
                                                'pending' =>
                                                    'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
                                                'partial' =>
                                                    'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200',
                                                'cancelled' =>
                                                    'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
                                            ];
                                            $class =
                                                $statusClasses[$status] ??
                                                'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200';
                                        @endphp
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $class }}">
                                            {{ strtoupper($status) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                        {{ $quotation->order_items_count ?? '' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                        {{ $quotation->created_at->format('M d, Y h:i A') }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 space-x-2">
                                        @if (!$quotation->is_void)
                                            @can('quotations.void')
                                                <button wire:click="confirmVoid({{ $quotation->id }})"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 cursor-pointer">Void</button>
                                            @endcan
                                            @can('quotations.edit')
                                                <a href="{{ route('pos.edit', $quotation->id) }}"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 cursor-pointer">Edit</a>
                                            @endcan
                                        @endif

                                        @can('quotations.delete')
                                            <button wire:click="confirmDelete({{ $quotation->id }})"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 cursor-pointer">Delete</button>
                                        @endcan
                                        <button wire:click="printReceipt({{ $quotation->id }})"
                                            class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 cursor-pointer">Print</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">
                {{ $quotations->links() }}
            </div>
        @endif
    </div>

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
                                    Delete Quotation
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to delete this quotation? This action cannot be undone.
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

    @if ($confirmingVoid)
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
                                    Void Quotation
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to void this quotation? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button wire:click="void"
                            class="inline-flex w-full justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-red-400 sm:ml-3 sm:w-auto sm:text-sm">
                            Void
                        </button>
                        <button wire:click="$set('confirmingVoid', false)"
                            class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Receipt Modal -->
    @if ($receiptModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white dark:bg-gray-800 p-8 rounded-lg w-[13cm]" id="printable-receipt">
                <!-- Receipt Header -->
                {{-- <div class="text-center mb-4">
             <h2 class="text-xl font-bold dark:text-white">Company Name</h2>
             <p class="text-sm dark:text-gray-300">123 Business Street</p>
             <p class="text-sm dark:text-gray-300">Phone: (123) 456-7890</p>
             <p class="text-sm dark:text-gray-300">Receipt #: {{ $this->receiptNumber }}</p>
             <p class="text-sm dark:text-gray-300">Date: {{ now()->format('M d, Y') }}</p>
         </div> --}}
                <br>
                <br>
                <br>

                <!-- Header -->
                <div class="mb-2">
                    <div class="flex justify-end">
                        <small>{{ $date->format('m/d/Y H:i') }}</small>
                    </div>
                    <div class="flex justify-start">
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <small>{{ Customer::find($customerSelected)?->name ?? '-----' }}</small>
                    </div>
                    <div class="flex justify-start">
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <small>{{ Customer::find($customerSelected)?->address ?? '-----' }}</small>
                    </div>
                    <div class="flex justify-start">
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <small>{{ Customer::find($customerSelected)?->phone ?? '-----' }}</small>
                    </div>

                    <div class="flex justify-end">
                        <small>Cashier: {{ Auth::user()?->name ?? '-----' }}</small>
                    </div>

                    <div class="flex justify-end">
                        <small>Server: {{ Employee::find($server)?->first_name ?? '-----' }}</small>
                    </div>
                </div>

                <!-- Items -->
                <div class="border-t border-b border-gray-200 dark:border-gray-700 py-2 mb-4">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-right dark:text-gray-300"></th>
                                <th class="text-left dark:text-gray-300"></th>
                                <th class="text-center dark:text-gray-300"><small>Product Name</small></th>
                                <th class="text-right dark:text-gray-300"><small>Unit Price</small></th>
                                <th class="text-right dark:text-gray-300"><small>Amount</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cart as $item)
                                <tr>
                                    <td class="text-center dark:text-gray-300">
                                        <small>{{ fmod($item['quantity'], 1) ? number_format($item['quantity'], 2) : number_format($item['quantity'], 0) }}</small>
                                    </td>
                                    <td class="text-center dark:text-gray-300"><small>{{ $item['unit'] }}</small></td>
                                    <td class="text-center dark:text-gray-300"><small>{{ $item['name'] }}</small></td>
                                    <td class="text-right dark:text-gray-300">
                                        <small>₱{{ number_format($item['price'], 2) }}</small>
                                    </td>
                                    <td class="text-right dark:text-gray-300">
                                        <small>₱{{ number_format($item['price'] * $item['quantity'], 2) }}</small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="space-y-1 mb-4 text-right">
                    <div class="flex justify-end text-sm">
                        <small>
                            <span class="mr-4 dark:text-gray-300">Sub-Total:</span>
                            <span class="dark:text-gray-300">₱{{ number_format($total, 2) }}</span>
                        </small>

                    </div>
                    <div class="flex justify-end text-sm">
                        <small>
                            <span class="mr-4 dark:text-gray-300">Tax ({{ $tax }}%):</span>
                            <span class="dark:text-gray-300">₱{{ number_format($total * ($tax / 100), 2) }}</span>
                        </small>

                    </div>
                    <div class="flex justify-end text-sm">
                        <small>
                            <span class="mr-4 dark:text-gray-300">Discount <i>(less)</i>:</span>
                            <span class="dark:text-gray-300">₱{{ number_format($discount, 2) }}</span>
                        </small>

                    </div>
                    @if ($paymentScheme == 'partial-payment')
                        <div class="flex justify-end text-sm">
                            <small>
                                <span class="mr-4 dark:text-gray-300">Partial Payment:</span>
                                <span class="dark:text-gray-300">₱{{ number_format($partialPaymentAmount, 2) }}</span>
                            </small>
                        </div>
                    @endif
                    @if ($paymentMethod == 'returned')
                        <div class="flex justify-end text-sm">
                            <small>
                                <span class="mr-4 dark:text-gray-300">Returned:</span>
                                <span
                                    class="dark:text-gray-300">₱{{ number_format($total + $total * ($tax / 100) - $discount, 2) }}</span>
                            </small>
                        </div>
                    @endif
                    <div class="flex justify-end font-bold">
                        <small>
                            <span class="mr-4 dark:text-gray-300">Total Amount Due:</span>
                            <span
                                class="dark:text-gray-300">₱{{ number_format($total + $total * ($tax / 100) - $discount, 2) }}</span>
                        </small>
                    </div>
                    @if ($paymentScheme == 'partial-payment')
                        <div class="flex justify-end font-bold">
                            <small>
                                <span class="mr-4 dark:text-gray-300">Balance Due:</span>
                                <span
                                    class="dark:text-gray-300">₱{{ number_format($total + $total * ($tax / 100) - $discount - $partialPaymentAmount, 2) }}</span>
                            </small>
                        </div>
                    @endif
                </div>

                {{-- general notes --}}
                <div class="space-y-1 mb-4 text-left">
                    <div class="flex justify-start text-sm">
                        <span class="mr-4 dark:text-gray-300">General Notes:</span>
                    </div>
                    <div class="flex justify-start text-sm">
                        <span class="dark:text-gray-300"><small>{{ $notes }}</small></span>
                    </div>
                    <br>
                    <div class="text-sm">
                        <span><small>Payment Method: {{ strtoupper($paymentMethod) }}</small></span><br>
                        <span><small>Payment Scheme: {{ strtoupper($paymentScheme) }}</small></span><br>
                        <span><small>Payment Status: {{ strtoupper($paymentStatus) }}</small></span>
                    </div>
                </div>
                <br>
                <br>
                <br>
                <p class="text-sm dark:text-gray-300 text-right"><strong>Receipt #: {{ $receiptNumber }}</strong></p>

                <!-- Buttons -->
                <div class="mt-4 flex justify-end gap-2 print:hidden">
                    <button wire:click="confirmAndPrint"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        Confirm & Print
                    </button>
                    <button wire:click="$set('receiptModal', false)"
                        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    window.addEventListener('print-receipt', () => {
        let printContents = document.getElementById('printable-receipt').innerHTML;
        let originalContents = document.body.innerHTML;

        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;

        // Redirect to /quotations after printing
        setTimeout(() => {
            window.location.href = '/quotations';
        }, 500);
    });
</script>
