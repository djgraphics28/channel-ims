<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Branch;
use Livewire\Attributes\Title;
use Spatie\Permission\Models\Role;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $user;
    public $isEditing = false;
    public $confirmingDelete = false;
    public $userToDelete;
    public $roles = [];
    public $selectedRoles = [];
    public $branches = [];
    public $branch;
    public $name;
    public $email;
    public $password;

    public function mount()
    {
        $this->roles = Role::all();
        $this->branches = Branch::all();
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'branch' => 'required',
            'email' => $this->isEditing ? 'required|email|unique:users,email,' . $this->user->id : 'required|email|unique:users,email',
            'password' => $this->isEditing ? '' : 'required|min:8',
            'selectedRoles' => 'array',
        ];
    }

    public function create()
    {
        $this->resetForm();
        $this->selectedRoles = [];
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->branch = $user->branch_id;
        $this->email = $user->email;
        $this->selectedRoles = $user->roles->pluck('id')->toArray();
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        // Convert all role IDs to integers
        $this->selectedRoles = array_map('intval', $this->selectedRoles);

        $formData = [
            'name' => $this->name,
            'email' => $this->email,
            'branch_id' => $this->branch,
        ];

        if (!$this->isEditing) {
            $formData['password'] = bcrypt($this->password);
        }

        if ($this->isEditing) {
            $this->user->update($formData);
            $this->user->syncRoles($this->selectedRoles);
            flash()->success('User updated successfully!');
        } else {
            $user = User::create($formData);
            $user->assignRole($this->selectedRoles);
            flash()->success('User created successfully!');
        }

        $this->showModal = false;
        $this->resetForm();
    }
    public function confirmDelete($userId)
    {
        $this->userToDelete = $userId;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        $user = User::find($this->userToDelete);
        if ($user) {
            $user->roles()->detach();
            $user->delete();
            flash()->success('User deleted successfully!');
        }
        $this->confirmingDelete = false;
        $this->userToDelete = null;
    }

    private function resetForm()
    {
        $this->form = [
            'name' => '',
            'email' => '',
            'password' => '',
        ];
        $this->selectedRoles = [];
        $this->user = null;
    }

    #[Title('Users')]
    public function with(): array
    {
        return [
            'users' => User::with(['roles','branch'])
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
                        <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">Users</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between">
            <div class="w-1/3">
                <input wire:model.live="search" type="search" placeholder="Search users..."
                    class="w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:outline-none transition duration-200 dark:border-gray-600">
            </div>
        </div>
        @if ($users->isEmpty())
            <div class="flex flex-col items-center justify-center p-8">
                <p class="mb-4 text-gray-500 dark:text-gray-400">No users found</p>
                <button wire:click="create"
                    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-3 text-sm font-medium text-white transition-all duration-200 ease-in-out hover:bg-green-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 active:bg-green-800 dark:bg-green-500 dark:hover:bg-green-600 dark:focus:ring-green-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="my-auto mr-2 h-5 w-5" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                            clip-rule="evenodd" />
                    </svg>
                    Add User
                </button>
            </div>
        @else
            <div class="flex justify-end">
                <button wire:click="create"
                    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 dark:bg-green-500 dark:hover:bg-green-600">
                    Add User
                </button>
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
                                Roles</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Branch</th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Status</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach ($users as $user)
                        {{-- //show only user id greater than 1 --}}
                            @if ($user->id == 1)
                                @continue
                            @endif
                            <tr class="dark:hover:bg-gray-800">
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $user->name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $user->email }}</td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">
                                    @foreach ($user->roles as $role)
                                        <span
                                            class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-300">
                                            {{ $role->name }}
                                        </span>
                                    @endforeach
                                    {{-- {{ $user->roles->pluck('name')->implode(', ') }} --}}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 dark:text-gray-300">{{ $user->branch->name ?? '' }}</td>

                                <td class="text-center">
                                    {{-- <livewire:widget.active-status-change model="User" modelId="1" /> --}}
                                    @livewire('widget.active-status-change', ['model' => $user, 'field' => 'is_active'], key($user->id))
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 space-x-2">
                                    @can('users.edit')

                                        <button wire:click="edit({{ $user->id }})"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Edit</button>

                                    @endcan
                                    @can('users.delete')
                                        <button wire:click="confirmDelete({{ $user->id }})"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $users->links() }}
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
                                <flux:input wire:model="name" :label="__('Name')" type="text"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="email" :label="__('Email')" type="email"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                            </div>
                            <div class="mb-4">
                                <flux:input wire:model="password" :label="__('Password')" type="password"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                            </div>
                            <div class="mb-4">
                                <flux:select wire:model="branch" :label="__('Branch')"
                                    class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600">
                                    <option value="">Select</option>
                                    @foreach ($branches as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Roles</label>
                                <div class="mt-2 space-y-2">
                                    @foreach ($roles as $role)
                                        <div class="flex items-center">
                                            <input type="checkbox" wire:model="selectedRoles"
                                                value="{{ $role->id }}"
                                                class="h-6 w-6 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                                id="role-{{ $role->id }}">
                                            <label for="role-{{ $role->id }}"
                                                class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $role->name }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('selectedRoles')
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
                                    Delete User
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to delete this user? This action cannot be undone.
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
