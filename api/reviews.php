<?php
// ============================================
// API: /api/reviews.php
// GET  ?product_id=X — get product reviews (public)
// POST              — create review (buyer)
// PUT  ?id=X        — edit review (buyer)
// DELETE ?id=X      — delete review (buyer)
// ============================================

require_once '../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

// GET is public
if ($method === 'GET') {
    $productId = (int)($_GET['product_id'] ?? 0);
    if (!$productId) jsonResponse(['error' => 'product_id required'], 400);

    $stmt = $db->prepare("
        SELECT r.*, u.name as buyer_name
        FROM reviews r JOIN users u ON u.id = r.buyer_id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$productId]);
    $reviews = $stmt->fetchAll();

    $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ?");
    $avgStmt->execute([$productId]);
    $stats = $avgStmt->fetch();

    jsonResponse(['success' => true, 'data' => $reviews, 'stats' => $stats]);
}

// All other methods need auth
$authUser = requireApiKey();

if ($method === 'POST') {
    if ($authUser['role'] !== 'buyer') jsonResponse(['error' => 'Hanya buyer yang bisa memberi ulasan'], 403);
    if (!hasFeaturePermission($authUser['id'], 'review')) {
        jsonResponse(['error' => 'Akses fitur review Anda telah dibatasi'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $productId  = (int)($body['product_id'] ?? 0);
    $rating     = (int)($body['rating'] ?? 0);
    $comment    = trim($body['comment'] ?? '');
    $checkoutId = (int)($body['checkout_id'] ?? 0) ?: null;

    if (!$productId || $rating < 1 || $rating > 5) {
        jsonResponse(['error' => 'product_id dan rating (1-5) wajib diisi'], 400);
    }

    // Verify buyer has completed purchase
    $verifyStmt = $db->prepare("SELECT id FROM checkouts WHERE buyer_id=? AND product_id=? AND status='completed'");
    $verifyStmt->execute([$authUser['id'], $productId]);
    if (!$verifyStmt->fetch()) {
        jsonResponse(['error' => 'Anda hanya bisa mengulas produk yang sudah dibeli dan selesai'], 403);
    }

    // Check duplicate
    $dup = $db->prepare("SELECT id FROM reviews WHERE buyer_id=? AND product_id=?");
    $dup->execute([$authUser['id'], $productId]);
    if ($dup->fetch()) jsonResponse(['error' => 'Anda sudah mengulas produk ini'], 409);

    $stmt = $db->prepare("INSERT INTO reviews (buyer_id, product_id, checkout_id, rating, comment) VALUES (?,?,?,?,?)");
    $stmt->execute([$authUser['id'], $productId, $checkoutId, $rating, $comment]);
    jsonResponse(['success' => true, 'message' => 'Ulasan berhasil ditambahkan'], 201);
}

if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Review ID required'], 400);

    $check = $db->prepare("SELECT * FROM reviews WHERE id=?");
    $check->execute([$id]);
    $review = $check->fetch();
    if (!$review) jsonResponse(['error' => 'Ulasan tidak ditemukan'], 404);
    if ($review['buyer_id'] != $authUser['id']) jsonResponse(['error' => 'Bukan ulasan Anda'], 403);

    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $rating  = (int)($body['rating'] ?? $review['rating']);
    $comment = trim($body['comment'] ?? $review['comment']);
    if ($rating < 1 || $rating > 5) jsonResponse(['error' => 'Rating harus 1-5'], 400);

    $db->prepare("UPDATE reviews SET rating=?, comment=? WHERE id=?")->execute([$rating, $comment, $id]);
    jsonResponse(['success' => true, 'message' => 'Ulasan berhasil diperbarui']);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Review ID required'], 400);

    $check = $db->prepare("SELECT * FROM reviews WHERE id=?");
    $check->execute([$id]);
    $review = $check->fetch();
    if (!$review) jsonResponse(['error' => 'Ulasan tidak ditemukan'], 404);
    if ($review['buyer_id'] != $authUser['id'] && $authUser['role'] !== 'admin') {
        jsonResponse(['error' => 'Bukan ulasan Anda'], 403);
    }

    $db->prepare("DELETE FROM reviews WHERE id=?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Ulasan berhasil dihapus']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
