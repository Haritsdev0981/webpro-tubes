<?php
// ============================================
// API: /api/products.php
// Endpoint 1: GET /api/products.php       — list seller's products
// Endpoint 2: POST /api/products.php      — create product
// Endpoint 3: PUT /api/products.php?id=X  — update product
// Endpoint 4: DELETE /api/products.php?id=X — delete product
// Endpoint 5: GET /api/products.php?public=1 — public product listing (buyer)
// ============================================

require_once '../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

// Public product listing (no auth needed)
if ($method === 'GET' && isset($_GET['public'])) {
    $search   = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $minPrice = (float)($_GET['min_price'] ?? 0);
    $maxPrice = (float)($_GET['max_price'] ?? 0);
    $condition = trim($_GET['condition'] ?? '');

    $where = "WHERE p.status = 'active'";
    $params = [];

    if ($search) { $where .= " AND (p.name LIKE ? OR p.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($category) { $where .= " AND c.slug = ?"; $params[] = $category; }
    if ($minPrice > 0) { $where .= " AND p.price >= ?"; $params[] = $minPrice; }
    if ($maxPrice > 0) { $where .= " AND p.price <= ?"; $params[] = $maxPrice; }
    if ($condition) { $where .= " AND p.condition_type = ?"; $params[] = $condition; }

    $stmt = $db->prepare("
        SELECT p.*, u.name as seller_name, c.name as cat_name, c.slug as cat_slug,
               COALESCE(AVG(r.rating), 0) as avg_rating, COUNT(r.id) as review_count
        FROM products p
        JOIN users u ON u.id = p.seller_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN reviews r ON r.product_id = p.id
        $where
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    jsonResponse(['success' => true, 'data' => $products]);
}

// All protected routes need API key
$authUser = requireApiKey();

// ============ GET: list own products (seller) ============
if ($method === 'GET') {
    if ($authUser['role'] !== 'seller' && $authUser['role'] !== 'admin') {
        jsonResponse(['error' => 'Only sellers can access this'], 403);
    }
    $sellerId = $authUser['role'] === 'admin' ? null : $authUser['id'];
    $whereId  = $sellerId ? "WHERE p.seller_id = $sellerId" : '';

    $stmt = $db->query("
        SELECT p.*, c.name as cat_name, c.slug as cat_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        $whereId
        ORDER BY p.created_at DESC
    ");
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ============ POST: create product ============
if ($method === 'POST') {
    if ($authUser['role'] !== 'seller') {
        jsonResponse(['error' => 'Only sellers can add products'], 403);
    }

    $name          = trim($_POST['name'] ?? '');
    $price         = (float)($_POST['price'] ?? 0);
    $description   = trim($_POST['description'] ?? '');
    $categoryId    = (int)($_POST['category_id'] ?? 0) ?: null;
    $conditionType = $_POST['condition_type'] ?? 'Bekas - Baik';
    $stock         = (int)($_POST['stock'] ?? 1);

    if (!$name || $price <= 0) {
        jsonResponse(['error' => 'Nama produk dan harga wajib diisi'], 400);
    }

    $stmt = $db->prepare("INSERT INTO products (seller_id, category_id, name, description, price, condition_type, stock, images)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$authUser['id'], $categoryId, $name, $description, $price, $conditionType, $stock, '["no-image.png"]']);
    $id = $db->lastInsertId();

    jsonResponse(['success' => true, 'message' => 'Produk berhasil ditambahkan', 'id' => $id], 201);
}

// ============ PUT: update product ============
if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Product ID required'], 400);

    // Verify ownership
    $check = $db->prepare("SELECT * FROM products WHERE id = ?");
    $check->execute([$id]);
    $product = $check->fetch();

    if (!$product) jsonResponse(['error' => 'Produk tidak ditemukan'], 404);
    if ($authUser['role'] === 'seller' && $product['seller_id'] != $authUser['id']) {
        jsonResponse(['error' => 'Bukan produk Anda'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name          = trim($body['name'] ?? $product['name']);
    $price         = (float)($body['price'] ?? $product['price']);
    $description   = trim($body['description'] ?? $product['description']);
    $categoryId    = isset($body['category_id']) ? ((int)$body['category_id'] ?: null) : $product['category_id'];
    $conditionType = $body['condition_type'] ?? $product['condition_type'];
    $stock         = (int)($body['stock'] ?? $product['stock']);
    $status        = $body['status'] ?? $product['status'];

    if (!in_array($status, ['active','inactive','sold'])) $status = 'active';

    $stmt = $db->prepare("UPDATE products SET name=?, price=?, description=?, category_id=?, condition_type=?, stock=?, status=? WHERE id=?");
    $stmt->execute([$name, $price, $description, $categoryId, $conditionType, $stock, $status, $id]);

    jsonResponse(['success' => true, 'message' => 'Produk berhasil diperbarui']);
}

// ============ DELETE: delete product ============
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Product ID required'], 400);

    $check = $db->prepare("SELECT * FROM products WHERE id = ?");
    $check->execute([$id]);
    $product = $check->fetch();

    if (!$product) jsonResponse(['error' => 'Produk tidak ditemukan'], 404);
    if ($authUser['role'] === 'seller' && $product['seller_id'] != $authUser['id']) {
        jsonResponse(['error' => 'Bukan produk Anda'], 403);
    }

    $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Produk berhasil dihapus']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
