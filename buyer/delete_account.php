<?php
// ============================================
// buyer/delete_account.php
// Handler: Hapus Akun Buyer (Soft Delete)
// ============================================

session_start();
require_once '../includes/config.php';

// Hanya bisa diakses oleh buyer yang sudah login
$user   = requireAuth('buyer');
$db     = getDB();
$userId = $user['id'];

// Hanya terima method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_delete'] ?? '';

// ── Validasi 1: Checkbox konfirmasi harus dicentang ──────────────────────────
if ($confirm !== 'yes') {
    header('Location: profile.php?delete_error=confirm');
    exit;
}

// ── Validasi 2: Password wajib diisi ─────────────────────────────────────────
if (empty($password)) {
    header('Location: profile.php?delete_error=password_empty');
    exit;
}

// ── Validasi 3: Verifikasi password ke database ───────────────────────────────
$stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row || !password_verify($password, $row['password'])) {
    header('Location: profile.php?delete_error=password_wrong');
    exit;
}

// ── Validasi 4: Cek apakah ada order yang masih aktif (belum selesai) ────────
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

// ── Proses Penghapusan ────────────────────────────────────────────────────────
try {
    $db->beginTransaction();

    // 1. Soft delete: nonaktifkan akun + catat waktu penghapusan
    $db->prepare("
        UPDATE users
        SET is_active    = 0,
            deleted_at   = NOW(),
            deletion_reason = 'Dihapus oleh pengguna'
        WHERE id = ?
    ")->execute([$userId]);

    // 2. Anonimisasi data pribadi di tabel users
    //    (data transaksi/order tetap ada untuk keperluan seller)
    $db->prepare("
        UPDATE users
        SET name  = 'Pengguna Teloved',
            phone = NULL,
            email = CONCAT('deleted_', id, '@removed.teloved')
        WHERE id = ?
    ")->execute([$userId]);

    // 3. Anonimisasi data di tabel profiles
    $db->prepare("
        UPDATE profiles
        SET bio        = NULL,
            university = NULL,
            major      = NULL,
            whatsapp   = NULL,
            instagram  = NULL
        WHERE user_id = ?
    ")->execute([$userId]);

    // 4. Hard delete data yang murni milik buyer (tidak dibutuhkan pihak lain)
    $db->prepare("DELETE FROM wishlist WHERE buyer_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM feature_permissions WHERE user_id = ?")->execute([$userId]);

    // 5. Hapus foto profil dari server jika ada
    if (!empty($user['profile_photo'])) {
        $photoPath = UPLOAD_DIR . $user['profile_photo'];
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
    }

    $db->commit();

    // 6. Hancurkan session setelah akun berhasil dihapus
    session_unset();
    session_destroy();
    session_regenerate_id(true);

    // 7. Redirect ke halaman utama dengan pesan sukses
    header('Location: ../index.php?account=deleted');
    exit;

} catch (Exception $e) {
    $db->rollBack();
    // Jika gagal, kembali ke profile dengan pesan error
    header('Location: profile.php?delete_error=server');
    exit;
}