<?php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    header("Location: " . BASE_URL . "/$role/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header("Location: " . BASE_URL . "/" . $user['role'] . "/dashboard.php");
            exit;
        } else {
            $error = 'Email atau password salah, atau akun tidak aktif.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Teloved</title>
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
                <h2>Jual & Beli Barang Preloved dengan Mudah & Terpercaya</h2>
                <ul>
                    <li>✅ Verifikasi Penjual</li>
                    <li>✅ Smart Search & Filter</li>
                    <li>✅ Transaksi Aman</li>
                </ul>
            </div>
        </div>
        <div class="auth-right">
            <div class="auth-card">
                <h2>Masuk ke Teloved</h2>
                <p class="auth-sub">Belum punya akun? <a href="register.php">Daftar di sini</a></p>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
                    <div class="alert alert-error">Akses tidak diizinkan untuk role Anda.</div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="email@mahasiswa.ac.id"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-pass">
                            <input type="password" id="password" name="password" placeholder="Password" required>
                            <button type="button" onclick="togglePass()" class="toggle-pass">👁</button>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Masuk</button>
                </form>

                <div class="demo-accounts">
                    <p>Demo Akun:</p>
                    <button onclick="fillDemo('admin@teloved.id','password')" class="demo-btn">👑 Admin</button>
                    <button onclick="fillDemo('aurel@teloved.id','password')" class="demo-btn">🏪 Seller</button>
                    <button onclick="fillDemo('harits@teloved.id','password')" class="demo-btn">🛒 Buyer</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        function togglePass() {
            const p = document.getElementById('password');
            p.type = p.type === 'password' ? 'text' : 'password';
        }
        function fillDemo(email, pass) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = pass;
        }
    </script>
</body>
</html>
