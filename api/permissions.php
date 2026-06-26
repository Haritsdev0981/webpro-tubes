<?php
require_once '../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$authUser = requireApiKey();
$db = getDB();

$validFeatures = ['wishlist', 'checkout', 'review'];

function badRequest($message) {
    jsonResponse(['success' => false, 'error' => $message], 400);
}

function forbidden($message) {
    jsonResponse(['success' => false, 'error' => $message], 403);
}

if ($authUser['role'] !== 'admin') {
    forbidden('Hanya admin yang dapat mengakses endpoint ini');
}

if ($method === 'GET') {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

    if ($userId > 0) {
        $stmt = $db->prepare("SELECT * FROM feature_permissions WHERE user_id = ? ORDER BY feature_name");
        $stmt->execute([$userId]);
    } else {
        $stmt = $db->query("SELECT * FROM feature_permissions ORDER BY user_id, feature_name");
    }

    $data = $stmt->fetchAll();
    jsonResponse(['success' => true, 'data' => $data]);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $userId = (int)($body['user_id'] ?? 0);
    $featureName = trim($body['feature_name'] ?? '');
    $isAllowed = isset($body['is_allowed']) ? (int)$body['is_allowed'] : null;
    $reason = trim($body['reason'] ?? '');

    if (!$userId || !$featureName || !in_array($featureName, $validFeatures, true) || $isAllowed === null) {
        badRequest('user_id, feature_name, dan is_allowed wajib diisi dengan nilai valid');
    }

    $check = $db->prepare("SELECT id FROM feature_permissions WHERE user_id = ? AND feature_name = ?");
    $check->execute([$userId, $featureName]);
    if ($check->fetch()) {
        badRequest('Permission untuk user dan fitur tersebut sudah ada');
    }

    $stmt = $db->prepare("INSERT INTO feature_permissions (user_id, feature_name, is_allowed, reason, updated_by) VALUES (?,?,?,?,?)");
    $stmt->execute([$userId, $featureName, $isAllowed ? 1 : 0, $reason, $authUser['id']]);

    jsonResponse(['success' => true, 'message' => 'Permission berhasil ditambahkan']);
}

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($body['id'] ?? 0);
    $isAllowed = isset($body['is_allowed']) ? (int)$body['is_allowed'] : null;
    $reason = isset($body['reason']) ? trim($body['reason']) : null;

    if (!$id || $isAllowed === null || $reason === null) {
        badRequest('id, is_allowed, dan reason wajib diisi');
    }

    $check = $db->prepare("SELECT id FROM feature_permissions WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Permission tidak ditemukan'], 404);
    }

    $stmt = $db->prepare("UPDATE feature_permissions SET is_allowed = ?, reason = ?, updated_by = ? WHERE id = ?");
    $stmt->execute([$isAllowed ? 1 : 0, $reason, $authUser['id'], $id]);

    jsonResponse(['success' => true, 'message' => 'Permission berhasil diperbarui']);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        badRequest('Parameter id wajib diisi');
    }

    $check = $db->prepare("SELECT id FROM feature_permissions WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Permission tidak ditemukan'], 404);
    }

    $stmt = $db->prepare("DELETE FROM feature_permissions WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse(['success' => true, 'message' => 'Permission berhasil dihapus']);
}

jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
