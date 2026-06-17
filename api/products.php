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

// ============ PUT: update product ============
// Gunakan method spoofing: POST + _method=PUT karena PHP tidak baca $_FILES pada PUT
if ($method === 'POST' && ($_POST['_method'] ?? '') === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Product ID required'], 400);

    $check = $db->prepare("SELECT * FROM products WHERE id = ?");
    $check->execute([$id]);
    $product = $check->fetch();

    if (!$product) jsonResponse(['error' => 'Produk tidak ditemukan'], 404);
    if ($authUser['role'] === 'seller' && $product['seller_id'] != $authUser['id']) {
        jsonResponse(['error' => 'Bukan produk Anda'], 403);
    }

    // Baca dari $_POST (bukan php://input, karena multipart/form-data)
    $name          = trim($_POST['name'] ?? $product['name']);
    $price         = (float)($_POST['price'] ?? $product['price']);
    $description   = trim($_POST['description'] ?? $product['description']);
    $categoryId    = isset($_POST['category_id']) ? ((int)$_POST['category_id'] ?: null) : $product['category_id'];
    $conditionType = $_POST['condition_type'] ?? $product['condition_type'];
    $stock         = (int)($_POST['stock'] ?? $product['stock']);
    $status        = $_POST['status'] ?? $product['status'];

    if (!in_array($status, ['active','inactive','sold'])) $status = 'active';

    // Handle upload gambar baru
    $existingImages = json_decode($product['images'], true) ?? ['no-image.png'];

    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir    = UPLOAD_DIR . 'products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize      = 2 * 1024 * 1024;
        $newImages    = [];

        foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($_FILES['images']['type'][$i], $allowedTypes)) continue;
            if ($_FILES['images']['size'][$i] > $maxSize) continue;

            $ext      = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
            $filename = 'prod_' . $authUser['id'] . '_' . uniqid() . '.' . strtolower($ext);
            if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                $newImages[] = $filename;
            }
        }

        if (!empty($newImages)) {
            // Hapus file lama di server
            foreach ($existingImages as $oldImg) {
                if ($oldImg !== 'no-image.png') {
                    @unlink(UPLOAD_DIR . 'products/' . $oldImg);
                }
            }
            $finalImages = $newImages;
        } else {
            $finalImages = $existingImages;
        }
    } else {
        $finalImages = $existingImages;
    }

    $stmt = $db->prepare("UPDATE products 
        SET name=?, price=?, description=?, category_id=?, condition_type=?, stock=?, status=?, images=? 
        WHERE id=?");
    $stmt->execute([$name, $price, $description, $categoryId, $conditionType, $stock, $status, json_encode($finalImages), $id]);

    jsonResponse(['success' => true, 'message' => 'Produk berhasil diperbarui']);
}

// Blok PUT lama (tetap ada untuk jaga kompatibilitas — hanya untuk request JSON tanpa file)
if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Product ID required'], 400);

    $check = $db->prepare("SELECT * FROM products WHERE id = ?");
    $check->execute([$id]);
    $product = $check->fetch();

    if (!$product) jsonResponse(['error' => 'Produk tidak ditemukan'], 404);
    if ($authUser['role'] === 'seller' && $product['seller_id'] != $authUser['id']) {
        jsonResponse(['error' => 'Bukan produk Anda'], 403);
    }

    $body          = json_decode(file_get_contents('php://input'), true) ?? [];
    $name          = trim($body['name'] ?? $product['name']);
    $price         = (float)($body['price'] ?? $product['price']);
    $description   = trim($body['description'] ?? $product['description']);
    $categoryId    = isset($body['category_id']) ? ((int)$body['category_id'] ?: null) : $product['category_id'];
    $conditionType = $body['condition_type'] ?? $product['condition_type'];
    $stock         = (int)($body['stock'] ?? $product['stock']);
    $status        = $body['status'] ?? $product['status'];
    $imagesJson    = $product['images'];

    if (!in_array($status, ['active','inactive','sold'])) $status = 'active';

    $stmt = $db->prepare("UPDATE products 
        SET name=?, price=?, description=?, category_id=?, condition_type=?, stock=?, status=?, images=? 
        WHERE id=?");
    $stmt->execute([$name, $price, $description, $categoryId, $conditionType, $stock, $status, $imagesJson, $id]);

    jsonResponse(['success' => true, 'message' => 'Produk berhasil diperbarui']);
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

    // === BARU: Handle upload gambar ===
    $imageList = ['no-image.png'];

    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = UPLOAD_DIR . 'products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize      = 2 * 1024 * 1024; // 2MB per file
        $imageList    = [];

        foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($_FILES['images']['type'][$i], $allowedTypes)) continue;
            if ($_FILES['images']['size'][$i] > $maxSize) continue;

            $ext      = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
            $filename = 'prod_' . $authUser['id'] . '_' . uniqid() . '.' . strtolower($ext);
            if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                $imageList[] = $filename;
            }
        }

        if (empty($imageList)) $imageList = ['no-image.png'];
    }
    // === Akhir handle upload ===

    $stmt = $db->prepare("INSERT INTO products 
        (seller_id, category_id, name, description, price, condition_type, stock, images)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $authUser['id'], $categoryId, $name, $description,
        $price, $conditionType, $stock, json_encode($imageList)
    ]);
    $id = $db->lastInsertId();

    jsonResponse(['success' => true, 'message' => 'Produk berhasil ditambahkan', 'id' => $id], 201);
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
