<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\CashFlow;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $cashflow;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $cashflowToDelete;
    #[Url]
    public $filterType = '';
    #[Url]
    public $startDate = '';
    #[Url]
    public $endDate = '';

    // Form fields
    public $description = '';
    public $amount = '';
    public $type = '';
    public $remarks = '';

    protected $rules = [
        'description' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0.01',
        'type' => 'required|in:in,out',
        'remarks' => 'nullable|string',
    ];

    public function mount()
    {
        // Set default date range to current month if not set
        if (empty($this->startDate) || empty($this->endDate)) {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
            $this->endDate = now()->endOfMonth()->format('Y-m-d');
        }
    }

    public function applyFilter($type)
    {
        $this->filterType = $type;
        $this->resetPage();
    }

    public function updatedStartDate()
    {
        $this->resetPage();
    }

    public function updatedEndDate()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(CashFlow $cashflow)
    {
        $this->cashflow = $cashflow;
        $this->isEditing = true;
        $this->description = $cashflow->description;
        $this->amount = $cashflow->amount;
        $this->type = $cashflow->type;
        $this->remarks = $cashflow->remarks;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'description' => $this->description,
            'amount' => $this->amount,
            'type' => $this->type,
            'remarks' => $this->remarks,
            'created_by' => auth()->id(),
        ];

        if (!$this->isEditing) {
            $data['branch_id'] = auth()->user()->branch_id;
        }

        if ($this->isEditing) {
            $this->cashflow->update($data);
            flash()->success('CashFlow updated successfully!');
        } else {
            CashFlow::create($data);
            flash()->success('CashFlow created successfully!');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete($cashflowId)
    {
        $this->cashflowToDelete = $cashflowId;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        $cashflow = CashFlow::find($this->cashflowToDelete);
        if ($cashflow) {
            $cashflow->delete();
            flash()->success('CashFlow deleted successfully!');
        }
        $this->confirmingDelete = false;
        $this->cashflowToDelete = null;
    }

    private function resetForm()
    {
        $this->reset(['description', 'amount', 'remarks', 'type']);
        $this->cashflow = null;
    }

    #[Title('CashFlow')]
    public function with(): array
    {
        $query = CashFlow::query()
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->latest();

        if ($this->filterType) {
            $query->where('description', $this->filterType);
            // if ($this->filterType === 'in' || $this->filterType === 'out') {
            //     $query->where('type', $this->filterType);
            // } else {
            //     $query->where('description', $this->filterType);
            // }
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('description', 'like', '%' . $this->search . '%')->orWhere('remarks', 'like', '%' . $this->search . '%');
            });
        }

        return [
            'cashflows' => $query->paginate(10),
            'counts' => [
                'all' => CashFlow::whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])->count(),
                'in' => CashFlow::whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                    ->where('description', 'Money In')
                    ->count(),
                'out' => CashFlow::whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                    ->where('description', 'Money Out')
                    ->count(),
                'expenses' => CashFlow::whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                    ->where('description', 'Expenses')
                    ->count(),
                'h1_cod' => CashFlow::whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                    ->where('description', 'H1 COD')
                    ->count(),
                'h2_cod' => CashFlow::whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                    ->where('description', 'H2 COD')
                    ->count(),
                'collection' => CashFlow::whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                    ->where('description', 'Collection')
                    ->count(),
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
                        <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">CashFlow</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <!-- Date Range Filters -->
        <div class="flex flex-col sm:flex-row gap-4 mb-4">
            <div class="flex-1">
                <label for="startDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start
                    Date</label>
                <input wire:model.live="startDate" type="date" id="startDate"
                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
            </div>
            <div class="flex-1">
                <label for="endDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End
                    Date</label>
                <input wire:model.live="endDate" type="date" id="endDate"
                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
            </div>
        </div>

        <!-- Filter Buttons -->
        <div class="flex flex-wrap gap-2 mb-4">
            <button wire:click="applyFilter('')"
                class="px-3 py-1 rounded-full text-sm font-medium
                    @if ($filterType === '') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300
                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                All ({{ $counts['all'] }})
            </button>
            <button wire:click="applyFilter('Money In')"
                class="px-3 py-1 rounded-full text-sm font-medium
                    @if ($filterType === 'Money In') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300
                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                Money In ({{ $counts['in'] }})
            </button>
            <button wire:click="applyFilter('Money Out')"
                class="px-3 py-1 rounded-full text-sm font-medium
                    @if ($filterType === 'Money Out') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300
                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                Money Out ({{ $counts['out'] }})
            </button>
            <button wire:click="applyFilter('Expenses')"
                class="px-3 py-1 rounded-full text-sm font-medium
                    @if ($filterType === 'Expenses') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300
                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                Expenses ({{ $counts['expenses'] }})
            </button>
            <button wire:click="applyFilter('H1 COD')"
                class="px-3 py-1 rounded-full text-sm font-medium
                    @if ($filterType === 'H1 COD') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300
                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                H1 COD ({{ $counts['h1_cod'] }})
            </button>
            <button wire:click="applyFilter('H2 COD')"
                class="px-3 py-1 rounded-full text-sm font-medium
                    @if ($filterType === 'H2 COD') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300
                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                H2 COD ({{ $counts['h2_cod'] }})
            </button>
            <button wire:click="applyFilter('Collection')"
                class="px-3 py-1 rounded-full text-sm font-medium
                    @if ($filterType === 'Collection') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300
                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                Collection ({{ $counts['collection'] }})
            </button>
        </div>

        <div class="flex items-center justify-between">
            <div class="w-1/3">
                <input wire:model.live="search" type="search" placeholder="Search CashFlows..."
                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
            </div>
        </div>

        @if ($cashflows->isEmpty())
            <div class="flex flex-col items-center justify-center p-8">
                <p class="mb-4 text-gray-500 dark:text-gray-400">No CashFlows found</p>
                @can('cashflow.create')
                    <button wire:click="create"
                        class="inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-3 text-sm font-medium text-white transition-all duration-200 ease-in-out hover:bg-green-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 active:bg-green-800 dark:bg-green-500 dark:hover:bg-green-600 dark:focus:ring-green-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="my-auto mr-2 h-5 w-5" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Add CashFlow
                    </button>
                @endcan
            </div>
        @else
            <div class="flex justify-end">
                @can('cashflow.create')
                    <button wire:click="create"
                        class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 dark:bg-green-500 dark:hover:bg-green-600">
                        Add CashFlow
                    </button>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Description
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Amount
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Type
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Remarks
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Created By
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Created At
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach ($cashflows as $cashflow)
                            <tr class="dark:hover:bg-gray-800">
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ $cashflow->description }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ number_format($cashflow->amount, 2) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    @if ($cashflow->type === 'in')
                                        <span
                                            class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-300">
                                            Money In
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:text-red-300">
                                            Money Out
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ $cashflow->remarks }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ $cashflow->creator->name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ $cashflow->created_at->format('M d, Y h:i A') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 space-x-2">
                                    @can('cashflow.edit')
                                        <button wire:click="edit({{ $cashflow->id }})"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            Edit
                                        </button>
                                    @endcan
                                    @can('cashflow.delete')
                                        <button wire:click="confirmDelete({{ $cashflow->id }})"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                            Delete
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $cashflows->links() }}
            </div>
        @endif
    </div>

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
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 mb-4">
                                {{ $isEditing ? 'Edit ' : 'Add ' }} CashFlow
                            </h3>

                            <div class="mb-4">
                                <label for="modalDescription"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Description
                                </label>
                                <select wire:model="description" id="modalDescription" required
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
                                    <option value="">Select Description</option>
                                    <option value="Money In">Money In</option>
                                    <option value="Money Out">Money Out</option>
                                    <option value="Expenses">Expenses</option>
                                    <option value="H1 COD">H1 COD</option>
                                    <option value="H2 COD">H2 COD</option>
                                    <option value="Collection">Collection</option>
                                    <option value="Other">Other</option>
                                </select>
                                @error('description')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="amount"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Amount
                                </label>
                                <input wire:model="amount" type="number" step="0.01" id="amount" required
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
                                @error('amount')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="remarks"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Remarks
                                </label>
                                <textarea wire:model="remarks" id="remarks" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600"></textarea>
                                @error('remarks')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="type"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Type
                                </label>
                                <select wire:model="type" id="type" required
                                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
                                    <option value="">Select Type</option>
                                    <option value="in">Money In</option>
                                    <option value="out">Money Out</option>
                                </select>
                                @error('type')
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
                                    Delete CashFlow
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to delete this CashFlow? This action cannot be undone.
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
