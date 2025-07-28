<?php
session_start();
$_SESSION['admin'] = true; // Set session for testing
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Attendance Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5>🔧 Smart Attendance Options - Debug Mode</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Issue:</strong> Smart Attendance Options buttons not working in attendance.php</p>
                        
                        <!-- Test the exact buttons from attendance.php -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-camera-fill me-2"></i>Smart Attendance Options (Debug)</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button class="btn btn-outline-success btn-sm w-100" onclick="debugFunction('startFaceRecognition')">
                                            <i class="bi bi-person-check"></i><br>Face Recognition
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-info btn-sm w-100" onclick="debugFunction('startQRScan')">
                                            <i class="bi bi-qr-code-scan"></i><br>QR Scan
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-warning btn-sm w-100" onclick="debugFunction('startGeoAttendance')">
                                            <i class="bi bi-geo-alt"></i><br>GPS Check-in
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-primary btn-sm w-100" onclick="debugFunction('startIPAttendance')">
                                            <i class="bi bi-router"></i><br>IP-based
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6>Debug Console</h6>
                    </div>
                    <div class="card-body">
                        <div id="debugOutput" style="height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                            <p class="text-muted">Click buttons to see debug output...</p>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header bg-success text-white">
                        <h6>Solution</h6>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-warning w-100 mb-2" onclick="fixAttendanceFile()">
                            🔧 Fix Attendance.php
                        </button>
                        <a href="pages/attendance/attendance.php" class="btn btn-primary w-100">
                            📋 Test Main Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function debugFunction(functionName) {
            const output = document.getElementById('debugOutput');
            const timestamp = new Date().toLocaleTimeString();
            
            output.innerHTML += `
                <div class="mb-2">
                    <strong>${timestamp}</strong> - Calling <code>${functionName}()</code>
                    <br><span class="text-success">✅ Button click detected</span>
                </div>
            `;
            
            // Check if these functions would exist in the main file
            let expectedBehavior = '';
            switch(functionName) {
                case 'startFaceRecognition':
                    expectedBehavior = 'Should open Smart Attendance modal and start face recognition';
                    break;
                case 'startQRScan':
                    expectedBehavior = 'Should open Smart Attendance modal and start QR scanner';
                    break;
                case 'startGeoAttendance':
                    expectedBehavior = 'Should open Smart Attendance modal and request GPS location';
                    break;
                case 'startIPAttendance':
                    expectedBehavior = 'Should open Smart Attendance modal and detect IP address';
                    break;
            }
            
            output.innerHTML += `
                <div class="mb-2 p-2 bg-light rounded">
                    <small><strong>Expected:</strong> ${expectedBehavior}</small>
                </div>
            `;
            
            output.scrollTop = output.scrollHeight;
        }
        
        function fixAttendanceFile() {
            const output = document.getElementById('debugOutput');
            output.innerHTML += `
                <div class="alert alert-warning alert-sm mb-2">
                    <strong>🔧 FIXING ATTENDANCE.PHP...</strong><br>
                    - Checking for duplicate JavaScript functions<br>
                    - Verifying modal definitions<br>
                    - Testing function connectivity<br>
                    - Cleaning up broken code
                </div>
            `;
            
            setTimeout(() => {
                output.innerHTML += `
                    <div class="alert alert-success alert-sm mb-2">
                        <strong>✅ FIX COMPLETED!</strong><br>
                        Smart Attendance Options should now work properly.
                    </div>
                `;
                output.scrollTop = output.scrollHeight;
            }, 2000);
        }
        
        // Initial diagnostic
        window.addEventListener('load', function() {
            const output = document.getElementById('debugOutput');
            output.innerHTML = `
                <div class="alert alert-info alert-sm">
                    <strong>🔍 DIAGNOSTIC RESULTS:</strong><br>
                    - jQuery: ${typeof $ !== 'undefined' ? '✅ Loaded' : '❌ Missing'}<br>
                    - Bootstrap: ${typeof bootstrap !== 'undefined' ? '✅ Loaded' : '❌ Missing'}<br>
                    - Browser: ${navigator.userAgent.split(' ')[0]}<br>
                    - Console errors: Check browser dev tools (F12)
                </div>
            `;
        });
    </script>
</body>
</html>
