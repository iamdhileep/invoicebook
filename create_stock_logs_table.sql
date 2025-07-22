-- Create stock_logs table for tracking stock changes
CREATE TABLE IF NOT EXISTS `stock_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `old_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `change_amount` int(11) NOT NULL,
  `updated_by` varchar(100) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key constraint if items table exists
-- ALTER TABLE `stock_logs` ADD CONSTRAINT `fk_stock_logs_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;