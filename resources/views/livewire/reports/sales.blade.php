<?php

use Livewire\Volt\Component;
use App\Models\Payment;
use Carbon\Carbon;
use App\Exports\PaymentsExport;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component {
    public $startDate;
    public $endDate;
    public $paymentMethod = '';
    public $paymentScheme = '';
    public $paymentStatus = '';
    public $payments = [];
    public $totalAmount = 0;

    public function mount()
    {
        $this->startDate = Carbon::now()->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
        $this->loadPayments();
    }

    public function loadPayments()
    {
        $query = Payment::query()->with(['order'])
            ->where('branch_id', auth()->user()->branch_id)
            ->whereHas('order')
            ->whereBetween('created_at', [$this->startDate, Carbon::parse($this->endDate)->endOfDay()]);
        if ($this->paymentMethod) {
            $query->where('payment_method', $this->paymentMethod);
        }

        if ($this->paymentScheme) {
            $query->where('payment_scheme', $this->paymentScheme);
        }

        if ($this->paymentStatus) {
            $query->where('payment_status', $this->paymentStatus);
        }

        $this->payments = $query->get();
        $this->totalAmount = $this->payments->sum('amount_paid');
    }

    public function exportExcel()
    {
        $fileName = 'sales-report-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new PaymentsExport($this->startDate, $this->endDate, $this->paymentMethod, $this->paymentScheme), $fileName);
    }

    public function updated($property)
    {
        if (in_array($property, ['startDate', 'endDate', 'paymentMethod', 'paymentScheme'])) {
            $this->loadPayments();
        }
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Sales Report</h2>
            <p class="text-sm text-muted-foreground">Track and analyze payment transactions</p>
        </div>

        <button wire:click="exportExcel"
            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium transition-colors rounded-md bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export Excel
        </button>
    </div>

    <div class="p-6 space-y-6 border rounded-lg bg-card text-card-foreground shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
            <div class="space-y-2">
                <label
                    class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Start
                    Date</label>
                <input type="date" wire:model.live="startDate"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
            </div>

            <div class="space-y-2">
                <label
                    class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">End
                    Date</label>
                <input type="date" wire:model.live="endDate"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
            </div>

            <div class="space-y-2">
                <label
                    class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Payment
                    Method</label>
                <select wire:model.live="paymentMethod"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                    <option value="">All Methods</option>
                    <option value="cash">Cash</option>
                    <option value="cod">COD</option>
                    <option value="sign">Sign</option>
                    <option value="returned">Returned</option>
                    <option value="refund">Refund</option>
                </select>
            </div>

            <div class="space-y-2">
                <label
                    class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Payment
                    Scheme</label>
                <select wire:model.live="paymentScheme"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                    <option value="">All Schemes</option>
                    <option value="full-payment">Full Payment</option>
                    <option value="partial-payment">Partial Payment</option>
                </select>
            </div>

            <div class="space-y-2">
                <label
                    class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Payment
                    Status</label>
                <select wire:model.live="paymentStatus"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                    <option value="">All Status</option>
                    <option value="paid">Paid</option>
                    <option value="not-paid">Not-Paid</option>
                </select>
            </div>
        </div>

        <div class="rounded-md border">
            <div class="relative w-full overflow-auto">
                <table class="w-full caption-bottom text-sm">
                    <thead class="[&_tr]:border-b">
                        <tr class="border-b transition-colors hover:bg-muted/50">
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Date</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Reference
                            </th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Customer</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Method</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Scheme</th>
                            <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="[&_tr:last-child]:border-0">
                        @forelse($payments as $payment)
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle">{{ $payment->created_at->format('M d, Y') }}</td>
                                <td class="p-4 align-middle">{{ $payment->order?->order_number ?? 'No Receipt Number' }}</td>
                                <td class="p-4 align-middle">{{ $payment->order?->customer->name ?? 'Walk-in' }}</td>
                                <td class="p-4 align-middle">{{ ucfirst($payment->payment_method) }}</td>
                                <td class="p-4 align-middle">{{ ucfirst($payment->payment_scheme) }}</td>
                                <td class="p-4 text-right align-middle">{{ number_format($payment->amount_paid, 2) }}</td>
                            </tr>
                        @empty
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <td colspan="5" class="p-4 text-center text-muted-foreground">No payments found</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-medium bg-muted/50">
                            <td colspan="5" class="p-4 align-middle">Total</td>
                            <td class="p-4 text-right align-middle">Php {{ number_format($totalAmount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
