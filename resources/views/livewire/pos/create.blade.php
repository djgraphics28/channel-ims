<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\Order;
use App\Models\Employee;
// use App\Models\OrderItem;
// use App\Models\Payment;
use App\Models\Customer;
use Livewire\Attributes\Title;
use Livewire\Attributes\Required;
use Illuminate\Support\Facades\Validator;

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
    public $selectedCategory;

    public $customerModal = false;
    public $name;
    public $email;
    public $phone;
    public $address;
    public $birth_date;

    public $partialPaymentAmount;

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
            // 'form.name' => 'required|string|max:255',
            // 'form.price' => 'required|numeric|min:0',
            // 'form.quantity' => 'required|integer|min:0',
            'receiptNumber' => 'required',
            'paymentScheme' => 'required',
            'paymentStatus' => 'required',
            'paymentMethod' => 'required',
            'partialPaymentAmount' => 'required_if:paymentScheme,partial-payment|numeric|min:0',
            // 'server' => 'required',
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

        if (!$product || ($product->product_stock->stock ?? 0) < 1) {
            $this->dispatch('notify', 'Product out of stock!', 'error');
            return;
        }

        if (isset($this->cart[$productId])) {
            if ($this->cart[$productId]['quantity'] >= $product->product_stock->stock) {
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

        if ($change > 0 && $this->cart[$productId]['quantity'] >= $product->product_stock->stock) {
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
        $this->validate();
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
                'branch_id' => auth()->user()->branch_id,
            ],
        );

        $qt->payment()->updateOrCreate(
            ['order_id' => $qt->id],
            [
                'amount_paid' => $this->paymentScheme == 'partial-payment' ? $this->partialPaymentAmount : $this->total + $this->total * ($this->tax / 100) - $this->discount,
                'payment_method' => $this->paymentMethod,
                'payment_scheme' => $this->paymentScheme,
                'payment_status' => $this->paymentStatus,
                'notes' => $this->notes,
                'branch_id' => auth()->user()->branch_id,
            ],
        );

        $qt->update(['status' => 'completed']);

        foreach ($this->cart as $productId => $item) {
            $product = Product::find($productId);
            $product->product_stock->decrement('stock', $item['quantity']);

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
        $this->receiptModal = false;
        $this->cart = [];

        flash()->success('Quotation created successfully!');
    }
    #[Title('Create Quotation')]
    public function with(): array
    {
        return [
            'users' => Employee::all(),
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

    public function addCustomer()
    {
        $this->customerModal = true;
    }

    public function saveCustomer()
    {
        Validator::make(
            [
                'name' => $this->name,
                'phone' => $this->phone,
            ],
            [
                'name' => 'required',
                'phone' => 'required',
            ],
        )->validate();
        Customer::create([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'birth_date' => $this->birth_date,
        ]);

        flash()->success('Customer created successfully!');
        $this->reset(['name', 'phone', 'email', 'address', 'birth_date']);

        $this->customerModal = false;
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
                <select wire:model.live="selectedCategory"
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
                                {{ $product->product_stock->stock ?? 0 }}</span>
                        </div>
                        @if ($product->hasMedia('product_images'))
                            <img src="{{ $product->getFirstMediaUrl('product_images') }}" alt="{{ $product->name }}"
                                class="mt-1 w-full h-40 object-cover rounded-lg">
                        @else
                            <div
                                class="mt-1 w-full h-40 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <span class="text-gray-500 dark:text-gray-400">Product Image</span>
                            </div>
                        @endif
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
                                <flux:input placeholder="Enter Receipt Number here" type="text"
                                    :label="__(key: 'Receipt Number')" wire:model.live="receiptNumber" />
                                <br>
                                <div class="flex items-center justify-between">
                                    <flux:input type="text" wire:model.live="customerSearch"
                                        :label="__(key: 'Search Customer')" placeholder="Search customer..."
                                        class="flex-1" />
                                    <flux:button title="Add New Customer" wire:click="addCustomer" icon="plus"
                                        class="mt-6 ml-2">
                                    </flux:button>
                                </div> <!-- Remove value attribute since wire:model handles the binding -->
                                @if ($filteredCustomers && count($filteredCustomers) > 0)
                                    <div
                                        class="absolute z-10 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg mt-1">
                                        @foreach ($filteredCustomers as $customer)
                                            <div wire:click="selectCustomer({{ $customer->id }})"
                                                class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-gray-900 dark:text-gray-300">
                                                {{ $customer->name }} | {{ $customer->address }}
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
                                                <small
                                                    class="font-medium dark:text-gray-300">{{ $item['name'] }}</small>
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
                                                    <input type="number"
                                                        wire:model.live="cart.{{ $productId }}.quantity"
                                                        min="1" max="{{ $item['quantity'] }}"
                                                        class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600 border border-gray-300 rounded-lg px-2 py-1"
                                                        value="{{ $item['quantity'] }}"
                                                        oninput="if(this.value < 1) this.value = 1" /> <button
                                                        wire:click="updateQuantity({{ $productId }}, 1)"
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
                                            label="Discount:" min="0" max="100" step="0.1" />
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

                                @if ($paymentScheme == 'partial-payment')
                                    <div>
                                        <flux:input type="number" id="partial_payment_amount"
                                            wire:model.live="partialPaymentAmount" label="Partial Payment Amount:"
                                            min="0" step="0.01" placeholder="Enter amount here" />
                                    </div>
                                @endif

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
                                            <option value="{{ $user->id }}">{{ $user->first_name }}
                                                {{ $user->last_name }}</option>
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
                                    <span>Discount:</span>
                                    <span>{{ $discount <= 0 ? '' : '-' }}
                                        ₱{{ number_format(floatval($discount), 2) }}</span>
                                </div>
                                <div class="flex justify-between font-bold text-lg">
                                    <span>Total:</span>
                                    <span>₱{{ number_format($total + ($total * floatval($tax)) / 100 - floatval($discount), 2) }}</span>
                                </div>

                                <button wire:click="checkout" wire:loading.attr="disabled"
                                    class="mt-4 w-full rounded-lg bg-green-600 px-4 py-3 text-white hover:bg-green-700 transition font-medium">
                                    <span>Submit Quotation</span>
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
                <div class="mb-2">
                    <div class="flex justify-end">
                        <small>{{ now()->format('m/d/Y H:i') }}</small>
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
                        <small>Server: {{ User::find($server)?->name ?? '-----' }}</small>
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
                                    <td class="text-center dark:text-gray-300"><small>{{ $item['quantity'] }}</small>
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

    @if ($customerModal)
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 dark:bg-gray-800 opacity-75"></div>
                </div>
                <div
                    class="inline-block transform overflow-hidden rounded-lg bg-white dark:bg-gray-900 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form wire:submit="saveCustomer">
                        <div class="bg-white dark:bg-gray-900 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="mb-4">
                                <flux:input wire:model="name" :label="__('Name')" type="text" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                            </div>
                            {{-- <div class="mb-4">
                            <flux:input wire:model="form.document_id" :label="__('Document ID')" type="text"
                                class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                            @error('form.document_id')
                                <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                            @enderror
                        </div> --}}
                            <div class="mb-4">
                                <flux:input wire:model="email" :label="__('Email')" type="email"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="phone" :label="__('Phone')" type="text"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="address" :label="__('Address')" type="text"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="birth_date" :label="__('Birth Date')" type="date"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="submit"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-blue-400 sm:ml-3 sm:w-auto sm:text-sm">
                                Save
                            </button>
                            <button type="button" wire:click="$set('customerModal', false)"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
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
