<?php
session_start();
if (!isset($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: login.php');
    exit;
}

include 'db.php';

// Get ID from POST (AJAX) or GET (direct link)
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id > 0) {
    try {
        // Get expense details first
        $stmt = $conn->prepare("SELECT description, note, amount FROM expenses WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $expense = $result->fetch_assoc();
            $description = $expense['description'] ?? $expense['note'] ?? 'Expense';
            
            // Delete expense
            $deleteStmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
            $deleteStmt->bind_param("i", $id);
            
            if ($deleteStmt->execute()) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => "Expense '{$description}' deleted successfully"
                    ]);
                } else {
                    header("Location: expense_history.php?success=" . urlencode('Expense deleted successfully'));
                }
            } else {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to delete expense: ' . $conn->error
                    ]);
                } else {
                    header("Location: expense_history.php?error=" . urlencode('Failed to delete expense'));
                }
            }
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Expense not found']);
            } else {
                header("Location: expense_history.php?error=" . urlencode('Expense not found'));
            }
        }
    } catch (Exception $e) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } else {
            header("Location: expense_history.php?error=" . urlencode('Error occurred while deleting expense'));
        }
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Valid expense ID is required']);
    } else {
        header("Location: expense_history.php?error=" . urlencode('Expense ID is required'));
    }
}

exit;
