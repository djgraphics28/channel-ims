<?php

use Livewire\Volt\Component;

new class extends Component {
    public $salesData = [50, 60, 70, 85, 90, 100, 120];
    public $bestSellersData = [30, 45, 25];
    public $bestSellersLabels = ['Product A', 'Product B', 'Product C'];
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
                    <p class="text-4xl font-semibold text-green-500">Php 50,000</p>
                </div>
            </div>
            <div class="mt-4 space-y-2 text-gray-700 dark:text-gray-300">
                <div class="flex justify-between">
                    <span>Cash:</span>
                    <span class="font-semibold">Php 25,000</span>
                </div>
                <div class="flex justify-between">
                    <span>COD:</span>
                    <span class="font-semibold">Php 10,000</span>
                </div>
                <div class="flex justify-between">
                    <span>Sign:</span>
                    <span class="font-semibold">Php 5,000</span>
                </div>
                <div class="flex justify-between">
                    <span>Return:</span>
                    <span class="font-semibold">Php -3,000</span>
                </div>
                <div class="flex justify-between">
                    <span>Refund:</span>
                    <span class="font-semibold">Php -2,000</span>
                </div>
            </div>
        </div>

        <!-- Cashflow Section -->
        <div class="p-6 bg-white shadow-lg rounded-xl text-gray-900 dark:bg-gray-800 dark:text-white">
            <h3 class="text-xl font-bold text-blue-500">Branches Cash</h3>
            <div class="mt-4 space-y-2 text-gray-700 dark:text-gray-300">
                <div class="flex justify-between">
                    <span>H1 Branch:</span>
                    <span class="font-semibold">Php 30,000</span>
                </div>
                <div class="flex justify-between">
                    <span>H2 Branch:</span>
                    <span class="font-semibold">Php 20,000</span>
                </div>
            </div>
            <h3 class="text-xl font-bold text-blue-500 mt-6">Cash Flow</h3>
            <div class="mt-4 space-y-2 text-gray-700 dark:text-gray-300">
                <div class="flex justify-between">
                    <span>Money In:</span>
                    <span class="font-semibold text-green-500">Php 15,000</span>
                </div>
                <div class="flex justify-between">
                    <span>Money Out:</span>
                    <span class="font-semibold text-red-500">Php -8,000</span>
                </div>
                <div class="flex justify-between border-t pt-2">
                    <span class="font-bold">COH:</span>
                    <span class="font-bold text-blue-500">Php 37,000</span>
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
                    <p class="text-4xl font-semibold text-blue-500">5,200</p>
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
                    <p class="text-4xl font-semibold text-purple-500">1,000</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Graph -->
    <div x-data x-init="() => {
        let salesChart;

        function initSalesChart() {
            if (salesChart) {
                salesChart.destroy();
            }

            const ctx = document.getElementById('salesChart').getContext('2d');
            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Sales',
                        data: $wire.salesData,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        initSalesChart();

        $wire.on('refreshCharts', () => {
            initSalesChart();
        });
    }"
        class="relative h-96 overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-gray-800">
        <canvas id="salesChart"></canvas>
    </div>

    <!-- Best Sellers and Recent Activities -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <!-- Best Sellers Pie Chart -->
        <div x-data x-init="() => {
            let bestSellersChart;

            function initBestSellersChart() {
                if (bestSellersChart) {
                    bestSellersChart.destroy();
                }

                const ctx = document.getElementById('bestSellersChart').getContext('2d');
                bestSellersChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: $wire.bestSellersLabels,
                        datasets: [{
                            data: $wire.bestSellersData,
                            backgroundColor: ['#ff6384', '#36a2eb', '#ffce56']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            initBestSellersChart();

            $wire.on('refreshCharts', () => {
                initBestSellersChart();
            });
        }"
            class="relative h-96 overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-gray-800">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Best Sellers</h3>
            <canvas id="bestSellersChart"></canvas>
        </div>


        <!-- Recent Activities -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-gray-800">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Recent Activities</h3>
            <div class="space-y-4">
                <div class="flex items-center gap-4 text-gray-700 dark:text-gray-300">
                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                    <div>
                        <p class="font-semibold">New order #1234</p>
                        <p class="text-sm">2 minutes ago</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-gray-700 dark:text-gray-300">
                    <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                    <div>
                        <p class="font-semibold">Payment received #5678</p>
                        <p class="text-sm">15 minutes ago</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-gray-700 dark:text-gray-300">
                    <div class="h-2 w-2 rounded-full bg-red-500"></div>
                    <div>
                        <p class="font-semibold">Refund processed #9012</p>
                        <p class="text-sm">1 hour ago</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-gray-700 dark:text-gray-300">
                    <div class="h-2 w-2 rounded-full bg-yellow-500"></div>
                    <div>
                        <p class="font-semibold">New product added</p>
                        <p class="text-sm">2 hours ago</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        initializeCharts(); // Run initially on full page load
    });

    Livewire.hook("message.processed", (message, component) => {
        if (component.fingerprint.name === "dashboard") {
            initializeCharts(); // Re-run when navigating back
        }
    });

    function initializeCharts() {
        // Destroy existing charts to prevent duplicates
        if (window.salesChart) {
            window.salesChart.destroy();
        }
        if (window.bestSellersChart) {
            window.bestSellersChart.destroy();
        }

        // Initialize Sales Chart
        const salesCtx = document.getElementById("salesChart").getContext("2d");
        window.salesChart = new Chart(salesCtx, {
            type: "line",
            data: {
                labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
                datasets: [{
                    label: "Sales",
                    data: [50, 60, 70, 85, 90, 100, 120],
                    backgroundColor: "rgba(75, 192, 192, 0.2)",
                    borderColor: "rgba(75, 192, 192, 1)",
                    borderWidth: 2
                }]
            }
        });

        // Initialize Best Sellers Pie Chart
        const bestSellersCtx = document.getElementById("bestSellersChart").getContext("2d");
        window.bestSellersChart = new Chart(bestSellersCtx, {
            type: "pie",
            data: {
                labels: ["Product A", "Product B", "Product C"],
                datasets: [{
                    data: [30, 45, 25],
                    backgroundColor: ["#ff6384", "#36a2eb", "#ffce56"]
                }]
            }
        });
    }
</script>
