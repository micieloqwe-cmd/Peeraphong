<?php
// admin_dashboard.php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

require_once '../FilePHP/db.php'; // ← ปรับ path ให้ถูกต้องตามโปรเจกต์

/* ============================== Helpers ============================== */
function table_exists($conn, $table) {
  $sql = "SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) return false;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  return intval($r['c'] ?? 0) > 0;
}

function column_exists($conn, $table, $col) {
  $sql = "SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) return false;
  $stmt->bind_param('ss', $table, $col);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  return intval($r['c'] ?? 0) > 0;
}

/* ========================== Config / Constants ========================== */
// สถานะที่นับเป็น "รายรับ"
$REVENUE_STATUSES = [
  'complete_only'       => ['ชำระเงินสมบูรณ์รอจัดส่ง'],
  'complete_plus_check' => ['ชำระเงินสมบูรณ์รอจัดส่ง', 'ชำระเงินสำเร็จรอการตรวจสอบ'],
];
$mode = $_GET['mode'] ?? 'complete_only';
$revenue_statuses = $REVENUE_STATUSES[$mode] ?? $REVENUE_STATUSES['complete_only'];

$ORDER_STATUS_MAP = [
  'pending'  => 'ยืนยันการสั่งซื้อและรอชำระเงิน',
  'checking' => 'ชำระเงินสำเร็จรอการตรวจสอบ',
  'complete' => 'ชำระเงินสมบูรณ์รอจัดส่ง',
  'prepare'  => 'เตรียมส่งสินค้าให้คุณแล้ว'
];

/* ================================ KPI ================================ */
// คำสั่งซื้อทั้งหมด
$total_orders = 0;
if (table_exists($conn, 'orders')) {
  if ($q = $conn->query("SELECT COUNT(*) c FROM orders")) {
    $total_orders = intval($q->fetch_assoc()['c'] ?? 0);
  }
}

// นับคำสั่งซื้อแยกสถานะหลัก
$status_counts = ['pending'=>0,'checking'=>0,'complete'=>0,'prepare'=>0];
if (table_exists($conn, 'orders') && column_exists($conn,'orders','status')) {
  $sql = "SELECT status, COUNT(*) c
          FROM orders
          WHERE status IN (?,?,?,?)
          GROUP BY status";
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('ssss',
      $ORDER_STATUS_MAP['pending'],
      $ORDER_STATUS_MAP['checking'],
      $ORDER_STATUS_MAP['complete'],
      $ORDER_STATUS_MAP['prepare']
    );
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      if ($row['status'] === $ORDER_STATUS_MAP['pending'])  $status_counts['pending']  = (int)$row['c'];
      if ($row['status'] === $ORDER_STATUS_MAP['checking']) $status_counts['checking'] = (int)$row['c'];
      if ($row['status'] === $ORDER_STATUS_MAP['complete']) $status_counts['complete'] = (int)$row['c'];
      if ($row['status'] === $ORDER_STATUS_MAP['prepare'])  $status_counts['prepare']  = (int)$row['c'];
    }
    $stmt->close();
  }
}

// รายรับรวมทั้งหมด / รายรับเดือนนี้ (อิงตาราง payments)
$total_revenue_all = 0.0;
$revenue_mtd       = 0.0;

$has_payments = table_exists($conn, 'payments') &&
                column_exists($conn,'payments','status') &&
                column_exists($conn,'payments','amount') &&
                column_exists($conn,'payments','created_at');

if ($has_payments) {
  $ph    = implode(',', array_fill(0, count($revenue_statuses), '?'));
  $types = str_repeat('s', count($revenue_statuses));

  // all-time
  if ($stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status IN ($ph)")) {
    $stmt->bind_param($types, ...$revenue_statuses);
    $stmt->execute();
    $total_revenue_all = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
    $stmt->close();
  }

  // MTD
  $y = (int)date('Y');
  $m = (int)date('n');
  if ($stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) s
                              FROM payments
                              WHERE status IN ($ph) AND YEAR(created_at)=? AND MONTH(created_at)=?")) {
    $stmt->bind_param($types.'ii', ...array_merge($revenue_statuses, [$y, $m]));
    $stmt->execute();
    $revenue_mtd = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
    $stmt->close();
  }
}

/* ============================ กราฟรายเดือน ============================ */
$year = (int)($_GET['year'] ?? date('Y'));
$monthly = array_fill(1, 12, ['count'=>0,'total'=>0.0]);

if (table_exists($conn, 'orders') && column_exists($conn,'orders','created_at') && column_exists($conn,'orders','total_amount')) {
  $ph    = implode(',', array_fill(0, count($revenue_statuses), '?'));
  $types = str_repeat('s', count($revenue_statuses));
  $sql = "SELECT MONTH(created_at) m, COUNT(*) c, COALESCE(SUM(total_amount),0) t
          FROM orders
          WHERE status IN ($ph) AND YEAR(created_at)=?
          GROUP BY MONTH(created_at) ORDER BY m";
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($types.'i', ...array_merge($revenue_statuses, [$year]));
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      $mm = (int)$r['m'];
      $monthly[$mm] = ['count' => (int)$r['c'], 'total' => (float)$r['t']];
    }
    $stmt->close();
  }
}

/* ========================== Top 5 สินค้าขายดี ========================== */
/* ทางเลือก A: ใช้ payments ที่มี product_name + stock_out
   ทางเลือก B (fallback): ใช้ order_details + orders + products และ filter ด้วยสถานะ orders */
$top_products = [];
$can_use_payments_products =
  $has_payments &&
  column_exists($conn,'payments','product_name') &&
  column_exists($conn,'payments','stock_out') &&
  table_exists($conn,'products') &&
  column_exists($conn,'products','product_name');

if ($can_use_payments_products) {
  $ph    = implode(',', array_fill(0, count($revenue_statuses), '?'));
  $types = str_repeat('s', count($revenue_statuses));
  $sql = "SELECT p.product_id, p.product_name,
                 COALESCE(SUM(pm.stock_out),0) qty_sold,
                 COALESCE(SUM(pm.amount),0)   revenue
          FROM payments pm
          JOIN products p ON pm.product_name = p.product_name
          WHERE pm.status IN ($ph)
          GROUP BY p.product_id, p.product_name
          ORDER BY qty_sold DESC, revenue DESC
          LIMIT 5";
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($types, ...$revenue_statuses);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $top_products[] = [
        'product_id'   => (int)$row['product_id'],
        'product_name' => $row['product_name'],
        'qty_sold'     => (int)$row['qty_sold'],
        'revenue'      => (float)$row['revenue'],
      ];
    }
    $stmt->close();
  }
} else {
  // Fallback: จาก order_details
  $has_od = table_exists($conn,'order_details') &&
            column_exists($conn,'order_details','order_id') &&
            column_exists($conn,'order_details','product_id') &&
            column_exists($conn,'order_details','quantity') &&
            column_exists($conn,'order_details','price');

  $has_orders = table_exists($conn,'orders') && column_exists($conn,'orders','status');
  $has_products = table_exists($conn,'products') && column_exists($conn,'products','product_id');

  if ($has_od && $has_orders && $has_products) {
    $ph    = implode(',', array_fill(0, count($revenue_statuses), '?'));
    $types = str_repeat('s', count($revenue_statuses));
    $sql = "SELECT p.product_id, p.product_name,
                   COALESCE(SUM(od.quantity),0) as qty_sold,
                   COALESCE(SUM(od.quantity * od.price),0) as revenue
            FROM order_details od
            JOIN orders o   ON od.order_id = o.order_id
            JOIN products p ON od.product_id = p.product_id
            WHERE o.status IN ($ph)
            GROUP BY p.product_id, p.product_name
            ORDER BY qty_sold DESC, revenue DESC
            LIMIT 5";
    if ($stmt = $conn->prepare($sql)) {
      $stmt->bind_param($types, ...$revenue_statuses);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) {
        $top_products[] = [
          'product_id'   => (int)$row['product_id'],
          'product_name' => $row['product_name'],
          'qty_sold'     => (int)$row['qty_sold'],
          'revenue'      => (float)$row['revenue'],
        ];
      }
      $stmt->close();
    }
  }
}

/* =========================== สินค้าคงเหลือน้อย =========================== */
$low_stock = [];
if (table_exists($conn,'products') && column_exists($conn,'products','stock')) {
  if ($q = $conn->query("SELECT product_id, product_name, stock
                         FROM products
                         ORDER BY stock ASC, product_id ASC
                         LIMIT 10")) {
    while ($r = $q->fetch_assoc()) {
      $low_stock[] = [
        'product_id'   => (int)$r['product_id'],
        'product_name' => $r['product_name'],
        'stock'        => (int)$r['stock'],
      ];
    }
  }
}

/* =============================== Output =============================== */
// ส่งออกผล
echo json_encode([
  'ok' => true,
  'kpis' => [
    'total_orders' => $total_orders,
    'orders' => [
      'pending'  => $status_counts['pending'],
      'checking' => $status_counts['checking'],
      'complete' => $status_counts['complete'],
      'prepare'  => $status_counts['prepare'],
      'cancelled'=> $status_counts['cancelled'] ?? 0
    ],
    'revenue' => [
      'all_time' => $total_revenue_all,
      'mtd'      => $revenue_mtd,
    ],
  ],
  'monthly_revenue' => [
    'year' => $year,
    'rows' => array_map(
      fn($mm,$v)=> ['month'=>$mm, 'count'=>$v['count'], 'total'=>$v['total']],
      array_keys($monthly), array_values($monthly)
    )
  ],
  'top_products' => $top_products,
  'low_stock'    => $low_stock,
], JSON_UNESCAPED_UNICODE);

$conn->close();
