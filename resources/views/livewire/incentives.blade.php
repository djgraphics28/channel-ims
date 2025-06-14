<?php

use Livewire\Volt\Component;
use App\Models\Incentive;
use App\Models\IncentiveAgent;
use App\Models\Order;
use App\Models\Employee;

new class extends Component {
    public $start;
    public $end;
    public $percentage;
    public $incentives;
    public $selectedIncentive;
    public $showAgents = false;
    public $confirmingDelete = false;
    public $deleteId;

    public function mount()
    {
        $this->incentives = Incentive::with('agents')->latest()->get();
    }

    public function generateIncentive()
    {
        $this->validate([
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        $incentive = Incentive::create([
            'start' => $this->start,
            'end' => $this->end,
            'branch_id' => auth()->user()->branch_id,
            'percentage' => $this->percentage,
            'created_by' => auth()->id(),
        ]);

        // Get all completed orders within date range with assisted_by
        $orders = Order::whereBetween('created_at', [$this->start, $this->end])
            ->where('status', 'completed')
            ->whereNotNull('assisted_by')
            ->where('is_void', 0)
            ->get();

        // Get unique employee IDs from orders and calculate total amount per agent
        $agentTotals = $orders->groupBy('assisted_by')
            ->map(function ($agentOrders) use ($incentive) {
                $totalAmount = $agentOrders->sum('total_amount');
                return [
                    'agent_id' => $agentOrders->first()->assisted_by,
                    'amount' => ($totalAmount * $incentive->percentage) / 100,
                    'total_order_amount' => $totalAmount,
                    'order_count' => $agentOrders->count()
                ];
            });

        // Create incentive agent records with calculated amounts
        foreach ($agentTotals as $agentData) {
            IncentiveAgent::create([
                'incentive_id' => $incentive->id,
                'agent_id' => $agentData['agent_id'],
                'amount' => $agentData['amount'],
            ]);
        }

        $this->reset(['start', 'end', 'percentage']);
        $this->incentives = Incentive::with('agents')->latest()->get();

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Incentive generated successfully!']);
    }
    public function editIncentive($id)
    {
        $incentive = Incentive::find($id);
        $this->start = $incentive->start;
        $this->end = $incentive->end;
        $this->percentage = $incentive->percentage;
        $this->selectedIncentive = $incentive;
    }

    public function deleteIncentive()
    {
        Incentive::find($this->deleteId)->delete();
        $this->incentives = Incentive::with('agents')->latest()->get();
        $this->confirmingDelete = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Incentive deleted successfully!']);
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function showAgentsList($id)
    {
        $this->selectedIncentive = Incentive::with(['agents', 'agents.employee'])->find($id);
        $this->showAgents = true;
    }

    public function exportIncentives()
    {
        $incentives = Incentive::with(['agents', 'agents.employee'])->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="incentive_agents_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($incentives) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'ID',
                'Start Date',
                'End Date',
                'Percentage',
                'Agent ID',
                'Agent Name',
                'Amount',
                'Total Order Amount',
                'Number of Assisted Orders'
            ]);

            // Data rows
            foreach ($incentives as $incentive) {
                foreach ($incentive->agents as $agent) {
                    fputcsv($file, [
                        $incentive->id,
                        $incentive->start,
                        $incentive->end,
                        $incentive->percentage . '%',
                        $agent->agent_id,
                        $agent->employee->name ?? 'Unknown',
                        number_format($agent->amount, 2),
                        number_format($agent->total_order_amount, 2),
                        $agent->order_count
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}; ?>

<div>
    <div class="mb-4">
        <nav class="flex items-center" aria-label="Breadcrumb">
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
                            class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">Incentives</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div>
        <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <form wire:submit="generateIncentive" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <flux:input wire:model="start" :label="__('Start Date')" type="date" required
                        class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />

                    <flux:input wire:model="end" :label="__('End Date')" type="date" required
                        class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />

                    <flux:input wire:model="percentage" :label="__('Percentage')" type="number" step="0.01" required
                        class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />
                </div>
                <div class="flex justify-end">
                    <button type="submit"
                        class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50"
                        wire:loading.attr="disabled">
                        <svg wire:loading class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove>Generate Incentive</span>
                        <span wire:loading>Generating...</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6">
            <div class="overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="flex justify-between items-center px-6 py-3 bg-gray-50 dark:bg-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Incentives</h3>
                    <button wire:click="exportIncentives" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-gray-800">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Export
                    </button>
                </div>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Start Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                End Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Percentage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Agents</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($incentives as $incentive)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $incentive->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $incentive->start }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $incentive->end }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $incentive->percentage }}%</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    <button wire:click="showAgentsList({{ $incentive->id }})"
                                        class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                        {{ $incentive->agents->count() }} Agents
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button wire:click="editIncentive({{ $incentive->id }})"
                                        class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 mr-4">Edit</button>
                                    <button wire:click="confirmDelete({{ $incentive->id }})"
                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($showAgents)
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg max-w-4xl w-full max-h-[80vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium dark:text-gray-100">Agents List - {{ $selectedIncentive->start }} to {{ $selectedIncentive->end }}</h3>
                        <button wire:click="$set('showAgents', false)"
                            class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Agent ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Agent Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Total Orders</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Order Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($selectedIncentive->agents as $agent)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                        {{ $agent->agent_id }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                        {{ $agent->employee->first_name ?? 'Unknown' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                        {{ number_format($agent->amount, 2) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                        {{ $agent->order_count }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                        {{ number_format($agent->total_order_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
                                    Delete Incentive
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to delete this incentive? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button wire:click="deleteIncentive"
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

    @push('scripts')
        <script>
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('notify', (data) => {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer)
                            toast.addEventListener('mouseleave', Swal.resumeTimer)
                        }
                    });

                    Toast.fire({
                        icon: data.type,
                        title: data.message
                    });
                });
            });
        </script>
    @endpush
</div>
