<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('seller');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Teloved Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
</head>
<body>
    <nav class="seller-nav">
        <div class="nav-brand"><span>♻️</span><a href="dashboard.php">TELOVED</a></div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="products.php">Manage Products</a>
            <a href="orders.php" class="active">Orders</a>
            <a href="profile.php">Profil</a>
        </div>
        <a href="../logout.php" class="nav-logout">Logout</a>
    </nav>

    <div class="seller-page">
        <div class="page-hd">
            <div><h1>Manajemen Order</h1><p>Kelola semua pesanan masuk dari buyer</p></div>
        </div>
        <div id="flash-area"></div>
        <div id="orders-container">
            <div class="loading">Memuat order...</div>
        </div>
    </div>

    <div class="modal-overlay" id="modalStatus" style="display:none">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Update Status Order</h3>
                <button onclick="closeModal()">✕</button>
            </div>
            <div id="modal-order-info" style="margin-bottom:16px;padding:12px;background:#f8f9fa;border-radius:8px;font-size:13px;"></div>
            <div class="form-group">
                <label>Status Order</label>
                <select id="newStatus">
                    <option value="accepted">✅ Diterima (Accepted)</option>
                    <option value="packed">📦 Dikemas (Packed)</option>
                    <option value="shipped">🚚 Dikirim (Shipped)</option>
                    <option value="delivered">✔️ Terkirim (Delivered)</option>
                    <option value="cancelled">❌ Batalkan</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nomor Resi (opsional)</label>
                <input type="text" id="trackingNum" placeholder="Contoh: JNE123456789">
            </div>
            <div class="form-group">
                <label>Catatan untuk Buyer</label>
                <textarea id="sellerNote" rows="2" placeholder="Catatan opsional..."></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeModal()">Batal</button>
                <button class="btn-primary" onclick="submitStatus()">Update</button>
            </div>
        </div>
    </div>

    <script>
        const API_KEY = '<?= htmlspecialchars($user['api_key']) ?>';
        let currentCheckoutId = null;

        function formatRupiah(num) {
            return 'Rp ' + Number(num).toLocaleString('id-ID');
        }

        function escHtml(str) {
            const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML;
        }

        const statusBadge = {
            pending: '<span style="background:#FEF9E7;color:#B7950B;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600">⏳ Pending</span>',
            confirmed: '<span style="background:#EBF5FB;color:#1A5276;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600">✅ Confirmed</span>',
            processing: '<span style="background:#EBF5FB;color:#1A5276;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600">🔄 Processing</span>',
            shipped: '<span style="background:#E8F8F5;color:#1E8449;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600">🚚 Shipped</span>',
            completed: '<span style="background:#EAFAF1;color:#1E8449;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600">✔️ Completed</span>',
            cancelled: '<span style="background:#FDEDEC;color:#DC3545;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600">❌ Cancelled</span>'
        };

        async function loadOrders() {
            const container = document.getElementById('orders-container');
            try {
                const res = await fetch('../api/orders.php', {
                    headers: { 'X-API-Key': API_KEY }
                });
                const data = await res.json();
                const orders = data.data || [];

                if (!orders.length) {
                    container.innerHTML = '<div class="empty">Belum ada order masuk.</div>';
                    return;
                }

                container.innerHTML = `
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,0.08);font-size:13px;">
                            <thead>
                                <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                                    <th style="padding:12px;text-align:left">#</th>
                                    <th style="padding:12px;text-align:left">Produk</th>
                                    <th style="padding:12px;text-align:left">Buyer</th>
                                    <th style="padding:12px;text-align:left">Total</th>
                                    <th style="padding:12px;text-align:left">Pembayaran</th>
                                    <th style="padding:12px;text-align:left">Status</th>
                                    <th style="padding:12px;text-align:left">Tanggal</th>
                                    <th style="padding:12px;text-align:left">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${orders.map((o, i) => `
                                    <tr style="border-bottom:1px solid #dee2e6;">
                                        <td style="padding:12px">${i+1}</td>
                                        <td style="padding:12px"><strong>${escHtml(o.product_name)}</strong><br><small style="color:#adb5bd">Qty: ${o.quantity}</small></td>
                                        <td style="padding:12px">${escHtml(o.buyer_name)}<br><small style="color:#adb5bd">${escHtml(o.buyer_phone || '-')}</small></td>
                                        <td style="padding:12px"><strong>${formatRupiah(o.total_price)}</strong></td>
                                        <td style="padding:12px">${escHtml(o.payment_method)}</td>
                                        <td style="padding:12px">${statusBadge[o.status] || o.status}</td>
                                        <td style="padding:12px">${new Date(o.created_at).toLocaleDateString('id-ID')}</td>
                                        <td style="padding:12px">
                                            ${!['completed','cancelled'].includes(o.status) ? `
                                                <button class="btn-primary" style="font-size:12px;padding:6px 12px" onclick='openStatusModal(${o.id}, "${escHtml(o.product_name)}", "${escHtml(o.buyer_name)}", "${o.status}")'>
                                                    Update Status
                                                </button>
                                            ` : '<span style="color:#adb5bd;font-size:12px">—</span>'}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } catch(e) {
                container.innerHTML = '<div class="empty">Gagal memuat order.</div>';
            }
        }

        function openStatusModal(checkoutId, productName, buyerName, currentStatus) {
            currentCheckoutId = checkoutId;
            document.getElementById('modal-order-info').innerHTML = `
                <strong>Produk:</strong> ${escHtml(productName)}<br>
                <strong>Buyer:</strong> ${escHtml(buyerName)}<br>
                <strong>Status sekarang:</strong> ${currentStatus}
            `;
            document.getElementById('trackingNum').value = '';
            document.getElementById('sellerNote').value = '';
            document.getElementById('modalStatus').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('modalStatus').style.display = 'none';
        }

        async function submitStatus() {
            const status = document.getElementById('newStatus').value;
            const tracking = document.getElementById('trackingNum').value;
            const note = document.getElementById('sellerNote').value;

            try {
                const res = await fetch(`../api/orders.php?id=${currentCheckoutId}`, {
                    method: 'PUT',
                    headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_status: status, tracking_number: tracking, seller_note: note })
                });
                const data = await res.json();
                if (data.success) {
                    closeModal();
                    showFlash('success', 'Status order berhasil diperbarui!');
                    loadOrders();
                } else {
                    showFlash('error', data.error || 'Gagal update.');
                }
            } catch(e) {
                showFlash('error', 'Terjadi kesalahan.');
            }
        }

        function showFlash(type, msg) {
            const div = document.getElementById('flash-area');
            div.innerHTML = `<div class="flash-msg flash-${type}">${msg} <button onclick="this.parentElement.remove()">✕</button></div>`;
            setTimeout(() => div.innerHTML = '', 4000);
        }

        loadOrders();
    </script>
</body>
</html>
