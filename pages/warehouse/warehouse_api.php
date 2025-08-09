<?php
/**
 * Warehouse Management API
 * Handles warehouse operations, stock movements, and logistics
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../../db.php';

// Get the action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'warehouse_stats':
        getWarehouseStats($conn);
        break;
        
    case 'stock_levels':
        getStockLevels($conn);
        break;
        
    case 'recent_activities':
        getRecentActivities($conn);
        break;
        
    case 'goods_receipt_details':
        getGoodsReceiptDetails($conn);
        break;
        
    case 'picking_list_details':
        getPickingListDetails($conn);
        break;
        
    case 'warehouse_utilization':
        getWarehouseUtilization($conn);
        break;
        
    case 'stock_movement_report':
        getStockMovementReport($conn);
        break;
        
    case 'low_stock_alerts':
        getLowStockAlerts($conn);
        break;
        
    case 'complete_picking':
        completePickingList($conn);
        break;
        
    case 'update_receipt_status':
        updateReceiptStatus($conn);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getWarehouseStats($conn) {
    try {
        $stats = [
            'warehouses' => [
                'total' => 0,
                'active' => 0,
                'maintenance' => 0,
                'total_capacity' => 0
            ],
            'operations' => [
                'pending_receipts' => 0,
                'active_picks' => 0,
                'completed_today' => 0,
                'movements_today' => 0
            ],
            'inventory' => [
                'total_items' => 0,
                'total_value' => 0,
                'low_stock_items' => 0,
                'out_of_stock' => 0
            ]
        ];
        
        // Warehouse statistics
        $warehouse_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                SUM(capacity) as total_capacity
            FROM warehouses
        ";
        
        $result = $conn->query($warehouse_query);
        if ($result && $row = $result->fetch_assoc()) {
            $stats['warehouses'] = $row;
        }
        
        // Operations statistics
        $ops_query = "
            SELECT 
                (SELECT COUNT(*) FROM goods_receipts WHERE status = 'pending') as pending_receipts,
                (SELECT COUNT(*) FROM picking_lists WHERE status IN ('pending', 'in_progress')) as active_picks,
                (SELECT COUNT(*) FROM goods_receipts WHERE DATE(received_date) = CURDATE() AND status = 'completed') as completed_today,
                (SELECT COUNT(*) FROM stock_movements WHERE DATE(movement_date) = CURDATE()) as movements_today
        ";
        
        $result = $conn->query($ops_query);
        if ($result && $row = $result->fetch_assoc()) {
            $stats['operations'] = $row;
        }
        
        // Inventory statistics
        $inventory_query = "
            SELECT 
                COUNT(*) as total_items,
                SUM(item_price * COALESCE(stock, 0)) as total_value,
                SUM(CASE WHEN COALESCE(stock, 0) <= 10 AND COALESCE(stock, 0) > 0 THEN 1 ELSE 0 END) as low_stock_items,
                SUM(CASE WHEN COALESCE(stock, 0) = 0 THEN 1 ELSE 0 END) as out_of_stock
            FROM items
        ";
        
        $result = $conn->query($inventory_query);
        if ($result && $row = $result->fetch_assoc()) {
            $stats['inventory'] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getStockLevels($conn) {
    try {
        $warehouse_id = $_GET['warehouse_id'] ?? null;
        $category = $_GET['category'] ?? null;
        
        $where_conditions = ["1=1"];
        
        if ($category) {
            $where_conditions[] = "category = '" . mysqli_real_escape_string($conn, $category) . "'";
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT 
                id,
                item_name,
                category,
                stock,
                item_price,
                (stock * item_price) as stock_value,
                CASE 
                    WHEN stock = 0 THEN 'out_of_stock'
                    WHEN stock <= 10 THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status
            FROM items 
            WHERE $where_clause
            ORDER BY stock ASC, item_name ASC
        ";
        
        $result = $conn->query($query);
        $items = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $items]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getRecentActivities($conn) {
    try {
        $limit = $_GET['limit'] ?? 20;
        
        $query = "
            (SELECT 
                'goods_receipt' as activity_type,
                gr.receipt_number as reference,
                CONCAT('Goods received from ', s.supplier_name) as description,
                gr.received_date as activity_date,
                w.name as warehouse,
                gr.received_by as user
            FROM goods_receipts gr
            LEFT JOIN warehouses w ON gr.warehouse_id = w.id
            LEFT JOIN suppliers s ON gr.supplier_id = s.id
            WHERE gr.received_date >= DATE_SUB(NOW(), INTERVAL 7 DAY))
            
            UNION ALL
            
            (SELECT 
                'picking_list' as activity_type,
                pl.pick_number as reference,
                CONCAT('Picking list created for ', pl.order_type) as description,
                pl.created_date as activity_date,
                w.name as warehouse,
                pl.assigned_to as user
            FROM picking_lists pl
            LEFT JOIN warehouses w ON pl.warehouse_id = w.id
            WHERE pl.created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY))
            
            ORDER BY activity_date DESC
            LIMIT $limit
        ";
        
        $result = $conn->query($query);
        $activities = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $activities]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getGoodsReceiptDetails($conn) {
    try {
        $receipt_id = $_GET['receipt_id'] ?? null;
        
        if (!$receipt_id) {
            echo json_encode(['success' => false, 'message' => 'Receipt ID required']);
            return;
        }
        
        // Get receipt header
        $receipt_query = "
            SELECT gr.*, w.name as warehouse_name, s.supplier_name, s.contact_person, s.phone
            FROM goods_receipts gr
            LEFT JOIN warehouses w ON gr.warehouse_id = w.id
            LEFT JOIN suppliers s ON gr.supplier_id = s.id
            WHERE gr.id = $receipt_id
        ";
        
        $receipt_result = $conn->query($receipt_query);
        $receipt = $receipt_result ? $receipt_result->fetch_assoc() : null;
        
        if (!$receipt) {
            echo json_encode(['success' => false, 'message' => 'Receipt not found']);
            return;
        }
        
        // Get receipt items
        $items_query = "
            SELECT gri.*, i.item_name, i.item_price
            FROM goods_receipt_items gri
            LEFT JOIN items i ON gri.item_id = i.id
            WHERE gri.receipt_id = $receipt_id
        ";
        
        $items_result = $conn->query($items_query);
        $items = [];
        
        if ($items_result) {
            while ($row = $items_result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        
        $receipt['items'] = $items;
        
        echo json_encode(['success' => true, 'data' => $receipt]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getPickingListDetails($conn) {
    try {
        $pick_id = $_GET['pick_id'] ?? null;
        
        if (!$pick_id) {
            echo json_encode(['success' => false, 'message' => 'Picking list ID required']);
            return;
        }
        
        // Get picking list header
        $pick_query = "
            SELECT pl.*, w.name as warehouse_name
            FROM picking_lists pl
            LEFT JOIN warehouses w ON pl.warehouse_id = w.id
            WHERE pl.id = $pick_id
        ";
        
        $pick_result = $conn->query($pick_query);
        $pick_list = $pick_result ? $pick_result->fetch_assoc() : null;
        
        if (!$pick_list) {
            echo json_encode(['success' => false, 'message' => 'Picking list not found']);
            return;
        }
        
        // Get picking list items
        $items_query = "
            SELECT pli.*, i.item_name, i.stock, i.item_price
            FROM picking_list_items pli
            LEFT JOIN items i ON pli.item_id = i.id
            WHERE pli.pick_id = $pick_id
        ";
        
        $items_result = $conn->query($items_query);
        $items = [];
        
        if ($items_result) {
            while ($row = $items_result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        
        $pick_list['items'] = $items;
        
        echo json_encode(['success' => true, 'data' => $pick_list]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getWarehouseUtilization($conn) {
    try {
        $query = "
            SELECT 
                w.id,
                w.name,
                w.capacity,
                w.location,
                COALESCE(stock_data.total_items, 0) as items_stored,
                COALESCE(stock_data.total_value, 0) as inventory_value,
                CASE 
                    WHEN w.capacity > 0 THEN 
                        ROUND((COALESCE(stock_data.total_items, 0) / w.capacity) * 100, 2)
                    ELSE 0 
                END as utilization_percentage
            FROM warehouses w
            LEFT JOIN (
                SELECT 
                    1 as warehouse_id,
                    COUNT(*) as total_items,
                    SUM(item_price * COALESCE(stock, 0)) as total_value
                FROM items
            ) stock_data ON w.id = stock_data.warehouse_id
            WHERE w.status = 'active'
            ORDER BY utilization_percentage DESC
        ";
        
        $result = $conn->query($query);
        $utilization = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $utilization[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $utilization]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getStockMovementReport($conn) {
    try {
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $warehouse_id = $_GET['warehouse_id'] ?? null;
        
        $where_conditions = [
            "DATE(movement_date) BETWEEN '$start_date' AND '$end_date'"
        ];
        
        if ($warehouse_id) {
            $where_conditions[] = "warehouse_id = " . intval($warehouse_id);
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT 
                sm.movement_date,
                sm.movement_type,
                sm.quantity,
                sm.reference_number,
                sm.reference_type,
                sm.notes,
                i.item_name,
                w.name as warehouse_name,
                sm.created_by
            FROM stock_movements sm
            LEFT JOIN items i ON sm.item_id = i.id
            LEFT JOIN warehouses w ON sm.warehouse_id = w.id
            WHERE $where_clause
            ORDER BY sm.movement_date DESC
            LIMIT 100
        ";
        
        $result = $conn->query($query);
        $movements = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $movements[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $movements]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getLowStockAlerts($conn) {
    try {
        $threshold = $_GET['threshold'] ?? 10;
        
        $query = "
            SELECT 
                id,
                item_name,
                category,
                stock,
                item_price,
                CASE 
                    WHEN stock = 0 THEN 'critical'
                    WHEN stock <= 5 THEN 'high'
                    WHEN stock <= $threshold THEN 'medium'
                    ELSE 'low'
                END as alert_level
            FROM items 
            WHERE stock <= $threshold
            ORDER BY stock ASC, item_name ASC
        ";
        
        $result = $conn->query($query);
        $alerts = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $alerts[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $alerts]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function completePickingList($conn) {
    try {
        $pick_id = $_POST['pick_id'] ?? null;
        $items = $_POST['items'] ?? [];
        
        if (!$pick_id) {
            echo json_encode(['success' => false, 'message' => 'Picking list ID required']);
            return;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Update picking list status
        $update_pick = "UPDATE picking_lists SET status = 'completed', completed_date = NOW() WHERE id = $pick_id";
        $conn->query($update_pick);
        
        // Process each item
        foreach ($items as $item) {
            $item_id = intval($item['item_id']);
            $picked_qty = intval($item['picked_quantity']);
            
            // Update picking list item
            $update_item = "UPDATE picking_list_items SET quantity_picked = $picked_qty, status = 'picked' 
                           WHERE pick_id = $pick_id AND item_id = $item_id";
            $conn->query($update_item);
            
            // Update item stock
            $update_stock = "UPDATE items SET stock = GREATEST(0, stock - $picked_qty) WHERE id = $item_id";
            $conn->query($update_stock);
            
            // Log stock movement
            $log_movement = "INSERT INTO stock_movements (warehouse_id, item_id, movement_type, quantity, 
                            reference_number, reference_type, created_by, movement_date) 
                            SELECT warehouse_id, $item_id, 'out', $picked_qty, pick_number, 'picking', 
                            '{$_SESSION['admin']}', NOW() FROM picking_lists WHERE id = $pick_id";
            $conn->query($log_movement);
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Picking list completed successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateReceiptStatus($conn) {
    try {
        $receipt_id = $_POST['receipt_id'] ?? null;
        $status = $_POST['status'] ?? null;
        
        if (!$receipt_id || !$status) {
            echo json_encode(['success' => false, 'message' => 'Receipt ID and status required']);
            return;
        }
        
        $allowed_statuses = ['pending', 'completed', 'discrepancy'];
        if (!in_array($status, $allowed_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            return;
        }
        
        $query = "UPDATE goods_receipts SET status = '$status' WHERE id = $receipt_id";
        
        if ($conn->query($query)) {
            echo json_encode(['success' => true, 'message' => 'Receipt status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Close database connection
$conn->close();
?>
