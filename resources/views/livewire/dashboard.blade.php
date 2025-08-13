<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\CashFlow;
use App\Models\Branch;
use App\Models\Order;
use Carbon\Carbon;

new class extends Component {
    public $salesData = [];
    public $bestSellersData = [];
    public $bestSellersLabels = [];
    public $branches = [];
    public $selectedBranch = null;
    public $branchSales = [];
    public $totalTransactions = 0;

    public function mount()
    {
        if (
            auth()
                ->user()
                ->hasRole(['superadmin'])
        ) {
            $this->branches = Branch::all();
        } else {
            $this->selectedBranch = auth()->user()->branch_id;
        }
    }

    #[Title('Dashboard')]
    public function with()
    {
        // Base query for payments with branch filtering
        $paymentQuery = Payment::when($this->selectedBranch, function ($query) {
            $query->whereHas('order', function ($q) {
                $q->where('branch_id', $this->selectedBranch);
            });
        })->when(
            !auth()
                ->user()
                ->hasRole(['superadmin']),
            function ($query) {
                $query->whereHas('order', function ($q) {
                    $q->where('branch_id', auth()->user()->branch_id);
                });
            },
        );

        // Weekly sales data
        $today = today();
        $startOfWeek = $today->copy()->startOfWeek();
        $endOfWeek = $today->copy()->endOfWeek();
        $weeklySales = [];
        $weeklyLabels = [];

        $currentDate = $startOfWeek->copy();
        while ($currentDate <= $endOfWeek) {
            $daySales = (clone $paymentQuery)->whereDate('created_at', $currentDate)->sum('amount_paid');

            $weeklySales[] = $daySales;
            $weeklyLabels[] = $currentDate->format('l, F j');
            $currentDate->addDay();
        }

        $this->salesData = $weeklySales;

        // Best sellers data
        $bestSellers = Product::withCount([
            'orderItems as total_sold' => function ($query) {
                $query->whereHas('order', function ($q) {
                    $q->where('status', 'completed')
                        ->where('is_void', false)
                        ->whereHas('payment', function ($p) {
                            $p->where('payment_method', '!=', 'delivery-only');
                        })
                        ->when($this->selectedBranch, function ($q) {
                            $q->where('branch_id', $this->selectedBranch);
                        })
                        ->when(
                            !auth()
                                ->user()
                                ->hasRole(['superadmin']),
                            function ($q) {
                                $q->where('branch_id', auth()->user()->branch_id);
                            },
                        );
                });
            },
        ])
            ->orderByDesc('total_sold')
            ->get()
            ->filter(function ($product) {
                return $product->total_sold > 0;
            })
            ->take(5);

        $this->bestSellersData = $bestSellers->pluck('total_sold')->toArray();
        $this->bestSellersLabels = $bestSellers->pluck('name')->toArray();

        $this->totalTransactions = Order::when(
            !auth()
                ->user()
                ->hasRole(['superadmin']),
            function ($q) {
                $q->where('branch_id', auth()->user()->branch_id);
            },
        )
            ->when($this->selectedBranch, function ($q) {
                $q->where('branch_id', $this->selectedBranch);
            })
            ->where('is_void', false) 
            ->whereDate('created_at', today())
            ->count();

        // Daily metrics
        // $dailySalesQuery = (clone $paymentQuery)->whereDate('created_at', today());
        $dailySalesQuery = (clone $paymentQuery)
            ->whereDate('created_at', today())
            ->whereHas('order', function ($q) {
                $q->where('is_void', false);
            });
        $dailySales = $dailySalesQuery->sum('amount_paid');
        $dailySalesForCoh = (clone $dailySalesQuery)->whereNotIn('payment_method', ['cod', 'sign'])->sum('amount_paid'); //i want to have a separate $dailySales for each Branches
        $branchSales = collect($this->branches)
            ->map(function ($branch) use ($dailySalesQuery) {
                return [
                    'name' => $branch->name,
                    'sales' => (clone $dailySalesQuery)
                        ->whereHas('order', function ($q) use ($branch) {
                            $q->where('branch_id', $branch->id);
                        })
                        ->sum('amount_paid'),
                ];
            })
            ->values()
            ->all();

        $this->branchSales = $branchSales;

        $totalCash = (clone $dailySalesQuery)->where('payment_method', 'cash')->where('payment_status', 'paid')->sum('amount_paid');
        $totalSalesOnly = (clone $dailySalesQuery)->where('payment_method', 'sales-only')->where('payment_status', 'paid')->sum('amount_paid');
        $totalCod = (clone $dailySalesQuery)
            ->where('payment_method', 'cod')
            ->whereIn('payment_status', ['paid', 'not-paid'])
            ->sum('amount_paid');
        $totalSign = (clone $dailySalesQuery)
            ->where('payment_method', 'sign')
            ->whereIn('payment_status', ['paid', 'not-paid'])
            ->sum('amount_paid');
        $totalReturn = (clone $dailySalesQuery)->where('payment_method', 'return')->where('payment_status', 'paid')->sum('amount_paid');
        $totalRefund = (clone $dailySalesQuery)->where('payment_method', 'refund')->where('payment_status', 'paid')->sum('amount_paid');

        $dailySales = $dailySales - ($totalReturn - $totalRefund);

        // Cashflows
        $cashFlowQuery = CashFlow::whereDate('created_at', today())
            ->when($this->selectedBranch, function ($query) {
                $query->where('branch_id', $this->selectedBranch);
            })
            ->when(
                !auth()
                    ->user()
                    ->hasRole(['superadmin']),
                function ($query) {
                    $query->where('branch_id', auth()->user()->branch_id);
                },
            );

        $totalMoneyIn = $cashFlowQuery->clone()->where('type', 'in')->sum('amount');
        $totalMoneyOut = $cashFlowQuery->clone()->where('type', 'out')->sum('amount');
        $cashOnHand = $dailySalesForCoh + $totalMoneyIn - $totalMoneyOut;

        // Customers count (assuming customers have branch_id)
        $totalCustomers = Customer::all()->count();

        return [
            'totalProducts' => Product::count(), // Products aren't branch-specific
            'totalCustomers' => $totalCustomers,
            'dailySales' => $dailySales,
            'totalCash' => $totalCash,
            'totalSalesOnly' => $totalSalesOnly,
            'totalCod' => $totalCod,
            'totalSign' => $totalSign,
            'totalReturn' => $totalReturn,
            'totalRefund' => $totalRefund,
            'totalMoneyIn' => $totalMoneyIn,
            'totalMoneyOut' => $totalMoneyOut,
            'cashOnHand' => $cashOnHand,
            'salesChartData' => $this->getSalesChartData(),
        ];
    }
    public function getSalesChartData()
    {
        $paymentQuery = Payment::where('payment_status', 'paid')
            ->when($this->selectedBranch, function ($query) {
                $query->whereHas('order', function ($q) {
                    $q->where('branch_id', $this->selectedBranch)->where('is_void', false);
                });
            })
            ->when(
                !auth()
                    ->user()
                    ->hasRole(['superadmin']),
                function ($query) {
                    $query->whereHas('order', function ($q) {
                        $q->where('branch_id', auth()->user()->branch_id)->where('is_void', false);
                    });
                },
            );

        // Daily data for last 7 days
        $dailyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyData[] = [
                'date' => $date->format('D, M j'),
                'total' => (clone $paymentQuery)->whereDate('created_at', $date)->sum('amount_paid'),
                'cash' => (clone $paymentQuery)->whereDate('created_at', $date)->where('payment_method', 'cash')->sum('amount_paid'),
                'cod' => (clone $paymentQuery)->whereDate('created_at', $date)->where('payment_method', 'cod')->sum('amount_paid'),
                'sign' => (clone $paymentQuery)->whereDate('created_at', $date)->where('payment_method', 'sign')->sum('amount_paid'),
            ];
        }

        // Monthly data for last 12 months
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyData[] = [
                'month' => $date->format('M Y'),
                'total' => (clone $paymentQuery)->whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)->sum('amount_paid'),
            ];
        }

        // Payment methods breakdown
        $paymentMethods = [
            'cash' => (clone $paymentQuery)->where('payment_method', 'cash')->sum('amount_paid'),
            'cod' => (clone $paymentQuery)->where('payment_method', 'cod')->sum('amount_paid'),
            'sign' => (clone $paymentQuery)->where('payment_method', 'sign')->sum('amount_paid'),
            'sales-only' => (clone $paymentQuery)->where('payment_method', 'sales-only')->sum('amount_paid'),
        ];

        return [
            'daily' => $dailyData,
            'monthly' => $monthlyData,
            'methods' => $paymentMethods,
            'total_sales' => array_sum(array_column($dailyData, 'total')),
            'average_daily' => array_sum(array_column($dailyData, 'total')) / 7,
        ];
    }

    public function updatedSelectedBranch()
    {
        $this->dispatch('salesDataUpdated');
        $this->dispatch('bestSellersUpdated');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <!-- Branch Filter (only show if user is admin/superadmin) -->
    @if (auth()->user()->hasRole(['superadmin']))
        <div class="p-4 bg-white shadow-lg rounded-xl dark:bg-gray-800">
            <label for="branchFilter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Filter by
                Branch</label>
            <select id="branchFilter" wire:model.live="selectedBranch"
                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-700 focus:border-blue-500 focus:outline-none focus:ring dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                <option value="">All Branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <!-- Dashboard Stats -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
        <!-- Daily Sales Card -->
        <div class="p-6 bg-white shadow-lg rounded-xl text-gray-900 dark:bg-gray-800 dark:text-white">
            <div class="flex items-center gap-4">
                <svg class="w-8 h-8 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                </svg>
                <div>
                    <h3 class="text-xl font-bold text-green-500">Daily Sales</h3>
                    <p class="text-4xl font-semibold text-green-500">Php {{ number_format($dailySales, 2) }}</p>
                </div>
            </div>
            <div class="mt-4 space-y-2 text-gray-700 dark:text-gray-300">
                <div class="flex justify-between">
                    <span>Cash:</span>
                    <span class="font-semibold">Php {{ number_format($totalCash, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Sales Only:</span>
                    <span class="font-semibold">Php {{ number_format($totalSalesOnly, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>COD:</span>
                    <span class="font-semibold">Php {{ number_format($totalCod, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Sign:</span>
                    <span class="font-semibold">Php {{ number_format($totalSign, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Return:</span>
                    <span class="font-semibold">Php -{{ number_format($totalReturn, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Refund:</span>
                    <span class="font-semibold">Php -{{ number_format($totalRefund, 2) }}</span>
                </div>
                <hr>
                <div class="flex justify-between">
                    <span>Total Transactions:</span>
                    <span class="font-semibold">{{ $totalTransactions }}</span>
                </div>
            </div>
        </div>

        <!-- Cashflow Section -->
        <div class="p-6 bg-white shadow-lg rounded-xl text-gray-900 dark:bg-gray-800 dark:text-white">
            <h3 class="text-xl font-bold text-blue-500 mt-6">Cash Flow</h3>
            <div class="mt-4 space-y-2 text-gray-700 dark:text-gray-300">
                @foreach ($branchSales as $bs)
                    <div class="flex justify-between">
                        <span>{{ $bs['name'] }}:</span>
                        <span class="font-semibold text-green-500">Php {{ number_format($bs['sales'], 2) }}</span>
                    </div>
                @endforeach
                <hr>
                <div class="flex justify-between">
                    <span>Money In:</span>
                    <span class="font-semibold text-green-500">Php {{ number_format($totalMoneyIn, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Money Out:</span>
                    <span class="font-semibold text-red-500">Php -{{ number_format($totalMoneyOut, 2) }}</span>
                </div>
                <div class="flex justify-between border-t pt-2">
                    <span class="font-bold">COH:</span>
                    <span class="font-bold text-blue-500">Php {{ number_format($cashOnHand, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="p-6 bg-white shadow-lg rounded-xl text-gray-900 dark:bg-gray-800 dark:text-white">
            <div class="flex items-center gap-4">
                <svg class="w-8 h-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
                <div>
                    <h3 class="text-xl font-bold text-blue-500">Total Customers</h3>
                    <p class="text-4xl font-semibold text-blue-500">{{ number_format($totalCustomers) }}</p>
                </div>
            </div>
        </div>
        <div class="p-6 bg-white shadow-lg rounded-xl text-gray-900 dark:bg-gray-800 dark:text-white">
            <div class="flex items-center gap-4">
                <svg class="w-8 h-8 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                </svg>
                <div>
                    <h3 class="text-xl font-bold text-purple-500">Total Products</h3>
                    <p class="text-4xl font-semibold text-purple-500">{{ number_format($totalProducts) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Best Sellers and Recent Activities -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2" wire:ignore>
        <!-- Best Sellers Pie Chart -->
        <div x-data="{
            bestSellersChart: null,
            init() {
                this.initBestSellersChart();

                $wire.on('bestSellersUpdated', () => {
                    this.initBestSellersChart();
                });
            },
            initBestSellersChart() {
                if (this.bestSellersChart) {
                    this.bestSellersChart.destroy();
                }

                const ctx = document.getElementById('bestSellersChart').getContext('2d');
                this.bestSellersChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: $wire.bestSellersLabels,
                        datasets: [{
                            data: $wire.bestSellersData,
                            backgroundColor: ['#ff6384', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                align: 'center',
                                labels: {
                                    boxWidth: 15,
                                    padding: 15,
                                    color: document.documentElement.classList.contains('dark') ? '#fff' : '#000'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Top Selling Products',
                                color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }
        }"
            class="relative h-96 overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-gray-800">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Best Sellers</h3>
            <div class="h-3/4">
                <canvas id="bestSellersChart"></canvas>
            </div>
        </div>


        <!-- Recently Added Products -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-gray-800">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Recently Added Products</h3>
            <div class="space-y-4">
                @php
                    $recentProductsQuery = \App\Models\Product::latest();
                    $recentProducts = $recentProductsQuery->take(5)->get();
                @endphp

                @foreach ($recentProducts as $product)
                    <div
                        class="flex justify-between items-center text-gray-700 dark:text-gray-300 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-6 w-6 text-blue-600 dark:text-blue-300" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold">{{ $product->name }}</p>
                                <p class="text-sm">Stock: {{ $product->stock }} | Price: Php
                                    {{ number_format($product->price, 2) }}/unit</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-1">
        <!-- Sales Graph -->
        <div x-data="{
            activeTab: 'daily',
            chart: null,
            init() {
                this.renderChart();

                $wire.on('salesDataUpdated', () => {
                    this.renderChart();
                });
            },
            renderChart() {
                if (this.chart) {
                    this.chart.destroy();
                }

                const ctx = document.getElementById('salesAnalyticsChart').getContext('2d');
                const isDark = document.documentElement.classList.contains('dark');
                const textColor = isDark ? '#fff' : '#000';
                const gridColor = isDark ? '#374151' : '#e5e7eb';

                if (this.activeTab === 'daily') {
                    const labels = $wire.salesChartData.daily.map(item => item.date);
                    this.chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                    label: 'Cash',
                                    data: $wire.salesChartData.daily.map(item => item.cash),
                                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'COD',
                                    data: $wire.salesChartData.daily.map(item => item.cod),
                                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Sign',
                                    data: $wire.salesChartData.daily.map(item => item.sign),
                                    backgroundColor: 'rgba(153, 102, 255, 0.7)',
                                    borderColor: 'rgba(153, 102, 255, 1)',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: this.getChartOptions('Daily Sales Performance', textColor, gridColor)
                    });
                } else if (this.activeTab === 'monthly') {
                    const labels = $wire.salesChartData.monthly.map(item => item.month);
                    this.chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Monthly Sales',
                                data: $wire.salesChartData.monthly.map(item => item.total),
                                fill: true,
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                tension: 0.3,
                                borderWidth: 2
                            }]
                        },
                        options: this.getChartOptions('Monthly Sales Trend', textColor, gridColor)
                    });
                } else {
                    this.chart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Cash', 'COD', 'Sign'],
                            datasets: [{
                                data: [
                                    $wire.salesChartData.methods.cash,
                                    $wire.salesChartData.methods.cod,
                                    $wire.salesChartData.methods.sign
                                ],
                                backgroundColor: [
                                    'rgba(75, 192, 192, 0.7)',
                                    'rgba(54, 162, 235, 0.7)',
                                    'rgba(153, 102, 255, 0.7)'
                                ],
                                borderColor: isDark ? '#374151' : '#fff',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        color: textColor
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Payment Methods Breakdown',
                                    color: textColor,
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    }
                                }
                            }
                        }
                    });
                }
            },
            getChartOptions(title, textColor, gridColor) {
                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: title,
                            color: textColor,
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        legend: {
                            labels: {
                                color: textColor
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ₱' + context.raw.toLocaleString('en-PH', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: gridColor
                            },
                            ticks: {
                                color: textColor
                            }
                        },
                        y: {
                            grid: {
                                color: gridColor
                            },
                            ticks: {
                                color: textColor,
                                callback: function(value) {
                                    return '₱' + value.toLocaleString('en-PH');
                                }
                            }
                        }
                    }
                };
            }
        }"
            class="relative h-[32rem] overflow-hidden rounded-xl border border-neutral-200 bg-white p-6 shadow-lg dark:border-neutral-700 dark:bg-gray-800">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Sales Analytics</h2>
                <div class="flex flex-wrap gap-2">
                    <button @click="activeTab = 'daily'; renderChart()"
                        :class="{ 'bg-blue-500 text-white': activeTab === 'daily', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300': activeTab !== 'daily' }"
                        class="px-4 py-2 rounded-lg text-sm">
                        Daily
                    </button>
                    <button @click="activeTab = 'monthly'; renderChart()"
                        :class="{ 'bg-blue-500 text-white': activeTab === 'monthly', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300': activeTab !== 'monthly' }"
                        class="px-4 py-2 rounded-lg text-sm">
                        Monthly
                    </button>
                    <button @click="activeTab = 'methods'; renderChart()"
                        :class="{ 'bg-blue-500 text-white': activeTab === 'methods', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300': activeTab !== 'methods' }"
                        class="px-4 py-2 rounded-lg text-sm">
                        Payment Methods
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                    <p class="text-sm text-blue-600 dark:text-blue-300">Total Sales</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-200">
                        ₱{{ number_format($salesChartData['total_sales'], 2) }}
                    </p>
                </div>
                <div class="p-4 bg-green-50 dark:bg-green-900/30 rounded-lg">
                    <p class="text-sm text-green-600 dark:text-green-300">Avg. Daily</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-200">
                        ₱{{ number_format($salesChartData['average_daily'], 2) }}
                    </p>
                </div>
                <div class="p-4 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                    <p class="text-sm text-purple-600 dark:text-purple-300">Cash Payments</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-200">
                        ₱{{ number_format($salesChartData['methods']['cash'], 2) }}
                    </p>
                </div>
                <div class="p-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                    <p class="text-sm text-indigo-600 dark:text-indigo-300">COD Payments</p>
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-200">
                        ₱{{ number_format($salesChartData['methods']['cod'], 2) }}
                    </p>
                </div>
                <div class="p-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                    <p class="text-sm text-indigo-600 dark:text-indigo-300">Sales Only</p>
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-200">
                        ₱{{ number_format($salesChartData['methods']['sales-only'], 2) }}
                    </p>
                </div>
            </div>

            <div class="h-80 w-full">
                <canvas id="salesAnalyticsChart"></canvas>
            </div>
        </div>
    </div>


</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
