<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use Livewire\Attributes\Title;
use Livewire\Attributes\Required;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $orderId;
    public $search = '';
    public $showModal = false;
    public $product;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $productToDelete;
    public $cart = [];
    public $total = 0;
    public $customerSearch = '';
    public $customerSelected = null;
    public $filteredCustomers = [];

    public $tax = 0;
    public $discount = 0;
    #[Required]
    public $paymentMethod = '';
    #[Required]
    public $paymentScheme = '';
    public $paymentStatus = '';
    public $server = '';
    public $notes = '';
    public $receiptNumber = '';
    public $selectedCategory = '';

    public $receiptModal = false;

    public function mount($orderId)
    {
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

    public function rules()
    {
        return [
            // 'form.name' => 'required|string|max:255',
            // 'form.price' => 'required|numeric|min:0',
            // 'form.quantity' => 'required|integer|min:0',
            'receiptNumber' => 'required',
            'paymentScheme' => 'required',
            'paymentStatus' => 'required',
            'paymentMethod' => 'required',
            'server' => 'required',
        ];
    }

    public function updatedCustomerSearch()
    {
        $this->filteredCustomers = $this->searchCustomer($this->customerSearch);
    }

    public function selectCustomer($customerId)
    {
        $this->customerSelected = $customerId;
        $this->customerSearch = Customer::find($customerId)->name;
        $this->filteredCustomers = [];
    }

    public function searchCustomer($query)
    {
        if (empty($query)) {
            return collect();
        }

        return Customer::where('name', 'like', '%' . $query . '%')
            ->orWhere('email', 'like', '%' . $query . '%')
            ->orWhere('phone', 'like', '%' . $query . '%')
            ->limit(5)
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
                'unit' => $product->unit_id ?? 'pc', // Assuming unit relationship exists
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
        $this->total = collect($this->cart)->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });
    }

    public function checkout()
    {
        $this->validate();
        if (empty($this->cart)) {
            $this->dispatch('notify', 'Cart is empty!', 'error');
            return;
        }

        if (empty($this->paymentMethod)) {
            $this->dispatch('notify', 'Payment Method is required!', 'error');
            return;
        }

        if (empty($this->paymentScheme)) {
            $this->dispatch('notify', 'Payment Scheme is required!', 'error');
            return;
        }

        $this->receiptModal = true;
        $this->dispatch('notify', 'Transaction completed successfully!', 'success');
    }

    public function confirmAndPrint()
    {
        $order = Order::updateOrCreate(
            ['order_number' => $this->receiptNumber],
            [
                'customer_id' => $this->customerSelected,
                'created_by' => Auth::id(),
                'assisted_by' => $this->server,
                'total_amount' => $this->total,
                'tax' => $this->tax,
                'discount' => $this->discount,
                'notes' => $this->notes,
            ],
        );

        $order->payment()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'amount_paid' => $this->total + ($this->total * $this->tax) / 100 - ($this->total * $this->discount) / 100,
                'payment_method' => $this->paymentMethod,
                'payment_scheme' => $this->paymentScheme,
                'payment_status' => $this->paymentStatus,
                'notes' => $this->notes,
            ],
        );

        // Get old order with order items
        $oldOrder = Order::with('order_items')->find($this->orderId);

        // First increment the product stocks from old order items
        if ($oldOrder) {
            foreach ($oldOrder->order_items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }
        }

        //remove all order_items
        $oldOrder->order_items()->delete();

        // Update new order items and decrement stock
        foreach ($this->cart as $productId => $item) {
            $product = Product::find($productId);
            if ($product) {
                $product->decrement('stock', $item['quantity']);

                $order->order_items()->updateOrCreate(
                    [
                        'product_id' => $productId,
                        'order_id' => $order->id,
                    ],
                    [
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'subtotal' => $item['quantity'] * $item['price'],
                    ],
                );
            }
        }
        flash()->success('Quotation updated successfully!');
        $this->dispatch('print-receipt');
        $this->receiptModal = false;
        $this->resetCart();
    }

    protected function resetCart()
    {
        $this->cart = [];
        $this->total = 0;
        $this->customerSelected = null;
        $this->customerSearch = '';
        $this->tax = 0;
        $this->discount = 0;
        $this->paymentMethod = '';
        $this->paymentScheme = '';
        $this->paymentStatus = '';
        $this->notes = '';
        $this->receiptNumber = $this->orderNumber();
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
                ->when($this->selectedCategory, function ($query) {
                    return $query->where('category_id', $this->selectedCategory);
                })
                ->paginate(12),
        ];
    }

    public function orderNumber()
    {
        $prefix = 'QT';
        $date = now()->format('Ymd');
        $lastOrder = Order::latest('id')->first();
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
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search products..."
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm dark:text-gray-300 dark:placeholder-gray-500">
                <select wire:model.live="selectedCategory"
                    class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm dark:text-gray-300">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($products->isEmpty())
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    No products found matching your search.
                </div>
            @else
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
                                    class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 px-2.5 py-0.5 text-sm font-medium text-blue-800 dark:text-blue-100">
                                    ₱{{ number_format($product->selling_price, 2) }}
                                </span>
                                <span
                                    class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-sm font-medium text-gray-800 dark:text-gray-100">
                                    Stock: {{ $product->stock }}
                                </span>
                            </div>
                            <div
                                class="mt-1 w-full h-40 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                @if ($product->image)
                                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}"
                                        class="h-full w-full object-cover">
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">No Image</span>
                                @endif
                            </div>
                            <h3 class="text-lg font-semibold dark:text-white mt-2 truncate">{{ $product->name }}</h3>
                            <span class="dark:text-gray-300 text-sm">{{ $product->code }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            @endif
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
                @if (empty($cart))
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        Your cart is empty
                    </div>
                @else
                    <div class="space-y-4">
                        <div class="mb-4">
                            <flux:input placeholder="Enter Receipt Number here" type="text" readonly
                                :label="__(key: 'Receipt Number')" wire:model.live="receiptNumber" />
                            <br>
                            <flux:input type="text" wire:model.live="customerSearch"
                                :label="__(key: 'Search Customer')" placeholder="Search customer..." />
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
                            <p><strong>Customer:
                                    {{ Customer::find($customerSelected)->name ?? 'Walk-In' }}</strong>
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
                        <div class="space-y-4 mt-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <flux:input type="number" id="tax" wire:model.live="tax"
                                        label="Tax Rate (%):" min="0" max="100" step="0.1" />
                                </div>
                                <div>
                                    <flux:input type="number" id="discount" wire:model.live="discount"
                                        label="Discount (%):" min="0" max="100" step="0.1" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <flux:select id="payment_method" wire:model.live="paymentMethod"
                                        label="Payment Method:"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 text-sm dark:text-gray-300">
                                        <option value="">Select</option>
                                        <option value="cash">CASH</option>
                                        <option value="cod">COD</option>
                                        <option value="sign">SIGN</option>
                                        <option value="returned">RETURNED</option>
                                        <option value="refund">REFUND</option>
                                    </flux:select>
                                </div>
                                <div>
                                    <flux:select id="payment_scheme" wire:model.live="paymentScheme"
                                        label="Payment Scheme:"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 text-sm dark:text-gray-300">
                                        <option value="">Select</option>
                                        <option value="full-payment">FULL-PAYMENT</option>
                                        <option value="partial-payment">PARTIAL-PAYMENT</option>
                                    </flux:select>
                                </div>
                            </div>

                            <div>
                                <flux:select id="payment_status" wire:model.live="paymentStatus"
                                    label="Payment Status:"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 text-sm dark:text-gray-300">
                                    <option value="">Select</option>
                                    <option value="paid">PAID</option>
                                    <option value="not-paid">NOT-PAID</option>
                                </flux:select>
                            </div>

                            <div>
                                <flux:select wire:model.live="server" class="w-full" label="Server">
                                    <option value="">Select</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div>
                                <label for="notes" class="block text-sm font-medium dark:text-gray-300 mb-1">
                                    Notes:
                                </label>
                                <flux:textarea wire:model.live="notes" placeholder="Enter notes here"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 text-sm dark:text-gray-300"
                                    rows="2"></flux:textarea>
                            </div>
                        </div>

                        <div class="border-t pt-4 space-y-2">
                            <div class="flex justify-between">
                                <span>Subtotal:</span>
                                <span>₱{{ number_format($total, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Tax ({{ floatval($tax) }}%):</span>
                                <span>₱{{ number_format($total * (floatval($tax) / 100), 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Discount ({{ floatval($discount) }}%):</span>
                                <span>₱{{ number_format($total * (floatval($discount) / 100), 2) }}</span>
                            </div>
                            <div class="flex justify-between font-bold text-lg">
                                <span>Total:</span>
                                <span>₱{{ number_format($total + ($total * floatval($tax)) / 100 - ($total * floatval($discount)) / 100, 2) }}</span>
                            </div>

                            <button wire:click="checkout" wire:loading.attr="disabled"
                                class="mt-4 w-full rounded-lg bg-green-600 px-4 py-3 text-white hover:bg-green-700 transition font-medium">
                                <span wire:loading.remove>Update Quotation</span>
                                <span wire:loading>Processing...</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

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
