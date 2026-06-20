-- ============================================
-- TELOVED DATABASE
-- Platform Marketplace Preloved Mahasiswa
-- ============================================

CREATE DATABASE IF NOT EXISTS teloved CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE teloved;

-- ============================================
-- TABLE: users
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
    phone VARCHAR(20),
    address TEXT,
    profile_photo VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    api_key VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: feature_permissions
-- CRUD 2: Manajemen Feature Permission (Admin)
-- ============================================
CREATE TABLE feature_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feature_name VARCHAR(100) NOT NULL COMMENT 'wishlist, checkout, review, chat',
    is_allowed TINYINT(1) DEFAULT 1,
    reason TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL COMMENT 'admin user id',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_feature (user_id, feature_name)
);

-- ============================================
-- TABLE: categories
-- ============================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(10) DEFAULT '🛍️',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: products
-- CRUD 3: Manajemen Listing Produk (Seller)
-- ============================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id INT DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(12,2) NOT NULL,
    condition_type ENUM('Baru','Seperti Baru','Bekas - Baik','Bekas - Cukup') DEFAULT 'Bekas - Baik',
    stock INT DEFAULT 1,
    status ENUM('active','inactive','sold') DEFAULT 'active',
    images TEXT COMMENT 'JSON array of image filenames',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ============================================
-- TABLE: wishlist
-- CRUD 5: Manajemen Wishlist (Buyer)
-- ============================================
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (buyer_id, product_id)
);

-- ============================================
-- TABLE: checkouts
-- CRUD 8: Checkout Produk (Buyer)
-- ============================================
CREATE TABLE checkouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    product_id INT NOT NULL,
    seller_id INT NOT NULL,
    quantity INT DEFAULT 1,
    total_price DECIMAL(12,2) NOT NULL,
    shipping_address TEXT NOT NULL,
    payment_method ENUM('transfer','cod','ewallet') DEFAULT 'transfer',
    note TEXT DEFAULT NULL,
    status ENUM('pending','confirmed','processing','shipped','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE: orders
-- CRUD 4: Manajemen Order/Transaksi (Seller)
-- ============================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkout_id INT NOT NULL,
    seller_id INT NOT NULL,
    status ENUM('new','accepted','packed','shipped','delivered','cancelled') DEFAULT 'new',
    tracking_number VARCHAR(100) DEFAULT NULL,
    seller_note TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (checkout_id) REFERENCES checkouts(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE: reviews
-- CRUD 6: Manajemen Ulasan/Rating (Buyer)
-- ============================================
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    product_id INT NOT NULL,
    checkout_id INT DEFAULT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_edited TINYINT(1) DEFAULT 0 COMMENT '0 = belum pernah diedit, 1 = sudah diedit sekali',
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (checkout_id) REFERENCES checkouts(id) ON DELETE SET NULL,
    UNIQUE KEY unique_review (buyer_id, product_id)
);

-- ============================================
-- TABLE: profiles
-- CRUD 7: Manajemen Profil (Buyer & Seller)
-- ============================================
CREATE TABLE profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    bio TEXT,
    university VARCHAR(150),
    major VARCHAR(150),
    whatsapp VARCHAR(20),
    instagram VARCHAR(100),
    banner_photo VARCHAR(255) DEFAULT NULL,
    total_sales INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Admin user (password: admin123)
INSERT INTO users (name, email, password, role, is_active, api_key) VALUES
('Admin Teloved', 'admin@teloved.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 'ADMIN-KEY-TELOVED-2025');

-- Demo Seller (password: seller123)
INSERT INTO users (name, email, password, role, is_active, api_key) VALUES
('Aurel Seller', 'aurel@teloved.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 1, 'SELLER-KEY-AUREL-2025');

-- Demo Buyer (password: buyer123)
INSERT INTO users (name, email, password, role, is_active, api_key) VALUES
('Harits Buyer', 'harits@teloved.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', 1, 'BUYER-KEY-HARITS-2025');

-- Categories
INSERT INTO categories (name, slug, icon) VALUES
('Tas', 'tas', '👜'),
('Sepatu', 'sepatu', '👟'),
('Atasan', 'atasan', '👕'),
('Bawahan', 'bawahan', '👖'),
('Dress', 'dress', '👗'),
('Aksesoris', 'aksesoris', '⌚'),
('Rok', 'rok', '🩱'),
('Elektronik', 'elektronik', '📱');

-- Default feature permissions for demo buyer
INSERT INTO feature_permissions (user_id, feature_name, is_allowed, updated_by) VALUES
(3, 'wishlist', 1, 1),
(3, 'checkout', 1, 1),
(3, 'review', 1, 1);

-- Demo products
INSERT INTO products (seller_id, category_id, name, description, price, condition_type, stock, status, images) VALUES
(2, 1, 'Tas Gunung Carrier 60L', 'Tas gunung berkualitas, merk Consina. Kondisi masih bagus, baru dipakai 2x hiking. Cocok untuk pendakian 3-4 hari.', 100000, 'Bekas - Baik', 1, 'active', '["no-image.png"]'),
(2, 3, 'Jaket Denim Vintage', 'Jaket denim vintage warna biru. Size M, cocok untuk badan S-M. Kondisi sangat baik, tidak ada cacat.', 250000, 'Seperti Baru', 1, 'active', '["no-image.png"]'),
(2, 6, 'Jam Tangan Casual', 'Jam tangan analog casual, merk Daniel Wellington KW Super. Masih berfungsi dengan baik.', 150000, 'Bekas - Baik', 1, 'active', '["no-image.png"]');

-- Profile for seller
INSERT INTO profiles (user_id, bio, university, major, whatsapp) VALUES
(2, 'Seller terpercaya di Teloved! Fast response dan barang berkualitas.', 'Universitas Indonesia', 'Teknik Informatika', '08123456789');
