<?php
// ============================================
// API: /api/profile.php
// GET  — get own profile
// PUT  — update profile
// ============================================

require_once '../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$method   = $_SERVER['REQUEST_METHOD'];
$authUser = requireApiKey();
$db       = getDB();

if ($method === 'GET') {
    $userId = (int)($_GET['user_id'] ?? $authUser['id']);

    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.role, u.phone, u.profile_photo,
               p.bio, p.university, p.major, p.whatsapp, p.instagram, p.banner_photo, p.total_sales
        FROM users u
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    if (!$profile) jsonResponse(['error' => 'User tidak ditemukan'], 404);
    jsonResponse(['success' => true, 'data' => $profile]);
}

if ($method === 'PUT') {
    $userId = $authUser['id'];
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    $name      = trim($body['name'] ?? '');
    $phone     = trim($body['phone'] ?? '');
    $bio       = trim($body['bio'] ?? '');
    $university= trim($body['university'] ?? '');
    $major     = trim($body['major'] ?? '');
    $whatsapp  = trim($body['whatsapp'] ?? '');
    $instagram = trim($body['instagram'] ?? '');

    if ($name) {
        $db->prepare("UPDATE users SET name=?, phone=? WHERE id=?")->execute([$name, $phone, $userId]);
    }

    $check = $db->prepare("SELECT id FROM profiles WHERE user_id=?");
    $check->execute([$userId]);
    if ($check->fetch()) {
        $db->prepare("UPDATE profiles SET bio=?, university=?, major=?, whatsapp=?, instagram=? WHERE user_id=?")
           ->execute([$bio, $university, $major, $whatsapp, $instagram, $userId]);
    } else {
        $db->prepare("INSERT INTO profiles (user_id, bio, university, major, whatsapp, instagram) VALUES (?,?,?,?,?,?)")
           ->execute([$userId, $bio, $university, $major, $whatsapp, $instagram]);
    }

    if (isset($body['new_password']) && strlen($body['new_password']) >= 6) {
        $hashed = password_hash($body['new_password'], PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $userId]);
    }

    jsonResponse(['success' => true, 'message' => 'Profil berhasil diperbarui']);
}

// ============================================
// DELETE — Hapus akun (Soft Delete + Anonimisasi)
// ============================================
if ($method === 'DELETE') {
    $userId = $authUser['id'];
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    // Validasi: password wajib dikirim
    $password = $body['password'] ?? '';
    if (empty($password)) {
        jsonResponse(['error' => 'Password wajib diisi untuk menghapus akun.'], 400);
    }

    // Verifikasi password
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password'])) {
        jsonResponse(['error' => 'Password salah.'], 401);
    }

    // Cek order aktif
    $stmtOrder = $db->prepare("
        SELECT COUNT(*) as total FROM checkouts
        WHERE buyer_id = ?
          AND status IN ('pending','confirmed','processing','shipped')
    ");
    $stmtOrder->execute([$userId]);
    $active = $stmtOrder->fetch();

    if ($active['total'] > 0) {
        jsonResponse([
            'error' => 'Tidak dapat menghapus akun. Masih ada ' . $active['total'] . ' order aktif yang belum selesai.'
        ], 409);
    }

    try {
        $db->beginTransaction();

        // Soft delete + anonimisasi users
        $db->prepare("
            UPDATE users
            SET is_active       = 0,
                deleted_at      = NOW(),
                deletion_reason = 'Dihapus via API oleh pengguna',
                name            = 'Pengguna Teloved',
                phone           = NULL,
                email           = CONCAT('deleted_', id, '@removed.teloved')
            WHERE id = ?
        ")->execute([$userId]);

        // Anonimisasi profiles
        $db->prepare("
            UPDATE profiles
            SET bio = NULL, university = NULL, major = NULL,
                whatsapp = NULL, instagram = NULL
            WHERE user_id = ?
        ")->execute([$userId]);

        // Hard delete data personal murni
        $db->prepare("DELETE FROM wishlist WHERE buyer_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM feature_permissions WHERE user_id = ?")->execute([$userId]);

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Akun berhasil dihapus.']);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Terjadi kesalahan server. Silakan coba lagi.'], 500);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);