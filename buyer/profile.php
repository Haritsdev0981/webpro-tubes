<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('buyer');
$db   = getDB();

$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $bio        = trim($_POST['bio'] ?? '');
    $university = trim($_POST['university'] ?? '');
    $major      = trim($_POST['major'] ?? '');
    $whatsapp   = trim($_POST['whatsapp'] ?? '');
    $instagram  = trim($_POST['instagram'] ?? '');
    $newPass    = $_POST['new_password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // Update users table
    if ($name) {
        $db->prepare("UPDATE users SET name=?, phone=? WHERE id=?")->execute([$name, $phone, $userId]);
    }

    // Upsert profile
    $check = $db->prepare("SELECT id FROM profiles WHERE user_id=?");
    $check->execute([$userId]);
    if ($check->fetch()) {
        $db->prepare("UPDATE profiles SET bio=?, university=?, major=?, whatsapp=?, instagram=? WHERE user_id=?")
           ->execute([$bio, $university, $major, $whatsapp, $instagram, $userId]);
    } else {
        $db->prepare("INSERT INTO profiles (user_id, bio, university, major, whatsapp, instagram) VALUES (?,?,?,?,?,?)")
           ->execute([$userId, $bio, $university, $major, $whatsapp, $instagram]);
    }

    // Change password
    if ($newPass) {
        if (strlen($newPass) < 6) {
            $error = 'Password minimal 6 karakter.';
        } elseif ($newPass !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $userId]);
        }
    }

    if (!isset($error)) {
        // Refresh session
        $newUser = $db->prepare("SELECT * FROM users WHERE id=?");
        $newUser->execute([$userId]);
        $_SESSION['user'] = $newUser->fetch();
        header('Location: profile.php?saved=1');
        exit;
    }
}

// Load profile
$profile = $db->prepare("SELECT * FROM profiles WHERE user_id=?");
$profile->execute([$userId]);
$profile = $profile->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Teloved</title>
    <link rel="stylesheet" href="../assets/css/buyer.css">
    <style>
        .profile-page { max-width: 700px; margin: 0 auto; padding: 28px 24px; }
        .profile-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden; }
        .profile-banner { height: 100px; background: linear-gradient(135deg, #1B4332, #2D6A4F); }
        .profile-avatar-area { padding: 0 24px 0; margin-top: -36px; margin-bottom: 16px; }
        .avatar-circle {
            width: 72px; height: 72px;
            background: #FFC107; border-radius: 50%;
            border: 4px solid #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; font-weight: 800; color: #1B4332;
        }
        .profile-form { padding: 0 24px 28px; }
        .section-title { font-size: 14px; font-weight: 700; color: #adb5bd; text-transform: uppercase; letter-spacing: 0.5px; margin: 20px 0 12px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .success-banner { background: #EAFAF1; border: 1px solid #A9DFBF; color: #1E8449; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; font-weight: 500; }
        .error-banner { background: #FDEDEC; border: 1px solid #F5C6CB; color: #DC3545; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }

        /* ── Danger Zone ──────────────────────────────── */
        .danger-zone {
            margin-top: 32px;
            border: 1.5px solid #f5c6cb;
            border-radius: 10px;
            padding: 20px 24px;
            background: #fff8f8;
        }
        .danger-zone-title {
            font-size: 13px; font-weight: 700;
            color: #DC3545; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 8px;
        }
        .danger-zone p {
            font-size: 13px; color: #6c757d; margin-bottom: 16px; line-height: 1.5;
        }
        .btn-danger {
            background: #DC3545; color: #fff; border: none;
            padding: 10px 20px; border-radius: 8px;
            font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .btn-danger:hover { background: #b02a37; }

        /* ── Modal Hapus Akun ─────────────────────────── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal-box {
            background: #fff; border-radius: 12px;
            padding: 28px; width: 100%; max-width: 440px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            margin: 16px;
        }
        .modal-head {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 16px;
        }
        .modal-head h3 { font-size: 16px; font-weight: 700; color: #DC3545; }
        .modal-head button {
            background: none; border: none; font-size: 20px;
            cursor: pointer; color: #adb5bd; line-height: 1;
        }
        .modal-warning {
            background: #fff3cd; border: 1px solid #ffc107;
            border-radius: 8px; padding: 12px 14px;
            font-size: 13px; color: #856404; margin-bottom: 16px;
            line-height: 1.5;
        }
        .modal-actions {
            display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;
        }
        .btn-secondary {
            background: #f8f9fa; color: #495057; border: 1px solid #dee2e6;
            padding: 10px 18px; border-radius: 8px;
            font-size: 13px; font-weight: 500; cursor: pointer;
        }
        .btn-secondary:hover { background: #e9ecef; }
        .checkbox-row {
            display: flex; align-items: flex-start; gap: 10px;
            font-size: 13px; color: #495057; margin-bottom: 14px;
            line-height: 1.4;
        }
        .checkbox-row input[type="checkbox"] { margin-top: 2px; flex-shrink: 0; }
    </style>
</head>
<body>
    <nav class="seller-nav">
        <div class="nav-brand"><span>♻️</span><a href="../index.php">TELOVED</a></div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="../products.php">Belanja</a>
            <a href="wishlist.php">Wishlist</a>
            <a href="orders.php">Order Saya</a>
            <a href="profile.php" class="active">Profil</a>
        </div>
        <a href="../logout.php" class="nav-logout">Logout</a>
    </nav>

    <div class="profile-page">
        <div class="profile-card">
            <div class="profile-banner"></div>
            <div class="profile-avatar-area">
                <div class="avatar-circle"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            </div>
            <div class="profile-form">
                <?php if (isset($_GET['saved'])): ?>
                <div class="success-banner">✅ Profil berhasil diperbarui!</div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                <div class="error-banner"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php
                // Pesan error dari proses hapus akun
                if (isset($_GET['delete_error'])):
                    $deleteMsg = match($_GET['delete_error']) {
                        'password_empty'  => '❌ Password wajib diisi untuk menghapus akun.',
                        'password_wrong'  => '❌ Password yang kamu masukkan salah.',
                        'confirm'         => '❌ Kamu harus mencentang persetujuan penghapusan.',
                        'active_orders'   => '❌ Tidak bisa menghapus akun. Kamu masih memiliki ' . ($_GET['count'] ?? '') . ' order yang belum selesai.',
                        'server'          => '❌ Terjadi kesalahan pada server. Silakan coba lagi.',
                        default           => '❌ Terjadi kesalahan. Silakan coba lagi.',
                    };
                ?>
                <div class="error-banner"><?= $deleteMsg ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="section-title">Informasi Akun</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Lengkap</label>
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

                    <div class="section-title">Profil Mahasiswa</div>
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="3" placeholder="Ceritakan tentang dirimu..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Universitas</label>
                            <input type="text" name="university" value="<?= htmlspecialchars($profile['university'] ?? '') ?>" placeholder="Nama universitas">
                        </div>
                        <div class="form-group">
                            <label>Jurusan</label>
                            <input type="text" name="major" value="<?= htmlspecialchars($profile['major'] ?? '') ?>" placeholder="Jurusan">
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
                            <label>Konfirmasi Password</label>
                            <input type="password" name="confirm_password" placeholder="Ulangi password baru">
                        </div>
                    </div>

                    <div style="margin-top:20px">
                        <button type="submit" class="btn-primary">💾 Simpan Perubahan</button>
                    </div>
                </form>

                <!-- ── Danger Zone ───────────────────────────────── -->
                <div class="danger-zone">
                    <div class="danger-zone-title">⚠️ Zona Berbahaya</div>
                    <p>
                        Menghapus akun akan menonaktifkan aksesmu ke Teloved secara permanen.
                        Wishlist dan data pribadimu akan dihapus. Riwayat transaksi tetap disimpan
                        untuk keperluan seller yang pernah bertransaksi denganmu.
                    </p>
                    <button type="button" class="btn-danger" onclick="openDeleteModal()">
                        🗑️ Hapus Akun
                    </button>
                </div>

            </div><!-- /.profile-form -->
        </div><!-- /.profile-card -->
    </div><!-- /.profile-page -->

    <!-- ── Modal Konfirmasi Hapus Akun ───────────────────── -->
    <div class="modal-overlay" id="modalDelete">
        <div class="modal-box">
            <div class="modal-head">
                <h3>🗑️ Hapus Akun Secara Permanen</h3>
                <button onclick="closeDeleteModal()">✕</button>
            </div>

            <div class="modal-warning">
                ⚠️ <strong>Tindakan ini tidak bisa dibatalkan.</strong><br>
                Akun kamu akan dinonaktifkan dan data pribadi akan dihapus permanen.
            </div>

            <form method="POST" action="delete_account.php" id="formDelete">
                <div class="form-group">
                    <label>Masukkan Password Kamu</label>
                    <input type="password" name="password" id="deletePassword"
                           placeholder="Password saat ini" autocomplete="current-password">
                </div>

                <div class="checkbox-row">
                    <input type="checkbox" name="confirm_delete" value="yes" id="checkConfirm">
                    <label for="checkConfirm">
                        Saya mengerti bahwa akun saya akan dihapus dan tindakan ini
                        <strong>tidak bisa dipulihkan</strong>.
                    </label>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    <button type="submit" class="btn-danger" id="btnConfirmDelete" disabled>
                        Ya, Hapus Akun Saya
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openDeleteModal() {
            document.getElementById('modalDelete').style.display = 'flex';
            document.getElementById('deletePassword').value = '';
            document.getElementById('checkConfirm').checked  = false;
            document.getElementById('btnConfirmDelete').disabled = true;
        }

        function closeDeleteModal() {
            document.getElementById('modalDelete').style.display = 'none';
        }

        // Aktifkan tombol submit hanya jika checkbox dicentang DAN password diisi
        function checkDeleteReady() {
            const hasPassword = document.getElementById('deletePassword').value.trim() !== '';
            const isChecked   = document.getElementById('checkConfirm').checked;
            document.getElementById('btnConfirmDelete').disabled = !(hasPassword && isChecked);
        }

        document.getElementById('deletePassword').addEventListener('input', checkDeleteReady);
        document.getElementById('checkConfirm').addEventListener('change', checkDeleteReady);

        // Tutup modal jika klik di luar area modal
        document.getElementById('modalDelete').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
    </script>

</body>
</html>