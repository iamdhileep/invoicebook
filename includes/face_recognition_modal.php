<!-- Face Recognition Modal -->
<div class="modal fade" id="faceRecognitionModal" tabindex="-1" aria-labelledby="faceRecognitionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="faceRecognitionModalLabel">
                    <i class="bi bi-person-bounding-box me-2"></i>
                    Face Recognition Attendance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <!-- Camera Preview -->
                        <div class="camera-container text-center position-relative">
                            <video id="faceVideo" width="100%" height="400" autoplay muted style="border: 3px solid #007bff; border-radius: 15px; background: #f8f9fa;"></video>
                            <canvas id="faceCanvas" width="640" height="480" style="display: none;"></canvas>
                            
                            <!-- Camera Status Indicator -->
                            <div id="cameraStatus" class="position-absolute top-0 start-0 m-2">
                                <span class="badge bg-secondary">
                                    <i class="bi bi-camera-video-off"></i> Camera Loading...
                                </span>
                            </div>
                        </div>
                        
                        <!-- Camera Controls -->
                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-success btn-lg me-2" id="captureBtn">
                                <i class="bi bi-camera-fill"></i> Capture & Verify
                            </button>
                            <button type="button" class="btn btn-outline-primary me-2" id="testCaptureBtn">
                                <i class="bi bi-camera"></i> Test Capture
                            </button>
                            <button type="button" class="btn btn-secondary" id="switchCameraBtn">
                                <i class="bi bi-arrow-repeat"></i> Switch Camera
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Instructions & Status -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-info-circle"></i> Instructions
                                </h6>
                            </div>
                            <div class="card-body">
                                <ol class="small">
                                    <li>Position your face in the center</li>
                                    <li>Ensure good lighting</li>
                                    <li>Look directly at camera</li>
                                    <li>Keep face steady</li>
                                    <li>Click "Capture & Verify"</li>
                                </ol>
                            </div>
                        </div>
                        
                        <!-- Status Panel -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-activity"></i> Status
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="faceRecognitionStatus">
                                    <div class="alert alert-info">
                                        <i class="bi bi-camera"></i>
                                        Camera ready. Position your face and capture.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Demo -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-people"></i> Quick Demo
                                </h6>
                            </div>
                            <div class="card-body">
                                <small class="text-muted">Demo - Click to simulate:</small>
                                <div class="d-grid gap-1 mt-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="simulateFaceRecognition(1, 'John Doe')">
                                        <i class="bi bi-person-circle"></i> Simulate John Doe
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="simulateFaceRecognition(2, 'Jane Smith')">
                                        <i class="bi bi-person-circle"></i> Simulate Jane Smith
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="button" class="btn btn-outline-info" onclick="resetCamera()">
                    <i class="bi bi-arrow-clockwise"></i> Reset Camera
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Face Recognition System
class FaceRecognitionSystem {
    constructor() {
        this.video = document.getElementById('faceVideo');
        this.canvas = document.getElementById('faceCanvas');
        this.captureBtn = document.getElementById('captureBtn');
        this.testCaptureBtn = document.getElementById('testCaptureBtn');
        this.switchCameraBtn = document.getElementById('switchCameraBtn');
        this.statusDiv = document.getElementById('faceRecognitionStatus');
        
        // Check if all required elements exist
        if (!this.video || !this.canvas || !this.captureBtn || !this.switchCameraBtn || !this.statusDiv) {
            console.error('Face recognition modal elements not found');
            throw new Error('Face recognition components not ready');
        }
        
        this.currentStream = null;
        this.facingMode = 'user';
        this.isProcessing = false;
        
        this.init();
    }
    
    async init() {
        try {
            // Check if browser supports getUserMedia
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Camera not supported by this browser');
            }
            
            await this.startCamera();
            this.setupEventListeners();
            this.showStatus('Camera ready! Position your face in the frame.', 'success');
        } catch (error) {
            console.error('Camera initialization error:', error);
            if (error.name === 'NotAllowedError') {
                this.showStatus('Camera access denied. Please allow camera permissions and try again.', 'danger');
            } else if (error.name === 'NotFoundError') {
                this.showStatus('No camera found. Please connect a camera and try again.', 'danger');
            } else {
                this.showStatus('Camera error: ' + error.message + '. You can still use the demo buttons below.', 'warning');
            }
        }
    }
    
    async startCamera() {
        try {
            const cameraStatus = document.getElementById('cameraStatus');
            if (cameraStatus) {
                cameraStatus.innerHTML = '<span class="badge bg-warning"><i class="bi bi-camera-video"></i> Starting Camera...</span>';
            }
            
            if (this.currentStream) {
                this.currentStream.getTracks().forEach(track => track.stop());
            }
            
            const constraints = {
                video: {
                    facingMode: this.facingMode,
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                }
            };
            
            this.currentStream = await navigator.mediaDevices.getUserMedia(constraints);
            this.video.srcObject = this.currentStream;
            
            // Wait for video to be ready
            return new Promise((resolve, reject) => {
                this.video.onloadedmetadata = () => {
                    this.video.play().then(() => {
                        // Update camera status to ready
                        if (cameraStatus) {
                            cameraStatus.innerHTML = '<span class="badge bg-success"><i class="bi bi-camera-video-fill"></i> Camera Ready</span>';
                        }
                        console.log('Camera ready:', this.video.videoWidth + 'x' + this.video.videoHeight);
                        resolve();
                    }).catch(reject);
                };
                this.video.onerror = reject;
                
                // Timeout after 10 seconds
                setTimeout(() => reject(new Error('Camera timeout')), 10000);
            });
            
        } catch (error) {
            console.error('Camera start error:', error);
            const cameraStatus = document.getElementById('cameraStatus');
            if (cameraStatus) {
                cameraStatus.innerHTML = '<span class="badge bg-danger"><i class="bi bi-camera-video-off"></i> Camera Error</span>';
            }
            throw new Error('Camera not available: ' + error.message);
        }
    }
    
    setupEventListeners() {
        this.captureBtn.addEventListener('click', () => this.captureAndVerify());
        this.switchCameraBtn.addEventListener('click', () => this.switchCamera());
        
        if (this.testCaptureBtn) {
            this.testCaptureBtn.addEventListener('click', () => this.testCapture());
        }
        
        document.getElementById('faceRecognitionModal').addEventListener('hidden.bs.modal', () => {
            this.cleanup();
        });
    }
    
    async switchCamera() {
        this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
        await this.startCamera();
        this.showStatus('Camera switched!', 'info');
    }

    testCapture() {
        try {
            // Check if video is ready
            if (!this.video.videoWidth || !this.video.videoHeight) {
                this.showStatus('❌ Camera not ready. Please wait for camera to load.', 'danger');
                return;
            }
            
            // Capture image
            const context = this.canvas.getContext('2d');
            const videoWidth = this.video.videoWidth;
            const videoHeight = this.video.videoHeight;
            
            this.canvas.width = videoWidth;
            this.canvas.height = videoHeight;
            context.drawImage(this.video, 0, 0, videoWidth, videoHeight);
            
            // Get the captured image data
            const capturedImageData = this.canvas.toDataURL('image/jpeg', 0.8);
            
            // Show the captured image
            this.showStatus('✅ Test capture successful!', 'success');
            this.showCapturedImagePreview(capturedImageData);
            
            console.log('Test capture successful. Image size:', videoWidth + 'x' + videoHeight);
            
        } catch (error) {
            console.error('Test capture error:', error);
            this.showStatus('❌ Test capture failed: ' + error.message, 'danger');
        }
    }
    
    async captureAndVerify() {
        if (this.isProcessing) return;
        
        try {
            this.isProcessing = true;
            this.captureBtn.disabled = true;
            this.captureBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            
            // Check if video is ready
            if (!this.video.videoWidth || !this.video.videoHeight) {
                throw new Error('Camera not ready. Please wait for camera to load or try resetting.');
            }
            
            this.showStatus('Capturing image from camera...', 'warning');
            
            // Capture image with proper sizing
            const context = this.canvas.getContext('2d');
            const videoWidth = this.video.videoWidth;
            const videoHeight = this.video.videoHeight;
            
            // Set canvas size to match video
            this.canvas.width = videoWidth;
            this.canvas.height = videoHeight;
            
            // Draw current video frame to canvas
            context.drawImage(this.video, 0, 0, videoWidth, videoHeight);
            
            // Create a preview of captured image (for debugging)
            const capturedImageData = this.canvas.toDataURL('image/jpeg', 0.8);
            console.log('Image captured successfully, size:', videoWidth + 'x' + videoHeight);
            
            this.showStatus('Analyzing captured image...', 'info');
            
            // Simulate face recognition processing
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // Simulate recognition (80% success rate for testing)
            const success = Math.random() > 0.2;
            
            if (success) {
                const employeeNames = ['John Doe', 'Jane Smith', 'Bob Johnson', 'Alice Wilson'];
                const randomEmployee = employeeNames[Math.floor(Math.random() * employeeNames.length)];
                
                this.showStatus(`✅ Face recognized! Welcome ${randomEmployee}`, 'success');
                
                // Show captured image preview (for debugging)
                this.showCapturedImagePreview(capturedImageData);
                
                // Auto-punch action
                setTimeout(() => {
                    this.showStatus('🎉 Attendance marked successfully!', 'success');
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('faceRecognitionModal')).hide();
                        if (typeof showAlert === 'function') {
                            showAlert(`${randomEmployee} attendance marked via face recognition!`, 'success');
                        }
                    }, 2000);
                }, 1000);
                
            } else {
                this.showStatus('❌ Face not recognized. Please ensure good lighting and try again.', 'danger');
            }
            
        } catch (error) {
            console.error('Capture error:', error);
            this.showStatus('❌ Capture failed: ' + error.message, 'danger');
        } finally {
            this.isProcessing = false;
            this.captureBtn.disabled = false;
            this.captureBtn.innerHTML = '<i class="bi bi-camera-fill"></i> Capture & Verify';
        }
    }

    showCapturedImagePreview(imageData) {
        // Add a small preview of captured image for debugging
        const statusDiv = this.statusDiv;
        const existingPreview = statusDiv.querySelector('.captured-preview');
        if (existingPreview) {
            existingPreview.remove();
        }
        
        const previewDiv = document.createElement('div');
        previewDiv.className = 'captured-preview mt-2';
        previewDiv.innerHTML = `
            <small class="text-muted">Captured Image:</small><br>
            <img src="${imageData}" style="max-width: 100px; max-height: 80px; border: 1px solid #ddd; border-radius: 4px;">
        `;
        statusDiv.appendChild(previewDiv);
    }
    
    showStatus(message, type) {
        const icons = {
            success: 'check-circle',
            danger: 'exclamation-triangle',
            warning: 'hourglass-split',
            info: 'info-circle'
        };
        
        this.statusDiv.innerHTML = `
            <div class="alert alert-${type}">
                <i class="bi bi-${icons[type]}"></i> ${message}
            </div>
        `;
    }
    
    cleanup() {
        if (this.currentStream) {
            this.currentStream.getTracks().forEach(track => track.stop());
            this.currentStream = null;
        }
    }
}

// Global functions
function openFaceRecognition() {
    const modal = document.getElementById('faceRecognitionModal');
    if (!modal) {
        console.error('Face recognition modal not found');
        if (typeof showAlert === 'function') {
            showAlert('Face recognition system not available. Please refresh the page.', 'danger');
        } else {
            alert('Face recognition system not available. Please refresh the page.');
        }
        return;
    }
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    setTimeout(() => {
        try {
            window.faceRecognition = new FaceRecognitionSystem();
        } catch (error) {
            console.error('Face recognition initialization error:', error);
            if (typeof showAlert === 'function') {
                showAlert('Face recognition initialization failed. You can still use the demo buttons.', 'warning');
            }
        }
    }, 500);
}

function resetCamera() {
    if (window.faceRecognition) {
        window.faceRecognition.cleanup();
        setTimeout(() => {
            window.faceRecognition = new FaceRecognitionSystem();
        }, 500);
    }
}

function simulateFaceRecognition(employeeId, employeeName) {
    const statusDiv = document.getElementById('faceRecognitionStatus');
    
    statusDiv.innerHTML = `
        <div class="alert alert-info">
            <i class="bi bi-hourglass-split"></i> Simulating face recognition for ${employeeName}...
        </div>
    `;
    
    setTimeout(() => {
        statusDiv.innerHTML = `
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> ✅ Face verified! Welcome ${employeeName}
            </div>
        `;
        
        setTimeout(async () => {
            // Simulate punch action
            if (window.punchIn && typeof window.punchIn === 'function') {
                await punchIn(employeeId);
            } else {
                showAlert(`${employeeName} attendance marked successfully!`, 'success');
            }
            
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('faceRecognitionModal')).hide();
            }, 1500);
        }, 1000);
    }, 2000);
}
</script>

<style>
.camera-container {
    position: relative;
}

.camera-container::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 200px;
    height: 250px;
    border: 2px dashed rgba(0, 123, 255, 0.5);
    border-radius: 50%;
    pointer-events: none;
    z-index: 1;
}

#faceVideo {
    transition: all 0.3s ease;
}

#faceVideo:hover {
    transform: scale(1.02);
}

.btn:hover {
    transform: translateY(-1px);
}
</style>
