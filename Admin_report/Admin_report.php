<?php
// Admin_report.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once '../FilePHP/db.php'; // ปรับ path ตามโปรเจกต์ของคุณ

$action = $_GET['action'] ?? '';

// mapping โหมดสถานะ → เงื่อนไขใน SQL
function buildStatusWhere($mode) {
  // ค่าเริ่มต้น: นับเฉพาะ "ชำระเงินสมบูรณ์รอจัดส่ง"
  $completed = "status = 'ชำระเงินสมบูรณ์รอจัดส่ง'";
  if ($mode === 'complete_checking') {
    return "(status = 'ชำระเงินสมบูรณ์รอจัดส่ง' OR status = 'ชำระเงินสำเร็จรอการตรวจสอบ')";
  }
  return $completed;
}

// ---------- รายรับรายเดือน ----------
if ($action === 'monthly') {
  $year = intval($_GET['year'] ?? date('Y'));
  $mode = $_GET['mode'] ?? 'complete';
  $where = buildStatusWhere($mode);

  $sql = "
    SELECT MONTH(created_at) AS m, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amt
    FROM payments
    WHERE $where AND YEAR(created_at) = ?
    GROUP BY MONTH(created_at)
    ORDER BY m
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $year);
  $stmt->execute();
  $res = $stmt->get_result();

  // สร้าง array เดือน 1-12 (index 0-11)
  $months = [];
  for ($i = 1; $i <= 12; $i++) {
    $months[$i-1] = ['month' => $i, 'count' => 0, 'total' => 0.0];
  }
  while ($r = $res->fetch_assoc()) {
    $m = (int)$r['m'];
    $months[$m-1] = [
      'month' => $m,
      'count' => (int)$r['cnt'],
      'total' => (float)$r['amt'],
    ];
  }
  echo json_encode(['ok'=>true, 'rows'=>array_values($months)], JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------- รายรับรายปี ----------
if ($action === 'yearly') {
  $from = intval($_GET['from'] ?? date('Y')-5);
  $to   = intval($_GET['to']   ?? date('Y'));
  if ($to < $from) { $t=$from; $from=$to; $to=$t; }

  $mode  = $_GET['mode'] ?? 'complete';
  $where = buildStatusWhere($mode);

  $sql = "
    SELECT YEAR(created_at) AS y, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amt
    FROM payments
    WHERE $where AND YEAR(created_at) BETWEEN ? AND ?
    GROUP BY YEAR(created_at)
    ORDER BY y
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ii', $from, $to);
  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  while ($r = $res->fetch_assoc()) {
    $rows[] = [
      'year'  => (int)$r['y'],
      'count' => (int)$r['cnt'],
      'total' => (float)$r['amt'],
    ];
  }
  echo json_encode(['ok'=>true, 'rows'=>$rows], JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------- (9.1) รายงาน orders รายเดือน ----------
if ($action === 'orders_monthly') {
  $year = intval($_GET['year'] ?? date('Y'));
  $mode = $_GET['mode'] ?? 'complete';
  $where = buildStatusWhere($mode);

  $sql = "
    SELECT MONTH(created_at) AS m, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amt
    FROM orders
    WHERE $where AND YEAR(created_at) = ?
    GROUP BY MONTH(created_at)
    ORDER BY m
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $year);
  $stmt->execute();
  $res = $stmt->get_result();

  // สร้าง array เดือน 1-12 (index 0-11)
  $months = [];
  for ($i = 1; $i <= 12; $i++) {
    $months[$i-1] = ['month' => $i, 'count' => 0, 'total' => 0.0];
  }
  while ($r = $res->fetch_assoc()) {
    $m = (int)$r['m'];
    $months[$m-1] = [
      'month' => $m,
      'count' => (int)$r['cnt'],
      'total' => (float)$r['amt'],
    ];
  }
  echo json_encode(['ok'=>true, 'rows'=>array_values($months)], JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------- (9.2) รายงาน orders รายปี ----------
if ($action === 'orders_yearly') {
  $from = intval($_GET['from'] ?? date('Y')-5);
  $to   = intval($_GET['to']   ?? date('Y'));
  if ($to < $from) { $t=$from; $from=$to; $to=$t; }

  $mode  = $_GET['mode'] ?? 'complete';
  $where = buildStatusWhere($mode);

  $sql = "
    SELECT YEAR(created_at) AS y, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amt
    FROM orders
    WHERE $where AND YEAR(created_at) BETWEEN ? AND ?
    GROUP BY YEAR(created_at)
    ORDER BY y
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ii', $from, $to);
  $stmt->execute();
  $res = $stmt->get_result();

  // สร้าง array ปีในช่วงที่เลือก
  $years = [];
  for ($y = $from; $y <= $to; $y++) {
    $years[$y] = ['year' => $y, 'count' => 0, 'total' => 0.0];
  }
  while ($r = $res->fetch_assoc()) {
    $y = (int)$r['y'];
    $years[$y] = [
      'year' => $y,
      'count' => (int)$r['cnt'],
      'total' => (float)$r['amt'],
    ];
  }
  echo json_encode(['ok'=>true, 'rows'=>array_values($years)], JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------- Export CSV ----------
if ($action === 'export') {
  // เปลี่ยน header เป็น CSV
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=report.csv');

  $out = fopen('php://output', 'w');
  // BOM สำหรับ Excel ภาษาไทย
  fwrite($out, "\xEF\xBB\xBF");

  $kind = $_GET['kind'] ?? 'monthly';
  $mode = $_GET['mode'] ?? 'complete';
  $where = buildStatusWhere($mode);

  if ($kind === 'monthly') {
    $year = intval($_GET['year'] ?? date('Y'));
    fputcsv($out, ["รายรับรายเดือน ปี $year"]);
    fputcsv($out, ['เดือน','จำนวนรายการ','รายรับรวม(บาท)']);

    $sql = "
      SELECT MONTH(created_at) AS m, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amt
      FROM payments
      WHERE $where AND YEAR(created_at) = ?
      GROUP BY MONTH(created_at)
      ORDER BY m
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $sumCnt=0; $sumAmt=0;
    while ($r = $res->fetch_assoc()) {
      $sumCnt += (int)$r['cnt'];
      $sumAmt += (float)$r['amt'];
      fputcsv($out, [ (int)$r['m'], (int)$r['cnt'], (float)$r['amt'] ]);
    }
    fputcsv($out, ['รวม', $sumCnt, $sumAmt]);
    fclose($out); exit;
  }

  if ($kind === 'yearly') {
    $from = intval($_GET['from'] ?? date('Y')-5);
    $to   = intval($_GET['to']   ?? date('Y'));
    if ($to < $from) { $t=$from; $from=$to; $to=$t; }

    fputcsv($out, ["รายรับรายปี ($from - $to)"]);
    fputcsv($out, ['ปี','จำนวนรายการ','รายรับรวม(บาท)']);

    $sql = "
      SELECT YEAR(created_at) AS y, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amt
      FROM payments
      WHERE $where AND YEAR(created_at) BETWEEN ? AND ?
      GROUP BY YEAR(created_at)
      ORDER BY y
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();

    $sumCnt=0; $sumAmt=0;
    while ($r = $res->fetch_assoc()) {
      $sumCnt += (int)$r['cnt'];
      $sumAmt += (float)$r['amt'];
      fputcsv($out, [ (int)$r['y'], (int)$r['cnt'], (float)$r['amt'] ]);
    }
    fputcsv($out, ['รวม', $sumCnt, $sumAmt]);
    fclose($out); exit;
  }

  // fallback
  fputcsv($out, ['ไม่พบรูปแบบรายงาน']);
  fclose($out); exit;
}

// ---------- default ----------
echo json_encode(['ok'=>false, 'message'=>'no action'], JSON_UNESCAPED_UNICODE);
