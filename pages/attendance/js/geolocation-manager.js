// Geolocation Utility Functions with Enhanced Error Handling
// This file provides robust geolocation functionality with fallback mechanisms

class GeoLocationManager {
    constructor() {
        this.officeLocation = { lat: 12.9716, lng: 77.5946 }; // Bangalore coordinates
        this.allowedRadius = 100; // meters
        this.options = {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 300000
        };
    }

    // Check if geolocation is supported and permissions are available
    async checkGeolocationSupport() {
        if (!navigator.geolocation) {
            return { supported: false, error: 'Geolocation not supported by this browser' };
        }

        try {
            // Check permissions API if available
            if ('permissions' in navigator) {
                const permission = await navigator.permissions.query({ name: 'geolocation' });
                return { 
                    supported: true, 
                    permission: permission.state,
                    error: permission.state === 'denied' ? 'Geolocation permission denied' : null
                };
            }
            
            return { supported: true, permission: 'unknown', error: null };
        } catch (error) {
            return { supported: true, permission: 'unknown', error: null };
        }
    }

    // Get current position with enhanced error handling
    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation not supported'));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        timestamp: position.timestamp
                    });
                },
                (error) => {
                    let errorMessage = 'Unknown location error';
                    let errorType = 'warning';
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Location permission denied. Please enable location access in your browser settings.';
                            errorType = 'permission';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'Location information unavailable. Please check your GPS/WiFi connection.';
                            errorType = 'unavailable';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Location request timeout. Please try again or move to an open area.';
                            errorType = 'timeout';
                            break;
                    }
                    
                    reject({ 
                        code: error.code, 
                        message: errorMessage, 
                        type: errorType,
                        originalError: error 
                    });
                },
                this.options
            );
        });
    }

    // Calculate distance between two coordinates
    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371000; // Earth's radius in meters
        const dLat = this.toRadians(lat2 - lat1);
        const dLng = this.toRadians(lng2 - lng1);
        
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(this.toRadians(lat1)) * Math.cos(this.toRadians(lat2)) *
                  Math.sin(dLng/2) * Math.sin(dLng/2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c; // Distance in meters
    }

    toRadians(degrees) {
        return degrees * (Math.PI/180);
    }

    // Verify if user is within office premises
    async verifyOfficeLocation() {
        try {
            const position = await this.getCurrentPosition();
            const distance = this.calculateDistance(
                position.latitude, 
                position.longitude,
                this.officeLocation.lat, 
                this.officeLocation.lng
            );

            return {
                success: true,
                position: position,
                distance: distance,
                withinOffice: distance <= this.allowedRadius,
                message: distance <= this.allowedRadius 
                    ? `✓ Location verified (${Math.round(distance)}m from office)`
                    : `⚠ You are ${Math.round(distance)}m from office (max allowed: ${this.allowedRadius}m)`
            };
        } catch (error) {
            return {
                success: false,
                error: error,
                message: error.message || 'Location verification failed'
            };
        }
    }

    // Get mock location for testing when real GPS is unavailable
    getMockLocation() {
        return {
            latitude: this.officeLocation.lat + (Math.random() - 0.5) * 0.001,
            longitude: this.officeLocation.lng + (Math.random() - 0.5) * 0.001,
            accuracy: 10,
            timestamp: Date.now(),
            isMock: true
        };
    }

    // Enhanced geo-punch function with fallbacks
    async performGeoPunch(employeeId, punchType = 'in') {
        try {
            // Check support first
            const support = await this.checkGeolocationSupport();
            
            if (!support.supported) {
                throw new Error(support.error);
            }

            if (support.permission === 'denied') {
                throw new Error('Location permission denied. Please enable location access.');
            }

            // Try to get location
            const locationResult = await this.verifyOfficeLocation();
            
            if (!locationResult.success) {
                // Handle different error types
                if (locationResult.error.type === 'permission') {
                    throw new Error('Permission denied. Please enable location access and reload the page.');
                } else if (locationResult.error.type === 'timeout') {
                    // Offer retry or fallback
                    const useManual = confirm('Location request timed out. Would you like to use manual punch instead?');
                    if (useManual) {
                        return this.performManualPunch(employeeId, punchType);
                    } else {
                        throw new Error('Location timeout. Please try again.');
                    }
                } else {
                    throw new Error(locationResult.message);
                }
            }

            if (!locationResult.withinOffice) {
                const allowAnyway = confirm(`${locationResult.message}\n\nWould you like to continue with manual punch?`);
                if (allowAnyway) {
                    return this.performManualPunch(employeeId, punchType);
                } else {
                    throw new Error('Location verification failed - not within office premises');
                }
            }

            // If we get here, location is verified - proceed with API call
            return await this.callGeoPunchAPI(employeeId, punchType, locationResult.position);

        } catch (error) {
            console.error('Geo punch error:', error);
            
            // Provide user-friendly error handling
            if (error.message.includes('permission') || error.message.includes('denied')) {
                const instructions = this.getLocationInstructions();
                alert(`Location Access Required\n\n${error.message}\n\n${instructions}`);
            }
            
            throw error;
        }
    }

    // API call for geo-punch
    async callGeoPunchAPI(employeeId, punchType, position) {
        const response = await fetch('api/biometric_api.php?action=geo_checkin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                employee_id: employeeId,
                latitude: position.latitude,
                longitude: position.longitude,
                accuracy: position.accuracy,
                punch_type: punchType
            })
        });

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Geo-punch API failed');
        }

        return data;
    }

    // Fallback manual punch
    async performManualPunch(employeeId, punchType) {
        const currentTime = new Date().toLocaleTimeString('en-GB', { hour12: false }).substring(0, 5);
        
        if (punchType === 'in') {
            const timeField = document.getElementById(`time_in_${employeeId}`);
            const statusField = document.getElementById(`status-${employeeId}`);
            
            if (timeField) timeField.value = currentTime;
            if (statusField) statusField.value = 'Present';
            
            return { success: true, method: 'manual', time: currentTime, type: 'punch_in' };
        } else {
            const timeField = document.getElementById(`time_out_${employeeId}`);
            if (timeField) timeField.value = currentTime;
            
            return { success: true, method: 'manual', time: currentTime, type: 'punch_out' };
        }
    }

    // Get browser-specific location instructions
    getLocationInstructions() {
        const userAgent = navigator.userAgent.toLowerCase();
        
        if (userAgent.includes('chrome')) {
            return `Chrome Instructions:
1. Click the location icon in the address bar
2. Select "Always allow" for this site
3. Refresh the page and try again`;
        } else if (userAgent.includes('firefox')) {
            return `Firefox Instructions:
1. Click on the shield icon in the address bar
2. Click "Allow Location Access"
3. Refresh the page and try again`;
        } else if (userAgent.includes('safari')) {
            return `Safari Instructions:
1. Go to Safari > Preferences > Websites > Location
2. Set this website to "Allow"
3. Refresh the page and try again`;
        } else {
            return `To enable location:
1. Look for a location/GPS icon in your browser
2. Allow location access for this website
3. Refresh the page and try again`;
        }
    }
}

// Global instance
window.geoManager = new GeoLocationManager();

// Enhanced wrapper functions for compatibility
window.enhancedGeoPunchIn = async function(employeeId) {
    try {
        showAlert('📍 Initiating geo-location punch-in...', 'info');
        const result = await window.geoManager.performGeoPunch(employeeId, 'in');
        
        if (result.success) {
            if (result.data && result.data.time_in) {
                document.getElementById(`time_in_${employeeId}`).value = result.data.time_in.substring(0, 5);
                document.getElementById(`status-${employeeId}`).value = result.data.status || 'Present';
            }
            showAlert(`✅ Geo punch-in successful! Method: ${result.method || 'geo'}`, 'success');
            updateGeoStatus(employeeId, 'verified');
            updateRealTimeStatus(employeeId);
        }
    } catch (error) {
        showAlert(`❌ Geo punch-in failed: ${error.message}`, 'warning');
        console.error('Geo punch-in error:', error);
    }
};

window.enhancedGeoPunchOut = async function(employeeId) {
    try {
        showAlert('📍 Initiating geo-location punch-out...', 'info');
        const result = await window.geoManager.performGeoPunch(employeeId, 'out');
        
        if (result.success) {
            if (result.data && result.data.time_out) {
                document.getElementById(`time_out_${employeeId}`).value = result.data.time_out.substring(0, 5);
            }
            const duration = result.data ? result.data.duration : '';
            showAlert(`✅ Geo punch-out successful! ${duration ? 'Duration: ' + duration : ''}`, 'success');
        }
    } catch (error) {
        showAlert(`❌ Geo punch-out failed: ${error.message}`, 'warning');
        console.error('Geo punch-out error:', error);
    }
};

// Test function for the test page
window.testGeolocationWithFallback = async function() {
    try {
        const support = await window.geoManager.checkGeolocationSupport();
        console.log('Geolocation support:', support);
        
        if (!support.supported) {
            return { success: false, message: support.error };
        }
        
        if (support.permission === 'denied') {
            // Use mock location for testing
            const mockPos = window.geoManager.getMockLocation();
            return { 
                success: true, 
                message: `Using mock location for testing: ${mockPos.latitude.toFixed(4)}, ${mockPos.longitude.toFixed(4)}`,
                position: mockPos,
                isMock: true
            };
        }
        
        const result = await window.geoManager.verifyOfficeLocation();
        return result;
    } catch (error) {
        return { success: false, message: error.message, error: error };
    }
};
