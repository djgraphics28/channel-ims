<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Livewire\Attributes\Title;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $role;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $roleToDelete;
    public $permissions = [];
    public $selectedPermissions = [];
    public $selectAll = false;

    public $form = [
        'name' => '',
        'description' => '',
    ];

    public function mount()
    {
        $this->permissions = Permission::all();
    }

    public function rules()
    {
        return [
            'form.name' => 'required|string|max:255',
            'selectedPermissions' => 'required|array',
        ];
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(Role $role)
    {
        $this->role = $role;
        $this->form = [
            'name' => $role->name,
        ];
        $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        // Convert all permission IDs to integers
        $this->selectedPermissions = array_map('intval', $this->selectedPermissions);

        if ($this->isEditing) {
            try {
                $this->role->update($this->form);
                $this->role->syncPermissions($this->selectedPermissions);
                flash()->success('Role updated successfully!');
            } catch (\Exception $e) {
                flash()->error('Error updating role: ' . $e->getMessage());
            }
        } else {
            $role = Role::create($this->form);
            $role->syncPermissions($this->selectedPermissions);
            flash()->success('Role created successfully!');
        }

        $this->showModal = false;
        $this->resetForm();
    }
    public function confirmDelete($roleId)
    {
        $this->roleToDelete = $roleId;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        $role = Role::find($this->roleToDelete);
        if ($role) {
            $role->delete();
            flash()->success('Role deleted successfully!');
        }
        $this->confirmingDelete = false;
        $this->roleToDelete = null;
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedPermissions = $this->permissions->pluck('id')->toArray();
        } else {
            $this->selectedPermissions = [];
        }
    }

    private function resetForm()
    {
        $this->form = [
            'name' => '',
        ];
        $this->selectedPermissions = [];
        $this->role = null;
        $this->selectAll = false;
    }

    #[Title('Roles')]
    public function with(): array
    {
        return [
            'roles' => Role::query()
                ->where('name', 'like', '%' . $this->search . '%')
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
                        <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">Roles</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between">
            <div class="w-1/3">
                <input wire:model.live="search" type="search" placeholder="Search roles..."
                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
            </div>
            <button wire:click="create"
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150 dark:bg-blue-500 dark:hover:bg-blue-600">
                Add Role
            </button>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Name
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Permissions
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach ($roles as $role)
                        <tr class="dark:hover:bg-gray-800">
                            <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $role->name }}</td>
                            <td class="px-6 py-4 dark:text-gray-300">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($role->permissions as $permission)
                                        <span
                                            class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-300">
                                            {{ $permission->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 space-x-2">
                                <button wire:click="edit({{ $role->id }})"
                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Edit</button>
                                <button wire:click="confirmDelete({{ $role->id }})"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($showModal)
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-800 opacity-75"></div>
                    </div>
                    <div
                        class="inline-block transform overflow-hidden rounded-lg bg-white dark:bg-gray-900 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-7xl sm:align-middle">
                        <form wire:submit="save">
                            <div class="bg-white dark:bg-gray-900 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="mb-4">
                                    <flux:input wire:model="form.name" :label="__('Name')" type="text" required
                                        class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                                    @error('form.name')
                                        <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Permissions
                                    </label>
                                    @error('selectedPermissions')
                                        <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span>
                                    @enderror
                                    <div class="flex items-center mb-4">
                                        <flux:switch wire:model.live="selectAll"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700" />
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">Give all
                                            permissions</span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-6">
                                        @php
                                            $groupedPermissions = $permissions->groupBy(function ($permission) {
                                                return explode('.', $permission->name)[0];
                                            });
                                        @endphp

                                        @foreach ($groupedPermissions as $group => $permissions)
                                            <div class="space-y-2">
                                                <h3 class="font-semibold text-gray-900 dark:text-gray-100 capitalize">
                                                    {{ $group }}</h3>
                                                @foreach ($permissions as $permission)
                                                    <div class="flex flex-row-reverse items-center justify-end gap-2">
                                                        <span
                                                            class="text-sm text-gray-700 dark:text-gray-300">{{ str_replace($group . '.', '', $permission->name) }}</span>
                                                        <flux:switch wire:model.live="selectedPermissions"
                                                            value="{{ $permission->id }}"
                                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700" />
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
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
                                <div
                                    class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none"
                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                                        Delete Role
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Are you sure you want to delete this role? This action cannot be undone.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="button" wire:click="delete"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-red-400 sm:ml-3 sm:w-auto sm:text-sm">
                                Delete
                            </button>
                            <button type="button" wire:click="$set('confirmingDelete', false)"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
