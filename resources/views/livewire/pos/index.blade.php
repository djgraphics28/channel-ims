<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Order;
use App\Models\Customer;
use App\Models\User;
use Livewire\Attributes\Title;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $quotation;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $quotationToDelete;

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

    public function confirmDelete($quotationId)
    {
        $this->quotationToDelete = $quotationId;
        $this->confirmingDelete = true;
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

    public function printReceipt($orderId)
    {
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

        foreach ($order->order_items as $item) {
            $this->cart[$item->product_id] = [
                'name' => $item->product->name,
                'unit' => $item->product->unit_id,
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

    #[Title('Quotations')]
    public function with(): array
    {
        return [
            'quotations' => Order::query()
                ->withCount('order_items')
                ->where('order_number', 'like', '%' . $this->search . '%')
                ->latest()
                ->paginate(10),
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
            <div class="flex justify-end">
                <a href="{{ route('pos') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 dark:bg-green-500 dark:hover:bg-green-600">
                    Create Quotation
                </a>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
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
                                Payment Method</th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Payment Scheme</th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Payment Status</th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Order Items</th>

                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach ($quotations as $quotation)
                            <tr class="dark:hover:bg-gray-800">
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ $quotation->order_number }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ strtoupper($quotation->customer->name ?? '') }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    Php{{ number_format($quotation->total_amount, 2) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                    {{ strtoupper($quotation->payment->payment_method ?? '') }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                    {{ strtoupper($quotation->payment->payment_scheme ?? '') }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                    {{ strtoupper($quotation->payment->payment_status ?? '') }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300 text-center">
                                    {{ $quotation->order_items_count ?? '' }}</td>
                                {{-- <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $quotation->status === 'approved'
                                            ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300'
                                            : ($quotation->status === 'rejected'
                                                ? 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300'
                                                : 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300') }}">
                                        {{ ucfirst($quotation->status) }}
                                    </span>
                                </td> --}}
                                <td class="whitespace-nowrap px-6 py-4 space-x-2">
                                    <a href="{{ route('pos.edit', $quotation->id) }}"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 cursor-pointer">Edit</a>
                                    <button wire:click="confirmDelete({{ $quotation->id }})"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 cursor-pointer">Delete</button>
                                    <button wire:click="printReceipt({{ $quotation->id }})"
                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 cursor-pointer">Print</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
                <div class="mb-4">
                    <table class="text-sm border-gray-200">
                        <tr>
                            <td width="15%" class="dark:text-gray-300">&nbsp;</td>
                            <td width="35%" class="dark:text-gray-300">&nbsp;</td>
                            <td width="35%" class="dark:text-gray-300">&nbsp;</td>
                            <td width="20%" class="dark:text-gray-300 text-right">{{ now()->format('M d, Y') }}
                            </td>
                        </tr>
                        <tr>
                            <td width="15%" class="dark:text-gray-300"></td>
                            <td width="35%"class="dark:text-gray-300">
                                {{ Customer::find($customerSelected)->name ?? '-----' }}
                            </td>
                            <td class="dark:text-gray-300">&nbsp;</td>
                            <td class="dark:text-gray-300">&nbsp;</td>

                        </tr>
                        <tr>
                            <td width="15%" class="dark:text-gray-300"></td>
                            <td width="45%" class="dark:text-gray-300">
                                {{ Customer::find($customerSelected)->address ?? '' }}</td>
                            <td class="dark:text-gray-300">&nbsp;</td>
                            <td class="dark:text-gray-300">&nbsp;</td>
                        </tr>
                        <tr>
                            <td width="15%" class="dark:text-gray-300"></td>
                            <td width="35%"class="dark:text-gray-300">
                                {{ Customer::find($customerSelected)->phone ?? '-----' }}</td>
                            <td width="15%" class="dark:text-gray-300"></td>
                            <td width="35%"class="dark:text-gray-300">
                                <small>Cashier:{{ Auth::user()->name ?? '-----' }}</small>
                            </td>
                        </tr>
                        <tr>
                            <td width="15%" class="dark:text-gray-300"></td>
                            <td width="35%"class="dark:text-gray-300"></td>
                            <td width="15%" class="dark:text-gray-300"></td>
                            <td width="35%"class="dark:text-gray-300">
                                <small>Server:{{ User::find($server)->name ?? '-----' }}</small>
                            </td>
                        </tr>
                    </table>
                </div>
                <br> <br> <br>

                <!-- Items -->
                <div class="border-t border-b border-gray-200 dark:border-gray-700 py-2 mb-4">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-right dark:text-gray-300"></th>
                                <th class="text-left dark:text-gray-300"></th>
                                <th class="text-center dark:text-gray-300">Product Name</th>
                                <th class="text-right dark:text-gray-300">Unit Price</th>
                                <th class="text-right dark:text-gray-300">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cart as $item)
                                <tr>
                                    <td class="text-center dark:text-gray-300">{{ $item['quantity'] }}</td>
                                    <td class="text-center dark:text-gray-300">{{ $item['unit'] }}</td>
                                    <td class="text-center dark:text-gray-300">{{ $item['name'] }}</td>
                                    <td class="text-right dark:text-gray-300">₱{{ number_format($item['price'], 2) }}
                                    </td>
                                    <td class="text-right dark:text-gray-300">
                                        ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="space-y-1 mb-4 text-right">
                    <div class="flex justify-end text-sm">
                        <span class="mr-4 dark:text-gray-300">Sub-Total:</span>
                        <span class="dark:text-gray-300">₱{{ number_format($total, 2) }}</span>
                    </div>
                    <div class="flex justify-end text-sm">
                        <span class="mr-4 dark:text-gray-300">Tax ({{ $tax }}%):</span>
                        <span class="dark:text-gray-300">₱{{ number_format($total * ($tax / 100), 2) }}</span>
                    </div>
                    <div class="flex justify-end font-bold">
                        <span class="mr-4 dark:text-gray-300">Net Price:</span>
                        <span
                            class="dark:text-gray-300">₱{{ number_format($total + $total * ($tax / 100) - $total * ($discount / 100), 2) }}</span>
                    </div>
                    <div class="flex justify-end text-sm">
                        <span class="mr-4 dark:text-gray-300">Discount <i>(less)</i> ({{ $discount }}%):</span>
                        <span class="dark:text-gray-300">₱{{ number_format($total * ($discount / 100), 2) }}</span>
                    </div>
                    <div class="flex justify-end font-bold">
                        <span class="mr-4 dark:text-gray-300">Total Amount Due:</span>
                        <span
                            class="dark:text-gray-300">₱{{ number_format($total + $total * ($tax / 100) - $total * ($discount / 100), 2) }}</span>
                    </div>
                </div>

                {{-- general notes --}}
                <div class="space-y-1 mb-4 text-left">
                    <div class="flex justify-start text-sm">
                        <span class="mr-4 dark:text-gray-300">General Notes:</span>
                        <span class="dark:text-gray-300">{{ $notes }}</span>
                    </div>
                    <br>
                    <div class="text-sm">
                        <span><small>Payment Method: {{ strtoupper($paymentMethod) }}</small></span><br>
                        <span><small>Payment Scheme: {{ strtoupper($paymentScheme) }}</small></span><br>
                        <span><small>Payment Status: {{ strtoupper($paymentStatus) }}</small></span>
                    </div>
                </div>

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
