<?php
// ============================================
// API: /api/profile.php
// GET  — get own profile
// PUT  — update profile
// ============================================

require_once '../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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

jsonResponse(['error' => 'Method not allowed'], 405);
