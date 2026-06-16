<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('admin');
$db = getDB();
$pageTitle = 'Feature Permission';

$features = ['wishlist', 'checkout', 'review'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_permission') {
        $userId      = (int)$_POST['user_id'];
        $featureName = $_POST['feature_name'] ?? '';
        $isAllowed   = isset($_POST['is_allowed']) ? 1 : 0;
        $reason      = trim($_POST['reason'] ?? '');

        if ($userId && in_array($featureName, $features)) {
            $check = $db->prepare("SELECT id FROM feature_permissions WHERE user_id = ? AND feature_name = ?");
            $check->execute([$userId, $featureName]);
            if ($check->fetch()) {
                $stmt = $db->prepare("UPDATE feature_permissions SET is_allowed=?, reason=?, updated_by=? WHERE user_id=? AND feature_name=?");
                $stmt->execute([$isAllowed, $reason, $user['id'], $userId, $featureName]);
            } else {
                $stmt = $db->prepare("INSERT INTO feature_permissions (user_id, feature_name, is_allowed, reason, updated_by) VALUES (?,?,?,?,?)");
                $stmt->execute([$userId, $featureName, $isAllowed, $reason, $user['id']]);
            }
            setFlash('success', 'Permission berhasil diperbarui.');
        } else {
            setFlash('error', 'Data tidak valid.');
        }
    }

    if ($action === 'reset_user') {
        $userId = (int)$_POST['user_id'];
        if ($userId) {
            $db->prepare("DELETE FROM feature_permissions WHERE user_id = ?")->execute([$userId]);
            foreach ($features as $feat) {
                $stmt = $db->prepare("INSERT INTO feature_permissions (user_id, feature_name, is_allowed, updated_by) VALUES (?,?,1,?)");
                $stmt->execute([$userId, $feat, $user['id']]);
            }
            setFlash('success', 'Permission user direset ke default.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            $db->prepare("DELETE FROM feature_permissions WHERE id = ?")->execute([$id]);
            setFlash('success', 'Permission dihapus.');
        }
    }

    header('Location: permissions.php');
    exit;
}

// Get buyers
$buyers = $db->query("SELECT id, name, email FROM users WHERE role = 'buyer' AND is_active = 1 ORDER BY name")->fetchAll();

// Get permissions with user info
$perms = $db->query("
    SELECT fp.*, u.name as user_name, u.email as user_email
    FROM feature_permissions fp
    JOIN users u ON u.id = fp.user_id
    ORDER BY u.name, fp.feature_name
")->fetchAll();

// Selected user for detail view
$selectedUserId = (int)($_GET['user_id'] ?? 0);
$userPerms = [];
if ($selectedUserId) {
    $ups = $db->prepare("SELECT * FROM feature_permissions WHERE user_id = ?");
    $ups->execute([$selectedUserId]);
    foreach ($ups->fetchAll() as $p) {
        $userPerms[$p['feature_name']] = $p;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feature Permission - Teloved Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <?php include 'partials/topbar.php'; ?>
        <main class="main-area">
            <div class="page-header">
                <div>
                    <h1>Feature Permission</h1>
                    <p>CRUD 2 — Atur akses fitur untuk setiap buyer</p>
                </div>
            </div>

            <!-- Select User -->
            <div class="section-card">
                <h2>Pilih User untuk Dikelola</h2>
                <form method="GET" class="filter-bar" style="margin-top:12px">
                    <select name="user_id" onchange="this.form.submit()">
                        <option value="">-- Pilih Buyer --</option>
                        <?php foreach ($buyers as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $selectedUserId == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?> (<?= htmlspecialchars($b['email']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selectedUserId): ?>
                <div class="perm-grid" style="margin-top:20px">
                    <?php foreach ($features as $feat):
                        $p = $userPerms[$feat] ?? null;
                        $allowed = $p ? (bool)$p['is_allowed'] : true;
                        $featureIcons = ['wishlist'=>'❤️','checkout'=>'🛒','review'=>'⭐'];
                        $featureLabels = ['wishlist'=>'Wishlist','checkout'=>'Checkout','review'=>'Beri Ulasan'];
                    ?>
                    <div class="perm-card <?= $allowed ? 'perm-allowed' : 'perm-denied' ?>">
                        <div class="perm-icon"><?= $featureIcons[$feat] ?></div>
                        <div class="perm-name"><?= $featureLabels[$feat] ?></div>
                        <div class="perm-status"><?= $allowed ? '✅ Diizinkan' : '🚫 Diblokir' ?></div>
                        <?php if ($p && $p['reason']): ?>
                            <div class="perm-reason"><?= htmlspecialchars($p['reason']) ?></div>
                        <?php endif; ?>
                        <button class="btn-sm btn-edit" onclick="openPermModal(<?= $selectedUserId ?>, '<?= $feat ?>', <?= $allowed ? 1 : 0 ?>, '<?= htmlspecialchars($p['reason'] ?? '') ?>')">
                            Ubah
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" style="margin-top:16px" onsubmit="return confirm('Reset semua permission user ini ke default?')">
                    <input type="hidden" name="action" value="reset_user">
                    <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                    <button type="submit" class="btn-secondary">🔄 Reset ke Default</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- All Permissions Table -->
            <div class="section-card">
                <h2>Semua Permission</h2>
                <table class="data-table" style="margin-top:12px">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Fitur</th>
                            <th>Status</th>
                            <th>Alasan</th>
                            <th>Diubah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($perms)): ?>
                        <tr><td colspan="7" class="empty-row">Belum ada data permission.</td></tr>
                        <?php else: ?>
                        <?php foreach ($perms as $i => $p): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($p['user_name']) ?></td>
                            <td><span class="badge badge-seller"><?= ucfirst($p['feature_name']) ?></span></td>
                            <td><span class="badge badge-<?= $p['is_allowed'] ? 'active' : 'inactive' ?>"><?= $p['is_allowed'] ? 'Diizinkan' : 'Diblokir' ?></span></td>
                            <td><?= htmlspecialchars($p['reason'] ?: '-') ?></td>
                            <td><?= date('d M Y', strtotime($p['updated_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus permission ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-sm btn-delete">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Permission Modal -->
    <div class="modal-overlay" id="modalPerm">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Ubah Permission</h3>
                <button onclick="closeModal('modalPerm')">✕</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="set_permission">
                <input type="hidden" name="user_id" id="perm_user_id">
                <input type="hidden" name="feature_name" id="perm_feature_name">
                <div class="form-group">
                    <label>Fitur: <strong id="perm_feature_label"></strong></label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_allowed" id="perm_is_allowed">
                        Izinkan akses fitur ini
                    </label>
                </div>
                <div class="form-group">
                    <label>Alasan (opsional)</label>
                    <textarea name="reason" id="perm_reason" rows="3" placeholder="Contoh: Akun terindikasi spam..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('modalPerm')">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
        function openPermModal(userId, feature, isAllowed, reason) {
            document.getElementById('perm_user_id').value = userId;
            document.getElementById('perm_feature_name').value = feature;
            document.getElementById('perm_feature_label').textContent = feature;
            document.getElementById('perm_is_allowed').checked = isAllowed == 1;
            document.getElementById('perm_reason').value = reason;
            openModal('modalPerm');
        }
    </script>
</body>
</html>
