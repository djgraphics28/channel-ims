<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Branch;
use Livewire\Attributes\Title;


new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $branch;
    public $isEditing = false;

    public $form = [
        'code' => '',
        'name' => '',
        'address' => '',
    ];

    public function rules()
    {
        return [
            'form.code' => 'required|string|max:50',
            'form.name' => 'required|string|max:255',
            'form.address' => 'required|string',
        ];
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(Branch $branch)
    {
        $this->branch = $branch;
        $this->form = $branch->only(['code', 'name', 'address']);
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->isEditing) {
            $this->branch->update($this->form);
            $this->dispatch('notify', 'Branch updated successfully!', 'success');
        } else {
            Branch::create($this->form);
            $this->dispatch('notify', 'Branch created successfully!', 'success');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete($branchId)
    {
        $this->dispatch('confirm-delete', $branchId);
    }

    public function delete(Branch $branch)
    {
        $branch->delete();
        $this->dispatch('notify', 'Branch deleted successfully!', 'success');
    }

    private function resetForm()
    {
        $this->form = [
            'code' => '',
            'name' => '',
            'address' => '',
        ];
        $this->branch = null;
    }

    #[Title('Branches')]
    public function with(): array
    {
        return [
            'branches' => Branch::query()
                ->where('name', 'like', '%' . $this->search . '%')
                ->paginate(10),
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
                        <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">Branches</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between">
            <div class="w-1/3">
                <input wire:model.live="search" type="search" placeholder="Search branches..."
                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
            </div>
        </div>
        @if ($branches->isEmpty())
            <div class="flex flex-col items-center justify-center p-8">
                <p class="mb-4 text-gray-500 dark:text-gray-400">No branches found</p>
                <button wire:click="create"
                    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-3 text-sm font-medium text-white transition-all duration-200 ease-in-out hover:bg-green-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 active:bg-green-800 dark:bg-green-500 dark:hover:bg-green-600 dark:focus:ring-green-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="my-auto mr-2 h-5 w-5" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                            clip-rule="evenodd" />
                    </svg>
                    Add Branch
                </button>
            </div>
        @else
            <div class="flex justify-end">
                <button wire:click="create"
                    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 dark:bg-green-500 dark:hover:bg-green-600">
                    Add Branch
                </button>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Code</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Name</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Address</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach ($branches as $branch)
                            <tr class="dark:hover:bg-gray-800">
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $branch->code }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $branch->name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $branch->address }}</td>
                                <td class="whitespace-nowrap px-6 py-4 space-x-2">
                                    <button wire:click="edit({{ $branch->id }})"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Edit</button>
                                    <button wire:click="confirmDelete({{ $branch->id }})"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $branches->links() }}
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
                            <div class="mb-4">
                                <flux:input wire:model="form.code" :label="__('Code')" type="text" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('form.code')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="form.name" :label="__('Name')" type="text" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('form.name')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="form.address" :label="__('Address')" type="text" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('form.address')
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    Livewire.on('confirm-delete', branchId => {
        Swal.fire({
            title: 'Are you sure?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                Livewire.dispatch('delete', branchId);
            }
        });
    });
</script>
