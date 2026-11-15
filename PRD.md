(Product specification) (Version 1.0)
STOCKORA
Inventory, Purchasing & Business Operations Platform

Product Requirements Document · November 2026
:::writing{variant="document" id="63821"}
# STOCKORA
## Product Requirements Document (PRD)

**Product:** Stockora  
**Product Category:** SaaS – Inventory, Purchasing & Business Operations  
**Version:** 1.0  
**Status:** Initial Product Specification  
**Platform:** Web Application (Responsive / Mobile Friendly)  
**Backend:** Laravel  
**Database:** MySQL

---

# 1. PRODUCT OVERVIEW

## 1.1 Product Description

Stockora adalah platform manajemen persediaan dan pembelian berbasis cloud yang membantu bisnis mengelola stok barang, gudang, supplier, pembelian, dan distribusi barang dalam satu sistem terintegrasi.

Stockora dirancang untuk berbagai jenis bisnis:

- Distributor dan supplier barang.
- Toko grosir dan toko bahan bangunan.
- UMKM manufaktur dan percetakan.
- Retail dan bisnis dengan beberapa cabang.

Stockora bukan sekadar aplikasi pencatatan stok, tetapi juga menyediakan informasi dan rekomendasi untuk membantu pemilik bisnis mengambil keputusan terkait persediaan dan pembelian.

## 1.2 Vision

Menjadi platform inventory dan purchasing management yang mudah digunakan, terjangkau, dan dapat diadaptasi oleh berbagai jenis bisnis di Indonesia.

## 1.3 Mission

1. Mengurangi kesalahan pencatatan persediaan.
2. Mempermudah pemantauan stok secara real-time.
3. Membantu bisnis merencanakan pembelian barang.
4. Meningkatkan kontrol terhadap supplier dan pengeluaran pembelian.
5. Menyediakan informasi operasional yang mudah dipahami.
6. Mendukung pengelolaan banyak gudang dan cabang.

## 1.4 Product Positioning

**Manage Stock. Control Growth.**

One platform to manage your entire business inventory and purchasing operations.

---

# 2. TARGET MARKET

Stockora menggunakan pendekatan multi-industry dengan empat segmen utama.

## 2.1 Distributor & Supplier

Kebutuhan:
- Mengelola banyak produk dan SKU.
- Memantau persediaan gudang.
- Mengelola supplier dan harga pembelian.
- Mengelola purchase order.
- Memantau barang masuk dan keluar.

## 2.2 Wholesale & Building Material Stores

Kebutuhan:
- Mengelola berbagai jenis dan satuan barang.
- Mengelola stok harian.
- Mengontrol barang masuk dan keluar.
- Memantau stok minimum.
- Mengelola supplier dan pembelian.

## 2.3 Small Manufacturing & Printing Businesses

Kebutuhan:
- Mengelola persediaan bahan baku.
- Mengelola barang jadi.
- Memantau penggunaan bahan.
- Mengelola beberapa gudang.
- Memisahkan bahan baku dan produk jadi.

Catatan: Manufacturing di versi awal difokuskan pada inventory bahan baku dan barang jadi, bukan production planning atau Manufacturing Execution System.

## 2.4 Retail & Multi-Branch Businesses

Kebutuhan:
- Mengelola stok setiap cabang.
- Memantau stok terpusat.
- Transfer barang antar cabang.
- Melihat laporan persediaan.
- Mengelola hak akses pengguna berdasarkan cabang.

---

# 3. USER ROLES & ACCESS CONTROL

Stockora menggunakan role-based access control (RBAC).

## 3.1 Platform Super Admin

Administrator utama platform Stockora.

Permissions:
- Mengelola seluruh tenant.
- Mengelola paket langganan.
- Memantau status tenant.
- Mengelola konfigurasi platform.
- Memantau penggunaan sistem.
- Mengelola pengumuman dan informasi sistem.

## 3.2 Company Owner

Pemilik perusahaan yang berlangganan Stockora.

Permissions:
- Mengakses seluruh data perusahaan.
- Mengelola pengguna dan hak akses.
- Mengelola gudang dan cabang.
- Mengakses seluruh laporan.
- Mengatur konfigurasi perusahaan.
- Mengelola supplier dan pembelian.
- Melihat aktivitas pengguna.

## 3.3 Administrator

Mengelola operasional aplikasi perusahaan.

Permissions:
- Mengelola master data.
- Mengelola pengguna sesuai kewenangan.
- Mengelola transaksi.
- Mengakses laporan yang diizinkan.

## 3.4 Warehouse Staff

Permissions:
- Melihat persediaan.
- Mencatat barang masuk.
- Mencatat barang keluar.
- Melakukan stock opname sesuai izin.
- Memproses transfer barang.

## 3.5 Purchasing Staff

Permissions:
- Mengelola supplier.
- Membuat purchase request.
- Membuat purchase order.
- Memantau status pembelian.
- Mencatat penerimaan barang sesuai izin.

## 3.6 Viewer / Manager

Permissions:
- Melihat dashboard.
- Melihat laporan.
- Memantau persediaan.
- Melihat transaksi tanpa mengubah data.

Semua role harus mendukung konfigurasi permission yang lebih detail.

---

# 4. CORE MODULES

## 4.1 Authentication & Company Management

Features:
- Registration.
- Login and logout.
- Forgot password.
- Email verification.
- Company registration.
- Company profile.
- Company logo.
- Company address.
- Company tax information.
- Business type selection.
- Company settings.

Setiap perusahaan memiliki workspace dan data yang terisolasi.

## 4.2 Multi-Tenant Management

Stockora menggunakan arsitektur multi-tenant.

Requirements:
- Satu aplikasi melayani banyak perusahaan.
- Setiap perusahaan mempunyai tenant identifier.
- Data operasional harus terisolasi berdasarkan tenant.
- Pengguna hanya dapat mengakses perusahaan yang menjadi haknya.
- Setiap perusahaan dapat memiliki banyak gudang dan cabang.
- Pengguna dapat memiliki akses ke satu atau beberapa lokasi.
- Platform Super Admin dapat memantau tenant tanpa mencampur data operasional.

Tenant isolation wajib diterapkan pada database query, API, background jobs, exports, dan seluruh proses transaksi.

## 4.3 Dashboard

Dashboard harus menampilkan informasi operasional secara ringkas.

KPI:
- Total Products.
- Total Stock Quantity.
- Total Inventory Value.
- Low Stock Items.
- Out of Stock Items.
- Pending Purchase Orders.
- Total Suppliers.
- Recent Stock Movements.

Charts:
- Stock Movement Trends.
- Purchase Trends.
- Inventory Value Overview.
- Top Moving Products.
- Low Stock Overview.

Dashboard mendukung filter:
- Date Range.
- Warehouse.
- Branch.
- Product Category.

## 4.4 Product Management

Features:
- Create product.
- Edit product.
- Delete/archive product.
- Product categories.
- Product SKU.
- Barcode.
- Product name.
- Product description.
- Product image.
- Brand.
- Unit of measurement.
- Purchase price.
- Selling price (optional).
- Minimum stock.
- Safety stock.
- Reorder quantity.
- Product status.

Additional requirements:
- SKU harus unik dalam tenant.
- Mendukung satuan barang.
- Mendukung konversi satuan, misalnya box ke pcs.
- Mendukung variasi produk jika diperlukan.
- Mendukung kategori bahan baku dan barang jadi.

## 4.5 Warehouse Management

Features:
- Create warehouse.
- Edit warehouse.
- Warehouse code.
- Warehouse address.
- Warehouse manager.
- Warehouse status.
- Warehouse inventory overview.

Setiap gudang memiliki pencatatan stok tersendiri.

## 4.6 Inventory Management

Core inventory features:

### Stock In
Mencatat barang masuk dari:
- Purchase Order.
- Manual stock receipt.
- Stock adjustment.
- Warehouse transfer.

### Stock Out
Mencatat barang keluar dari:
- Sales or delivery.
- Internal usage.
- Material consumption.
- Warehouse transfer.
- Stock adjustment.

### Stock Adjustment
Mengelola koreksi stok dengan:
- Adjustment reason.
- Previous quantity.
- New quantity.
- Difference.
- User.
- Timestamp.

### Stock Transfer
Memindahkan persediaan antar gudang atau cabang.

Transfer workflow:
1. Create transfer request.
2. Select source warehouse.
3. Select destination warehouse.
4. Select products and quantities.
5. Submit transfer.
6. Approve transfer if required.
7. Process shipment.
8. Confirm receipt.
9. Update stock balances.

### Stock Opname

Features:
- Create stock opname session.
- Physical stock counting.
- System stock comparison.
- Variance calculation.
- Adjustment approval.
- Opname history.

### Inventory Ledger

Setiap perubahan stok wajib menghasilkan inventory movement record.

Inventory ledger harus menyimpan:
- Product.
- Warehouse.
- Movement type.
- Reference document.
- Quantity in.
- Quantity out.
- Balance after transaction.
- Transaction date.
- Created by.

Stok tidak boleh berubah tanpa transaksi yang tercatat dalam ledger.

## 4.7 Supplier Management

Features:
- Supplier profile.
- Supplier code.
- Company name.
- Contact person.
- Phone number.
- WhatsApp number.
- Email.
- Address.
- Tax information.
- Payment terms.
- Supplier status.
- Supplier product catalog.
- Supplier purchase history.

Additional features:
- Supplier price history.
- Average purchase price.
- Supplier performance.
- Lead time history.

## 4.8 Purchasing Management

Purchasing menjadi salah satu fitur utama Stockora.

### Purchase Request (PR)

Features:
- Create purchase request.
- Select products.
- Request quantity.
- Request date.
- Required date.
- Reason for purchase.
- Requesting department.
- Approval workflow.

### Purchase Order (PO)

Features:
- Generate PO number.
- Select supplier.
- Select warehouse destination.
- Add products.
- Purchase quantity.
- Unit price.
- Discount.
- Tax.
- Shipping cost.
- Total amount.
- Payment terms.
- Expected delivery date.
- PO status.

PO statuses:
- Draft.
- Pending Approval.
- Approved.
- Ordered.
- Partially Received.
- Fully Received.
- Cancelled.

### Goods Receipt (GRN)

Features:
- Receive goods against PO.
- Record received quantities.
- Record rejected quantities.
- Partial receiving.
- Record damaged items.
- Attach supporting documents.
- Update inventory automatically after confirmation.

Receiving must support partial deliveries and prevent receiving quantities beyond the remaining approved quantity without authorized override.

### Purchase History

Reports:
- Purchase by supplier.
- Purchase by product.
- Purchase by period.
- Purchase value.
- Outstanding purchase orders.
- Purchase price history.

## 4.9 Smart Reorder Recommendation

Fitur unggulan Stockora.

Sistem memberikan rekomendasi pembelian berdasarkan:
- Current stock.
- Reserved stock.
- Incoming stock.
- Average daily usage.
- Supplier lead time.
- Safety stock.
- Reorder point.

Initial calculation:

Reorder Point = Average Daily Usage × Lead Time + Safety Stock

Suggested Purchase Quantity:

Suggested Quantity = Target Stock Level − Projected Available Stock

Hasil rekomendasi:
- Product.
- Current stock.
- Projected available stock.
- Estimated stock-out date.
- Reorder point.
- Suggested purchase quantity.
- Preferred supplier.
- Estimated purchase cost.

Pengguna tetap memiliki kendali untuk menyetujui, mengubah, atau menolak rekomendasi.

## 4.10 Stock Alerts & Notifications

Notification types:
- Low stock.
- Out of stock.
- Reorder recommendation.
- Pending purchase approval.
- Purchase order overdue.
- Goods receipt pending.
- Stock transfer pending.
- Expiring products (future feature).

Notification channels:
- In-app notifications.
- Email.
- WhatsApp integration (future phase).

## 4.11 Reporting & Analytics

Reports:
- Stock Summary.
- Stock Card.
- Inventory Valuation.
- Stock Movement.
- Low Stock Report.
- Out of Stock Report.
- Stock Opname Report.
- Purchase Report.
- Supplier Report.
- Warehouse Report.
- Dead Stock Report.

Export:
- Excel.
- PDF.
- CSV.

Reports must support date range, warehouse, category, and product filters where relevant.

---

# 5. MULTI-INDUSTRY CONFIGURATION

Stockora harus menyediakan konfigurasi bisnis tanpa membuat aplikasi terpisah.

Business profiles:

### Distributor
- Multiple suppliers.
- Multiple warehouses.
- Bulk purchasing.
- Supplier price comparison.
- Stock transfers.

### Wholesale & Building Materials
- Multiple product units.
- Barcode support.
- Bulk quantity management.
- Reorder alerts.

### Manufacturing & Printing
- Raw material categories.
- Finished goods categories.
- Material usage recording.
- Material warehouse.
- Finished goods warehouse.

### Retail & Multi-Branch
- Multiple branches.
- Branch-level inventory.
- Stock transfers.
- Centralized reporting.
- Location-based access control.

Business profile berfungsi sebagai konfigurasi awal dan template dashboard. Seluruh fitur inti tetap dapat digunakan lintas kategori sesuai hak akses dan kebutuhan tenant.

---

# 6. UI/UX REQUIREMENTS

## 6.1 Design Direction

Stockora harus memiliki tampilan modern, minimalis, profesional, dan berorientasi pada produktivitas.

Design principles:
- Clean interface.
- Easy navigation.
- Clear data hierarchy.
- Responsive layout.
- Minimal unnecessary decoration.
- Consistent spacing.
- Readable typography.
- Fast transaction workflows.

## 6.2 Main Navigation

Sidebar structure:

**OVERVIEW**
- Dashboard

**INVENTORY**
- Products
- Categories
- Warehouses
- Stock Overview
- Stock Movements
- Stock Transfers
- Stock Opname

**PURCHASING**
- Purchase Requests
- Purchase Orders
- Goods Receipt
- Suppliers
- Reorder Recommendations

**REPORTS**
- Inventory Reports
- Purchase Reports
- Supplier Reports
- Stock Analytics

**MANAGEMENT**
- Users & Roles
- Branches
- Settings
- Activity Logs

Menu harus mengikuti permission pengguna.

## 6.3 UI Components

Reusable components:
- Data tables.
- Search and filters.
- Status badges.
- Confirmation dialogs.
- Modal forms.
- Dropdown selectors.
- Date pickers.
- Dashboard cards.
- Charts.
- Pagination.
- Empty states.
- Loading states.
- Toast notifications.

---

# 7. BUSINESS RULES

1. Setiap produk memiliki identitas SKU dalam satu tenant.
2. Semua transaksi stok harus memiliki referensi dan histori.
3. Stok tidak boleh berubah secara langsung tanpa inventory movement.
4. Transaksi yang sudah diposting tidak boleh dihapus sembarangan.
5. Koreksi transaksi harus menggunakan mekanisme reversal atau adjustment.
6. Purchase Order yang sudah disetujui harus memiliki audit trail.
7. Goods Receipt harus terhubung dengan Purchase Order apabila berasal dari pembelian.
8. Partial receiving wajib didukung.
9. Transfer barang tidak boleh menambah stok tujuan sebelum penerimaan dikonfirmasi.
10. Setiap gudang memiliki saldo persediaan tersendiri.
11. Setiap perubahan penting harus mencatat user dan timestamp.
12. Semua nilai uang menggunakan decimal precision, bukan floating-point arithmetic.
13. Stock transaction harus menggunakan database transaction dan concurrency protection.
14. Default inventory costing menggunakan Moving Weighted Average.
15. Negative stock dinonaktifkan secara default dan hanya dapat diaktifkan oleh administrator dengan pengaturan khusus.

---

# 8. NON-FUNCTIONAL REQUIREMENTS

## Performance
- Dashboard harus memiliki response time yang baik.
- Gunakan pagination untuk data besar.
- Gunakan database indexing.
- Gunakan queue untuk proses berat.
- Gunakan caching untuk data yang sering diakses.

## Security
- Secure authentication.
- Role-based permissions.
- Tenant data isolation.
- CSRF protection.
- Input validation.
- SQL injection protection.
- Rate limiting.
- Audit logging.
- Secure file uploads.
- Encrypted sensitive data.
- Backup and recovery strategy.

## Scalability
- Mendukung banyak tenant.
- Mendukung banyak warehouse.
- Mendukung banyak branch.
- Mendukung pertumbuhan transaksi.
- Mendukung integrasi API di masa depan.

## Compatibility
- Desktop browsers.
- Tablet.
- Mobile browsers.
- Responsive web design.

---

# 9. SAAS SUBSCRIPTION MODEL

Initial subscription proposal:

### Starter
Rp49.000 / month
- 1 warehouse.
- Basic inventory.
- Product management.
- Supplier management.
- Basic purchasing.
- Basic reports.

### Growth
Rp99.000 / month
- Up to 3 warehouses.
- Advanced inventory.
- Purchase request.
- Purchase order.
- Reorder recommendation.
- Advanced reports.
- Multiple users.

### Business
Rp199.000 / month
- Up to 10 warehouses.
- Multi-branch management.
- Advanced permissions.
- Approval workflows.
- Inventory analytics.
- Advanced reporting.

### Enterprise
Custom pricing.
- Custom workflows.
- Extended integrations.
- Higher usage limits.
- Priority support.
- Custom implementation.

Pricing and limits are initial hypotheses and must be validated with actual customers.

---

# 10. DEVELOPMENT ROADMAP

## Phase 1 — Foundation & MVP

Priority: Critical

- Laravel project setup.
- Authentication.
- Multi-tenant architecture.
- Company management.
- User and role management.
- Product management.
- Category management.
- Warehouse management.
- Inventory ledger.
- Stock in/out.
- Supplier management.
- Purchase order.
- Goods receipt.
- Basic dashboard.
- Stock alerts.

Goal: Deliver a usable inventory and purchasing product for the first pilot customers.

## Phase 2 — Smart Operations

- Purchase request workflow.
- Purchase approval.
- Smart reorder recommendations.
- Supplier price comparison.
- Stock opname.
- Stock transfer.
- Inventory valuation.
- Advanced reporting.
- Audit trail improvements.

Goal: Make Stockora a more intelligent purchasing and inventory management platform.

## Phase 3 — Multi-Branch & Industry Expansion

- Multi-branch management.
- Advanced warehouse permissions.
- Manufacturing material tracking.
- Dead stock analytics.
- Barcode scanning.
- Mobile-optimized warehouse workflows.
- Advanced business analytics.

Goal: Expand to more business categories and operational complexity.

## Phase 4 — Automation & Integrations

- WhatsApp notifications.
- API integrations.
- Accounting integrations.
- AI-assisted inventory analysis.
- Demand forecasting.
- Automated purchase planning.
- External commerce integrations.

Goal: Build a connected business operations ecosystem.

---

# 11. MVP SUCCESS METRICS

Initial product validation targets:

- 3 paying pilot companies.
- 10 active companies during early validation.
- At least 70% of pilot users actively record stock transactions.
- At least 50% of pilot companies use purchasing features.
- Reduce manual stock reconciliation effort.
- Reduce stock-out incidents for tracked products.
- Positive user feedback on ease of use.

These are proposed validation targets, not guaranteed outcomes.

---

# 12. OUT OF SCOPE FOR INITIAL MVP

To prevent scope creep, the following are excluded from Phase 1:

- Full accounting system.
- Payroll management.
- HR management.
- Full POS system.
- E-commerce marketplace.
- Manufacturing execution system.
- Production scheduling.
- Complex demand forecasting.
- AI chatbot.
- Advanced financial statements.

---

# 13. PRODUCT DEVELOPMENT PRINCIPLES

1. Build for real business workflows, not feature quantity.
2. Keep the application modular and extensible.
3. Prioritize data accuracy and inventory consistency.
4. Design every module for multi-tenant operation.
5. Build reusable components.
6. Avoid unnecessary complexity in the MVP.
7. Maintain audit trails for operational transactions.
8. Every feature must solve a measurable business problem.
9. Do not introduce AI features before reliable operational data exists.
10. Prioritize usability and customer validation over premature optimization.

---

# 14. FINAL PRODUCT DEFINITION

Stockora is a multi-tenant SaaS platform focused on inventory management, purchasing operations, supplier management, and warehouse visibility.

It serves distributors, wholesalers, building material stores, small manufacturers, printing businesses, retailers, and multi-branch businesses through a unified and configurable system.

**Core Value Proposition:**

"Stockora helps businesses control inventory, simplify purchasing, manage suppliers, and make smarter stock decisions — all from one platform."

**Product Tagline:**

Manage Stock. Control Growth.
