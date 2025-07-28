<?php
session_start();
$_SESSION['admin'] = true; // Set session for testing
include 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Recognition Complete Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container-fluid mt-3">
        <div class="alert alert-info">
            <h4>🔍 Face Recognition Complete System Test</h4>
            <p>Testing the exact flow from Smart Attendance Options button click to face recognition completion</p>
        </div>

        <div class="row">
            <!-- Left: Smart Attendance Options (Exact replica) -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-camera-fill me-2"></i>Smart Attendance Options</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <button class="btn btn-outline-success btn-sm w-100" onclick="startFaceRecognition()">
                                    <i class="bi bi-person-check"></i><br>Face Recognition
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-info btn-sm w-100" onclick="startQRScan()">
                                    <i class="bi bi-qr-code-scan"></i><br>QR Scan
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-warning btn-sm w-100" onclick="startGeoAttendance()">
                                    <i class="bi bi-geo-alt"></i><br>GPS Check-in
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-primary btn-sm w-100" onclick="startIPAttendance()">
                                    <i class="bi bi-router"></i><br>IP-based
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center: Smart Attendance Modal (Exact replica) -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5>Smart Touchless Attendance Modal</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="bi bi-person-check me-2"></i>Face Recognition</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <div id="faceRecognitionArea" class="border rounded p-4 mb-3" style="min-height: 200px; background: #f8f9fa;">
                                            <i class="bi bi-camera text-muted" style="font-size: 3rem;"></i>
                                            <p class="text-muted mt-2">Click button to start face recognition</p>
                                        </div>
                                        <button class="btn btn-primary" onclick="initFaceRecognition()">
                                            <i class="bi bi-camera-video"></i> Start Face Recognition
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="bi bi-qr-code-scan me-2"></i>QR Code Scanner</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <div id="qrScannerArea" class="border rounded p-4 mb-3" style="min-height: 200px; background: #f8f9fa;">
                                            <i class="bi bi-qr-code text-muted" style="font-size: 3rem;"></i>
                                            <p class="text-muted mt-2">QR scanner area</p>
                                        </div>
                                        <button class="btn btn-info" onclick="initQRScanner()">
                                            <i class="bi bi-qr-code-scan"></i> Start QR Scanner
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>GPS Location</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="locationStatus" class="text-center">
                                            <div class="spinner-border text-warning" role="status" id="locationSpinner">
                                                <span class="visually-hidden">Getting location...</span>
                                            </div>
                                            <p class="mt-2" id="locationText">Getting your location...</p>
                                        </div>
                                        <button class="btn btn-warning w-100" onclick="checkInWithGPS()" id="gpsCheckInBtn" disabled>
                                            <i class="bi bi-geo-alt-fill"></i> Check-in with GPS
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-secondary text-white">
                                        <h6 class="mb-0"><i class="bi bi-router me-2"></i>IP-based Check-in</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="text-center">
                                            <p class="mb-2">Your IP: <strong id="userIP">Loading...</strong></p>
                                            <p class="mb-3">Office Network: <span class="badge bg-success">Detected</span></p>
                                        </div>
                                        <button class="btn btn-secondary w-100" onclick="checkInWithIP()">
                                            <i class="bi bi-router-fill"></i> Check-in with IP
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Console -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h6>Debug Console - Real-time Function Execution Log</h6>
                    </div>
                    <div class="card-body">
                        <div id="debugConsole" style="height: 200px; overflow-y: auto; background: #000; color: #0f0; padding: 10px; font-family: monospace; font-size: 12px;">
                            <div>System initialized. Click Face Recognition button to test...</div>
                        </div>
                        <button class="btn btn-warning btn-sm mt-2" onclick="clearConsole()">Clear Console</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 text-center">
            <a href="pages/attendance/attendance.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Main Attendance Page
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Exact replica of the JavaScript from attendance.php
        let modalStates = {
            faceRecognitionInProgress: false,
            qrScannerInProgress: false,
            locationRequestInProgress: false,
            ipRequestInProgress: false
        };

        let mediaStreams = {
            faceRecognition: null
        };

        function logToConsole(message, type = 'info') {
            const console = document.getElementById('debugConsole');
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                info: '#0ff',
                success: '#0f0',
                error: '#f00',
                warning: '#ff0'
            };
            
            console.innerHTML += `<div style="color: ${colors[type] || colors.info}">[${timestamp}] ${message}</div>`;
            console.scrollTop = console.scrollHeight;
        }

        function clearConsole() {
            document.getElementById('debugConsole').innerHTML = '<div>Console cleared...</div>';
            logToConsole('Console cleared', 'info');
        }

        // EXACT FUNCTIONS FROM ATTENDANCE.PHP
        function startFaceRecognition() {
            logToConsole('📱 startFaceRecognition() called', 'info');
            openSmartAttendance();
            setTimeout(() => {
                logToConsole('⏰ Delayed call to initFaceRecognition()', 'info');
                initFaceRecognition();
            }, 500);
        }

        function openSmartAttendance() {
            logToConsole('🚪 openSmartAttendance() called', 'info');
            // Since we don't have the modal, we'll just log that it would open
            logToConsole('✅ Smart attendance modal would open (simulated)', 'success');
            // Initialize location and IP
            setTimeout(() => {
                getUserLocation();
                getUserIP();
            }, 300);
        }

        function initFaceRecognition() {
            logToConsole('🎥 initFaceRecognition() called', 'info');
            
            if (modalStates.faceRecognitionInProgress) {
                logToConsole('⚠️ Face recognition already in progress', 'warning');
                return;
            }
            
            const area = document.getElementById('faceRecognitionArea');
            if (!area) {
                logToConsole('❌ Face recognition area not found!', 'error');
                showAlert('Face recognition not available', 'danger');
                return;
            }
            
            logToConsole('✅ Face recognition area found', 'success');
            modalStates.faceRecognitionInProgress = true;
            
            area.innerHTML = `
                <div class="d-flex flex-column align-items-center">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <p class="mt-2 mb-0">Initializing camera...</p>
                </div>
            `;
            
            logToConsole('🔄 Showing loading state', 'info');
            
            // Clean up existing stream
            if (mediaStreams.faceRecognition) {
                logToConsole('🧹 Cleaning up existing stream', 'info');
                mediaStreams.faceRecognition.getTracks().forEach(track => track.stop());
                mediaStreams.faceRecognition = null;
            }
            
            logToConsole('📷 Requesting camera access...', 'info');
            navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                logToConsole('✅ Camera access granted!', 'success');
                mediaStreams.faceRecognition = stream;
                const video = document.createElement('video');
                video.srcObject = stream;
                video.autoplay = true;
                video.playsInline = true;
                video.style.cssText = 'width:100%;height:200px;object-fit:cover;border-radius:8px;';
                
                area.innerHTML = '';
                area.appendChild(video);
                logToConsole('📺 Video element created and added', 'success');
                
                // Simulate recognition after 3 seconds
                setTimeout(() => {
                    if (modalStates.faceRecognitionInProgress) {
                        logToConsole('🔍 Starting face recognition simulation...', 'info');
                        completeFaceRecognition();
                    }
                }, 3000);
            })
            .catch(err => {
                logToConsole(`❌ Camera error: ${err.name} - ${err.message}`, 'error');
                area.innerHTML = `
                    <div class="text-center">
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        <p class="text-danger mt-2">Camera access denied</p>
                        <button class="btn btn-outline-primary btn-sm mt-2" onclick="initFaceRecognition()">
                            Try Again
                        </button>
                    </div>
                `;
                modalStates.faceRecognitionInProgress = false;
            });
        }

        function completeFaceRecognition() {
            logToConsole('🎉 completeFaceRecognition() called', 'success');
            const area = document.getElementById('faceRecognitionArea');
            if (!area) {
                logToConsole('❌ Face recognition area not found during completion!', 'error');
                return;
            }
            
            area.innerHTML = `
                <div class="text-center success-state p-3 rounded">
                    <i class="bi bi-person-check-fill text-success" style="font-size: 3rem;"></i>
                    <div class="alert alert-success mt-3 mb-3">
                        <strong>Face Recognized Successfully!</strong>
                    </div>
                    <button class="btn btn-success" onclick="finalizeFaceCheckIn()">
                        <i class="bi bi-check-circle"></i> Complete Check-In
                    </button>
                </div>
            `;
            logToConsole('✅ Face recognition completed successfully', 'success');
        }

        function finalizeFaceCheckIn() {
            logToConsole('🏁 finalizeFaceCheckIn() called', 'success');
            
            // Clean up camera stream
            if (mediaStreams.faceRecognition) {
                logToConsole('🧹 Cleaning up camera stream...', 'info');
                mediaStreams.faceRecognition.getTracks().forEach(track => track.stop());
                mediaStreams.faceRecognition = null;
            }
            
            showAlert('Face recognition check-in completed successfully!', 'success');
            logToConsole('✅ Face check-in finalized successfully', 'success');
            
            // Reset state
            modalStates.faceRecognitionInProgress = false;
            logToConsole('🔄 Face recognition state reset', 'info');
        }

        // Other functions for completeness
        function startQRScan() {
            logToConsole('📱 startQRScan() called', 'info');
            showAlert('QR Scanner would start here', 'info');
        }

        function startGeoAttendance() {
            logToConsole('📍 startGeoAttendance() called', 'info');
            showAlert('GPS Check-in would start here', 'info');
        }

        function startIPAttendance() {
            logToConsole('🌐 startIPAttendance() called', 'info');
            showAlert('IP-based check-in would start here', 'info');
        }

        function getUserLocation() {
            logToConsole('📍 getUserLocation() called', 'info');
            // Simulate location detection
        }

        function getUserIP() {
            logToConsole('🌐 getUserIP() called', 'info');
            const ipElement = document.getElementById('userIP');
            if (ipElement) {
                ipElement.textContent = '192.168.1.100 (simulated)';
            }
        }

        function checkInWithGPS() {
            logToConsole('📍 checkInWithGPS() called', 'info');
            showAlert('GPS check-in completed!', 'success');
        }

        function checkInWithIP() {
            logToConsole('🌐 checkInWithIP() called', 'info');
            showAlert('IP-based check-in completed!', 'success');
        }

        // Alert function
        function showAlert(message, type = 'info') {
            logToConsole(`🔔 showAlert: ${message} (${type})`, 'info');
            
            // Remove existing alerts
            document.querySelectorAll('.test-alert').forEach(alert => alert.remove());
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed test-alert`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);';
            
            alertDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">${message}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto remove
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.classList.remove('show');
                    setTimeout(() => {
                        if (alertDiv.parentNode) {
                            alertDiv.parentNode.removeChild(alertDiv);
                        }
                    }, 300);
                }
            }, 5000);
        }

        // Initialize
        window.addEventListener('load', function() {
            logToConsole('🚀 Face Recognition Test System Loaded', 'success');
            logToConsole('💻 Browser: ' + navigator.userAgent.split(' ')[0], 'info');
            logToConsole('📷 Camera API: ' + (navigator.mediaDevices ? 'Available' : 'Not Available'), 'info');
            logToConsole('🎯 Ready to test Face Recognition', 'success');
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (mediaStreams.faceRecognition) {
                mediaStreams.faceRecognition.getTracks().forEach(track => track.stop());
            }
        });
    </script>
</body>
</html>
