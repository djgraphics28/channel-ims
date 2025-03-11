<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\Order;
// use App\Models\OrderItem;
// use App\Models\Payment;
use App\Models\Customer;
use Livewire\Attributes\Title;
use Livewire\Attributes\Required;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $product;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $productToDelete;
    public $cart = [];
    public $total = 0;
    public $customerSearch = '';
    public $customerSelected = null; // Changed to customerSelected
    public $filteredCustomers = [];

    public $tax = 0;
    public $discount = 0;
    #[Required]
    public $paymentMethod;
    #[Required]
    public $paymentScheme;
    public $paymentStatus;
    public $server;
    public $notes;
    public $receiptNumber;

    public $receiptModal = false;

    public $form = [
        'name' => '',
        'unit' => '',
        'discount' => 0,
        'price' => '',
        'quantity' => '',
    ];

    public function rules()
    {
        return [
            'form.name' => 'required|string|max:255',
            'form.price' => 'required|numeric|min:0',
            'form.quantity' => 'required|integer|min:0',
        ];
    }

    public function updatedCustomerSearch()
    {
        $this->filteredCustomers = $this->searchCustomer($this->customerSearch);
    }

    public function selectCustomer($customerId)
    {
        $this->customerSelected = $customerId; // Changed to customerSelected
        $this->customerSearch = '';
        $this->filteredCustomers = [];
    }

    public function searchCustomer($query)
    {
        if (empty($query)) {
            return Customer::all();
        }

        return Customer::where('name', 'like', '%' . $query . '%')
            ->orWhere('email', 'like', '%' . $query . '%')
            ->orWhere('phone', 'like', '%' . $query . '%')
            ->get();
    }

    public function addToCart($productId)
    {
        $product = Product::find($productId);

        if (!$product || $product->stock < 1) {
            $this->dispatch('notify', 'Product out of stock!', 'error');
            return;
        }

        if (isset($this->cart[$productId])) {
            if ($this->cart[$productId]['quantity'] >= $product->stock) {
                $this->dispatch('notify', 'Maximum stock reached!', 'error');
                return;
            }
            $this->cart[$productId]['quantity']++;
        } else {
            $this->cart[$productId] = [
                'name' => $product->name,
                'unit' => $product->unit_id,
                'price' => $product->selling_price,
                'quantity' => 1,
            ];
        }

        $this->calculateTotal();
        $this->dispatch('notify', 'Product added to cart!', 'success');
    }

    public function removeFromCart($productId)
    {
        unset($this->cart[$productId]);
        $this->calculateTotal();
        $this->dispatch('notify', 'Product removed from cart!', 'success');
    }

    public function updateQuantity($productId, $change)
    {
        $product = Product::find($productId);

        if ($change > 0 && $this->cart[$productId]['quantity'] >= $product->stock) {
            $this->dispatch('notify', 'Maximum stock reached!', 'error');
            return;
        }

        $this->cart[$productId]['quantity'] += $change;

        if ($this->cart[$productId]['quantity'] <= 0) {
            unset($this->cart[$productId]);
        }

        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $this->total = 0;
        foreach ($this->cart as $item) {
            $this->total += $item['price'] * $item['quantity'];
        }
    }

    public function checkout()
    {
        if (empty($this->cart)) {
            $this->dispatch('notify', 'Cart is empty!', 'error');
            return;
        }

        if (empty($this->paymentMethod)) {
            $this->dispatch('notify', 'Payment Method empty!', 'error');
            return;
        }

        if (empty($this->paymentScheme)) {
            $this->dispatch('notify', 'Payment Scheme empty!', 'error');
            return;
        }

        $this->receiptModal = true;

        // $this->cart = [];
        // $this->total = 0;
        // $this->customerSelected = null;
        // $this->tax = 0;
        // $this->discount = 0;
        // $this->paymentMethod = null;
        // $this->paymentScheme = null;
        $this->dispatch('notify', 'Transaction completed successfully!', 'success');
    }

    public function confirmAndPrint()
    {
        $qt = Order::updateOrCreate(
            ['order_number' => $this->receiptNumber],
            [
                'customer_id' => $this->customerSelected,
                'created_by' => Auth::user()->id,
                'assisted_by' => $this->server,
                'total_amount' => $this->total,
                'tax' => $this->tax,
                'discount' => $this->discount,
                'notes' => $this->notes,
            ],
        );

        $qt->payment()->updateOrCreate(
            ['order_id' => $qt->id],
            [
                'amount_paid' => $this->total + $this->total * ($this->tax / 100) - $this->total * ($this->discount / 100),
                'payment_method' => $this->paymentMethod,
                'payment_scheme' => $this->paymentScheme,
                'payment_status' => $this->paymentStatus,
                'notes' => $this->notes,
            ],
        );

        foreach ($this->cart as $productId => $item) {
            $product = Product::find($productId);
            $product->decrement('stock', $item['quantity']);

            $qt->order_items()->updateOrCreate(
                [
                    'product_id' => $productId,
                    'order_id' => $qt->id,
                ],
                [
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['quantity'] * $item['price'],
                ],
            );
        }

        // Trigger the print function in the browser
        $this->dispatch('print-receipt');
    }
    #[Title('Create Quotation')]
    public function with(): array
    {
        return [
            'users' => User::all(),
            'customers' => Customer::all(),
            'categories' => Category::all(),
            'products' => Product::query()
                ->where('name', 'like', '%' . $this->search . '%')
                ->paginate(12),
        ];
    }

    public function orderNumber()
    {
        $prefix = 'QT';
        $date = now()->format('Ymd');
        $lastOrder = \DB::table('orders')->latest('id')->first();
        $sequence = $lastOrder ? str_pad(intval(substr($lastOrder->order_number, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';

        return $prefix . $date . $sequence;
    }
};

?>

<div>
    <div class="flex h-full w-full gap-4">
        <!-- Products Grid -->
        <div class="w-2/3">
            <div class="mb-4 flex gap-4">
                <input wire:model.live="search" type="search" placeholder="Search products..."
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm dark:text-gray-300 dark:placeholder-gray-500">
                <select wire:model.live="category"
                    class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm dark:text-gray-300">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-4 gap-4">
                @foreach ($products as $product)
                    <div class="rounded-lg border p-4 shadow-sm cursor-pointer dark:border-gray-700 dark:bg-gray-800"
                        wire:click="addToCart({{ $product->id }}); $dispatch('play-sound', {sound: 'https://s3.amazonaws.com/freecodecamp/drums/Heater-4_1.mp3'})"
                        x-data
                        @play-sound.window="
        const audio = new Audio($event.detail.sound);
        audio.volume = 0.1;
        audio.play();
    ">

                        <div class="flex justify-between">
                            <span
                                class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 px-2.5 py-0.5 text-sm font-medium text-blue-800 dark:text-blue-100">₱{{ number_format($product->selling_price, 2) }}</span>
                            <span
                                class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-sm font-medium text-gray-800 dark:text-gray-100">Stock:
                                {{ $product->stock }}</span>
                        </div>
                        <div
                            class="mt-1 w-full h-40 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                            <span class="text-gray-500 dark:text-gray-400">Product Image</span>
                        </div>
                        <h3 class="text-lg font-semibold dark:text-white">{{ $product->name }}</h3>
                        <span class="dark:text-gray-300">{{ $product->code }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $products->links() }}
            </div>
        </div>

        <!-- Cart -->
        <div class="w-1/3">
            <div class="rounded-lg border p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="font-medium">Cashier:</span>
                    <span>{{ auth()->user()->name }}</span>
                </div>
            </div>
            <div class="rounded-lg border p-4 shadow-sm mt-2">
                {{-- <h2 class="mb-4 text-xl font-bold">Cart</h2> --}}

                @if (empty($cart))
                    <p class="text-gray-500">Cart is empty</p>
                @else
                    <div class="space-y-4">
                        <div class="relative">
                            <div class="mb-4">
                                <label for="receipt_number" class="text-sm font-medium dark:text-gray-300">Receipt
                                    Number:</label>
                                <input placeholder="Enter Receipt Number here" type="text"
                                    wire:model.live="receiptNumber"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm dark:text-gray-300">
                            </div>
                            <input type="text" wire:model.live="customerSearch" placeholder="Search customer..."
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm dark:text-gray-300 dark:placeholder-gray-500 mb-4">
                            <!-- Remove value attribute since wire:model handles the binding -->
                            @if ($filteredCustomers && count($filteredCustomers) > 0)
                                <div
                                    class="absolute z-10 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg mt-1">
                                    @foreach ($filteredCustomers as $customer)
                                        <div wire:click="selectCustomer({{ $customer->id }})"
                                            class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-gray-900 dark:text-gray-300">
                                            {{ $customer->name }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <p><strong>Customer: {{ Customer::find($customerSelected)->name ?? 'Walk-In' }}</strong>
                            </p>
                        </div>
                        <table class="w-full">
                            <thead>
                                <tr class="border-b dark:border-gray-700">
                                    <th class="text-left py-2 dark:text-gray-300">Item</th>
                                    <th class="text-right py-2 dark:text-gray-300">Price</th>
                                    <th class="text-right py-2 dark:text-gray-300">Quantity</th>
                                    <th class="text-right py-2 dark:text-gray-300">Subtotal</th>
                                    <th class="text-right py-2 dark:text-gray-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cart as $productId => $item)
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="py-2">
                                            <h4 class="font-medium dark:text-gray-300">{{ $item['name'] }}</h4>
                                        </td>
                                        <td class="text-right py-2">
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                ₱{{ number_format($item['price'], 2) }}
                                            </p>
                                        </td>
                                        <td class="text-right py-2">
                                            <div class="flex items-center justify-end gap-2">
                                                <button wire:click="updateQuantity({{ $productId }}, -1)"
                                                    class="rounded-full bg-gray-200 dark:bg-gray-700 dark:text-gray-300 px-2 py-1">-</button>
                                                <span class="dark:text-gray-300">{{ $item['quantity'] }}</span>
                                                <button wire:click="updateQuantity({{ $productId }}, 1)"
                                                    class="rounded-full bg-gray-200 dark:bg-gray-700 dark:text-gray-300 px-2 py-1">+</button>
                                            </div>
                                        </td>
                                        <td class="text-right py-2">
                                            <span
                                                class="font-medium dark:text-gray-300">₱{{ number_format($item['price'] * $item['quantity'], 2) }}</span>
                                        </td>
                                        <td class="text-right py-2">
                                            <flux:button icon="trash" variant="danger"
                                                wire:click="removeFromCart({{ $productId }})"></flux:button>
                                        </td>
                                        </>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center mb-2">
                                <label for="tax" class="text-sm font-medium dark:text-gray-300">Tax Rate
                                    (%):</label>
                                <input type="number" id="tax" wire:model.live="tax"
                                    class="w-24 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-sm dark:text-gray-300"
                                    min="0" max="100" step="1">
                            </div>
                            <div class="flex justify-between items-center">
                                <label for="discount" class="text-sm font-medium dark:text-gray-300">Discount
                                    (%):</label>
                                <input type="number" id="discount" wire:model.live="discount"
                                    class="w-24 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-sm dark:text-gray-300"
                                    min="0" max="100" step="1">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center mb-2">
                                <label for="payment_method" class="text-sm font-medium dark:text-gray-300">Payment
                                    Method:</label>
                                <select id="payment_method" wire:model.live="paymentMethod"
                                    class="w-48 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-sm dark:text-gray-300">
                                    <option value="">Select</option>
                                    <option value="cash">CASH</option>
                                    <option value="card">COD</option>
                                    <option value="sign">SIGN</option>
                                    <option value="returned">RETURNED</option>
                                    <option value="refund">REFUND</option>
                                </select>
                            </div>
                            <div class="flex justify-between items-center">
                                <label for="payment_scheme" class="text-sm font-medium dark:text-gray-300">Payment
                                    Scheme:</label>
                                <select id="payment_scheme" wire:model.live="paymentScheme"
                                    class="w-48 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-sm dark:text-gray-300">
                                    <option value="">Select</option>
                                    <option value="full-payment">FULL-PAYMENT</option>
                                    <option value="partial-payment">PARTIAL-PAYMENT</option>
                                </select>
                            </div>
                            <div class="flex justify-between items-center">
                                <label for="payment_status" class="text-sm font-medium dark:text-gray-300">Admin
                                    Note:</label>
                                <select id="payment_status" wire:model.live="paymentStatus"
                                    class="w-48 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-sm dark:text-gray-300">
                                    <option value="">Select</option>
                                    <option value="paid">PAID</option>
                                    <option value="not-paid">NOT-PAID</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="notes" class="text-sm font-medium dark:text-gray-300">General
                                    Note:</label>
                                <textarea placeholder="Enter general notes here" wire:model.live="notes"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm dark:text-gray-300"
                                    rows="3"></textarea>
                            </div>
                            <div class="flex justify-between items-center">
                                <label for="payment_scheme"
                                    class="text-sm font-medium dark:text-gray-300">Server:</label>
                                <select id="server" wire:model.live="server"
                                    class="w-48 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-sm dark:text-gray-300">
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="border-t pt-4">
                            <div class="space-y-2">
                                <div class="flex justify-between text-base">
                                    <span>Subtotal:</span>
                                    <span>₱{{ number_format($total, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-base">
                                    <span>Tax ({{ $tax ?? 0 }}%):</span>
                                    <span>₱{{ number_format($total * (($tax ?? 0) / 100), 2) }}</span>
                                </div>
                                <div class="flex justify-between text-base">
                                    <span>Discount ({{ $discount ?? 0 }}%):</span>
                                    <span>₱{{ number_format($total * ($discount / 100), 2) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold">
                                    <span>Total:</span>
                                    <span>₱{{ number_format($total + $total * ($tax / 100) - $total * ($discount / 100), 2) }}</span>
                                </div>
                            </div> <button wire:click="checkout"
                                class="mt-4 w-full rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700">
                                Submit Quotation
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($receiptModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white dark:bg-gray-800 p-8 rounded-lg w-120" id="printable-receipt">
                <!-- Receipt Header -->
                <div class="text-center mb-4">
                    <h2 class="text-xl font-bold dark:text-white">Company Name</h2>
                    <p class="text-sm dark:text-gray-300">123 Business Street</p>
                    <p class="text-sm dark:text-gray-300">Phone: (123) 456-7890</p>
                    <p class="text-sm dark:text-gray-300">Receipt #: {{ $this->receiptNumber }}</p>
                    <p class="text-sm dark:text-gray-300">Date: {{ now()->format('M d, Y') }}</p>
                </div>

                <!-- Customer Info -->
                <div class="mb-4">
                    <p class="text-sm dark:text-gray-300">Customer:
                        {{ Customer::find($customerSelected)->name ?? 'Walk-In' }}</p>
                    <p class="text-sm dark:text-gray-300">Address:
                        {{ Customer::find($customerSelected)->address ?? '' }}</p>
                </div>

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
                    <div class="flex justify-end text-sm">
                        <span class="mr-4 dark:text-gray-300">Discount ({{ $discount }}%):</span>
                        <span class="dark:text-gray-300">₱{{ number_format($total * ($discount / 100), 2) }}</span>
                    </div>
                    <div class="flex justify-end font-bold">
                        <span class="mr-4 dark:text-gray-300">Total Amount Due:</span>
                        <span
                            class="dark:text-gray-300">₱{{ number_format($total + $total * ($tax / 100) - $total * ($discount / 100), 2) }}</span>
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
        window.print();
    });
</script>
