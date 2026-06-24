<?php
/**
 * api/save_partnership.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../includes/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
  exit;
}

// Required fields
$required = ['company_id', 'school_id', 'amount', 'focus_area', 'start_date', 'end_date'];
foreach ($required as $field) {
  if (empty($input[$field])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
    exit;
  }
}

try {
  $stmt = $pdo->prepare("
    INSERT INTO partnerships
      (company_id, school_id, amount, focus_area, start_date, end_date, description, status)
    VALUES
      (:company_id, :school_id, :amount, :focus_area, :start_date, :end_date, :description, :status)
  ");

  $stmt->execute([
    ':company_id'  => (int) $input['company_id'],
    ':school_id'   => (int) $input['school_id'],
    ':amount'      => (float) preg_replace('/[^0-9.]/', '', $input['amount']),
    ':focus_area'  => $input['focus_area'],
    ':start_date'  => $input['start_date'],
    ':end_date'    => $input['end_date'],
    ':description' => $input['description'] ?? '',
    ':status'      => $input['status'] ?? 'pending',
  ]);

  echo json_encode([
    'success' => true,
    'id'      => $pdo->lastInsertId(),
    'message' => 'Partnership saved successfully'
  ]);

} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
