<?php
// Holiday System Setup Script
// This script creates necessary tables and sets up automatic holiday updates

include 'db.php';

echo "<h2>Holiday System Setup</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 900px; margin: 20px;'>";

try {
    // Create holidays table
    echo "<h3>Creating Holidays Table...</h3>";
    
    $holidaysTable = "
        CREATE TABLE IF NOT EXISTS holidays (
            holiday_id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL,
            holiday_name VARCHAR(255) NOT NULL,
            holiday_type ENUM('national', 'religious', 'cultural', 'banking', 'social', 'harvest') NOT NULL,
            holiday_category ENUM('gazetted', 'restricted', 'observance', 'state') NOT NULL,
            region VARCHAR(100) DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_holiday (holiday_date, holiday_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    if ($conn->query($holidaysTable)) {
        echo "<p style='color: green;'>✅ Holidays table created successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating holidays table: " . $conn->error . "</p>";
    }
    
    // Create leaves table
    echo "<h3>Creating Leaves Table...</h3>";
    
    $leavesTable = "
        CREATE TABLE IF NOT EXISTS leaves (
            leave_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type ENUM('casual', 'sick', 'earned', 'maternity', 'paternity', 'emergency', 'other') NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days_count INT NOT NULL,
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_by INT DEFAULT NULL,
            approved_date TIMESTAMP NULL,
            comments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
            INDEX idx_employee_dates (employee_id, start_date, end_date),
            INDEX idx_status (status),
            INDEX idx_leave_type (leave_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    if ($conn->query($leavesTable)) {
        echo "<p style='color: green;'>✅ Leaves table created successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating leaves table: " . $conn->error . "</p>";
    }
    
    // Function to populate holidays for a given year
    function populateHolidays($conn, $year) {
        // Clear existing holidays for the year
        $deleteQuery = "DELETE FROM holidays WHERE YEAR(holiday_date) = $year";
        $conn->query($deleteQuery);
        
        $holidays = [];
        
        // Indian National Holidays
        $nationalHolidays = [
            "$year-01-26" => ['Republic Day', 'national', 'gazetted'],
            "$year-08-15" => ['Independence Day', 'national', 'gazetted'],
            "$year-10-02" => ['Gandhi Jayanti', 'national', 'gazetted'],
        ];
        
        // Religious Holidays (approximate dates)
        $religiousHolidays = [
            "$year-01-14" => ['Makar Sankranti', 'religious', 'gazetted'],
            "$year-03-08" => ['Holi', 'religious', 'gazetted'],
            "$year-03-29" => ['Good Friday', 'religious', 'gazetted'],
            "$year-04-17" => ['Ram Navami', 'religious', 'gazetted'],
            "$year-08-19" => ['Janmashtami', 'religious', 'gazetted'],
            "$year-09-07" => ['Ganesh Chaturthi', 'religious', 'gazetted'],
            "$year-10-12" => ['Dussehra', 'religious', 'gazetted'],
            "$year-11-01" => ['Diwali', 'religious', 'gazetted'],
            "$year-11-15" => ['Guru Nanak Jayanti', 'religious', 'gazetted'],
            "$year-04-11" => ['Eid ul-Fitr', 'religious', 'gazetted'],
            "$year-06-17" => ['Eid ul-Adha (Bakrid)', 'religious', 'gazetted'],
            "$year-07-17" => ['Muharram', 'religious', 'gazetted'],
            "$year-09-16" => ['Milad un-Nabi', 'religious', 'gazetted'],
            "$year-12-25" => ['Christmas', 'religious', 'gazetted'],
        ];
        
        // Tamil Nadu Specific Holidays
        $tamilNaduHolidays = [
            "$year-04-14" => ['Tamil New Year (Puthandu)', 'cultural', 'state', 'Tamil Nadu'],
            "$year-01-15" => ['Thai Pusam', 'religious', 'state', 'Tamil Nadu'],
            "$year-01-14" => ['Pongal (Bhogi)', 'harvest', 'state', 'Tamil Nadu'],
            "$year-01-15" => ['Thai Pongal', 'harvest', 'state', 'Tamil Nadu'],
            "$year-01-16" => ['Mattu Pongal', 'harvest', 'state', 'Tamil Nadu'],
            "$year-01-17" => ['Kaanum Pongal', 'harvest', 'state', 'Tamil Nadu'],
            "$year-08-31" => ['Vinayaka Chaturthi', 'religious', 'state', 'Tamil Nadu'],
            "$year-09-17" => ['Navarathri', 'religious', 'state', 'Tamil Nadu'],
        ];
        
        // Social Observances
        $socialHolidays = [
            "$year-05-01" => ['May Day (Labour Day)', 'social', 'gazetted'],
            "$year-09-05" => ['Teachers Day', 'social', 'observance'],
            "$year-11-14" => ['Childrens Day', 'social', 'observance'],
            "$year-04-01" => ['Bank Holiday', 'banking', 'restricted'],
        ];
        
        // Combine all holidays
        $allHolidays = array_merge($nationalHolidays, $religiousHolidays, $tamilNaduHolidays, $socialHolidays);
        
        $insertCount = 0;
        foreach ($allHolidays as $date => $holidayInfo) {
            $name = $holidayInfo[0];
            $type = $holidayInfo[1];
            $category = $holidayInfo[2];
            $region = isset($holidayInfo[3]) ? $holidayInfo[3] : NULL;
            
            $stmt = $conn->prepare("
                INSERT IGNORE INTO holidays (holiday_date, holiday_name, holiday_type, holiday_category, region) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if ($stmt->bind_param('sssss', $date, $name, $type, $category, $region) && $stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $insertCount++;
                }
            }
        }
        
        return $insertCount;
    }
    
    // Populate holidays for current year and next year
    $currentYear = date('Y');
    $nextYear = $currentYear + 1;
    
    echo "<h3>Populating Holidays...</h3>";
    
    $currentYearCount = populateHolidays($conn, $currentYear);
    echo "<p style='color: green;'>✅ Added $currentYearCount holidays for $currentYear</p>";
    
    $nextYearCount = populateHolidays($conn, $nextYear);
    echo "<p style='color: green;'>✅ Added $nextYearCount holidays for $nextYear</p>";
    
    // Create automatic holiday update procedure
    echo "<h3>Creating Holiday Update Procedure...</h3>";
    
    $procedure = "
        DROP PROCEDURE IF EXISTS UpdateHolidaysForYear;
        
        DELIMITER $$
        CREATE PROCEDURE UpdateHolidaysForYear(IN target_year INT)
        BEGIN
            DECLARE done INT DEFAULT FALSE;
            DECLARE holiday_date DATE;
            DECLARE holiday_name VARCHAR(255);
            DECLARE holiday_type VARCHAR(50);
            DECLARE holiday_category VARCHAR(50);
            DECLARE holiday_region VARCHAR(100);
            
            -- Delete existing holidays for the year
            DELETE FROM holidays WHERE YEAR(holiday_date) = target_year;
            
            -- National Holidays
            INSERT IGNORE INTO holidays (holiday_date, holiday_name, holiday_type, holiday_category) VALUES
            (CONCAT(target_year, '-01-26'), 'Republic Day', 'national', 'gazetted'),
            (CONCAT(target_year, '-08-15'), 'Independence Day', 'national', 'gazetted'),
            (CONCAT(target_year, '-10-02'), 'Gandhi Jayanti', 'national', 'gazetted'),
            (CONCAT(target_year, '-12-25'), 'Christmas', 'religious', 'gazetted'),
            (CONCAT(target_year, '-05-01'), 'May Day (Labour Day)', 'social', 'gazetted');
            
            -- Tamil Nadu Specific
            INSERT IGNORE INTO holidays (holiday_date, holiday_name, holiday_type, holiday_category, region) VALUES
            (CONCAT(target_year, '-04-14'), 'Tamil New Year (Puthandu)', 'cultural', 'state', 'Tamil Nadu'),
            (CONCAT(target_year, '-01-14'), 'Pongal (Bhogi)', 'harvest', 'state', 'Tamil Nadu'),
            (CONCAT(target_year, '-01-15'), 'Thai Pongal', 'harvest', 'state', 'Tamil Nadu'),
            (CONCAT(target_year, '-01-16'), 'Mattu Pongal', 'harvest', 'state', 'Tamil Nadu'),
            (CONCAT(target_year, '-01-17'), 'Kaanum Pongal', 'harvest', 'state', 'Tamil Nadu');
            
        END$$
        DELIMITER ;
    ";
    
    if ($conn->multi_query($procedure)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        echo "<p style='color: green;'>✅ Holiday update procedure created successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Could not create procedure: " . $conn->error . "</p>";
    }
    
    // Create holiday management views
    echo "<h3>Creating Holiday Management Views...</h3>";
    
    $holidayView = "
        CREATE OR REPLACE VIEW holiday_calendar AS
        SELECT 
            h.holiday_id,
            h.holiday_date,
            h.holiday_name,
            h.holiday_type,
            h.holiday_category,
            h.region,
            DAYNAME(h.holiday_date) as day_name,
            MONTHNAME(h.holiday_date) as month_name,
            YEAR(h.holiday_date) as year,
            CASE 
                WHEN h.holiday_type = 'national' THEN 'National Holiday'
                WHEN h.holiday_type = 'religious' THEN 'Religious Festival'
                WHEN h.holiday_type = 'cultural' THEN 'Cultural Celebration'
                WHEN h.holiday_type = 'harvest' THEN 'Harvest Festival'
                WHEN h.holiday_type = 'social' THEN 'Social Observance'
                WHEN h.holiday_type = 'banking' THEN 'Banking Holiday'
            END as type_description,
            CASE 
                WHEN h.holiday_category = 'gazetted' THEN 'Government Holiday'
                WHEN h.holiday_category = 'restricted' THEN 'Restricted Holiday'
                WHEN h.holiday_category = 'observance' THEN 'Observance Day'
                WHEN h.holiday_category = 'state' THEN 'State Holiday'
            END as category_description
        FROM holidays h
        WHERE h.is_active = 1
        ORDER BY h.holiday_date
    ";
    
    if ($conn->query($holidayView)) {
        echo "<p style='color: green;'>✅ Holiday calendar view created successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating holiday view: " . $conn->error . "</p>";
    }
    
    // Show current holidays
    echo "<h3>Current Holidays in Database:</h3>";
    
    $holidayList = $conn->query("
        SELECT holiday_date, holiday_name, holiday_type, holiday_category, region 
        FROM holidays 
        WHERE YEAR(holiday_date) = $currentYear 
        ORDER BY holiday_date
    ");
    
    if ($holidayList && $holidayList->num_rows > 0) {
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Date</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Holiday Name</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Type</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Category</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Region</th>";
        echo "</tr>";
        
        while ($holiday = $holidayList->fetch_assoc()) {
            $typeColor = '';
            switch($holiday['holiday_type']) {
                case 'national': $typeColor = 'style="background: #007bff; color: white;"'; break;
                case 'religious': $typeColor = 'style="background: #28a745; color: white;"'; break;
                case 'cultural': $typeColor = 'style="background: #ffc107; color: black;"'; break;
                case 'harvest': $typeColor = 'style="background: #fd7e14; color: white;"'; break;
                case 'social': $typeColor = 'style="background: #6f42c1; color: white;"'; break;
            }
            
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . date('j M Y (l)', strtotime($holiday['holiday_date'])) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>" . $holiday['holiday_name'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;' $typeColor>" . ucfirst($holiday['holiday_type']) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ucfirst($holiday['holiday_category']) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ($holiday['region'] ?: 'All India') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Instructions for annual updates
    echo "<h3>📅 Annual Holiday Update Instructions</h3>";
    echo "<div class='alert alert-info' style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px;'>";
    echo "<h5>🔄 Automatic Updates:</h5>";
    echo "<p>The system is now configured to automatically manage holidays. To update holidays for a new year:</p>";
    echo "<ol>";
    echo "<li><strong>Method 1 - SQL Procedure:</strong><br>";
    echo "<code>CALL UpdateHolidaysForYear(" . ($currentYear + 2) . ");</code></li>";
    echo "<li><strong>Method 2 - Run this script again</strong> (it will update current and next year)</li>";
    echo "<li><strong>Method 3 - Use Holiday Manager</strong> in the attendance calendar</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div class='alert alert-warning' style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h5>⚠️ Important Notes:</h5>";
    echo "<ul>";
    echo "<li><strong>Variable Dates:</strong> Some religious holidays (Eid, Diwali, etc.) have approximate dates and may need manual adjustment based on lunar calendar</li>";
    echo "<li><strong>Regional Holidays:</strong> Additional regional holidays can be added through the Holiday Manager</li>";
    echo "<li><strong>Government Updates:</strong> Check official government notifications for any new holidays or date changes</li>";
    echo "<li><strong>Weekend Adjustments:</strong> When holidays fall on weekends, substitutes may be declared by government</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='color: green; padding: 15px; border: 1px solid green; background: #f0fff0; border-radius: 5px; margin-top: 20px;'>";
    echo "<strong>🎉 Holiday System Setup Complete!</strong><br>";
    echo "Your attendance calendar now includes:<br>";
    echo "• " . ($currentYearCount + $nextYearCount) . " holidays for $currentYear-$nextYear<br>";
    echo "• Indian National Holidays<br>";
    echo "• Tamil Nadu Regional Holidays<br>";
    echo "• Religious Festivals<br>";
    echo "• Social Observances<br>";
    echo "• Automatic yearly updates<br>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px; padding: 10px; background: #f9f9f9; border-radius: 5px;'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Go back to your <a href='attendance-calendar.php' target='_blank'>Advanced Attendance Calendar</a><br>";
    echo "2. Explore the new holiday features and analytics<br>";
    echo "3. Use the Holiday Manager to add custom holidays<br>";
    echo "4. Set up the Leave Management system for employees<br>";
    echo "5. You can delete this file (setup_holidays_system.php) after running it once";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 15px; border: 1px solid red; background: #fff0f0; border-radius: 5px;'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

$conn->close();
?> 