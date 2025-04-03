<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Employee;
use Livewire\Attributes\Title;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $employee;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $employeeToDelete;
    public $pageRows = 10;

    public $first_name = '';
    public $middle_name = '';
    public $last_name = '';
    public $suffix = '';
    public $email = '';
    public $phone = '';
    public $address = '';
    public $salary = '';
    public $hire_date = '';
    public $birth_date = '';
    public $position = '';

    public function rules()
    {
        return [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'salary' => 'nullable|numeric',
            'hire_date' => 'nullable|date',
            'birth_date' => 'nullable|date',
            'position' => 'nullable',
        ];
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(Employee $employee)
    {
        $this->employee = $employee;
        $this->first_name = $employee->first_name;
        $this->middle_name = $employee->middle_name;
        $this->last_name = $employee->last_name;
        $this->suffix = $employee->suffix;
        $this->email = $employee->email;
        $this->phone = $employee->phone;
        $this->address = $employee->address;
        $this->salary = $employee->salary;
        $this->hire_date = $employee->hire_date;
        $this->birth_date = $employee->birth_date;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'suffix' => $this->suffix,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'salary' => $this->salary,
            'hire_date' => $this->hire_date,
            'birth_date' => $this->birth_date,
            'position' => $this->position,
        ];

        if ($this->isEditing) {
            $this->employee->update($data);
            flash()->success('Employee updated successfully!');
        } else {
            Employee::create($data);
            flash()->success('Employee created successfully!');
            $this->dispatch('notify', 'Employee created successfully!', 'success');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete($employeeId)
    {
        $this->employeeToDelete = $employeeId;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        $employee = Employee::find($this->employeeToDelete);
        if ($employee) {
            $employee->delete();
            $this->dispatch('notify', 'Employee deleted successfully!', 'success');
        }

        $this->confirmingDelete = false;
        $this->employeeToDelete = null;
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
        $this->first_name = '';
        $this->middle_name = '';
        $this->last_name = '';
        $this->suffix = '';
        $this->email = '';
        $this->phone = '';
        $this->address = '';
        $this->salary = '';
        $this->hire_date = '';
        $this->birth_date = '';
        $this->employee = null;
    }

    #[Title('Employees')]
    public function with(): array
    {
        return [
            'employees' => Employee::query()
                ->where('last_name', 'like', '%' . $this->search . '%')
                ->orWhere('first_name', 'like', '%' . $this->search . '%')
                ->paginate($this->pageRows),
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
                        <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">Employees</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between">
            <div class="w-1/3">
                <input wire:model.live="search" type="search" placeholder="Search employees..."
                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
            </div>
            <div class="flex items-center space-x-2">
                <select wire:model.live="pageRows"
                    class="rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
        </div>
        @if ($employees->isEmpty())
            <div class="flex flex-col items-center justify-center p-8">
                <p class="mb-4 text-gray-500 dark:text-gray-400">No employees found</p>
                @can('employees.create')
                    <button wire:click="create"
                        class="inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-3 text-sm font-medium text-white transition-all duration-200 ease-in-out hover:bg-green-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 active:bg-green-800 dark:bg-green-500 dark:hover:bg-green-600 dark:focus:ring-green-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="my-auto mr-2 h-5 w-5" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Add Employee
                    </button>
                @endcan
            </div>
        @else
            <div class="flex justify-end">
                @can('employees.create')
                    <button wire:click="create"
                        class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 dark:bg-green-500 dark:hover:bg-green-600">
                        Add Employee
                    </button>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Name</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Email</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Phone</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Position</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Salary</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach ($employees as $employee)
                            <tr class="dark:hover:bg-gray-800">
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    {{ $employee->first_name }} {{ $employee->middle_name }} {{ $employee->last_name }}
                                    {{ $employee->suffix }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $employee->email }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $employee->phone }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $employee->position }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $employee->salary }}</td>
                                <td class="whitespace-nowrap px-6 py-4 space-x-2">
                                    @can('employees.edit')
                                        <button wire:click="edit({{ $employee->id }})"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Edit</button>
                                    @endcan
                                    @can('employees.delete')
                                        <button wire:click="confirmDelete({{ $employee->id }})"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                    @endcan

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $employees->links() }}
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
                                <flux:input wire:model="first_name" :label="__('First Name')" type="text" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('first_name')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="middle_name" :label="__('Middle Name')" type="text"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('middle_name')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="last_name" :label="__('Last Name')" type="text" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('last_name')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="suffix" :label="__('Suffix')" type="text"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('suffix')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="email" :label="__('Email')" type="email"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('email')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="phone" :label="__('Phone')" type="text"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('phone')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="address" :label="__('Address')" type="text"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('address')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="position" :label="__('Position')" type="text" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('position')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="salary" :label="__('Salary')" type="number" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('salary')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="hire_date" :label="__('Date Hired')" type="date" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('hire_date')
                                    <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="birth_date" :label="__('Birth Date')" type="date" required
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                @error('birth_date')
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
                                    Delete Employee
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to delete this employee? This action cannot be undone.
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
