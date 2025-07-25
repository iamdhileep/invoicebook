<?php
session_start();
header('Content-Type: application/json');

// Face Recognition API Endpoint
// This is a template for integrating with real face recognition services

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST method required']);
    exit;
}

include 'db.php';

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$image_data = $input['image_data'] ?? '';
$action = $input['action'] ?? 'recognize';

if (empty($image_data)) {
    echo json_encode(['success' => false, 'message' => 'No image data provided']);
    exit;
}

try {
    switch ($action) {
        case 'recognize':
            $result = recognizeFace($image_data, $conn);
            break;
        case 'register':
            $employee_id = $input['employee_id'] ?? 0;
            $result = registerFace($image_data, $employee_id, $conn);
            break;
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function recognizeFace($image_data, $conn) {
    // For demo purposes, simulate face recognition
    // In production, integrate with AWS Rekognition, Azure Face API, or similar
    
    // Remove data URL prefix
    $image_data = preg_replace('/^data:image\/\w+;base64,/', '', $image_data);
    $image_binary = base64_decode($image_data);
    
    if (!$image_binary) {
        return ['success' => false, 'message' => 'Invalid image data'];
    }
    
    // Simulate processing time
    usleep(2000000); // 2 seconds
    
    // Simulate recognition (70% success rate for demo)
    if (rand(1, 100) <= 70) {
        // Get random employee for demo
        $result = $conn->query("SELECT * FROM employees ORDER BY RAND() LIMIT 1");
        $employee = $result->fetch_assoc();
        
        if ($employee) {
            // Check current attendance status
            $today = date('Y-m-d');
            $attendance_check = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $attendance_check->bind_param('is', $employee['employee_id'], $today);
            $attendance_check->execute();
            $attendance = $attendance_check->get_result()->fetch_assoc();
            
            // Determine action needed
            $action_needed = 'punch_in';
            if ($attendance && $attendance['time_in'] && !$attendance['time_out']) {
                $action_needed = 'punch_out';
            }
            
            return [
                'success' => true,
                'employee_id' => $employee['employee_id'],
                'employee_name' => $employee['name'],
                'employee_code' => $employee['employee_code'],
                'action' => $action_needed,
                'confidence' => rand(75, 98) / 100,
                'message' => 'Face recognized successfully'
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'Face not recognized in database',
        'confidence' => rand(20, 50) / 100
    ];
}

function registerFace($image_data, $employee_id, $conn) {
    // Register face for an employee
    // In production, this would store face encodings in the database
    
    if (!$employee_id) {
        return ['success' => false, 'message' => 'Employee ID required'];
    }
    
    // Verify employee exists
    $employee_check = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $employee_check->bind_param('i', $employee_id);
    $employee_check->execute();
    $employee = $employee_check->get_result()->fetch_assoc();
    
    if (!$employee) {
        return ['success' => false, 'message' => 'Employee not found'];
    }
    
    // Simulate face registration
    // In production, extract face encodings and store them
    
    // For demo, just mark that face is registered
    $update_stmt = $conn->prepare("UPDATE employees SET face_registered = 1 WHERE employee_id = ?");
    $update_stmt->bind_param('i', $employee_id);
    
    if ($update_stmt->execute()) {
        return [
            'success' => true,
            'message' => 'Face registered successfully for ' . $employee['name'],
            'employee_id' => $employee_id,
            'employee_name' => $employee['name']
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to register face'];
    }
}

// Example integration functions for different services:

/* 
// AWS Rekognition Integration Example
function recognizeFaceAWS($image_data) {
    require_once 'vendor/autoload.php';
    
    $rekognition = new Aws\Rekognition\RekognitionClient([
        'version' => 'latest',
        'region' => 'us-east-1',
        'credentials' => [
            'key' => 'YOUR_AWS_KEY',
            'secret' => 'YOUR_AWS_SECRET',
        ],
    ]);
    
    try {
        $result = $rekognition->searchFacesByImage([
            'CollectionId' => 'employee_faces',
            'Image' => ['Bytes' => base64_decode($image_data)],
            'MaxFaces' => 1,
            'FaceMatchThreshold' => 80,
        ]);
        
        if (!empty($result['FaceMatches'])) {
            $match = $result['FaceMatches'][0];
            $employee_id = $match['Face']['ExternalImageId'];
            $confidence = $match['Similarity'];
            
            return [
                'success' => true,
                'employee_id' => $employee_id,
                'confidence' => $confidence / 100
            ];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
    
    return ['success' => false, 'message' => 'No face match found'];
}

// Azure Face API Integration Example
function recognizeFaceAzure($image_data) {
    $endpoint = 'https://YOUR_REGION.api.cognitive.microsoft.com/face/v1.0';
    $subscription_key = 'YOUR_SUBSCRIPTION_KEY';
    
    // First, detect face
    $detect_url = $endpoint . '/detect';
    $detect_data = json_encode(['url' => $image_data]);
    
    $detect_response = makeAzureRequest($detect_url, $detect_data, $subscription_key);
    
    if (!empty($detect_response)) {
        $face_id = $detect_response[0]['faceId'];
        
        // Then identify face
        $identify_url = $endpoint . '/identify';
        $identify_data = json_encode([
            'personGroupId' => 'employees',
            'faceIds' => [$face_id],
            'maxNumOfCandidatesReturned' => 1,
            'confidenceThreshold' => 0.7
        ]);
        
        $identify_response = makeAzureRequest($identify_url, $identify_data, $subscription_key);
        
        if (!empty($identify_response[0]['candidates'])) {
            $candidate = $identify_response[0]['candidates'][0];
            return [
                'success' => true,
                'employee_id' => $candidate['personId'],
                'confidence' => $candidate['confidence']
            ];
        }
    }
    
    return ['success' => false, 'message' => 'No face match found'];
}

function makeAzureRequest($url, $data, $key) {
    $headers = [
        'Content-Type: application/json',
        'Ocp-Apim-Subscription-Key: ' . $key
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
*/

?>
