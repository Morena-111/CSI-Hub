<?php
/**
 * api/get_partnerships.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/db.php';

try {
  $stmt = $pdo->query("
    SELECT
      p.id,
      c.name        AS company,
      c.logo_style,
      c.initials,
      s.name        AS school,
      p.amount,
      p.focus_area  AS focus,
      p.start_date  AS start,
      p.end_date    AS end,
      p.description AS description,
      p.status
    FROM partnerships p
    JOIN companies c ON p.company_id = c.id
    JOIN schools   s ON p.school_id  = s.id
    ORDER BY p.created_at DESC
  ");

  $partnerships = $stmt->fetchAll();

  // Add focusCls for frontend CSS class
  $focus_map = [
    'STEM'          => 'stem',
    'Digital Skills'=> 'digital',
    'Literacy'      => 'literacy',
    'Arts & Culture'=> 'arts',
    'Science'       => 'science',
  ];

  foreach ($partnerships as &$p) {
    $p['focusCls'] = $focus_map[$p['focus']] ?? 'stem';
    $p['amount']   = 'R' . number_format($p['amount'] / 1000, 0) . 'k';
  }

  echo json_encode(['success' => true, 'data' => $partnerships]);

} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
