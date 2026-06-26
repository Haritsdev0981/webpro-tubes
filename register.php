<?php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/" . $_SESSION['user']['role'] . "/dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? 'buyer';

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Semua field wajib diisi.';
    } elseif (!in_array($role, ['buyer', 'seller'])) {
        $error = 'Role tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $db = getDB();
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Email sudah terdaftar.';
        } else {
            $hashed  = password_hash($password, PASSWORD_DEFAULT);
            $apiKey  = strtoupper($role) . '-KEY-' . strtoupper(substr(md5($email . time()), 0, 12));
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role, api_key) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed, $role, $apiKey]);
            $userId = $db->lastInsertId();

            if ($role === 'buyer') {
                foreach (['wishlist', 'checkout', 'review'] as $feat) {
                    $fp = $db->prepare("INSERT INTO feature_permissions (user_id, feature_name, is_allowed) VALUES (?, ?, 1)");
                    $fp->execute([$userId, $feat]);
                }
            }

            $db->prepare("INSERT INTO profiles (user_id) VALUES (?)")->execute([$userId]);

            $success = 'Registrasi berhasil! Silakan login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Teloved</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-left">
            <div class="auth-brand">
                <span class="brand-icon">♻️</span>
                <h1>TELOVED</h1>
                <p>Preloved Marketplace untuk Mahasiswa</p>
            </div>
            <div class="auth-tagline">
                <h2>Bergabung Sekarang!</h2>
                <ul>
                    <li>🎓 Khusus untuk Mahasiswa</li>
                    <li>💰 Jual barang yang tidak terpakai</li>
                    <li>🔒 Transaksi aman & terpercaya</li>
                </ul>
            </div>
        </div>
        <div class="auth-right">
            <div class="auth-card">
                <h2>Buat Akun Baru</h2>
                <p class="auth-sub">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="login.php">Login</a></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="name" placeholder="Nama lengkap kamu" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="email@mahasiswa.ac.id" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Daftar sebagai</label>
                        <div class="role-select">
                            <label class="role-option">
                                <input type="radio" name="role" value="buyer" <?= ($_POST['role'] ?? 'buyer') === 'buyer' ? 'checked' : '' ?>>
                                <span>🛒 Pembeli (Buyer)</span>
                            </label>
                            <label class="role-option">
                                <input type="radio" name="role" value="seller" <?= ($_POST['role'] ?? '') === 'seller' ? 'checked' : '' ?>>
                                <span>🏪 Penjual (Seller)</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Min. 6 karakter" required>
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password</label>
                        <input type="password" name="confirm_password" placeholder="Ulangi password" required>
                    </div>
                    <button type="submit" class="btn-primary">Daftar Sekarang</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
