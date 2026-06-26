<?php
session_start();
require_once '../includes/config.php';

$user   = requireAuth('buyer');
$db     = getDB();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_delete'] ?? '';

if ($confirm !== 'yes') {
    header('Location: profile.php?delete_error=confirm');
    exit;
}

if (empty($password)) {
    header('Location: profile.php?delete_error=password_empty');
    exit;
}

$stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row || !password_verify($password, $row['password'])) {
    header('Location: profile.php?delete_error=password_wrong');
    exit;
}

$stmtOrder = $db->prepare("
    SELECT COUNT(*) as total
    FROM checkouts
    WHERE buyer_id = ?
      AND status IN ('pending', 'confirmed', 'processing', 'shipped')
");
$stmtOrder->execute([$userId]);
$activeOrders = $stmtOrder->fetch();

if ($activeOrders['total'] > 0) {
    header('Location: profile.php?delete_error=active_orders&count=' . $activeOrders['total']);
    exit;
}

try {
    $db->beginTransaction();

    $db->prepare("
        UPDATE users
        SET is_active    = 0,
            deleted_at   = NOW(),
            deletion_reason = 'Dihapus oleh pengguna'
        WHERE id = ?
    ")->execute([$userId]);

    $db->prepare("
        UPDATE users
        SET name  = 'Pengguna Teloved',
            phone = NULL,
            email = CONCAT('deleted_', id, '@removed.teloved')
        WHERE id = ?
    ")->execute([$userId]);

    $db->prepare("
        UPDATE profiles
        SET bio        = NULL,
            university = NULL,
            major      = NULL,
            whatsapp   = NULL,
            instagram  = NULL
        WHERE user_id = ?
    ")->execute([$userId]);

    $db->prepare("DELETE FROM wishlist WHERE buyer_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM feature_permissions WHERE user_id = ?")->execute([$userId]);

    if (!empty($user['profile_photo'])) {
        $photoPath = UPLOAD_DIR . $user['profile_photo'];
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
    }

    $db->commit();

    session_unset();
    session_destroy();
    session_regenerate_id(true);

    header('Location: ../index.php?account=deleted');
    exit;

} catch (Exception $e) {
    $db->rollBack();
    header('Location: profile.php?delete_error=server');
    exit;
}