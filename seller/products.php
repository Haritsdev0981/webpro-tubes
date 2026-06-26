<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('seller');
$db = getDB();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$sellerId = $user['id'];

// GET: fetch products via JS (API), but also show page shell
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Listings - Teloved</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
</head>
<body>
    <nav class="seller-nav">
        <div class="nav-brand"><span>♻️</span><a href="dashboard.php">TELOVED</a></div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="products.php" class="active">Manage Products</a>
            <a href="orders.php">Orders</a>
            <a href="profile.php">Profil</a>
        </div>
        <a href="../logout.php" class="nav-logout">Logout</a>
    </nav>

    <div class="seller-page">
        <div class="page-hd">
            <div>
                <h1>My Listings</h1>
                <p>Manage your product inventory.</p>
            </div>
            <button class="btn-primary" onclick="openModal('modalAdd')">+ Add Product</button>
        </div>

        <div id="flash-area"></div>

        <div id="products-grid" class="products-grid">
            <div class="loading">Memuat produk...</div>
        </div>
    </div>

    <!-- ADD PRODUCT MODAL -->
    <div class="modal-overlay" id="modalAdd">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Tambah Produk Baru</h3>
                <button onclick="closeModal('modalAdd')">✕</button>
            </div>
            <form id="formAdd" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Harga (Rp)</label>
                        <input type="number" name="price" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Stok</label>
                        <input type="number" name="stock" value="1" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category_id">
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kondisi</label>
                    <select name="condition_type">
                        <option value="Baru">Baru</option>
                        <option value="Seperti Baru">Seperti Baru</option>
                        <option value="Bekas - Baik" selected>Bekas - Baik</option>
                        <option value="Bekas - Cukup">Bekas - Cukup</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" rows="3" placeholder="Deskripsikan produk kamu..."></textarea>
                </div>
                <div class="form-group">
                    <label>Foto Produk <span style="color:var(--gray-400);font-size:12px">(maks. 3 foto, 2MB/foto)</span></label>
                    <div class="upload-area" id="uploadAreaAdd">
                        <input type="file" name="images[]" id="imagesAdd" 
                            accept="image/*" multiple style="display:none">
                        <div class="upload-placeholder" onclick="document.getElementById('imagesAdd').click()">
                            <div style="font-size:32px">📷</div>
                            <div>Klik untuk memilih foto</div>
                            <div style="font-size:12px;color:var(--gray-400)">JPG, PNG, WEBP (maks. 3 foto)</div>
                        </div>
                        <div class="image-preview-list" id="previewAdd"></div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('modalAdd')">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT PRODUCT MODAL -->
    <div class="modal-overlay" id="modalEdit">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Edit Produk</h3>
                <button onclick="closeModal('modalEdit')">✕</button>
            </div>
            <form id="formEdit">
                <input type="hidden" id="edit_id">
                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" id="edit_name" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Harga (Rp)</label>
                        <input type="number" id="edit_price" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Stok</label>
                        <input type="number" id="edit_stock" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <select id="edit_category_id">
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kondisi</label>
                    <select id="edit_condition">
                        <option value="Baru">Baru</option>
                        <option value="Seperti Baru">Seperti Baru</option>
                        <option value="Bekas - Baik">Bekas - Baik</option>
                        <option value="Bekas - Cukup">Bekas - Cukup</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="edit_status">
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea id="edit_description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Foto Produk <span style="color:var(--gray-400);font-size:12px">(ganti foto lama)</span></label>
                    <div class="upload-area" id="uploadAreaEdit">
                        <input type="file" name="images[]" id="imagesEdit" 
                            accept="image/*" multiple style="display:none">
                        <div class="upload-placeholder" onclick="document.getElementById('imagesEdit').click()">
                            <div style="font-size:32px">📷</div>
                            <div>Klik untuk mengganti foto</div>
                        </div>
                        <div class="image-preview-list" id="previewEdit"></div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('modalEdit')">Batal</button>
                    <button type="submit" class="btn-primary">Perbarui</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_KEY = '<?= htmlspecialchars($user['api_key']) ?>';
        const API_BASE = '../api';

        function showFlash(type, msg) {
            const div = document.getElementById('flash-area');
            div.innerHTML = `<div class="flash-msg flash-${type}">${msg} <button onclick="this.parentElement.remove()">✕</button></div>`;
            setTimeout(() => div.innerHTML = '', 4000);
        }

        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

       async function loadProducts() {
            const grid = document.getElementById('products-grid');
            grid.innerHTML = '<div class="loading">Memuat produk...</div>';
            try {
                const res = await fetch(`${API_BASE}/products.php?t=${Date.now()}`, {  // ← tambah ?t=timestamp
                    headers: { 'X-API-Key': API_KEY }
                });
                const data = await res.json();
                if (data.error) { grid.innerHTML = `<div class="empty-state">Error: ${data.error}</div>`; return; }
                renderProducts(data.data || []);
            } catch (e) {
                grid.innerHTML = '<div class="empty-state">Gagal memuat produk. Pastikan API berjalan.</div>';
            }
        }

        function getProductImage(p) {
            const placeholder = '../assets/uploads/placeholder/no-image.jpeg';
            if (!p.images) return placeholder;
            try {
                const imgs = JSON.parse(p.images || '[]');
                if (!Array.isArray(imgs) || !imgs.length) return placeholder;
                const firstImg = imgs[0];
                if (!firstImg || firstImg === 'no-image.png' || firstImg === 'no-image.jpeg') return placeholder;
                return `../assets/uploads/products/${firstImg}`;
            } catch (e) {
                return placeholder;
            }
        }

        function renderProducts(products) {
            const grid = document.getElementById('products-grid');
            if (!products.length) {
                grid.innerHTML = '<div class="empty-state">Belum ada produk. Klik "+ Add Product" untuk menambahkan.</div>';
                return;
            }
            grid.innerHTML = products.map(p => {
                const imgSrc = getProductImage(p);
                const imgHtml = `<img src="${imgSrc}" alt="${escHtml(p.name)}" class="product-thumb" onerror="this.onerror=null;this.src='../assets/uploads/placeholder/no-image.jpeg';" />`;

                return `
                    <div class="product-card">
                        <div class="product-img">
                            ${imgHtml}
                            <span class="product-status status-${p.status}">${p.status.toUpperCase()}</span>
                        </div>
                        <div class="product-info">
                            <h3>${escHtml(p.name)}</h3>
                            <div class="product-price">Rp ${Number(p.price).toLocaleString('id-ID')}</div>
                            <div class="product-meta">
                                <span>🏷️ ${escHtml(p.cat_name || 'Tanpa Kategori')}</span>
                                <span>ℹ️ ${escHtml(p.condition_type)}</span>
                            </div>
                        </div>
                        <div class="product-actions">
                            <button class="btn-edit-full" onclick='openEdit(${JSON.stringify(p)})'>Edit Details</button>
                            <button class="btn-delete-icon" onclick="deleteProduct(${p.id})" title="Hapus">🗑</button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function escHtml(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }

        // Preview gambar sebelum upload
        function setupImagePreview(inputId, previewId, max = 3) {
            document.getElementById(inputId).addEventListener('change', function() {
                const preview = document.getElementById(previewId);
                preview.innerHTML = '';
                const files = Array.from(this.files).slice(0, max);
                files.forEach(file => {
                    const reader = new FileReader();
                    reader.onload = e => {
                        const div = document.createElement('div');
                        div.className = 'preview-item';
                        div.innerHTML = `<img src="${e.target.result}" alt="preview">`;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        // Jalankan setup preview untuk kedua form
        setupImagePreview('imagesAdd', 'previewAdd');
        setupImagePreview('imagesEdit', 'previewEdit');

        function openEdit(p) {
            document.getElementById('edit_id').value = p.id;
            document.getElementById('edit_name').value = p.name;
            document.getElementById('edit_price').value = p.price;
            document.getElementById('edit_stock').value = p.stock;
            document.getElementById('edit_category_id').value = p.category_id || '';
            document.getElementById('edit_condition').value = p.condition_type;
            document.getElementById('edit_status').value = p.status;
            document.getElementById('edit_description').value = p.description || '';

            // === BARU: tampilkan gambar lama di preview edit ===
            const previewEdit = document.getElementById('previewEdit');
            previewEdit.innerHTML = '';
            try {
                const imgs = JSON.parse(p.images || '[]');
                imgs.forEach(img => {
                    if (img === 'no-image.png') return;
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `<img src="../assets/uploads/products/${escHtml(img)}" alt="foto">`;
                    previewEdit.appendChild(div);
                });
            } catch(e) {}
            // === Akhir BARU ===

            openModal('modalEdit');
        }

        // CREATE product
        document.getElementById('formAdd').addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            try {
                const res = await fetch(`${API_BASE}/products.php`, {
                    method: 'POST',
                    headers: { 'X-API-Key': API_KEY },
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    closeModal('modalAdd');
                    this.reset();
                    showFlash('success', 'Produk berhasil ditambahkan!');
                    loadProducts();
                } else {
                    showFlash('error', data.error || 'Gagal menambahkan produk.');
                }
            } catch (e) {
                showFlash('error', 'Terjadi kesalahan.');
            }
        });

        // UPDATE product
        document.getElementById('formEdit').addEventListener('submit', async function(e) {
            e.preventDefault();
            const id = document.getElementById('edit_id').value;

            const fd = new FormData();
            fd.append('_method', 'PUT');   // ← method spoofing agar PHP bisa baca $_FILES
            fd.append('name', document.getElementById('edit_name').value);
            fd.append('price', document.getElementById('edit_price').value);
            fd.append('stock', document.getElementById('edit_stock').value);
            fd.append('category_id', document.getElementById('edit_category_id').value);
            fd.append('condition_type', document.getElementById('edit_condition').value);
            fd.append('status', document.getElementById('edit_status').value);
            fd.append('description', document.getElementById('edit_description').value);

            const files = document.getElementById('imagesEdit').files;
            for (let f of files) fd.append('images[]', f);

            try {
                const res = await fetch(`${API_BASE}/products.php?id=${id}`, {
                    method: 'POST',   // ← ganti PUT menjadi POST
                    headers: { 'X-API-Key': API_KEY },
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    closeModal('modalEdit');
                    showFlash('success', 'Produk berhasil diperbarui!');
                    loadProducts();
                } else {
                    showFlash('error', data.error || 'Gagal memperbarui produk.');
                }
            } catch (e) {
                showFlash('error', 'Terjadi kesalahan.');
            }
        });

        async function deleteProduct(id) {
            if (!confirm('Hapus produk ini?')) return;
            try {
                const res = await fetch(`${API_BASE}/products.php?id=${id}`, {
                    method: 'DELETE',
                    headers: { 'X-API-Key': API_KEY }
                });
                const data = await res.json();
                if (data.success) {
                    showFlash('success', 'Produk berhasil dihapus!');
                    loadProducts();
                } else {
                    showFlash('error', data.error || 'Gagal menghapus.');
                }
            } catch (e) {
                showFlash('error', 'Terjadi kesalahan.');
            }
        }

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.style.display = 'none';
            }
        });

        loadProducts();
    </script>
</body>
</html>
