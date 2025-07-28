<?php
session_start();
$_SESSION['admin'] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Recognition Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="alert alert-info">
            <h4>🔍 Face Recognition Debug Test</h4>
            <p>Testing the face recognition functionality step by step</p>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5>Face Recognition Test Area</h5>
                    </div>
                    <div class="card-body">
                        <!-- Face Recognition Area (exact replica from attendance.php) -->
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-person-check me-2"></i>Face Recognition</h6>
                            </div>
                            <div class="card-body text-center">
                                <div id="faceRecognitionArea" class="border rounded p-4 mb-3" style="min-height: 200px; background: #f8f9fa;">
                                    <i class="bi bi-camera text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Click to enable camera for face recognition</p>
                                </div>
                                <button class="btn btn-primary" onclick="debugFaceRecognition()">
                                    <i class="bi bi-camera-video"></i> Test Face Recognition
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6>Debug Log</h6>
                    </div>
                    <div class="card-body">
                        <div id="debugLog" style="height: 400px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                            <div class="text-muted">Debug log will appear here...</div>
                        </div>
                        <button class="btn btn-warning btn-sm mt-2 w-100" onclick="clearDebugLog()">Clear Log</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <a href="pages/attendance/attendance.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Attendance Page
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global state management (replica from attendance.php)
        let modalStates = {
            faceRecognitionInProgress: false,
            qrScannerInProgress: false,
            locationRequestInProgress: false,
            ipRequestInProgress: false
        };

        let mediaStreams = {
            faceRecognition: null
        };

        function logDebug(message, type = 'info') {
            const log = document.getElementById('debugLog');
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                info: '#007bff',
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107'
            };
            
            log.innerHTML += `
                <div style="color: ${colors[type] || colors.info}; margin-bottom: 5px;">
                    [${timestamp}] ${message}
                </div>
            `;
            log.scrollTop = log.scrollHeight;
        }

        function clearDebugLog() {
            document.getElementById('debugLog').innerHTML = '<div class="text-muted">Debug log cleared...</div>';
        }

        function debugFaceRecognition() {
            logDebug('🎯 Starting Face Recognition Debug Test', 'info');
            
            // Check browser support
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                logDebug('❌ ERROR: Browser does not support camera access', 'error');
                showAlert('Your browser does not support camera access', 'danger');
                return;
            }
            logDebug('✅ Browser supports camera access', 'success');

            // Check if already in progress
            if (modalStates.faceRecognitionInProgress) {
                logDebug('⚠️ WARNING: Face recognition already in progress', 'warning');
                return;
            }

            // Check for face recognition area
            const area = document.getElementById('faceRecognitionArea');
            if (!area) {
                logDebug('❌ ERROR: Face recognition area not found!', 'error');
                showAlert('Face recognition area not available', 'danger');
                return;
            }
            logDebug('✅ Face recognition area found', 'success');

            modalStates.faceRecognitionInProgress = true;
            logDebug('🔄 Setting face recognition in progress...', 'info');
            
            // Show loading state
            area.innerHTML = `
                <div class="d-flex flex-column align-items-center">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <p class="mt-2 mb-0">Initializing camera...</p>
                </div>
            `;
            logDebug('🔄 Showing loading state...', 'info');

            // Clean up existing stream
            if (mediaStreams.faceRecognition) {
                logDebug('🧹 Cleaning up existing camera stream...', 'info');
                mediaStreams.faceRecognition.getTracks().forEach(track => track.stop());
                mediaStreams.faceRecognition = null;
            }

            // Request camera access
            logDebug('📷 Requesting camera access...', 'info');
            navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                logDebug('✅ Camera access granted!', 'success');
                mediaStreams.faceRecognition = stream;
                
                // Create video element
                const video = document.createElement('video');
                video.srcObject = stream;
                video.autoplay = true;
                video.playsInline = true;
                video.style.cssText = 'width:100%;height:200px;object-fit:cover;border-radius:8px;';
                
                logDebug('📺 Creating video element...', 'info');
                area.innerHTML = '';
                area.appendChild(video);
                
                logDebug('🎬 Video stream started successfully', 'success');
                
                // Simulate recognition after 3 seconds
                setTimeout(() => {
                    if (modalStates.faceRecognitionInProgress) {
                        logDebug('🔍 Simulating face recognition...', 'info');
                        completeFaceRecognition();
                    }
                }, 3000);
            })
            .catch(err => {
                logDebug(`❌ Camera error: ${err.name} - ${err.message}`, 'error');
                
                let errorMessage = 'Camera access denied';
                if (err.name === 'NotAllowedError') {
                    errorMessage = 'Camera permission denied by user';
                } else if (err.name === 'NotFoundError') {
                    errorMessage = 'No camera found on device';
                } else if (err.name === 'NotReadableError') {
                    errorMessage = 'Camera is being used by another application';
                }
                
                area.innerHTML = `
                    <div class="text-center">
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        <p class="text-danger mt-2">${errorMessage}</p>
                        <button class="btn btn-outline-primary btn-sm mt-2" onclick="debugFaceRecognition()">
                            Try Again
                        </button>
                    </div>
                `;
                modalStates.faceRecognitionInProgress = false;
                showAlert(errorMessage, 'danger');
            });
        }

        function completeFaceRecognition() {
            logDebug('🎉 Completing face recognition...', 'success');
            const area = document.getElementById('faceRecognitionArea');
            if (!area) {
                logDebug('❌ ERROR: Face recognition area not found during completion!', 'error');
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
            logDebug('✅ Face recognition completed successfully', 'success');
        }

        function finalizeFaceCheckIn() {
            logDebug('🏁 Finalizing face check-in...', 'info');
            
            // Clean up camera stream
            if (mediaStreams.faceRecognition) {
                logDebug('🧹 Cleaning up camera stream...', 'info');
                mediaStreams.faceRecognition.getTracks().forEach(track => track.stop());
                mediaStreams.faceRecognition = null;
            }
            
            showAlert('Face recognition check-in completed successfully!', 'success');
            logDebug('✅ Face check-in finalized successfully', 'success');
            
            // Reset state
            modalStates.faceRecognitionInProgress = false;
            logDebug('🔄 Face recognition state reset', 'info');
        }

        // Alert function
        function showAlert(message, type = 'info') {
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

        // Initialize debug
        window.addEventListener('load', function() {
            logDebug('🚀 Face Recognition Debug Tool Loaded', 'success');
            logDebug('💻 Browser: ' + navigator.userAgent.split(' ')[0], 'info');
            logDebug('📷 Camera API: ' + (navigator.mediaDevices ? 'Available' : 'Not Available'), 'info');
            logDebug('🔧 Ready for testing...', 'info');
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
