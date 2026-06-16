<?php
// ============================================
// API: /api/wishlist.php
// GET    — list buyer's wishlist
// POST   — add to wishlist
// DELETE ?id=X — remove from wishlist
// ============================================

require_once '../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$method   = $_SERVER['REQUEST_METHOD'];
$authUser = requireApiKey();
$db       = getDB();

if ($authUser['role'] !== 'buyer') {
    jsonResponse(['error' => 'Fitur wishlist hanya untuk buyer'], 403);
}

// Check permission
if (!hasFeaturePermission($authUser['id'], 'wishlist')) {
    jsonResponse(['error' => 'Akses fitur wishlist Anda telah dibatasi oleh admin'], 403);
}

// GET: list wishlist
if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT w.id, w.created_at, p.id as product_id, p.name, p.price,
               p.condition_type, p.status, p.images, u.name as seller_name,
               c.name as cat_name, COALESCE(AVG(r.rating),0) as avg_rating
        FROM wishlist w
        JOIN products p ON p.id = w.product_id
        JOIN users u ON u.id = p.seller_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN reviews r ON r.product_id = p.id
        WHERE w.buyer_id = ?
        GROUP BY w.id
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$authUser['id']]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

// POST: add to wishlist
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $productId = (int)($body['product_id'] ?? 0);
    if (!$productId) jsonResponse(['error' => 'Product ID wajib diisi'], 400);

    // Check product exists
    $pCheck = $db->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
    $pCheck->execute([$productId]);
    if (!$pCheck->fetch()) jsonResponse(['error' => 'Produk tidak ditemukan'], 404);

    // Check duplicate
    $dup = $db->prepare("SELECT id FROM wishlist WHERE buyer_id = ? AND product_id = ?");
    $dup->execute([$authUser['id'], $productId]);
    if ($dup->fetch()) jsonResponse(['error' => 'Produk sudah ada di wishlist'], 409);

    $db->prepare("INSERT INTO wishlist (buyer_id, product_id) VALUES (?,?)")->execute([$authUser['id'], $productId]);
    jsonResponse(['success' => true, 'message' => 'Ditambahkan ke wishlist'], 201);
}

// DELETE: remove from wishlist
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Wishlist ID required'], 400);

    $check = $db->prepare("SELECT id FROM wishlist WHERE id = ? AND buyer_id = ?");
    $check->execute([$id, $authUser['id']]);
    if (!$check->fetch()) jsonResponse(['error' => 'Item tidak ditemukan di wishlist Anda'], 404);

    $db->prepare("DELETE FROM wishlist WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Dihapus dari wishlist']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
