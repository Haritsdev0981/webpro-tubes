# TELOVED - Preloved Marketplace Mahasiswa
## Panduan Setup & Instalasi

---

## 📦 Stack Teknologi
- **Backend**: PHP Native (tanpa framework)
- **Frontend**: HTML + CSS + Vanilla JS
- **Database**: MySQL (via PHPMyAdmin)
- **Server**: XAMPP / WAMP / Laragon

---

## 🚀 Cara Setup

### 1. Import Database
1. Buka **PHPMyAdmin** → http://localhost/phpmyadmin
2. Klik **"New"** → buat database baru: `teloved`
3. Pilih database `teloved` → tab **"Import"**
4. Pilih file `teloved.sql` → klik **"Go"**

### 2. Copy File ke htdocs
1. Copy folder `teloved/` ke:
   - XAMPP: `C:/xampp/htdocs/teloved/`
   - Laragon: `C:/laragon/www/teloved/`

### 3. Konfigurasi Database
Edit file `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // sesuaikan username MySQL
define('DB_PASS', '');           // sesuaikan password MySQL
define('DB_NAME', 'teloved');
define('BASE_URL', 'http://localhost/teloved');
```

### 4. Jalankan
Buka browser → http://localhost/teloved

---

## 🔑 Akun Demo (password semua: `password`)

| Role  | Email                 | Password  |
|-------|-----------------------|-----------|
| Admin | admin@teloved.id      | password  |
| Seller| aurel@teloved.id      | password  |
| Buyer | harits@teloved.id     | password  |

---

## 📂 Struktur Folder

```
teloved/
├── index.php                  ← Homepage publik
├── products.php               ← Listing produk publik
├── product-detail.php         ← Detail produk
├── login.php                  ← Login semua role
├── register.php               ← Registrasi
├── logout.php                 ← Logout
├── teloved.sql                ← File database
├── .htaccess
│
├── includes/
│   └── config.php             ← Konfigurasi DB + helper functions
│
├── assets/
│   ├── css/
│   │   ├── auth.css           ← Styling login/register
│   │   ├── admin.css          ← Styling admin panel
│   │   ├── buyer.css          ← Styling buyer + publik
│   │   └── seller.css         ← Styling seller (import buyer.css)
│   ├── js/
│   │   └── admin.js           ← JS untuk admin panel
│   └── uploads/               ← Upload gambar produk
│
├── admin/
│   ├── dashboard.php          ← Dashboard admin
│   ├── users.php              ← CRUD 1: Manajemen User
│   ├── permissions.php        ← CRUD 2: Feature Permission
│   ├── products.php           ← Moderasi produk
│   ├── orders.php             ← Lihat semua transaksi
│   └── partials/
│       ├── sidebar.php
│       └── topbar.php
│
├── seller/
│   ├── dashboard.php          ← Dashboard seller
│   ├── products.php           ← CRUD 3: Kelola produk (JS+API)
│   ├── orders.php             ← CRUD 4: Kelola order (JS+API)
│   └── profile.php            ← CRUD 7: Profil seller
│
├── buyer/
│   ├── dashboard.php          ← Dashboard buyer
│   ├── wishlist.php           ← CRUD 5: Wishlist + Checkout (CRUD 8)
│   ├── orders.php             ← CRUD 8 view + CRUD 6: Review
│   └── profile.php            ← CRUD 7: Profil buyer
│
└── api/
    ├── products.php           ← REST API produk
    ├── orders.php             ← REST API checkout & order
    ├── wishlist.php           ← REST API wishlist
    ├── reviews.php            ← REST API ulasan
    └── profile.php            ← REST API profil
```

---

## 🗂️ Pembagian CRUD per Anggota

| Anggota  | CRUD             | File Utama                                     |
|----------|------------------|------------------------------------------------|
| Harits   | CRUD 1 (User)    | `admin/users.php`                              |
|          | CRUD 8 (Checkout)| `buyer/wishlist.php`, `api/orders.php`         |
| Aurel    | CRUD 2 (Permission)| `admin/permissions.php`                      |
|          | CRUD 7 (Profil)  | `buyer/profile.php`, `seller/profile.php`, `api/profile.php` |
| Ares     | CRUD 3 (Produk)  | `seller/products.php`, `api/products.php`      |
|          | CRUD 4 (Order)   | `seller/orders.php`                            |
| Clarisa  | CRUD 5 (Wishlist)| `buyer/wishlist.php`, `api/wishlist.php`       |
|          | CRUD 6 (Review)  | `buyer/orders.php`, `api/reviews.php`          |

---

## 🔐 Autentikasi

- **Admin**: Login via session PHP → `$_SESSION['user']`
- **Seller/Buyer**: Login via session + API Key untuk endpoint API
- **API Key**: Dikirim via header `X-API-Key: KEY_DISINI`

---

## 💡 Flow CRUD 8 (Checkout → Order)

```
Buyer browse produk
    ↓
Klik ❤️ Wishlist → simpan ke wishlist
    ↓
Klik 🛒 Checkout dari wishlist → isi form → POST /api/orders.php
    ↓
API cek feature_permissions (checkout harus allowed)
    ↓
Insert ke tabel checkouts + orders + kurangi stock
    ↓
Seller lihat di seller/orders.php → update status
    ↓
Status completed → buyer bisa beri review di buyer/orders.php
```

---

## ⚠️ Catatan Penting

1. Folder `assets/uploads/` harus bisa ditulis (permission 755 atau 777)
2. PHP versi minimal: 7.4 (untuk `match()` expression)
3. Semua password di-hash dengan `password_hash()` / `password_verify()`
4. API menggunakan autentikasi API Key, bukan session

---

*TELOVED — Dibuat untuk keperluan tugas kuliah Web Programming*
