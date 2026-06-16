<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('seller');
$db   = getDB();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');
    $university= trim($_POST['university'] ?? '');
    $major     = trim($_POST['major'] ?? '');
    $whatsapp  = trim($_POST['whatsapp'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $newPass   = $_POST['new_password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

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

    if ($newPass) {
        if (strlen($newPass) < 6) $error = 'Password minimal 6 karakter.';
        elseif ($newPass !== $confirm) $error = 'Konfirmasi password tidak cocok.';
        else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
        }
    }

    if (!isset($error)) {
        $nu = $db->prepare("SELECT * FROM users WHERE id=?");
        $nu->execute([$userId]);
        $_SESSION['user'] = $nu->fetch();
        header('Location: profile.php?saved=1'); exit;
    }
}

$profile = $db->prepare("SELECT * FROM profiles WHERE user_id=?");
$profile->execute([$userId]);
$profile = $profile->fetch();

$totalSales = $db->prepare("SELECT COUNT(*) FROM checkouts WHERE seller_id=? AND status='completed'");
$totalSales->execute([$userId]);
$totalSales = $totalSales->fetchColumn();

$totalProducts = $db->prepare("SELECT COUNT(*) FROM products WHERE seller_id=? AND status='active'");
$totalProducts->execute([$userId]);
$totalProducts = $totalProducts->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Seller - Teloved</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
    <style>
        .profile-page { max-width: 750px; margin: 0 auto; padding: 28px 24px; }
        .profile-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden; }
        .profile-banner { height: 100px; background: linear-gradient(135deg, #1B4332, #2D6A4F); }
        .profile-top { padding: 0 24px 16px; margin-top: -36px; display: flex; gap: 16px; align-items: flex-end; }
        .avatar-circle { width: 72px; height: 72px; background: #FFC107; border-radius: 50%; border: 4px solid #fff; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; color: #1B4332; flex-shrink: 0; }
        .seller-stats-mini { display: flex; gap: 16px; padding: 0 0 8px; }
        .stat-mini { text-align: center; }
        .stat-mini .num { font-size: 20px; font-weight: 700; color: #1B4332; }
        .stat-mini .lbl { font-size: 11px; color: #adb5bd; }
        .profile-form { padding: 0 24px 28px; }
        .section-title { font-size: 13px; font-weight: 700; color: #adb5bd; text-transform: uppercase; letter-spacing: 0.5px; margin: 20px 0 12px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .success-banner { background: #EAFAF1; border: 1px solid #A9DFBF; color: #1E8449; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; font-weight: 500; }
        .error-banner { background: #FDEDEC; border: 1px solid #F5C6CB; color: #DC3545; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
    </style>
</head>
<body>
    <nav class="seller-nav">
        <div class="nav-brand"><span>♻️</span><a href="dashboard.php">TELOVED</a></div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="products.php">Manage Products</a>
            <a href="orders.php">Orders</a>
            <a href="profile.php" class="active">Profil</a>
        </div>
        <a href="../logout.php" class="nav-logout">Logout</a>
    </nav>

    <div class="profile-page">
        <div class="profile-card">
            <div class="profile-banner"></div>
            <div class="profile-top">
                <div class="avatar-circle"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                <div style="padding-bottom:8px">
                    <div style="font-size:18px;font-weight:700;color:#212529"><?= htmlspecialchars($user['name']) ?></div>
                    <div style="font-size:13px;color:#adb5bd">🏪 Seller Teloved</div>
                    <div class="seller-stats-mini" style="margin-top:8px">
                        <div class="stat-mini"><div class="num"><?= $totalProducts ?></div><div class="lbl">Produk Aktif</div></div>
                        <div class="stat-mini"><div class="num"><?= $totalSales ?></div><div class="lbl">Transaksi Selesai</div></div>
                    </div>
                </div>
            </div>
            <div class="profile-form">
                <?php if (isset($_GET['saved'])): ?>
                <div class="success-banner">✅ Profil berhasil diperbarui!</div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                <div class="error-banner"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="section-title">Informasi Akun</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Toko / Nama Lengkap</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>No. HP</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="08xx">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email (tidak bisa diubah)</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:#f8f9fa;color:#adb5bd">
                    </div>

                    <div class="section-title">Bio Toko</div>
                    <div class="form-group">
                        <label>Deskripsi Toko / Bio</label>
                        <textarea name="bio" rows="3" placeholder="Deskripsikan toko kamu..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Universitas</label>
                            <input type="text" name="university" value="<?= htmlspecialchars($profile['university'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Jurusan</label>
                            <input type="text" name="major" value="<?= htmlspecialchars($profile['major'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>WhatsApp</label>
                            <input type="text" name="whatsapp" value="<?= htmlspecialchars($profile['whatsapp'] ?? '') ?>" placeholder="08xx">
                        </div>
                        <div class="form-group">
                            <label>Instagram</label>
                            <input type="text" name="instagram" value="<?= htmlspecialchars($profile['instagram'] ?? '') ?>" placeholder="@username">
                        </div>
                    </div>

                    <div class="section-title">Ganti Password</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="new_password" placeholder="Kosongkan jika tidak ingin ganti">
                        </div>
                        <div class="form-group">
                            <label>Konfirmasi</label>
                            <input type="password" name="confirm_password" placeholder="Ulangi password baru">
                        </div>
                    </div>

                    <div style="margin-top:20px">
                        <button type="submit" class="btn-primary">💾 Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
