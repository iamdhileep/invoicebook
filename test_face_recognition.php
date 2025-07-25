<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Recognition Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Face Recognition System Test</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>System Status</h5>
            </div>
            <div class="card-body">
                <div id="systemStatus">Checking system...</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Test Face Recognition</h5>
            </div>
            <div class="card-body">
                <button type="button" class="btn btn-primary" onclick="testFaceRecognition()">
                    <i class="bi bi-camera-fill"></i> Test Face Recognition
                </button>
            </div>
        </div>
    </div>

    <!-- Include the face recognition modal -->
    <?php include 'includes/face_recognition_modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Test camera availability
        async function checkSystemStatus() {
            const statusDiv = document.getElementById('systemStatus');
            let status = [];
            
            // Check browser support
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                status.push('<span class="badge bg-danger">Camera API not supported</span>');
            } else {
                status.push('<span class="badge bg-success">Camera API supported</span>');
            }
            
            // Check modal elements
            const modal = document.getElementById('faceRecognitionModal');
            if (modal) {
                status.push('<span class="badge bg-success">Face recognition modal loaded</span>');
            } else {
                status.push('<span class="badge bg-danger">Face recognition modal not found</span>');
            }
            
            // Check required functions
            if (typeof openFaceRecognition === 'function') {
                status.push('<span class="badge bg-success">openFaceRecognition function available</span>');
            } else {
                status.push('<span class="badge bg-danger">openFaceRecognition function missing</span>');
            }
            
            // Test camera permissions
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                stream.getTracks().forEach(track => track.stop());
                status.push('<span class="badge bg-success">Camera permissions granted</span>');
            } catch (error) {
                status.push('<span class="badge bg-warning">Camera permissions needed</span>');
            }
            
            statusDiv.innerHTML = status.join(' ');
        }
        
        function testFaceRecognition() {
            if (typeof openFaceRecognition === 'function') {
                openFaceRecognition();
            } else {
                alert('Face recognition function not available');
            }
        }
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }
        
        // Run system check when page loads
        document.addEventListener('DOMContentLoaded', checkSystemStatus);
    </script>
</body>
</html>
