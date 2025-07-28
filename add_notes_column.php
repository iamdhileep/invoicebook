<?php
include 'db.php';
$result = $conn->query('ALTER TABLE attendance ADD COLUMN notes TEXT');
echo $result ? 'Notes column added successfully' : 'Error: ' . $conn->error;
?>
