<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Order;
use Livewire\Attributes\Title;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $quotation;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $quotationToDelete;

    public $form = [
        'customer_name' => '',
        'amount' => '',
        'status' => 'pending',
    ];

    public function rules()
    {
        return [
            'form.customer_name' => 'required|string|max:255',
            'form.amount' => 'required|numeric|min:0',
            'form.status' => 'required|string',
        ];
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(Quotation $quotation)
    {
        $this->quotation = $quotation;
        $this->form = $quotation->only(['customer_name', 'amount', 'status']);
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->isEditing) {
            $this->quotation->update($this->form);
            $this->dispatch('notify', 'Quotation updated successfully!', 'success');
        } else {
            Order::create($this->form);
            $this->dispatch('notify', 'Quotation created successfully!', 'success');
        }

        $this->showModal = false;
        $this->resetForm();
    }

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

    private function resetForm()
    {
        $this->form = [
            'customer_name' => '',
            'amount' => '',
            'status' => 'pending',
        ];
        $this->quotation = null;
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
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Payment Method</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Payment Scheme</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Order Items</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Status</th>
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
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ strtoupper($quotation->payment->payment_method ?? '') }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ strtoupper($quotation->payment->payment_scheme ?? '') }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ $quotation->order_items_count ?? '' }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $quotation->status === 'approved'
                                            ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300'
                                            : ($quotation->status === 'rejected'
                                                ? 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300'
                                                : 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300') }}">
                                        {{ ucfirst($quotation->status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 space-x-2">
                                    <a href="{{ route('pos.edit', $quotation->id) }}"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Edit</a>
                                    <button wire:click="confirmDelete({{ $quotation->id }})"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
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
</div>
