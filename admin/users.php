<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('admin');
$db = getDB();
$pageTitle = 'Manajemen User';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'buyer';
        $phone    = trim($_POST['phone'] ?? '');

        if ($name && $email && $password && in_array($role, ['buyer','seller','admin'])) {
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                setFlash('error', 'Email sudah terdaftar.');
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $apiKey = strtoupper($role) . '-KEY-' . strtoupper(substr(md5($email . time()), 0, 12));
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, phone, api_key) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$name, $email, $hashed, $role, $phone, $apiKey]);
                $newId = $db->lastInsertId();
                if ($role === 'buyer') {
                    foreach (['wishlist','checkout','review'] as $feat) {
                        $fp = $db->prepare("INSERT INTO feature_permissions (user_id, feature_name, is_allowed) VALUES (?,?,1)");
                        $fp->execute([$newId, $feat]);
                    }
                }
                $db->prepare("INSERT INTO profiles (user_id) VALUES (?)")->execute([$newId]);
                setFlash('success', 'User berhasil ditambahkan.');
            }
        } else {
            setFlash('error', 'Semua field wajib diisi dengan benar.');
        }
    }

    if ($action === 'edit') {
        $id      = (int)$_POST['id'];
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $role    = $_POST['role'] ?? 'buyer';
        $phone   = trim($_POST['phone'] ?? '');
        $active  = isset($_POST['is_active']) ? 1 : 0;

        if ($id && $name && $email) {
            $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=?, phone=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $email, $role, $phone, $active, $id]);
            setFlash('success', 'User berhasil diperbarui.');
        } else {
            setFlash('error', 'Data tidak lengkap.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id && $id !== (int)$user['id']) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            setFlash('success', 'User berhasil dihapus.');
        } else {
            setFlash('error', 'Tidak dapat menghapus diri sendiri.');
        }
    }

    header('Location: users.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$filterRole = $_GET['role'] ?? '';
$where = "WHERE u.role != 'admin'";
$params = [];
if ($search) { $where .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterRole) { $where .= " AND u.role = ?"; $params[] = $filterRole; }

$stmt = $db->prepare("SELECT u.* FROM users u $where ORDER BY u.created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $es = $db->prepare("SELECT * FROM users WHERE id = ?");
    $es->execute([(int)$_GET['edit']]);
    $editUser = $es->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - Teloved Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <?php include 'partials/topbar.php'; ?>
        <main class="main-area">
            <div class="page-header">
                <div>
                    <h1>Manajemen User</h1>
                    <p>CRUD 1 — Kelola semua akun buyer dan seller</p>
                </div>
                <button class="btn-primary" onclick="openModal('modalCreate')">+ Tambah User</button>
            </div>

            <form method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="Cari nama atau email..." value="<?= htmlspecialchars($search) ?>">
                <select name="role">
                    <option value="">Semua Role</option>
                    <option value="buyer" <?= $filterRole==='buyer'?'selected':'' ?>>Buyer</option>
                    <option value="seller" <?= $filterRole==='seller'?'selected':'' ?>>Seller</option>
                </select>
                <button type="submit" class="btn-primary">Cari</button>
                <a href="users.php" class="btn-secondary">Reset</a>
            </form>

            <div class="section-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>No. HP</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="empty-row">Tidak ada user ditemukan.</td></tr>
                        <?php else: ?>
                        <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                            <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td class="action-cell">
                                <a href="users.php?edit=<?= $u['id'] ?>" class="btn-sm btn-edit">Edit</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus user ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
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

    <div class="modal-overlay" id="modalCreate">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Tambah User Baru</h3>
                <button onclick="closeModal('modalCreate')">✕</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="text" name="phone">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="buyer">Buyer</option>
                        <option value="seller">Seller</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('modalCreate')">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($editUser): ?>
    <div class="modal-overlay open" id="modalEdit">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Edit User</h3>
                <a href="users.php">✕</a>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editUser['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editUser['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="buyer" <?= $editUser['role']==='buyer'?'selected':'' ?>>Buyer</option>
                        <option value="seller" <?= $editUser['role']==='seller'?'selected':'' ?>>Seller</option>
                        <option value="admin" <?= $editUser['role']==='admin'?'selected':'' ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" <?= $editUser['is_active'] ? 'checked' : '' ?>>
                        Akun Aktif
                    </label>
                </div>
                <div class="modal-actions">
                    <a href="users.php" class="btn-secondary">Batal</a>
                    <button type="submit" class="btn-primary">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="../assets/js/admin.js"></script>
</body>
</html>
