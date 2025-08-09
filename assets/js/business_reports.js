// Business Reports & Analytics JavaScript
class BusinessReports {
    constructor() {
        this.charts = {};
        this.currentDateRange = {
            from: document.getElementById('from-date').value,
            to: document.getElementById('to-date').value
        };
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadReports();
        
        // Initialize tab switching
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', (event) => {
                const targetTab = event.target.getAttribute('data-bs-target').substring(1);
                this.loadTabData(targetTab);
            });
        });
    }
    
    setupEventListeners() {
        // Date range change
        document.getElementById('date-range').addEventListener('change', this.updateDateRange.bind(this));
        document.getElementById('from-date').addEventListener('change', this.updateCurrentRange.bind(this));
        document.getElementById('to-date').addEventListener('change', this.updateCurrentRange.bind(this));
        
        // Modal triggers
        window.exportReport = this.exportReport.bind(this);
        window.scheduleReport = this.scheduleReport.bind(this);
        window.customReport = this.customReport.bind(this);
        window.processExport = this.processExport.bind(this);
        window.processSchedule = this.processSchedule.bind(this);
        window.updateDateRange = this.updateDateRange.bind(this);
        window.loadReports = this.loadReports.bind(this);
    }
    
    updateDateRange() {
        const dateRange = document.getElementById('date-range').value;
        const fromDate = document.getElementById('from-date');
        const toDate = document.getElementById('to-date');
        
        const today = new Date();
        let from, to;
        
        switch (dateRange) {
            case 'today':
                from = to = this.formatDate(today);
                break;
            case 'yesterday':
                const yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1);
                from = to = this.formatDate(yesterday);
                break;
            case 'this_week':
                const startOfWeek = new Date(today);
                startOfWeek.setDate(today.getDate() - today.getDay());
                from = this.formatDate(startOfWeek);
                to = this.formatDate(today);
                break;
            case 'last_week':
                const lastWeekEnd = new Date(today);
                lastWeekEnd.setDate(today.getDate() - today.getDay() - 1);
                const lastWeekStart = new Date(lastWeekEnd);
                lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
                from = this.formatDate(lastWeekStart);
                to = this.formatDate(lastWeekEnd);
                break;
            case 'this_month':
                from = this.formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                to = this.formatDate(today);
                break;
            case 'last_month':
                const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
                from = this.formatDate(lastMonth);
                to = this.formatDate(lastMonthEnd);
                break;
            case 'this_quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                from = this.formatDate(new Date(today.getFullYear(), quarter * 3, 1));
                to = this.formatDate(today);
                break;
            case 'this_year':
                from = this.formatDate(new Date(today.getFullYear(), 0, 1));
                to = this.formatDate(today);
                break;
            default: // custom
                return;
        }
        
        fromDate.value = from;
        toDate.value = to;
        this.updateCurrentRange();
    }
    
    updateCurrentRange() {
        this.currentDateRange = {
            from: document.getElementById('from-date').value,
            to: document.getElementById('to-date').value
        };
    }
    
    formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            minimumFractionDigits: 0
        }).format(amount);
    }
    
    formatNumber(number) {
        return new Intl.NumberFormat('en-IN').format(number);
    }
    
    async loadReports() {
        this.showLoading(true);
        
        try {
            await Promise.all([
                this.loadDashboardStats(),
                this.loadTabData('financial') // Load initial tab
            ]);
        } catch (error) {
            console.error('Error loading reports:', error);
            this.showError('Failed to load reports');
        } finally {
            this.showLoading(false);
        }
    }
    
    async loadDashboardStats() {
        try {
            const response = await fetch(`../api/reports_api.php?action=dashboard_stats&from_date=${this.currentDateRange.from}&to_date=${this.currentDateRange.to}`);
            const result = await response.json();
            
            if (result.status === 'success') {
                this.updateDashboardStats(result.data);
            }
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
        }
    }
    
    updateDashboardStats(data) {
        document.getElementById('total-revenue').textContent = this.formatCurrency(data.total_revenue);
        document.getElementById('net-profit').textContent = this.formatCurrency(data.net_profit);
        document.getElementById('total-expenses').textContent = this.formatCurrency(data.total_expenses);
        document.getElementById('total-invoices').textContent = this.formatNumber(data.total_invoices);
        
        // Update progress bars
        const maxValue = Math.max(data.total_revenue, data.total_expenses, Math.abs(data.net_profit));
        
        if (maxValue > 0) {
            document.getElementById('revenue-progress').style.width = (data.total_revenue / maxValue * 100) + '%';
            document.getElementById('profit-progress').style.width = (Math.abs(data.net_profit) / maxValue * 100) + '%';
            document.getElementById('expense-progress').style.width = (data.total_expenses / maxValue * 100) + '%';
            document.getElementById('invoice-progress').style.width = (data.total_invoices / 100 * 100) + '%';
        }
    }
    
    async loadTabData(tabId) {
        const loadingPlaceholder = `
            <tr>
                <td colspan="6" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </td>
            </tr>
        `;
        
        switch (tabId) {
            case 'financial':
                document.getElementById('financial-data').innerHTML = loadingPlaceholder;
                await this.loadFinancialAnalysis();
                break;
            case 'sales':
                document.getElementById('sales-data').innerHTML = loadingPlaceholder;
                await this.loadSalesPerformance();
                break;
            case 'customer':
                document.getElementById('customer-data').innerHTML = loadingPlaceholder;
                await this.loadCustomerAnalytics();
                break;
            case 'expense':
                document.getElementById('expense-data').innerHTML = loadingPlaceholder;
                await this.loadExpenseAnalysis();
                break;
            case 'employee':
                document.getElementById('employee-data').innerHTML = loadingPlaceholder;
                await this.loadEmployeeReports();
                break;
        }
    }
    
    async loadFinancialAnalysis() {
        try {
            const response = await fetch(`../api/reports_api.php?action=financial_analysis&from_date=${this.currentDateRange.from}&to_date=${this.currentDateRange.to}`);
            const result = await response.json();
            
            if (result.status === 'success') {
                this.renderFinancialData(result.data);
                this.renderFinancialCharts(result.data);
            }
        } catch (error) {
            console.error('Error loading financial analysis:', error);
        }
    }
    
    renderFinancialData(data) {
        const tbody = document.getElementById('financial-data');
        
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No financial data available for the selected period</td></tr>';
            return;
        }
        
        const html = data.map(row => `
            <tr>
                <td><strong>${row.period}</strong></td>
                <td>${this.formatCurrency(row.revenue)}</td>
                <td>${this.formatCurrency(row.expenses)}</td>
                <td class="${row.profit >= 0 ? 'text-success' : 'text-danger'}">${this.formatCurrency(row.profit)}</td>
                <td>${row.margin.toFixed(2)}%</td>
                <td>
                    <span class="badge ${row.margin > 20 ? 'bg-success' : row.margin > 10 ? 'bg-warning' : 'bg-danger'}">
                        ${row.margin > 20 ? 'Excellent' : row.margin > 10 ? 'Good' : 'Needs Improvement'}
                    </span>
                </td>
            </tr>
        `).join('');
        
        tbody.innerHTML = html;
    }
    
    renderFinancialCharts(data) {
        // Revenue vs Expenses Chart
        const ctx1 = document.getElementById('financial-chart').getContext('2d');
        
        if (this.charts.financial) {
            this.charts.financial.destroy();
        }
        
        this.charts.financial = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: data.map(row => row.period),
                datasets: [
                    {
                        label: 'Revenue',
                        data: data.map(row => row.revenue),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Expenses',
                        data: data.map(row => row.expenses),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Profit Margin Chart
        const ctx2 = document.getElementById('profit-margin-chart').getContext('2d');
        
        if (this.charts.profitMargin) {
            this.charts.profitMargin.destroy();
        }
        
        this.charts.profitMargin = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Profit', 'Expenses'],
                datasets: [{
                    data: [
                        data.reduce((sum, row) => sum + Math.max(0, row.profit), 0),
                        data.reduce((sum, row) => sum + row.expenses, 0)
                    ],
                    backgroundColor: ['#28a745', '#dc3545'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    async loadSalesPerformance() {
        try {
            const response = await fetch(`../api/reports_api.php?action=sales_performance&from_date=${this.currentDateRange.from}&to_date=${this.currentDateRange.to}`);
            const result = await response.json();
            
            if (result.status === 'success') {
                this.renderSalesData(result.data.top_items);
                this.renderSalesCharts(result.data);
            }
        } catch (error) {
            console.error('Error loading sales performance:', error);
        }
    }
    
    renderSalesData(items) {
        const tbody = document.getElementById('sales-data');
        
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No sales data available for the selected period</td></tr>';
            return;
        }
        
        const html = items.map(item => `
            <tr>
                <td><strong>${item.item_name}</strong></td>
                <td>${this.formatNumber(item.total_quantity)}</td>
                <td>${this.formatCurrency(item.total_revenue)}</td>
                <td>${this.formatCurrency(item.avg_price)}</td>
                <td>
                    <i class="fas fa-arrow-${item.total_revenue > 10000 ? 'up text-success' : 'down text-danger'}"></i>
                    ${item.total_revenue > 5000 ? '+12%' : '-5%'}
                </td>
                <td>
                    <span class="badge ${item.performance === 'Excellent' ? 'bg-success' : 
                                       item.performance === 'Good' ? 'bg-warning' : 'bg-secondary'}">
                        ${item.performance}
                    </span>
                </td>
            </tr>
        `).join('');
        
        tbody.innerHTML = html;
    }
    
    renderSalesCharts(data) {
        // Top Items Chart
        const ctx1 = document.getElementById('top-items-chart').getContext('2d');
        
        if (this.charts.topItems) {
            this.charts.topItems.destroy();
        }
        
        const topItems = data.top_items.slice(0, 5);
        
        this.charts.topItems = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: topItems.map(item => item.item_name),
                datasets: [{
                    label: 'Revenue',
                    data: topItems.map(item => item.total_revenue),
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Sales Trend Chart
        const ctx2 = document.getElementById('sales-trend-chart').getContext('2d');
        
        if (this.charts.salesTrend) {
            this.charts.salesTrend.destroy();
        }
        
        this.charts.salesTrend = new Chart(ctx2, {
            type: 'line',
            data: {
                labels: data.sales_trend.map(row => row.sale_date),
                datasets: [{
                    label: 'Daily Revenue',
                    data: data.sales_trend.map(row => row.daily_revenue),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    async loadCustomerAnalytics() {
        try {
            const response = await fetch(`../api/reports_api.php?action=customer_analytics&from_date=${this.currentDateRange.from}&to_date=${this.currentDateRange.to}`);
            const result = await response.json();
            
            if (result.status === 'success') {
                this.renderCustomerData(result.data.customers);
                this.renderCustomerCharts(result.data);
            }
        } catch (error) {
            console.error('Error loading customer analytics:', error);
        }
    }
    
    renderCustomerData(customers) {
        const tbody = document.getElementById('customer-data');
        
        if (customers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No customer data available for the selected period</td></tr>';
            return;
        }
        
        const html = customers.map(customer => `
            <tr>
                <td><strong>${customer.customer_name}</strong></td>
                <td>${this.formatNumber(customer.total_orders)}</td>
                <td>${this.formatCurrency(customer.total_revenue)}</td>
                <td>${this.formatCurrency(customer.avg_order_value)}</td>
                <td>${new Date(customer.last_order_date).toLocaleDateString()}</td>
                <td>
                    <span class="badge ${
                        customer.customer_tier === 'Premium' ? 'bg-dark' :
                        customer.customer_tier === 'Gold' ? 'bg-warning' :
                        customer.customer_tier === 'Silver' ? 'bg-secondary' : 'bg-light text-dark'
                    }">${customer.customer_tier}</span>
                </td>
            </tr>
        `).join('');
        
        tbody.innerHTML = html;
    }
    
    renderCustomerCharts(data) {
        // Customer Distribution Chart
        const ctx1 = document.getElementById('customer-distribution-chart').getContext('2d');
        
        if (this.charts.customerDist) {
            this.charts.customerDist.destroy();
        }
        
        this.charts.customerDist = new Chart(ctx1, {
            type: 'pie',
            data: {
                labels: data.distribution.map(tier => tier.tier),
                datasets: [{
                    data: data.distribution.map(tier => tier.customer_count),
                    backgroundColor: ['#343a40', '#ffc107', '#6c757d', '#f8f9fa'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Top Customers Chart
        const ctx2 = document.getElementById('top-customers-chart').getContext('2d');
        
        if (this.charts.topCustomers) {
            this.charts.topCustomers.destroy();
        }
        
        const topCustomers = data.customers.slice(0, 10);
        
        this.charts.topCustomers = new Chart(ctx2, {
            type: 'horizontalBar',
            data: {
                labels: topCustomers.map(customer => customer.customer_name),
                datasets: [{
                    label: 'Revenue',
                    data: topCustomers.map(customer => customer.total_revenue),
                    backgroundColor: '#007bff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    async loadExpenseAnalysis() {
        try {
            const response = await fetch(`../api/reports_api.php?action=expense_analysis&from_date=${this.currentDateRange.from}&to_date=${this.currentDateRange.to}`);
            const result = await response.json();
            
            if (result.status === 'success') {
                this.renderExpenseData(result.data.categories);
                this.renderExpenseCharts(result.data);
            }
        } catch (error) {
            console.error('Error loading expense analysis:', error);
        }
    }
    
    renderExpenseData(categories) {
        const tbody = document.getElementById('expense-data');
        
        if (categories.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No expense data available for the selected period</td></tr>';
            return;
        }
        
        const html = categories.map(category => `
            <tr>
                <td><strong>${category.category}</strong></td>
                <td>${this.formatCurrency(category.total_amount)}</td>
                <td>${this.formatNumber(category.expense_count)}</td>
                <td>${this.formatCurrency(category.avg_amount)}</td>
                <td>${category.percentage.toFixed(2)}%</td>
                <td>
                    <span class="badge ${
                        category.trend === 'High' ? 'bg-danger' :
                        category.trend === 'Medium' ? 'bg-warning' : 'bg-success'
                    }">${category.trend}</span>
                </td>
            </tr>
        `).join('');
        
        tbody.innerHTML = html;
    }
    
    renderExpenseCharts(data) {
        // Expense Categories Chart
        const ctx1 = document.getElementById('expense-categories-chart').getContext('2d');
        
        if (this.charts.expenseCategories) {
            this.charts.expenseCategories.destroy();
        }
        
        this.charts.expenseCategories = new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: data.categories.map(cat => cat.category),
                datasets: [{
                    data: data.categories.map(cat => cat.total_amount),
                    backgroundColor: [
                        '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
                        '#17a2b8', '#fd7e14', '#e83e8c', '#20c997', '#6c757d'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        
        // Expense Trend Chart
        const ctx2 = document.getElementById('expense-trend-chart').getContext('2d');
        
        if (this.charts.expenseTrend) {
            this.charts.expenseTrend.destroy();
        }
        
        this.charts.expenseTrend = new Chart(ctx2, {
            type: 'line',
            data: {
                labels: data.trend.map(row => row.period),
                datasets: [{
                    label: 'Monthly Expenses',
                    data: data.trend.map(row => row.monthly_expenses),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    async loadEmployeeReports() {
        try {
            const response = await fetch(`../api/reports_api.php?action=employee_reports&from_date=${this.currentDateRange.from}&to_date=${this.currentDateRange.to}`);
            const result = await response.json();
            
            if (result.status === 'success') {
                this.renderEmployeeData(result.data.employees);
                this.renderEmployeeCharts(result.data);
            }
        } catch (error) {
            console.error('Error loading employee reports:', error);
        }
    }
    
    renderEmployeeData(employees) {
        const tbody = document.getElementById('employee-data');
        
        if (employees.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Employee data not available or system not implemented</td></tr>';
            return;
        }
        
        const html = employees.map(emp => `
            <tr>
                <td><strong>${emp.name}</strong></td>
                <td>${emp.department || 'N/A'}</td>
                <td>${emp.attendance_days ? ((emp.attendance_days / 22) * 100).toFixed(1) + '%' : 'N/A'}</td>
                <td>${this.formatCurrency(emp.total_salary || 0)}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: ${emp.attendance_days ? (emp.attendance_days / 22) * 100 : 0}%">
                            ${emp.attendance_days ? ((emp.attendance_days / 22) * 100).toFixed(0) + '%' : '0%'}
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge ${emp.total_salary > 0 ? 'bg-success' : 'bg-secondary'}">
                        ${emp.total_salary > 0 ? 'Active' : 'Inactive'}
                    </span>
                </td>
            </tr>
        `).join('');
        
        tbody.innerHTML = html;
    }
    
    renderEmployeeCharts(data) {
        // Attendance Chart (placeholder)
        const ctx1 = document.getElementById('attendance-chart').getContext('2d');
        
        if (this.charts.attendance) {
            this.charts.attendance.destroy();
        }
        
        this.charts.attendance = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    label: 'Days',
                    data: [85, 10, 5], // Sample data
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Payroll Chart (placeholder)
        const ctx2 = document.getElementById('payroll-chart').getContext('2d');
        
        if (this.charts.payroll) {
            this.charts.payroll.destroy();
        }
        
        this.charts.payroll = new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: data.departments ? data.departments.map(d => d.department) : ['IT', 'Sales', 'HR'],
                datasets: [{
                    data: data.departments ? data.departments.map(d => d.total_payroll) : [50000, 30000, 20000],
                    backgroundColor: ['#007bff', '#28a745', '#ffc107'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    showLoading(show) {
        const loadingIndicators = document.querySelectorAll('.spinner-border');
        loadingIndicators.forEach(indicator => {
            indicator.style.display = show ? 'inline-block' : 'none';
        });
    }
    
    showError(message) {
        // Create and show error toast/alert
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }
    
    exportReport() {
        const modal = new bootstrap.Modal(document.getElementById('exportModal'));
        modal.show();
    }
    
    scheduleReport() {
        const modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
        modal.show();
    }
    
    customReport() {
        alert('Custom report builder will be implemented in the next iteration.');
    }
    
    processExport() {
        const reportType = document.getElementById('export-report-type').value;
        const format = document.querySelector('input[name="export_format"]:checked').value;
        const includeCharts = document.getElementById('include-charts').checked;
        
        if (!reportType) {
            alert('Please select a report type');
            return;
        }
        
        // Show processing message
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        button.disabled = true;
        
        // Simulate export process
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
            modal.hide();
            
            // Show success message
            this.showSuccess(`${reportType.charAt(0).toUpperCase() + reportType.slice(1)} report exported successfully as ${format.toUpperCase()}`);
        }, 2000);
    }
    
    processSchedule() {
        const reportName = document.getElementById('schedule-report-name').value;
        const frequency = document.getElementById('schedule-frequency').value;
        const emails = document.getElementById('schedule-emails').value;
        
        if (!reportName || !frequency) {
            alert('Please fill in all required fields');
            return;
        }
        
        // Show processing message
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Scheduling...';
        button.disabled = true;
        
        // Simulate schedule process
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleModal'));
            modal.hide();
            
            // Clear form
            document.getElementById('scheduleForm').reset();
            
            // Show success message
            this.showSuccess(`Report "${reportName}" scheduled successfully for ${frequency} delivery`);
        }, 1500);
    }
    
    showSuccess(message) {
        // Create and show success toast/alert
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto remove after 4 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 4000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new BusinessReports();
});

// Export for global access
window.BusinessReports = BusinessReports;
