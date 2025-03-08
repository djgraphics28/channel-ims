<x-layouts.app title="Dashboard">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
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
                    <svg class="w-8 h-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
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
        <div class="relative h-96 overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-gray-800">
            <canvas id="salesChart"></canvas>
        </div>

        <!-- Best Sellers and Recent Activities -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Best Sellers Pie Chart -->
            <div class="relative h-96 overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-gray-800">
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
            // Sales Line Chart
            var ctx = document.getElementById("salesChart").getContext("2d");
            new Chart(ctx, {
                type: "line",
                data: {
                    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
                    datasets: [{
                        label: "Sales",
                        data: [12000, 15000, 18000, 20000, 25000, 30000],
                        borderColor: "#4F46E5",
                        backgroundColor: "rgba(79, 70, 229, 0.1)",
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointBackgroundColor: "#4F46E5",
                        pointBorderColor: "#fff",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: document.documentElement.classList.contains('dark') ? '#fff' : '#374151'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: document.documentElement.classList.contains('dark') ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                color: document.documentElement.classList.contains('dark') ? '#fff' : '#374151',
                                callback: function(value) {
                                    return 'Php ' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: document.documentElement.classList.contains('dark') ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                color: document.documentElement.classList.contains('dark') ? '#fff' : '#374151'
                            }
                        }
                    }
                }
            });

            // Best Sellers Pie Chart
            var bestSellersCtx = document.getElementById("bestSellersChart").getContext("2d");
            new Chart(bestSellersCtx, {
                type: "pie",
                data: {
                    labels: ["Product A", "Product B", "Product C", "Product D", "Product E"],
                    datasets: [{
                        data: [300, 250, 200, 150, 100],
                        backgroundColor: [
                            "#4F46E5",
                            "#10B981",
                            "#F59E0B",
                            "#EF4444",
                            "#8B5CF6"
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: document.documentElement.classList.contains('dark') ? '#fff' : '#374151',
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</x-layouts.app>
