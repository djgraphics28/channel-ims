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
    public $paymentMethod;
    #[Required]
    public $paymentScheme;
    public $server;
    public $notes;

    public $form = [
        'name' => '',
        'discount' => 0,
        'price' => '',
        'quantity' => '',
    ];

    public function mount($orderId)
    {
        $order = Order::with(['order_items','order_items.product', 'customer', 'payment'])->findOrFail($orderId);

        $this->customerSelected = $order->customer_id;
        $this->tax = $order->tax;
        $this->discount = $order->discount;
        $this->paymentMethod = $order->payment->payment_method;
        $this->paymentScheme = $order->payment->payment_scheme;
        $this->server = $order->assisted_by;
        $this->notes = $order->notes;

        foreach ($order->order_items as $item) {
            $this->cart[$item->product_id] = [
                'name' => $item->product->name,
                'price' => $item->price,
                'quantity' => $item->quantity,
            ];
        }

        $this->calculateTotal();
    }

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
        $this->customerSelected = $customerId;
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

    public function update()
    {
        if (empty($this->cart)) {
            $this->dispatch('notify', 'Cart is empty!', 'error');
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $order->customer_id = $this->customerSelected;
        $order->assisted_by = $this->server;
        $order->total_amount = $this->total;
        $order->tax = $this->tax;
        $order->discount = $this->discount;
        $order->notes = $this->notes;
        $order->save();

        $order->payment()->update([
            'amount_paid' => $this->total + $this->total * ($this->tax / 100) - $this->total * ($this->discount / 100),
            'payment_method' => $this->paymentMethod,
            'payment_scheme' => $this->paymentScheme,
            'notes' => $this->notes,
        ]);

        // Delete existing order items
        $order->order_items()->delete();

        // Create new order items
        foreach ($this->cart as $productId => $item) {
            $order->order_items()->create([
                'product_id' => $productId,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['quantity'] * $item['price'],
            ]);
        }

        $this->dispatch('notify', 'Order updated successfully!', 'success');
    }

    #[Title('Edit Quotation')]
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
};

?>

<div>
    <div class="flex h-full w-full gap-4">
        <!-- Products Grid -->
        <div class="w-2/3">
            <div class="mb-4 flex gap-4">
                <input wire:model.live="search" type="search" placeholder="Search products..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm">
                <select wire:model.live="category" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-4 gap-4">
                @foreach ($products as $product)
                    <div class="rounded-lg border p-4 shadow-sm cursor-pointer"
                        wire:click="addToCart({{ $product->id }}); $dispatch('play-sound', {sound: 'https://s3.amazonaws.com/freecodecamp/drums/Heater-4_1.mp3'})"
                        x-data
                        @play-sound.window="
        const audio = new Audio($event.detail.sound);
        audio.volume = 0.1;
        audio.play();
    ">

                        <div class="flex justify-between">
                            <span
                                class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-sm font-medium text-blue-800">₱{{ number_format($product->selling_price, 2) }}</span>
                            <span
                                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-800">Stock:
                                {{ $product->stock }}</span>
                        </div>
                        <div class="mt-1 w-full h-40 bg-gray-200 rounded-lg flex items-center justify-center">
                            <span class="text-gray-500">Product Image</span>
                        </div>
                        <h3 class="text-lg font-semibold">{{ $product->name }}</h3>
                        <span>{{ $product->code }}</span>
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
                            <input type="text" wire:model.live="customerSearch" placeholder="Search customer..."
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm mb-4">
                            <!-- Remove value attribute since wire:model handles the binding -->
                            @if ($filteredCustomers && count($filteredCustomers) > 0)
                                <div class="absolute z-10 w-full bg-white border rounded-lg mt-1">
                                    @foreach ($filteredCustomers as $customer)
                                        <div wire:click="selectCustomer({{ $customer->id }})"
                                            class="px-4 py-2 hover:bg-gray-100 cursor-pointer">
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
                                <tr class="border-b">
                                    <th class="text-left py-2">Item</th>
                                    <th class="text-right py-2">Price</th>
                                    <th class="text-right py-2">Quantity</th>
                                    <th class="text-right py-2">Subtotal</th>
                                    <th class="text-right py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cart as $productId => $item)
                                    <tr class="border-b">
                                        <td class="py-2">
                                            <h4 class="font-medium">{{ $item['name'] }}</h4>
                                        </td>
                                        <td class="text-right py-2">
                                            <p class="text-sm text-gray-600">₱{{ number_format($item['price'], 2) }}
                                            </p>
                                        </td>
                                        <td class="text-right py-2">
                                            <div class="flex items-center justify-end gap-2">
                                                <button wire:click="updateQuantity({{ $productId }}, -1)"
                                                    class="rounded-full bg-gray-200 px-2 py-1">-</button>
                                                <span>{{ $item['quantity'] }}</span>
                                                <button wire:click="updateQuantity({{ $productId }}, 1)"
                                                    class="rounded-full bg-gray-200 px-2 py-1">+</button>
                                            </div>
                                        </td>
                                        <td class="text-right py-2">
                                            <span
                                                class="font-medium">₱{{ number_format($item['price'] * $item['quantity'], 2) }}</span>
                                        </td>
                                        <td class="text-right py-2">
                                            {{-- <button wire:click="removeFromCart({{ $productId }})"
                                                class="text-red-600">
                                                <x-heroicon-o-trash class="w-5 h-5" />
                                            </button> --}}
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
                                <label for="tax" class="text-sm font-medium">Tax Rate (%):</label>
                                <input type="number" id="tax" wire:model.live="tax"
                                    class="w-24 rounded-lg border border-gray-300 px-2 py-1 text-sm" min="0"
                                    max="100" step="1">
                            </div>
                            <div class="flex justify-between items-center">
                                <label for="discount" class="text-sm font-medium">Discount (%):</label>
                                <input type="number" id="discount" wire:model.live="discount"
                                    class="w-24 rounded-lg border border-gray-300 px-2 py-1 text-sm" min="0"
                                    max="100" step="1">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center mb-2">
                                <label for="payment_method" class="text-sm font-medium">Payment Method:</label>
                                <select id="payment_method" wire:model.live="paymentMethod"
                                    class="w-48 rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                    <option value="">Select</option>
                                    <option value="cash">CASH</option>
                                    <option value="card">COD</option>
                                    <option value="sign">SIGN</option>
                                    <option value="returned">RETURNED</option>
                                    <option value="refund">REFUND</option>
                                </select>
                            </div>
                            <div class="flex justify-between items-center">
                                <label for="payment_scheme" class="text-sm font-medium">Payment Scheme:</label>
                                <select id="payment_scheme" wire:model.live="paymentScheme"
                                    class="w-48 rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                    <option value="">Select</option>
                                    <option value="full-payment">Full Payment</option>
                                    <option value="partial-payment">Partial Payment</option>
                                </select>
                            </div>
                            <div class="flex justify-between items-center">
                                <label for="payment_scheme" class="text-sm font-medium">Server:</label>
                                <select id="server" wire:model.live="server"
                                    class="w-48 rounded-lg border border-gray-300 px-2 py-1 text-sm">
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
                            </div> <button wire:click="update"
                                class="mt-4 w-full rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700">
                                Update Quotation
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
