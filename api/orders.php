<?php
// ============================================
// API: /api/orders.php
// Endpoint: Orders & Checkout
// GET    /api/orders.php          — list orders (buyer: my checkouts, seller: incoming)
// POST   /api/orders.php          — buyer creates checkout
// PUT    /api/orders.php?id=X     — seller updates order status
// DELETE /api/orders.php?id=X     — buyer cancels pending checkout
// ============================================

require_once '../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$method   = $_SERVER['REQUEST_METHOD'];
$authUser = requireApiKey();
$db       = getDB();

// ============ GET: list orders ============
if ($method === 'GET') {
    if ($authUser['role'] === 'buyer') {
        // Buyer sees own checkouts
        $stmt = $db->prepare("
            SELECT c.*, p.name as product_name, p.price, u.name as seller_name,
                   o.status as order_status, o.tracking_number, o.seller_note, o.id as order_id
            FROM checkouts c
            JOIN products p ON p.id = c.product_id
            JOIN users u ON u.id = c.seller_id
            LEFT JOIN orders o ON o.checkout_id = c.id
            WHERE c.buyer_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$authUser['id']]);

    } elseif ($authUser['role'] === 'seller') {
        // Seller sees incoming orders
        $stmt = $db->prepare("
            SELECT c.*, p.name as product_name, u.name as buyer_name, u.phone as buyer_phone,
                   o.status as order_status, o.tracking_number, o.seller_note, o.id as order_id
            FROM checkouts c
            JOIN products p ON p.id = c.product_id
            JOIN users u ON u.id = c.buyer_id
            LEFT JOIN orders o ON o.checkout_id = c.id
            WHERE c.seller_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$authUser['id']]);

    } else {
        // Admin sees all
        $stmt = $db->query("
            SELECT c.*, p.name as product_name, b.name as buyer_name, s.name as seller_name,
                   o.status as order_status, o.id as order_id
            FROM checkouts c
            JOIN products p ON p.id = c.product_id
            JOIN users b ON b.id = c.buyer_id
            JOIN users s ON s.id = c.seller_id
            LEFT JOIN orders o ON o.checkout_id = c.id
            ORDER BY c.created_at DESC
        ");
    }

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ============ POST: buyer creates checkout ============
if ($method === 'POST') {
    if ($authUser['role'] !== 'buyer') {
        jsonResponse(['error' => 'Hanya buyer yang bisa checkout'], 403);
    }

    // Check permission
    if (!hasFeaturePermission($authUser['id'], 'checkout')) {
        jsonResponse(['error' => 'Akses fitur checkout Anda telah dibatasi oleh admin'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $productId      = (int)($body['product_id'] ?? 0);
    $quantity       = (int)($body['quantity'] ?? 1);
    $shippingAddr   = trim($body['shipping_address'] ?? '');
    $paymentMethod  = $body['payment_method'] ?? 'transfer';
    $note           = trim($body['note'] ?? '');

    if (!$productId || !$shippingAddr) {
        jsonResponse(['error' => 'Product ID dan alamat pengiriman wajib diisi'], 400);
    }

    // Get product
    $pStmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
    $pStmt->execute([$productId]);
    $product = $pStmt->fetch();
    if (!$product) jsonResponse(['error' => 'Produk tidak ditemukan atau tidak aktif'], 404);
    if ($product['seller_id'] == $authUser['id']) jsonResponse(['error' => 'Tidak bisa beli produk sendiri'], 400);
    if ($product['stock'] < $quantity) jsonResponse(['error' => 'Stok tidak cukup'], 400);
    if (!in_array($paymentMethod, ['transfer','cod','ewallet'])) $paymentMethod = 'transfer';

    $totalPrice = $product['price'] * $quantity;

    $db->beginTransaction();
    try {
        // Create checkout
        $stmt = $db->prepare("INSERT INTO checkouts (buyer_id, product_id, seller_id, quantity, total_price, shipping_address, payment_method, note)
                               VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$authUser['id'], $productId, $product['seller_id'], $quantity, $totalPrice, $shippingAddr, $paymentMethod, $note]);
        $checkoutId = $db->lastInsertId();

        // Create order record for seller
        $db->prepare("INSERT INTO orders (checkout_id, seller_id, status) VALUES (?,?,?)")
           ->execute([$checkoutId, $product['seller_id'], 'new']);

        // Reduce stock
        $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$quantity, $productId]);

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Checkout berhasil!', 'checkout_id' => $checkoutId, 'total_price' => $totalPrice], 201);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Gagal checkout: ' . $e->getMessage()], 500);
    }
}

// ============ PUT: seller updates order status ============
if ($method === 'PUT') {
    $checkoutId = (int)($_GET['id'] ?? 0);
    if (!$checkoutId) jsonResponse(['error' => 'Checkout ID required'], 400);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // Verify ownership
    $cStmt = $db->prepare("SELECT * FROM checkouts WHERE id = ?");
    $cStmt->execute([$checkoutId]);
    $checkout = $cStmt->fetch();
    if (!$checkout) jsonResponse(['error' => 'Checkout tidak ditemukan'], 404);

    if ($authUser['role'] === 'seller') {
        if ($checkout['seller_id'] != $authUser['id']) {
            jsonResponse(['error' => 'Bukan order Anda'], 403);
        }
        $newStatus = $body['order_status'] ?? 'accepted';
        $validStatuses = ['accepted','packed','shipped','delivered','cancelled'];
        if (!in_array($newStatus, $validStatuses)) jsonResponse(['error' => 'Status tidak valid'], 400);

        $trackingNum = trim($body['tracking_number'] ?? '');
        $sellerNote  = trim($body['seller_note'] ?? '');

        $db->prepare("UPDATE orders SET status=?, tracking_number=?, seller_note=? WHERE checkout_id=?")
           ->execute([$newStatus, $trackingNum, $sellerNote, $checkoutId]);

        // Update checkout status
        $checkoutStatus = match($newStatus) {
            'accepted', 'packed' => 'confirmed',
            'shipped' => 'shipped',
            'delivered' => 'completed',
            'cancelled' => 'cancelled',
            default => 'processing'
        };
        $db->prepare("UPDATE checkouts SET status=? WHERE id=?")->execute([$checkoutStatus, $checkoutId]);

        jsonResponse(['success' => true, 'message' => 'Status order diperbarui']);

    } elseif ($authUser['role'] === 'buyer') {
        // Buyer can only cancel pending
        if ($checkout['buyer_id'] != $authUser['id']) jsonResponse(['error' => 'Bukan order Anda'], 403);
        if ($checkout['status'] !== 'pending') jsonResponse(['error' => 'Order tidak bisa dibatalkan'], 400);

        $db->prepare("UPDATE checkouts SET status='cancelled' WHERE id=?")->execute([$checkoutId]);
        $db->prepare("UPDATE orders SET status='cancelled' WHERE checkout_id=?")->execute([$checkoutId]);
        // Restore stock
        $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$checkout['quantity'], $checkout['product_id']]);

        jsonResponse(['success' => true, 'message' => 'Order dibatalkan']);
    } else {
        jsonResponse(['error' => 'Unauthorized'], 403);
    }
}

// ============ DELETE: admin delete checkout ============
if ($method === 'DELETE') {
    if ($authUser['role'] !== 'admin') jsonResponse(['error' => 'Unauthorized'], 403);
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID required'], 400);
    $db->prepare("DELETE FROM checkouts WHERE id=?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Checkout dihapus']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
