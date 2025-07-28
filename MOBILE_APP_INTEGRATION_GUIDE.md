# 📱 Mobile App Integration Guide for Modern Attendance System

## Overview
This guide provides comprehensive instructions for integrating mobile applications with the enhanced attendance management system. The system supports multiple mobile platforms and various authentication methods.

## 🚀 Quick Start

### Prerequisites
- PHP 7.4+ server with the attendance system installed
- Mobile device with GPS and camera capabilities
- API endpoints configured and accessible

### Base API URL
```
https://your-domain.com/billbook/
```

## 📊 Available API Endpoints

### 1. Authentication Endpoints

#### Login
```http
POST /authenticate.php
Content-Type: application/json

{
    "action": "mobile_login",
    "employee_id": "EMP001",
    "password": "password123",
    "device_info": {
        "device_id": "unique_device_id",
        "device_name": "iPhone 12",
        "os_version": "iOS 15.0",
        "app_version": "1.0.0"
    }
}
```

**Response:**
```json
{
    "success": true,
    "token": "jwt_token_here",
    "employee": {
        "id": "EMP001",
        "name": "John Doe",
        "department": "IT",
        "designation": "Software Engineer"
    },
    "permissions": ["punch_attendance", "view_leave_balance", "request_leave"]
}
```

#### Device Registration
```http
POST /realtime_attendance_api.php
Content-Type: application/json

{
    "action": "register_device",
    "employee_id": "EMP001",
    "device_id": "unique_device_id",
    "device_name": "John's iPhone",
    "platform": "iOS",
    "push_token": "fcm_token_for_notifications"
}
```

### 2. Attendance Management

#### Punch In/Out
```http
POST /realtime_attendance_api.php
Content-Type: application/json

{
    "action": "punch",
    "employee_id": "EMP001",
    "punch_type": "in", // or "out"
    "location": {
        "latitude": 12.9716,
        "longitude": 77.5946,
        "accuracy": 5
    },
    "method": "mobile_app",
    "biometric_data": "base64_encoded_fingerprint", // optional
    "photo": "base64_encoded_selfie", // optional
    "notes": "Working from office today"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Punch in successful",
    "punch_time": "2024-01-15 09:30:00",
    "location_verified": true,
    "next_action": "punch_out",
    "work_hours_today": "0:30"
}
```

#### Get Attendance Status
```http
GET /realtime_attendance_api.php?action=get_status&employee_id=EMP001
```

**Response:**
```json
{
    "success": true,
    "current_status": "Present",
    "last_punch": {
        "type": "in",
        "time": "2024-01-15 09:30:00",
        "location": "Office"
    },
    "today_summary": {
        "total_hours": "8:30",
        "break_time": "1:00",
        "overtime": "0:30"
    }
}
```

### 3. Leave Management

#### Submit Leave Request
```http
POST /smart_leave_api.php
Content-Type: application/json

{
    "action": "submit_leave",
    "employee_id": "EMP001",
    "leave_type": "sick",
    "start_date": "2024-01-20",
    "end_date": "2024-01-22",
    "reason": "Medical appointment",
    "attachment": "base64_encoded_medical_certificate", // optional
    "emergency_contact": "+91-9876543210"
}
```

#### Get Leave Balance
```http
GET /smart_leave_api.php?action=get_balance&employee_id=EMP001
```

**Response:**
```json
{
    "success": true,
    "balances": {
        "sick_leave": {"available": 8, "used": 4, "total": 12},
        "casual_leave": {"available": 10, "used": 2, "total": 12},
        "earned_leave": {"available": 18, "used": 12, "total": 30}
    }
}
```

### 4. Short Leave/Notes

#### Submit Short Leave
```http
POST /smart_leave_api.php
Content-Type: application/json

{
    "action": "submit_short_leave",
    "employee_id": "EMP001",
    "date": "2024-01-15",
    "start_time": "14:00",
    "end_time": "15:30",
    "reason": "Personal work",
    "type": "personal"
}
```

## 📱 Mobile SDK Integration

### Android Integration

#### Add Dependencies (build.gradle)
```gradle
dependencies {
    implementation 'com.squareup.retrofit2:retrofit:2.9.0'
    implementation 'com.squareup.retrofit2:converter-gson:2.9.0'
    implementation 'com.google.android.gms:play-services-location:21.0.1'
    implementation 'androidx.biometric:biometric:1.1.0'
}
```

#### Basic API Client (Java/Kotlin)
```java
public class AttendanceApiClient {
    private static final String BASE_URL = "https://your-domain.com/billbook/";
    private Retrofit retrofit;
    
    public AttendanceApiClient() {
        retrofit = new Retrofit.Builder()
            .baseUrl(BASE_URL)
            .addConverterFactory(GsonConverterFactory.create())
            .build();
    }
    
    public void punchIn(String employeeId, Location location, 
                       String biometricData, ApiCallback callback) {
        AttendanceService service = retrofit.create(AttendanceService.class);
        
        PunchRequest request = new PunchRequest();
        request.action = "punch";
        request.employee_id = employeeId;
        request.punch_type = "in";
        request.location = new LocationData(location.getLatitude(), 
                                          location.getLongitude(), 
                                          location.getAccuracy());
        request.method = "mobile_app";
        request.biometric_data = biometricData;
        
        Call<ApiResponse> call = service.punch(request);
        call.enqueue(new Callback<ApiResponse>() {
            @Override
            public void onResponse(Call<ApiResponse> call, Response<ApiResponse> response) {
                if (response.isSuccessful()) {
                    callback.onSuccess(response.body());
                } else {
                    callback.onError("Network error");
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse> call, Throwable t) {
                callback.onError(t.getMessage());
            }
        });
    }
}
```

### iOS Integration

#### Basic API Client (Swift)
```swift
import Foundation
import CoreLocation

class AttendanceAPIClient {
    let baseURL = "https://your-domain.com/billbook/"
    
    func punchIn(employeeId: String, location: CLLocation, 
                biometricData: String?, completion: @escaping (Result<PunchResponse, Error>) -> Void) {
        
        let url = URL(string: "\(baseURL)realtime_attendance_api.php")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        
        let requestBody: [String: Any] = [
            "action": "punch",
            "employee_id": employeeId,
            "punch_type": "in",
            "location": [
                "latitude": location.coordinate.latitude,
                "longitude": location.coordinate.longitude,
                "accuracy": location.horizontalAccuracy
            ],
            "method": "mobile_app",
            "biometric_data": biometricData ?? ""
        ]
        
        do {
            request.httpBody = try JSONSerialization.data(withJSONObject: requestBody)
        } catch {
            completion(.failure(error))
            return
        }
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            if let error = error {
                completion(.failure(error))
                return
            }
            
            guard let data = data else {
                completion(.failure(NSError(domain: "NoData", code: 0, userInfo: nil)))
                return
            }
            
            do {
                let punchResponse = try JSONDecoder().decode(PunchResponse.self, from: data)
                completion(.success(punchResponse))
            } catch {
                completion(.failure(error))
            }
        }.resume()
    }
}
```

## 🔐 Security Implementation

### JWT Token Management
```javascript
// Store JWT token securely
const storeToken = (token) => {
    // Use secure storage (Keychain on iOS, Keystore on Android)
    SecureStorage.setItem('attendance_token', token);
};

// Add token to API requests
const makeAuthenticatedRequest = (url, data) => {
    const token = SecureStorage.getItem('attendance_token');
    
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(data)
    });
};
```

### Biometric Authentication
```java
// Android biometric implementation
public class BiometricAuthManager {
    public void authenticateUser(BiometricPrompt.AuthenticationCallback callback) {
        BiometricPrompt biometricPrompt = new BiometricPrompt(this, 
            ContextCompat.getMainExecutor(this), callback);
        
        BiometricPrompt.PromptInfo promptInfo = new BiometricPrompt.PromptInfo.Builder()
            .setTitle("Biometric Authentication")
            .setSubtitle("Use your fingerprint to punch in/out")
            .setNegativeButtonText("Cancel")
            .build();
            
        biometricPrompt.authenticate(promptInfo);
    }
}
```

## 📍 Geolocation Features

### Geo-fencing Implementation
```javascript
// Check if user is within office geo-fence
const checkGeofence = (userLat, userLng, officeLat, officeLng, radius) => {
    const distance = calculateDistance(userLat, userLng, officeLat, officeLng);
    return distance <= radius;
};

const calculateDistance = (lat1, lon1, lat2, lon2) => {
    const R = 6371; // Earth's radius in kilometers
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c * 1000; // Distance in meters
};
```

## 🔔 Push Notifications

### Firebase Setup
```json
{
    "notification_types": {
        "punch_reminder": "Reminder to punch in/out",
        "leave_approved": "Your leave request has been approved",
        "leave_rejected": "Your leave request has been rejected",
        "policy_update": "Attendance policy has been updated",
        "overtime_alert": "You have worked overtime today"
    }
}
```

### Handle Notifications (React Native)
```javascript
import messaging from '@react-native-firebase/messaging';

// Handle background messages
messaging().setBackgroundMessageHandler(async remoteMessage => {
    console.log('Message handled in the background!', remoteMessage);
});

// Handle foreground messages
messaging().onMessage(async remoteMessage => {
    Alert.alert('Attendance Notification', remoteMessage.notification.body);
});
```

## 🧪 Testing

### API Testing with Postman
1. Import the provided Postman collection
2. Set environment variables:
   - `base_url`: Your server URL
   - `employee_id`: Test employee ID
   - `auth_token`: JWT token after login

### Unit Testing
```javascript
// Jest test example
describe('Attendance API', () => {
    test('should punch in successfully', async () => {
        const mockLocation = { latitude: 12.9716, longitude: 77.5946, accuracy: 5 };
        const response = await AttendanceAPI.punchIn('EMP001', mockLocation);
        
        expect(response.success).toBe(true);
        expect(response.punch_time).toBeDefined();
    });
    
    test('should reject punch outside geofence', async () => {
        const outsideLocation = { latitude: 13.0000, longitude: 78.0000, accuracy: 5 };
        const response = await AttendanceAPI.punchIn('EMP001', outsideLocation);
        
        expect(response.success).toBe(false);
        expect(response.error).toContain('geofence');
    });
});
```

## 📊 Analytics Integration

### Track User Events
```javascript
// Google Analytics 4 integration
const trackPunchEvent = (action, location, method) => {
    gtag('event', 'punch_attendance', {
        'action': action,
        'location_verified': location.verified,
        'method': method,
        'custom_parameter_1': 'mobile_app'
    });
};

// Custom analytics
const sendCustomAnalytics = (event, data) => {
    fetch('/advanced_analytics_dashboard.php?action=track_event', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ event, data, timestamp: Date.now() })
    });
};
```

## 🔧 Troubleshooting

### Common Issues

1. **GPS Not Working**
   - Ensure location permissions are granted
   - Check GPS accuracy settings
   - Verify network connectivity

2. **Biometric Authentication Fails**
   - Verify device supports biometric authentication
   - Check if biometric templates are enrolled
   - Handle fallback authentication methods

3. **API Connection Issues**
   - Verify server URL and SSL certificate
   - Check network connectivity
   - Implement proper error handling and retry logic

### Debug Mode
```javascript
const DEBUG_MODE = __DEV__ || process.env.NODE_ENV === 'development';

const debugLog = (message, data = null) => {
    if (DEBUG_MODE) {
        console.log(`[AttendanceApp] ${message}`, data);
    }
};

// Enable API debugging
const apiConfig = {
    baseURL: BASE_URL,
    timeout: 10000,
    headers: {
        'X-Debug-Mode': DEBUG_MODE ? '1' : '0'
    }
};
```

## 📈 Performance Optimization

### API Caching
```javascript
const cache = new Map();
const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

const cachedApiCall = async (url, options = {}) => {
    const cacheKey = `${url}_${JSON.stringify(options)}`;
    const cached = cache.get(cacheKey);
    
    if (cached && (Date.now() - cached.timestamp) < CACHE_DURATION) {
        return cached.data;
    }
    
    const response = await fetch(url, options);
    const data = await response.json();
    
    cache.set(cacheKey, {
        data,
        timestamp: Date.now()
    });
    
    return data;
};
```

### Offline Support
```javascript
const offlineQueue = [];

const queueOfflineAction = (action, data) => {
    offlineQueue.push({
        action,
        data,
        timestamp: Date.now(),
        id: generateUniqueId()
    });
    
    // Store in local storage
    AsyncStorage.setItem('offline_queue', JSON.stringify(offlineQueue));
};

const syncOfflineActions = async () => {
    const queue = JSON.parse(await AsyncStorage.getItem('offline_queue') || '[]');
    
    for (const item of queue) {
        try {
            await processOfflineAction(item);
            // Remove from queue on success
        } catch (error) {
            console.error('Failed to sync offline action:', error);
        }
    }
};
```

## 🚀 Deployment

### Production Checklist
- [ ] Configure SSL certificate
- [ ] Set up proper error logging
- [ ] Implement rate limiting
- [ ] Configure push notification certificates
- [ ] Set up monitoring and alerts
- [ ] Test on multiple devices and OS versions
- [ ] Implement proper backup and recovery procedures

### Environment Configuration
```javascript
const config = {
    development: {
        apiUrl: 'http://localhost/billbook/',
        debug: true,
        pushNotifications: false
    },
    staging: {
        apiUrl: 'https://staging.company.com/billbook/',
        debug: true,
        pushNotifications: true
    },
    production: {
        apiUrl: 'https://attendance.company.com/billbook/',
        debug: false,
        pushNotifications: true
    }
};
```

## 📞 Support

For technical support and integration assistance:
- 📧 Email: tech-support@company.com
- 📱 Phone: +91-XXXX-XXXX-XX
- 🌐 Documentation: https://docs.company.com/attendance-api
- 💬 Developer Chat: https://chat.company.com/dev-support

---

*This guide covers the essential aspects of mobile app integration with the modern attendance management system. For specific implementation details or custom requirements, please contact the development team.*
