<div class="d-flex align-items-center mb-4">
                        <h1 class="page-title mb-0">Dashboard Overview</h1>
                        <div class="welcome-message ms-auto">
                            <i class="fas fa-hand-wave"></i>
                            <span>Welcome, <?= $_SESSION['admin'] ?></span>
                        </div>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="stats-cards">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-title">Total Invoices</div>
                                <div class="stat-value">₹ <?= number_format($totalInvoices, 2) ?></div>
                                <div class="stat-change change-up">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>12.5% from last month</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon icon-pink">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-title">Today's Expenses</div>
                                <div class="stat-value">₹ <?= number_format($todayExpenses, 2) ?></div>
                                <div class="stat-change change-down">
                                    <i class="fas fa-arrow-down"></i>
                                    <span>3.1% from yesterday</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon icon-purple">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-title">Employees</div>
                                <div class="stat-value"><?= $totalEmployees ?></div>
                                <div class="stat-change change-up">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>2 new hires this month</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon icon-green">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-title">Items in Stock</div>
                                <div class="stat-value"><?= $totalItems ?></div>
                                <div class="stat-change change-down">
                                    <i class="fas fa-arrow-down"></i>
                                    <span>15 items sold today</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Row -->
                    <div class="charts-row">
                        <div class="chart-container">
                            <div class="chart-header">
                                <div class="chart-title">Revenue Overview</div>
                                <div class="chart-actions">
                                    <button class="chart-btn"><i class="fas fa-download"></i></button>
                                    <button class="chart-btn"><i class="fas fa-ellipsis-h"></i></button>
                                </div>
                            </div>
                            <canvas class="chart-canvas" id="revenueChart"></canvas>
                        </div>
                        
                        <div class="chart-container">
                            <div class="chart-header">
                                <div class="chart-title">Expense Distribution</div>
                                <div class="chart-actions">
                                    <button class="chart-btn"><i class="fas fa-download"></i></button>
                                    <button class="chart-btn"><i class="fas fa-ellipsis-h"></i></button>
                                </div>
                            </div>
                            <canvas class="chart-canvas" id="expenseChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Recent Orders -->
                    <div class="data-table">
                        <div class="table-header">
                            <div class="table-title">Recent Transactions</div>
                            <button class="btn btn-primary">View All</button>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>#TX-7841</td>
                                    <td>Office Supplies Purchase</td>
                                    <td><?= date('M d, Y') ?></td>
                                    <td>₹ 12,500</td>
                                    <td><span class="status status-completed">Completed</span></td>
                                    <td><button class="btn btn-outline">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TX-7840</td>
                                    <td>Software Subscription</td>
                                    <td><?= date('M d, Y', strtotime('-1 day')) ?></td>
                                    <td>₹ 8,900</td>
                                    <td><span class="status status-completed">Completed</span></td>
                                    <td><button class="btn btn-outline">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TX-7839</td>
                                    <td>Client Invoice Payment</td>
                                    <td><?= date('M d, Y', strtotime('-2 days')) ?></td>
                                    <td>₹ 24,800</td>
                                    <td><span class="status status-completed">Completed</span></td>
                                    <td><button class="btn btn-outline">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TX-7838</td>
                                    <td>Employee Salary Payment</td>
                                    <td><?= date('M d, Y', strtotime('-3 days')) ?></td>
                                    <td>₹ 87,500</td>
                                    <td><span class="status status-pending">Processing</span></td>
                                    <td><button class="btn btn-outline">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TX-7837</td>
                                    <td>Utility Bill Payment</td>
                                    <td><?= date('M d, Y', strtotime('-4 days')) ?></td>
                                    <td>₹ 15,200</td>
                                    <td><span class="status status-active">Pending</span></td>
                                    <td><button class="btn btn-outline">View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    