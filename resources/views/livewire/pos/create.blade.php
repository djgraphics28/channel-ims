<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\CashFlow;
use Carbon\Carbon;

new class extends Component {
    public $salesData = [];
    public $bestSellersData = [];
    public $bestSellersLabels = [];
    public $bestSellersRevenue = [];

    #[Title('Dashboard')]
    public function with()
    {
        // Get today's date
        $today = today();

        // Get sales data for the current week
        $startOfWeek = $today->copy()->startOfWeek();
        $endOfWeek = $today->copy()->endOfWeek();

        $weeklySales = [];
        $weeklyLabels = [];

        for ($date = $startOfWeek; $date <= $endOfWeek; $date->addDay()) {
            $daySales = Payment::whereDate('created_at', $date)
                ->where('payment_status', 'paid')
                ->sum('amount_paid');

            $weeklySales[] = $daySales;
            $weeklyLabels[] = $date->format('l, F j');
        }

        $this->salesData = $weeklySales;

        // Get best sellers data (top 5 products with revenue)
        $bestSellers = Product::withCount([
            'orderItems as total_sold' => function ($query) {
                $query->whereHas('order', function ($q) {
                    $q->where('status', 'completed');
                });
            }
        ])
        ->withSum([
            'orderItems as total_revenue' => function ($query) {
                $query->select(DB::raw('SUM(quantity * price)'))
                    ->whereHas('order', function ($q) {
                        $q->where('status', 'completed');
                    });
            }
        ])
        ->orderByDesc('total_sold')
        ->take(5)
        ->get();

        $this->bestSellersData = $bestSellers->pluck('total_sold')->toArray();
        $this->bestSellersLabels = $bestSellers->pluck('name')->toArray();
        $this->bestSellersRevenue = $bestSellers->pluck('total_revenue')->toArray();

        $dailySales = Payment::whereDate('created_at', today())
            ->where('payment_status', 'paid')
            ->sum('amount_paid');

        $totalCash = Payment::where('payment_method', 'cash')
            ->where('payment_status', 'paid')
            ->whereDate('created_at', today())
            ->sum('amount_paid');

        $totalCod = Payment::where('payment_method', 'cod')
            ->where('payment_status', 'paid')
            ->whereDate('created_at', today())
            ->sum('amount_paid');

        $totalSign = Payment::where('payment_method', 'sign')
            ->where('payment_status', 'paid')
            ->whereDate('created_at', today())
            ->sum('amount_paid');

        // Return and refund
        $totalReturn = Payment::where('payment_method', 'return')
            ->where('payment_status', 'paid')
            ->whereDate('created_at', today())
            ->sum('amount_paid');

        $totalRefund = Payment::where('payment_method', 'refund')
            ->where('payment_status', 'paid')
            ->whereDate('created_at', today())
            ->sum('amount_paid');

        // Daily sales minus the return and refund
        $dailySales = $dailySales - ($totalReturn + $totalRefund);

        // Cashflows
        $totalMoneyIn = CashFlow::whereDate('created_at', today())
            ->where('type', 'in')
            ->sum('amount');

        $totalMoneyOut = CashFlow::whereDate('created_at', today())
            ->where('type', 'out')
            ->sum('amount');

        $cashOnHand = $totalMoneyIn - $totalMoneyOut;

        return [
            'totalProducts' => Product::count(),
            'totalCustomers' => Customer::count(),
            'dailySales' => $dailySales,
            'totalCash' => $totalCash,
            'totalCod' => $totalCod,
            'totalSign' => $totalSign,
            'totalReturn' => $totalReturn,
            'totalRefund' => $totalRefund,
            'totalMoneyIn' => $totalMoneyIn,
            'totalMoneyOut' => $totalMoneyOut,
            'cashOnHand' => $cashOnHand,
        ];
    }

    public function getMonthlySalesData()
    {
        $monthlySales = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlySales[] = Payment::whereMonth('created_at', $i)
                ->whereYear('created_at', now()->year)
                ->where('payment_status', 'paid')
                ->sum('amount_paid');
        }
        return $monthlySales;
    }

    public function getYearlySalesData()
    {
        $yearlySales = [];
        for ($i = 4; $i >= 0; $i--) {
            $year = now()->year - $i;
            $yearlySales[] = Payment::whereYear('created_at', $year)
                ->where('payment_status', 'paid')
                ->sum('amount_paid');
        }
        return $yearlySales;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <!-- Dashboard Stats -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
        <!-- Daily Sales Card -->
        <div class="p-6 bg-white shadow-lg rounded-xl text-gray-900 dark:bg-gray-800 dark:text-white">
            <div class="flex items-center gap-4">
                <svg class="w-8 h-8 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
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
                    <span>COD:</span>
                    <span class="font-semibold">Php {{ number_format($totalCod, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Sign:</span>
                    <span class="font-semibold">Php {{ number_format($totalSign, 2) }}</span>
                </div>
                <div class="flex justify-between text-red-500">
                    <span>Return:</span>
                    <span class="font-semibold">Php -{{ number_format($totalReturn, 2) }}</span>
                </div>
                <div class="flex justify-between text-red-500">
                    <span>Refund:</span>
                    <span class="font-semibold">Php -{{ number_format($totalRefund, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Cashflow Section -->
        <div class="p-6 bg-white shadow-lg rounded-xl text-gray-900 dark:bg-gray-800 dark:text-white">
            <h3 class="text-xl font-bold text-blue-500">Cash Flow</h3>
            <div class="mt-4 space-y-2 text-gray-700 dark:text-gray-300">
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

    <div class="grid grid-cols-1 gap-6 md:grid-cols-1">
        <!-- Sales Graph -->
        <div x-data="{
            period: 'week',
            salesChart: null,
            weekLabels: $wire.salesData.map((_, index) => {
                const date = new Date();
                date.setDate(date.getDate() - (6 - index));
                return date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            }),
            monthLabels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            yearLabels: Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 4 + i),
            weekData: $wire.salesData,
            monthData: [],
            yearData: [],
            init() {
                this.fetchMonthData();
                this.fetchYearData();
                this.initSalesChart();

                // Listen for Livewire updates
                $wire.on('salesDataUpdated', () => {
                    this.weekData = $wire.salesData;
                    this.initSalesChart();
                });
            },
            async fetchMonthData() {
                const response = await $wire.getMonthlySalesData();
                this.monthData = response;
            },
            async fetchYearData() {
                const response = await $wire.getYearlySalesData();
                this.yearData = response;
            },
            initSalesChart() {
                if (this.salesChart) {
                    this.salesChart.destroy();
                }

                const isDarkMode = document.documentElement.classList.contains('dark');
                const textColor = isDarkMode ? '#fff' : '#000';
                const gridColor = isDarkMode ? '#374151' : '#e5e7eb';
                const bgColor = isDarkMode ? 'rgba(75, 192, 192, 0.4)' : 'rgba(75, 192, 192, 0.2)';

                const ctx = document.getElementById('salesChart').getContext('2d');
                this.salesChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: this.period === 'week' ? this.weekLabels :
                               this.period === 'month' ? this.monthLabels :
                               this.yearLabels,
                        datasets: [{
                            label: 'Sales (Php)',
                            data: this.period === 'week' ? this.weekData :
                                  this.period === 'month' ? this.monthData :
                                  this.yearData,
                            backgroundColor: bgColor,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2,
                            borderRadius: 4,
                            borderSkipped: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Php ' + context.raw.toLocaleString('en-PH', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: 'Sales Performance Overview',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 20
                                },
                                color: textColor
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    color: textColor
                                },
                                grid: {
                                    color: gridColor,
                                    display: false
                                }
                            },
                            y: {
                                ticks: {
                                    color: textColor,
                                    callback: function(value) {
                                        return 'Php ' + value.toLocaleString('en-PH');
                                    }
                                },
                                grid: {
                                    color: gridColor
                                }
                            }
                        }
                    }
                });
            }
        }"
            class="relative h-96 overflow-hidden rounded-xl border border-neutral-200 bg-white p-6 shadow-lg dark:border-neutral-700 dark:bg-gray-800">
            <div class="mb-4 flex gap-2">
                <button @click="period = 'week'; initSalesChart()"
                    :class="{ 'bg-blue-500 text-white': period === 'week', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300': period !== 'week' }"
                    class="px-4 py-2 rounded-lg transition-colors">Week</button>
                <button @click="period = 'month'; fetchMonthData(); initSalesChart()"
                    :class="{ 'bg-blue-500 text-white': period === 'month', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300': period !== 'month' }"
                    class="px-4 py-2 rounded-lg transition-colors">Month</button>
                <button @click="period = 'year'; fetchYearData(); initSalesChart()"
                    :class="{ 'bg-blue-500 text-white': period === 'year', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300': period !== 'year' }"
                    class="px-4 py-2 rounded-lg transition-colors">Year</button>
            </div>
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Best Sellers and Recent Activities -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <!-- Best Sellers Chart -->
        <div x-data="{
            chartType: 'pie',
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
                const isDarkMode = document.documentElement.classList.contains('dark');
                const textColor = isDarkMode ? '#fff' : '#000';

                const backgroundColors = [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ];

                this.bestSellersChart = new Chart(ctx, {
                    type: this.chartType,
                    data: {
                        labels: $wire.bestSellersLabels.map((label, index) =>
                            `${label} (${$wire.bestSellersData[index]} sold)`),
                        datasets: [{
                            data: $wire.bestSellersRevenue,
                            backgroundColor: backgroundColors,
                            borderColor: isDarkMode ? 'rgba(0, 0, 0, 0.5)' : 'rgba(255, 255, 255, 0.8)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = $wire.bestSellersLabels[context.dataIndex] || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return [
                                            label,
                                            `Revenue: Php ${value.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`,
                                            `Market Share: ${percentage}%`
                                        ];
                                    }
                                }
                            },
                            legend: {
                                position: this.chartType === 'pie' ? 'right' : 'top',
                                align: 'center',
                                labels: {
                                    boxWidth: 15,
                                    padding: 15,
                                    color: textColor,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: 'Top Selling Products by Revenue',
                                color: textColor,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 20
                                }
                            }
                        },
                        scales: this.chartType !== 'pie' ? {
                            x: {
                                ticks: {
                                    color: textColor
                                },
                                grid: {
                                    color: isDarkMode ? 'rgba(55, 65, 81, 0.5)' : 'rgba(229, 231, 235, 0.5)'
                                }
                            },
                            y: {
                                ticks: {
                                    color: textColor,
                                    callback: function(value) {
                                        return 'Php ' + value.toLocaleString('en-PH');
                                    }
                                },
                                grid: {
                                    color: isDarkMode ? 'rgba(55, 65, 81, 0.5)' : 'rgba(229, 231, 235, 0.5)'
                                }
                            }
                        } : undefined
                    }
                });
            }
        }"
            class="relative h-96 overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-gray-800">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Best Sellers</h3>
                <div class="flex gap-2">
                    <button @click="chartType = 'pie'; initBestSellersChart()"
                        :class="{ 'bg-blue-500 text-white': chartType === 'pie', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300': chartType !== 'pie' }"
                        class="px-3 py-1 rounded-lg text-sm transition-colors">Pie</button>
                    <button @click="chartType = 'bar'; initBestSellersChart()"
                        :class="{ 'bg-blue-500 text-white': chartType === 'bar', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300': chartType !== 'bar' }"
                        class="px-3 py-1 rounded-lg text-sm transition-colors">Bar</button>
                    <button @click="chartType = 'doughnut'; initBestSellersChart()"
                        :class="{ 'bg-blue-500 text-white': chartType === 'doughnut', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300': chartType !== 'doughnut' }"
                        class="px-3 py-1 rounded-lg text-sm transition-colors">Doughnut</button>
                </div>
            </div>
            <div class="h-80">
                <canvas id="bestSellersChart"></canvas>
            </div>
        </div>

        <!-- Recently Added Products -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-gray-800">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Recently Added Products</h3>
            <div class="space-y-4">
                @foreach (\App\Models\Product::latest()->take(5)->get() as $product)
                    <div
                        class="flex justify-between items-center text-gray-700 dark:text-gray-300 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 dark:text-blue-300"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                        <span class="text-xs text-gray-500">{{ $product->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
