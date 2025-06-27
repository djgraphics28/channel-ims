<?php

use Livewire\Volt\Component;
use App\Models\Incentive;
use App\Models\IncentiveAgent;
use App\Models\Order;
use App\Models\Employee;
use App\Models\Branch;

new class extends Component {
    public $start;
    public $end;
    public $percentage = 0.025;
    public $incentives;
    public $selectedIncentive;
    public $confirmingDelete = false;
    public $deleteId;
    public $expandedIncentive = null;
    public $branch;

    public function mount()
    {
        $this->incentives = Incentive::with('agents.employee')->where('branch_id', auth()->user()->branch_id)->latest()->get();
        $this->branch = Branch::find(auth()->user()->branch_id)?->code;
    }

    public function generateIncentive()
    {
        $this->validate([
            'start' => 'required|date|unique:incentives,start,NULL,id,end,' . $this->end,
            'end' => 'required|date|after:start|unique:incentives,end,NULL,id,start,' . $this->start,
        ]);

        $incentive = Incentive::create([
            'start' => $this->start,
            'end' => $this->end,
            'branch_id' => auth()->user()->branch_id,
            'created_by' => auth()->id(),
        ]);

        $orders = Order::whereBetween('created_at', [$this->start, $this->end])
            ->where('status', 'completed')
            ->whereNotNull('assisted_by')
            ->where('is_void', 0)
            ->get();

        $agentTotals = $orders->groupBy('assisted_by')->map(function ($agentOrders) use ($incentive) {
            $totalAmount = $agentOrders->sum('total_amount');
            return [
                'agent_id' => $agentOrders->first()->assisted_by,
                'amount' => $totalAmount * 0.00025,
                'total_order_amount' => $totalAmount,
                'order_count' => $agentOrders->count(),
            ];
        });

        foreach ($agentTotals as $agentData) {
            IncentiveAgent::create([
                'incentive_id' => $incentive->id,
                'agent_id' => $agentData['agent_id'],
                'amount' => $agentData['amount'],
                'total_order_amount' => $agentData['total_order_amount'],
                'order_count' => $agentData['order_count'],
            ]);
        }

        $this->reset(['start', 'end', 'percentage']);
        $this->incentives = Incentive::with('agents.employee')->latest()->get();

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Incentive generated successfully!']);
    }

    public function editIncentive($id)
    {
        $incentive = Incentive::find($id);
        $this->start = $incentive->start;
        $this->end = $incentive->end;
        $this->percentage = $this->percentage;
        $this->selectedIncentive = $incentive;
    }

    public function deleteIncentive()
    {
        Incentive::find($this->deleteId)->delete();
        $this->incentives = Incentive::with('agents.employee')->latest()->get();
        $this->confirmingDelete = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Incentive deleted successfully!']);
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function toggleAgents($id)
    {
        if ($this->expandedIncentive === $id) {
            $this->expandedIncentive = null;
        } else {
            $this->expandedIncentive = $id;
        }
    }

    public function exportIncentive($id)
    {
        $incentive = Incentive::with(['agents', 'agents.employee'])->find($id);
        $branch = Branch::find(auth()->user()->branch_id); // Get branch info again to be sure

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set document properties
        $spreadsheet
            ->getProperties()
            ->setCreator(auth()->user()->name)
            ->setTitle('Incentive Report - ' . date('M Y', strtotime($incentive->start)))
            ->setSubject('Incentive Report');

        // Header rows
        $sheet->mergeCells('A1:B1');
        $sheet->mergeCells('A2:B2');
        $sheet->mergeCells('C3:D3');
        $sheet->mergeCells('E3:F3');

        $sheet->setCellValue('A1', 'Location: '.'Channel-' . ($branch->code ?? 'N/A'));
        // $sheet->setCellValue('B1', 'Channel-' . ($branch->code ?? 'N/A')); // Use branch code safely
        $sheet->setCellValue('A2', 'Month: '. date('M Y', strtotime($incentive->start)));
        // $sheet->setCellValue('B2', date('M Y', strtotime($incentive->start))); // Changed to M Y format
        $sheet->setCellValue('C3', 'Total Sales');
        $sheet->setCellValue('E3', '# of Receipt');

        // Column headers
        $sheet->setCellValue('A4', 'Server Name');
        $sheet->setCellValue('B4', 'ID');
        $sheet->setCellValue('C4', 'Sales');
        $sheet->setCellValue('D4', 'Incentive');
        $sheet->setCellValue('E4', 'Receipt');
        $sheet->setCellValue('F4', 'Incentive');
        $sheet->setCellValue('G4', 'Total');

        // Format header row
        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A4:G4')->applyFromArray($headerStyle);

        $row = 5;
        $totals = 0;
        $totalSales = 0;
        $totalIncentive = 0;
        $totalReceipts = 0;
        $totalReceiptIncentive = 0;

        foreach ($incentive->agents as $agent) {
            $sales = $agent->total_order_amount;
            $incentiveAmount = $agent->amount;
            $receipts = $agent->order_count;
            $receiptIncentive = $receipts * 0.25;
            $totalIncentives = $incentiveAmount + $receiptIncentive;
            $totals += $totalIncentives;

            $sheet->setCellValue('A' . $row, $agent->employee->first_name ?? 'Unknown');
            $sheet->setCellValue('B' . $row, $agent->agent_id);
            $sheet->setCellValue('C' . $row, $sales);
            $sheet->setCellValue('D' . $row, $incentiveAmount);
            $sheet->setCellValue('E' . $row, $receipts); // Receipt count (integer)
            $sheet->setCellValue('F' . $row, $receiptIncentive);
            $sheet->setCellValue('G' . $row, $totalIncentives);

            $totalSales += $sales;
            $totalIncentive += $incentiveAmount;
            $totalReceipts += $receipts;
            $totalReceiptIncentive += $receiptIncentive;

            $row++;
        }

        $totalRow = $row + 1;

        // Format numbers
        $sheet
            ->getStyle('C5:D' . ($row - 1))
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');
        $sheet
            ->getStyle('E5:E' . ($row - 1))
            ->getNumberFormat()
            ->setFormatCode('0'); // Integer format for receipt count
        $sheet
            ->getStyle('F5:G' . ($row - 1))
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        // Totals row
        $sheet->setCellValue('C' . $totalRow, $totalSales);
        $sheet->setCellValue('D' . $totalRow, $totalIncentive);
        $sheet->setCellValue('E' . $totalRow, $totalReceipts);
        $sheet->setCellValue('F' . $totalRow, $totalReceiptIncentive);
        $sheet->setCellValue('G' . $totalRow, $totals);

        // Format totals row
        $totalsStyle = [
            'font' => ['bold' => true],
            'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->applyFromArray($totalsStyle);
        $sheet
            ->getStyle('C' . $totalRow . ':D' . $totalRow)
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');
        $sheet
            ->getStyle('E' . $totalRow)
            ->getNumberFormat()
            ->setFormatCode('0'); // Integer format for total receipts
        $sheet
            ->getStyle('F' . $totalRow . ':G' . $totalRow)
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        // Style Total Sales columns blue
        $sheet
            ->getStyle('C3:D' . $totalRow)
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('ADD8E6');

        // Style Receipt columns peach
        $sheet
            ->getStyle('E3:F' . $totalRow)
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('FFDAB9');

        // Auto size columns
        foreach (range('A', 'G') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $filename = 'Incentive_Report_' . date('M_Y', strtotime($incentive->start)) . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename);
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
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="start" :label="__('Start Date')" type="date" required
                        class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />

                    <flux:input wire:model="end" :label="__('End Date')" type="date" required
                        class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" />

                    {{-- <flux:input wire:model="percentage" :label="__('Percentage')" type="number" step="0.01" readonly
                        class="dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600" /> --}}
                </div>
                <div class="flex justify-end">
                    <button type="submit"
                        class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50"
                        wire:loading.attr="disabled">
                        <svg wire:loading class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
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
                </div>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                ID</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Period</th>
                            {{-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Percentage</th> --}}
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Total Sales</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Servers</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Total Incentives</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($incentives as $incentive)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $incentive->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ date('M d', strtotime($incentive->start)) }} -
                                    {{ date('M d, Y', strtotime($incentive->end)) }}</td>
                                {{-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $incentive->percentage }}%</td> --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    ₱{{ number_format($incentive->agents->sum('total_order_amount'), 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    <button wire:click="toggleAgents({{ $incentive->id }})"
                                        class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                        {{ $incentive->agents->count() }} Servers
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    ₱{{ number_format($incentive->agents->sum('amount'), 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    {{-- <button wire:click="editIncentive({{ $incentive->id }})"
                                        class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">Edit</button> --}}
                                    <button wire:click="confirmDelete({{ $incentive->id }})"
                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">Delete</button>
                                    <a href="#" wire:click.prevent="exportIncentive({{ $incentive->id }})"
                                        class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300">
                                        Export
                                    </a>
                                </td>
                            </tr>

                            @if ($expandedIncentive === $incentive->id)
                                <tr>
                                    <td colspan="7" class="px-0 py-0">
                                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                                                Servers Incentive Details</h4>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                                    <thead class="bg-gray-100 dark:bg-gray-600">
                                                        <tr>
                                                            <th
                                                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                                Server</th>
                                                            <th
                                                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                                ID</th>
                                                            <th
                                                                class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                                Total Sales</th>
                                                            <th
                                                                class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                                Incentive </th>
                                                            <th
                                                                class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                                # of Receipts</th>
                                                            <th
                                                                class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                                Receipt Incentive </th>
                                                            <th
                                                                class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                                Total
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody
                                                        class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                                                        @foreach ($incentive->agents as $agent)
                                                            @php
                                                                $incentiveAmount = $agent->amount;
                                                                $orderCountIncentives = $agent->order_count * 0.25;
                                                                $totalIncentives =
                                                                    $incentiveAmount + $orderCountIncentives;
                                                                $totals = ($totals ?? 0) + $totalIncentives;
                                                            @endphp
                                                            <tr>
                                                                <td
                                                                    class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                                    {{ $agent->employee->first_name ?? 'Unknown' }}
                                                                </td>
                                                                <td
                                                                    class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                                    {{ $agent->agent_id }}</td>
                                                                <td
                                                                    class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">
                                                                    ₱{{ number_format($agent->total_order_amount, 2) }}
                                                                </td>
                                                                <td
                                                                    class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">
                                                                    ₱{{ number_format($incentiveAmount, 2) }}</td>
                                                                <td
                                                                    class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">
                                                                    {{ $agent->order_count }}</td>
                                                                <td
                                                                    class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">
                                                                    ₱{{ number_format($orderCountIncentives, 2) }}</td>
                                                                <td
                                                                    class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">
                                                                    ₱{{ number_format($totalIncentives, 2) }}</td>
                                                            </tr>
                                                        @endforeach
                                                        <tr class="bg-gray-50 dark:bg-gray-700 font-semibold">
                                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"
                                                                colspan="2">
                                                                Totals</td>
                                                            <td
                                                                class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 text-center">
                                                                ₱{{ number_format($incentive->agents->sum('total_order_amount'), 2) }}
                                                            </td>
                                                            <td
                                                                class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 text-center">
                                                                ₱{{ number_format($incentive->agents->sum('amount'), 2) }}
                                                            </td>
                                                            <td
                                                                class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 text-center">
                                                                {{ $incentive->agents->sum('order_count') }}</td>
                                                            <td
                                                                class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 text-center">
                                                                ₱{{ number_format($incentive->agents->sum('order_count') * 0.25, 2) }}
                                                            </td>
                                                            <td
                                                                class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 text-center">
                                                                ₱{{ number_format($totals, 2) }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
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
