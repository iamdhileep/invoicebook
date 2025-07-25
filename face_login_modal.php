<!-- Face Login Modal for Employee_attendance.php -->
<div class="modal fade" id="faceLoginModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-bounding-box"></i>
                    Face Recognition Login
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="cameraContainer">
                    <video id="video" width="400" height="300" autoplay muted style="border: 2px solid #007bff; border-radius: 8px;"></video>
                    <canvas id="canvas" width="400" height="300" style="display: none;"></canvas>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" id="captureBtn">
                        <i class="bi bi-camera"></i> Capture & Verify
                    </button>
                    <button type="button" class="btn btn-secondary" id="switchCameraBtn">
                        <i class="bi bi-arrow-repeat"></i> Switch Camera
                    </button>
                </div>
                <div id="faceLoginStatus" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
// Face Recognition System
class FaceLoginSystem {
    constructor() {
        this.video = document.getElementById('video');
        this.canvas = document.getElementById('canvas');
        this.captureBtn = document.getElementById('captureBtn');
        this.switchCameraBtn = document.getElementById('switchCameraBtn');
        this.statusDiv = document.getElementById('faceLoginStatus');
        this.currentStream = null;
        this.facingMode = 'user'; // Start with front camera
        
        this.init();
    }
    
    async init() {
        try {
            await this.startCamera();
            this.setupEventListeners();
        } catch (error) {
            this.showStatus('Error accessing camera: ' + error.message, 'danger');
        }
    }
    
    async startCamera() {
        try {
            // Stop existing stream
            if (this.currentStream) {
                this.currentStream.getTracks().forEach(track => track.stop());
            }
            
            const constraints = {
                video: {
                    facingMode: this.facingMode,
                    width: { ideal: 400 },
                    height: { ideal: 300 }
                }
            };
            
            this.currentStream = await navigator.mediaDevices.getUserMedia(constraints);
            this.video.srcObject = this.currentStream;
            
            this.showStatus('Camera ready - Position your face in the frame', 'info');
        } catch (error) {
            throw new Error('Camera access denied or not available');
        }
    }
    
    setupEventListeners() {
        this.captureBtn.addEventListener('click', () => this.captureAndVerify());
        this.switchCameraBtn.addEventListener('click', () => this.switchCamera());
        
        // Auto-capture when modal closes
        document.getElementById('faceLoginModal').addEventListener('hidden.bs.modal', () => {
            if (this.currentStream) {
                this.currentStream.getTracks().forEach(track => track.stop());
            }
        });
    }
    
    async switchCamera() {
        this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
        await this.startCamera();
    }
    
    async captureAndVerify() {
        try {
            this.captureBtn.disabled = true;
            this.showStatus('Capturing image...', 'info');
            
            // Draw video frame to canvas
            const context = this.canvas.getContext('2d');
            context.drawImage(this.video, 0, 0, 400, 300);
            
            // Convert to base64
            const imageData = this.canvas.toDataURL('image/jpeg', 0.8);
            
            this.showStatus('Verifying face...', 'warning');
            
            // Send to server for verification (simulated for now)
            const response = await this.sendToServer(imageData);
            
            if (response.success) {
                this.showStatus(`✅ Face verified! Welcome ${response.employee_name}`, 'success');
                
                // Auto punch in/out
                setTimeout(() => {
                    if (response.action === 'punch_in') {
                        punchIn(response.employee_id);
                    } else {
                        punchOut(response.employee_id);
                    }
                    
                    // Close modal
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('faceLoginModal')).hide();
                    }, 2000);
                }, 1000);
                
            } else {
                this.showStatus('❌ Face not recognized. Please try again.', 'danger');
            }
            
        } catch (error) {
            this.showStatus('Error: ' + error.message, 'danger');
        } finally {
            this.captureBtn.disabled = false;
        }
    }
    
    async sendToServer(imageData) {
        // Simulate API call (replace with actual face recognition API)
        return new Promise((resolve) => {
            setTimeout(() => {
                // Simulate successful recognition for demo
                const mockResponse = {
                    success: Math.random() > 0.3, // 70% success rate for demo
                    employee_id: Math.floor(Math.random() * 10) + 1,
                    employee_name: 'John Doe',
                    action: Math.random() > 0.5 ? 'punch_in' : 'punch_out'
                };
                resolve(mockResponse);
            }, 2000);
        });
    }
    
    showStatus(message, type) {
        this.statusDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    }
}

// Initialize face login system when modal opens
document.getElementById('faceLoginModal').addEventListener('shown.bs.modal', function () {
    new FaceLoginSystem();
});
</script>
