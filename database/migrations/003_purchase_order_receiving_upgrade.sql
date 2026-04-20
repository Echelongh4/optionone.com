USE pos_system;

ALTER TABLE purchase_orders
    MODIFY status ENUM('draft', 'ordered', 'partial_received', 'received', 'cancelled') NOT NULL DEFAULT 'draft';

ALTER TABLE purchase_order_items
    ADD COLUMN IF NOT EXISTS received_quantity DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity,
    ADD COLUMN IF NOT EXISTS received_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total,
    ADD COLUMN IF NOT EXISTS last_received_at DATETIME NULL AFTER received_total;

UPDATE purchase_order_items poi
INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
SET poi.received_quantity = CASE WHEN poi.received_quantity = 0 THEN poi.quantity ELSE poi.received_quantity END,
    poi.received_total = CASE WHEN poi.received_total = 0 THEN poi.total ELSE poi.received_total END,
    poi.last_received_at = COALESCE(poi.last_received_at, po.received_at, poi.updated_at)
WHERE po.status = 'received';

UPDATE purchase_orders po
INNER JOIN (
    SELECT purchase_order_id,
           COALESCE(SUM(quantity), 0) AS ordered_units,
           COALESCE(SUM(received_quantity), 0) AS received_units
    FROM purchase_order_items
    GROUP BY purchase_order_id
) summary ON summary.purchase_order_id = po.id
SET po.status = CASE
                    WHEN po.status = 'cancelled' THEN po.status
                    WHEN summary.received_units >= summary.ordered_units AND summary.ordered_units > 0 THEN 'received'
                    WHEN summary.received_units > 0 AND summary.received_units < summary.ordered_units THEN 'partial_received'
                    WHEN po.status = 'received' AND summary.received_units = 0 THEN 'ordered'
                    ELSE po.status
                END,
    po.received_at = CASE
                        WHEN summary.received_units >= summary.ordered_units AND summary.ordered_units > 0 THEN COALESCE(po.received_at, NOW())
                        ELSE po.received_at
                     END,
    po.updated_at = NOW();