// Advanced Attendance System JavaScript
class AdvancedAttendanceSystem {
    constructor() {
        this.apiBase = './api/advanced_attendance_api.php';
        this.currentUser = null;
        this.initializeSystem();
    }

    async initializeSystem() {
        await this.loadUserData();
        this.setupEventListeners();
        this.initializeFeatures();
        console.log('Advanced Attendance System initialized');
    }

    // Smart Attendance Methods
    async initializeFaceRecognition() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { width: 640, height: 480 } 
            });
            
            const video = document.getElementById('faceRecognitionVideo');
            const canvas = document.getElementById('faceCanvas');
            
            if (video && canvas) {
                video.srcObject = stream;
                video.play();
                
                // Setup face detection
                this.setupFaceDetection(video, canvas);
            }
        } catch (error) {
            console.error('Camera access denied:', error);
            this.showAlert('Camera access is required for face recognition', 'error');
        }
    }

    setupFaceDetection(video, canvas) {
        const ctx = canvas.getContext('2d');
        
        const detectFace = () => {
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Simulate face detection (in real implementation, use face-api.js or similar)
            const faceDetected = Math.random() > 0.3;
            
            if (faceDetected) {
                // Draw face detection box
                ctx.strokeStyle = '#00ff00';
                ctx.lineWidth = 3;
                ctx.strokeRect(150, 100, 340, 280);
                
                document.getElementById('faceStatus').innerHTML = 
                    '<i class="fas fa-check text-success"></i> Face detected - Ready to punch';
                document.getElementById('facePunchBtn').disabled = false;
            } else {
                ctx.strokeStyle = '#ff0000';
                ctx.lineWidth = 2;
                ctx.strokeRect(200, 150, 240, 180);
                
                document.getElementById('faceStatus').innerHTML = 
                    '<i class="fas fa-times text-danger"></i> Position your face in the frame';
                document.getElementById('facePunchBtn').disabled = true;
            }
        };
        
        setInterval(detectFace, 100);
    }

    async performFaceRecognitionPunch(punchType) {
        const canvas = document.getElementById('faceCanvas');
        const faceData = canvas.toDataURL('image/jpeg');
        
        const position = await this.getCurrentPosition();
        
        const formData = new FormData();
        formData.append('action', 'face_recognition_punch');
        formData.append('employee_id', this.currentUser.employee_id);
        formData.append('face_data', faceData);
        formData.append('punch_type', punchType);
        formData.append('location', JSON.stringify(position));
        
        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert(`Face recognition ${punchType} recorded successfully! Confidence: ${(result.confidence * 100).toFixed(1)}%`, 'success');
                this.refreshAttendanceData();
                this.closeFaceRecognitionModal();
            } else {
                this.showAlert(result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Network error occurred', 'error');
        }
    }

    // QR Code Methods
    async initializeQRScanner() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: "environment" } 
            });
            
            const video = document.getElementById('qrScannerVideo');
            if (video) {
                video.srcObject = stream;
                video.play();
                
                this.startQRDetection(video);
            }
        } catch (error) {
            console.error('Camera access denied:', error);
            this.showAlert('Camera access is required for QR scanning', 'error');
        }
    }

    startQRDetection(video) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        const scanQR = () => {
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);
                
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                
                // Simulate QR detection (use jsQR library in real implementation)
                const qrDetected = Math.random() > 0.7;
                
                if (qrDetected) {
                    const mockQRCode = 'qr_' + Date.now();
                    this.processQRCode(mockQRCode);
                    return;
                }
            }
            
            requestAnimationFrame(scanQR);
        };
        
        scanQR();
    }

    async processQRCode(qrCode) {
        const formData = new FormData();
        formData.append('action', 'qr_code_punch');
        formData.append('employee_id', this.currentUser.employee_id);
        formData.append('qr_code', qrCode);
        formData.append('punch_type', document.getElementById('qrPunchType').value);
        
        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert(`QR punch recorded at ${result.location}`, 'success');
                this.refreshAttendanceData();
                this.closeQRScannerModal();
            } else {
                this.showAlert(result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Network error occurred', 'error');
        }
    }

    // GPS Methods
    async performGPSPunch(punchType) {
        this.showAlert('Getting your location...', 'info');
        
        try {
            const position = await this.getCurrentPosition();
            
            const formData = new FormData();
            formData.append('action', 'gps_punch');
            formData.append('employee_id', this.currentUser.employee_id);
            formData.append('latitude', position.latitude);
            formData.append('longitude', position.longitude);
            formData.append('accuracy', position.accuracy);
            formData.append('punch_type', punchType);
            
            const response = await fetch(this.apiBase, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert(`GPS ${punchType} recorded at ${result.location} (±${result.accuracy}m)`, 'success');
                this.refreshAttendanceData();
            } else {
                this.showAlert(result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Unable to get location: ' + error.message, 'error');
        }
    }

    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation is not supported'));
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    resolve({
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    });
                },
                (error) => {
                    reject(error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                }
            );
        });
    }

    // Manager Dashboard Methods
    async loadTeamDashboard() {
        try {
            const response = await fetch(`${this.apiBase}?action=get_team_dashboard&manager_id=${this.currentUser.employee_id}&date=${this.getCurrentDate()}`);
            const result = await response.json();
            
            if (result.success) {
                this.renderTeamDashboard(result.team_data, result.summary);
            } else {
                this.showAlert('Failed to load team dashboard', 'error');
            }
        } catch (error) {
            this.showAlert('Network error occurred', 'error');
        }
    }

    renderTeamDashboard(teamData, summary) {
        const tbody = document.getElementById('teamDashboardTable');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        teamData.forEach(member => {
            const attendance = member.attendance.length > 0 ? member.attendance[0] : null;
            const status = this.getAttendanceStatus(attendance, member.leave_status);
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-2">
                            <span class="avatar-title bg-primary rounded-circle">
                                ${member.employee.name.charAt(0)}
                            </span>
                        </div>
                        <div>
                            <strong>${member.employee.name}</strong>
                            <small class="d-block text-muted">${member.employee.employee_code}</small>
                        </div>
                    </div>
                </td>
                <td>${member.employee.department}</td>
                <td>
                    <span class="badge ${this.getStatusBadgeClass(status.type)}">
                        ${status.text}
                    </span>
                </td>
                <td>${attendance ? this.formatTime(attendance.punch_time) : '-'}</td>
                <td>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" style="width: ${member.performance.attendance_rate}%"></div>
                    </div>
                    <small>${member.performance.attendance_rate}%</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="attendanceSystem.viewEmployeeDetails(${member.employee.employee_id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="attendanceSystem.sendNotification(${member.employee.employee_id})">
                            <i class="fas fa-bell"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
        
        this.updateDashboardSummary(summary);
    }

    // Leave Management Methods
    async loadLeaveCalendar() {
        try {
            const response = await fetch(`${this.apiBase}?action=get_leave_calendar&employee_id=${this.currentUser.employee_id}`);
            const result = await response.json();
            
            if (result.success) {
                this.renderLeaveCalendar(result.calendar_data);
            }
        } catch (error) {
            this.showAlert('Failed to load leave calendar', 'error');
        }
    }

    async getAISuggestions() {
        try {
            const response = await fetch(`${this.apiBase}?action=get_ai_suggestions&employee_id=${this.currentUser.employee_id}`);
            const result = await response.json();
            
            if (result.success) {
                this.displayAISuggestions(result.suggestions);
            }
        } catch (error) {
            this.showAlert('Failed to get AI suggestions', 'error');
        }
    }

    displayAISuggestions(suggestions) {
        const container = document.getElementById('aiSuggestions');
        if (!container) return;
        
        container.innerHTML = '';
        
        suggestions.recommendations.forEach(rec => {
            const card = document.createElement('div');
            card.className = 'alert alert-info';
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="alert-heading">${rec.title}</h6>
                        <p class="mb-1">${rec.description}</p>
                        <small class="text-muted">Best dates: ${rec.suggested_dates.join(', ')}</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="attendanceSystem.applyAISuggestion('${rec.id}')">
                        Apply
                    </button>
                </div>
            `;
            container.appendChild(card);
        });
    }

    // Mobile Integration Methods
    async checkMobileStatus() {
        try {
            const response = await fetch(`${this.apiBase}?action=get_mobile_status&employee_id=${this.currentUser.employee_id}`);
            const result = await response.json();
            
            if (result.success) {
                this.updateMobileStatus(result.mobile_data);
            }
        } catch (error) {
            console.error('Failed to check mobile status:', error);
        }
    }

    updateMobileStatus(mobileData) {
        const statusContainer = document.getElementById('mobileStatus');
        if (!statusContainer) return;
        
        statusContainer.innerHTML = `
            <div class="row">
                ${mobileData.devices.map(device => `
                    <div class="col-md-6 mb-3">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">${device.device_name}</h6>
                                        <p class="card-text small">
                                            <i class="fas fa-mobile-alt"></i> ${device.platform}<br>
                                            <i class="fas fa-clock"></i> Last sync: ${this.formatDateTime(device.last_sync)}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="badge ${device.is_active ? 'badge-success' : 'badge-secondary'}">
                                            ${device.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // Notification Methods
    async loadNotifications() {
        try {
            const response = await fetch(`${this.apiBase}?action=get_notification_stats&employee_id=${this.currentUser.employee_id}`);
            const result = await response.json();
            
            if (result.success) {
                this.displayNotifications(result.notifications);
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }

    displayNotifications(notifications) {
        const container = document.getElementById('notificationsList');
        if (!container) return;
        
        container.innerHTML = '';
        
        notifications.forEach(notification => {
            const item = document.createElement('div');
            item.className = `notification-item ${notification.is_read ? '' : 'unread'}`;
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="notification-icon">
                            <i class="fas ${this.getNotificationIcon(notification.type)}"></i>
                        </div>
                        <div class="notification-content">
                            <h6>${notification.title}</h6>
                            <p>${notification.message}</p>
                            <small class="text-muted">${this.formatDateTime(notification.created_at)}</small>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <button class="btn btn-sm btn-outline-primary" onclick="attendanceSystem.markAsRead(${notification.id})">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(item);
        });
    }

    // Utility Methods
    setupEventListeners() {
        // Face Recognition Events
        document.getElementById('openFaceRecognition')?.addEventListener('click', () => {
            this.openFaceRecognitionModal();
        });
        
        document.getElementById('facePunchInBtn')?.addEventListener('click', () => {
            this.performFaceRecognitionPunch('in');
        });
        
        document.getElementById('facePunchOutBtn')?.addEventListener('click', () => {
            this.performFaceRecognitionPunch('out');
        });
        
        // QR Scanner Events
        document.getElementById('openQRScanner')?.addEventListener('click', () => {
            this.openQRScannerModal();
        });
        
        // GPS Events
        document.getElementById('gpsPunchIn')?.addEventListener('click', () => {
            this.performGPSPunch('in');
        });
        
        document.getElementById('gpsPunchOut')?.addEventListener('click', () => {
            this.performGPSPunch('out');
        });
        
        // Manager Dashboard Events
        document.getElementById('refreshTeamDashboard')?.addEventListener('click', () => {
            this.loadTeamDashboard();
        });
        
        // Leave Management Events
        document.getElementById('loadAISuggestions')?.addEventListener('click', () => {
            this.getAISuggestions();
        });
    }

    async initializeFeatures() {
        // Load initial data
        await this.checkMobileStatus();
        await this.loadNotifications();
        
        if (this.currentUser.role === 'manager') {
            await this.loadTeamDashboard();
        }
        
        // Setup periodic updates
        setInterval(() => {
            this.checkMobileStatus();
            this.loadNotifications();
        }, 30000); // Update every 30 seconds
    }

    // Modal Methods
    openFaceRecognitionModal() {
        const modal = new bootstrap.Modal(document.getElementById('faceRecognitionModal'));
        modal.show();
        setTimeout(() => this.initializeFaceRecognition(), 500);
    }

    closeFaceRecognitionModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('faceRecognitionModal'));
        modal?.hide();
        
        // Stop camera stream
        const video = document.getElementById('faceRecognitionVideo');
        if (video && video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
        }
    }

    openQRScannerModal() {
        const modal = new bootstrap.Modal(document.getElementById('qrScannerModal'));
        modal.show();
        setTimeout(() => this.initializeQRScanner(), 500);
    }

    closeQRScannerModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
        modal?.hide();
        
        // Stop camera stream
        const video = document.getElementById('qrScannerVideo');
        if (video && video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
        }
    }

    // Helper Methods
    showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alertContainer') || document.body;
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        alertContainer.appendChild(alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    getCurrentDate() {
        return new Date().toISOString().split('T')[0];
    }

    formatTime(datetime) {
        return new Date(datetime).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    formatDateTime(datetime) {
        return new Date(datetime).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    getStatusBadgeClass(statusType) {
        const classes = {
            'present': 'badge-success',
            'late': 'badge-warning',
            'absent': 'badge-danger',
            'on_leave': 'badge-info',
            'partial': 'badge-secondary'
        };
        return classes[statusType] || 'badge-secondary';
    }

    getNotificationIcon(type) {
        const icons = {
            'attendance': 'fa-clock',
            'leave': 'fa-calendar-alt',
            'approval': 'fa-check-circle',
            'reminder': 'fa-bell',
            'alert': 'fa-exclamation-triangle',
            'system': 'fa-cog'
        };
        return icons[type] || 'fa-info-circle';
    }

    async loadUserData() {
        // In a real implementation, this would fetch user data from the server
        this.currentUser = {
            employee_id: 1,
            name: 'Current User',
            role: 'employee'
        };
    }

    async refreshAttendanceData() {
        // Refresh attendance data display
        console.log('Refreshing attendance data...');
    }
}

// Initialize the system when the page loads
document.addEventListener('DOMContentLoaded', () => {
    window.attendanceSystem = new AdvancedAttendanceSystem();
});

// Additional utility functions
function generateQRCode() {
    attendanceSystem.showAlert('Generating QR code...', 'info');
    
    const formData = new FormData();
    formData.append('action', 'generate_qr_code');
    formData.append('location_name', document.getElementById('qrLocationName').value || 'Office');
    formData.append('valid_minutes', document.getElementById('qrValidMinutes').value || 15);
    formData.append('max_uses', document.getElementById('qrMaxUses').value || 0);
    
    fetch('./api/advanced_attendance_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            displayQRCode(result);
        } else {
            attendanceSystem.showAlert(result.message, 'error');
        }
    })
    .catch(error => {
        attendanceSystem.showAlert('Network error occurred', 'error');
    });
}

function displayQRCode(qrData) {
    const qrContainer = document.getElementById('generatedQRCode');
    if (qrContainer) {
        // In a real implementation, use a QR code library like qrcode.js
        qrContainer.innerHTML = `
            <div class="text-center">
                <div class="qr-code-placeholder bg-light border p-4 mb-3" style="width: 200px; height: 200px; margin: 0 auto;">
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <i class="fas fa-qrcode fa-5x text-muted"></i>
                    </div>
                </div>
                <h6>QR Code for ${qrData.location_name}</h6>
                <p class="small text-muted">
                    Valid until: ${new Date(qrData.valid_until).toLocaleString()}<br>
                    ${qrData.max_uses > 0 ? `Max uses: ${qrData.max_uses}` : 'Unlimited uses'}
                </p>
                <button class="btn btn-primary btn-sm" onclick="downloadQRCode('${qrData.qr_code}')">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        `;
    }
}

function downloadQRCode(qrCode) {
    // Implementation for downloading QR code
    attendanceSystem.showAlert('QR code download feature would be implemented here', 'info');
}
