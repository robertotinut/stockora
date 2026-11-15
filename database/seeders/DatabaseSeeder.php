<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with realistic stock and purchase data.
     */
    public function run(): void
    {
        $this->call(SubscriptionPlanSeeder::class);

        $defaultCompany = \App\Models\Company::firstOrCreate(
            ['code' => 'DEFAULT'],
            ['name' => 'Default Company', 'is_active' => 1, 'account_status' => 'ACTIVE', 'access_type' => 'INTERNAL']
        );
        app(\App\Services\TenantContext::class)->setCompany($defaultCompany->id);

        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@stockora.com'],
            [
                'name' => 'Bagas Bagaskara',
                'password' => Hash::make('password'),
            ]
        );
        $admin->companies()->syncWithoutDetaching([$defaultCompany->id => ['role' => 'owner', 'is_default' => 1, 'is_active' => 1]]);

        $defaultWarehouse = \App\Models\Warehouse::firstOrCreate(
            ['company_id' => $defaultCompany->id, 'is_default' => 1],
            ['name' => 'Main Warehouse', 'code' => 'WH-MAIN', 'is_active' => 1]
        );

        // 2. Units
        $pcs = Unit::firstOrCreate(['name' => 'Pieces'], ['symbol' => 'pcs']);
        $box = Unit::firstOrCreate(['name' => 'Box'], ['symbol' => 'box']);
        $rim = Unit::firstOrCreate(['name' => 'Rim'], ['symbol' => 'rim']);
        $pack = Unit::firstOrCreate(['name' => 'Pack'], ['symbol' => 'pack']);
        $roll = Unit::firstOrCreate(['name' => 'Roll'], ['symbol' => 'roll']);

        // 3. Categories
        $catAtk = Category::firstOrCreate(['code' => 'CAT-ATK'], ['name' => 'Alat Tulis Kantor', 'description' => 'Kebutuhan administrasi dan ATK']);
        $catElec = Category::firstOrCreate(['code' => 'CAT-ELK'], ['name' => 'Elektronik & Gadget', 'description' => 'Perangkat keras & aksesoris IT']);
        $catPack = Category::firstOrCreate(['code' => 'CAT-PCK'], ['name' => 'Packing & Logistik', 'description' => 'Bahan pengemasan barang']);
        $catRaw = Category::firstOrCreate(['code' => 'CAT-RAW'], ['name' => 'Bahan Baku Cetak', 'description' => 'Bahan produksi cetakan']);

        // 4. Suppliers
        $sup1 = Supplier::firstOrCreate(['code' => 'SUP-001'], [
            'name' => 'PT Sumber Makmur Sentosa',
            'contact_person' => 'Budi Hartono',
            'phone' => '0812-8899-1234',
            'email' => 'sales@sumbermakmur.co.id',
            'address' => 'Kawasan Industri Pulogadung, Jakarta Timur',
        ]);

        $sup2 = Supplier::firstOrCreate(['code' => 'SUP-002'], [
            'name' => 'CV Mitra Abadi Paper',
            'contact_person' => 'Dewi Anggraeni',
            'phone' => '0813-7722-5566',
            'email' => 'order@mitrapaper.com',
            'address' => 'Jl. Soekarno Hatta No. 45, Bandung',
        ]);

        $sup3 = Supplier::firstOrCreate(['code' => 'SUP-003'], [
            'name' => 'PT Digital Tech Nusantara',
            'contact_person' => 'Rian Hidayat',
            'phone' => '0821-3344-9000',
            'email' => 'partnership@digitaltech.id',
            'address' => 'Ruko Mangga Dua Mall Blok C No. 12, Jakarta',
        ]);

        // 5. Products
        $productsData = [
            [
                'code' => 'PRD-A4-75',
                'name' => 'Kertas HVS A4 75gr PaperOne',
                'category_id' => $catAtk->id,
                'unit_id' => $rim->id,
                'purchase_price' => 45000,
                'selling_price' => 52000,
                'stock' => 140,
                'min_stock' => 30,
                'description' => 'Kertas A4 kualitas prima untuk print dan fotokopi',
            ],
            [
                'code' => 'PRD-PLP-GEL',
                'name' => 'Pulpen Gel Pilot G2 0.5mm Hitam',
                'category_id' => $catAtk->id,
                'unit_id' => $box->id,
                'purchase_price' => 145000,
                'selling_price' => 165000,
                'stock' => 12,
                'min_stock' => 15, // Warning low stock!
                'description' => 'Pulpen gel tinta hitam 1 box isi 12 pcs',
            ],
            [
                'code' => 'PRD-TNR-85A',
                'name' => 'Toner Cartridge HP LaserJet 85A',
                'category_id' => $catElec->id,
                'unit_id' => $pcs->id,
                'purchase_price' => 380000,
                'selling_price' => 460000,
                'stock' => 3,
                'min_stock' => 5, // Warning low stock!
                'description' => 'Cartridge toner original kompatibel printer HP',
            ],
            [
                'code' => 'PRD-LKB-BNG',
                'name' => 'Lakban Bening Daimaru 2 Inch 100 Yard',
                'category_id' => $catPack->id,
                'unit_id' => $roll->id,
                'purchase_price' => 12500,
                'selling_price' => 16000,
                'stock' => 85,
                'min_stock' => 25,
                'description' => 'Lakban opp tape perekat kuat untuk kardus',
            ],
            [
                'code' => 'PRD-KBL-HDM',
                'name' => 'Kabel HDMI 2.0 Ultra HD 4K (2 Meter)',
                'category_id' => $catElec->id,
                'unit_id' => $pcs->id,
                'purchase_price' => 48000,
                'selling_price' => 75000,
                'stock' => 4,
                'min_stock' => 10, // Warning low stock!
                'description' => 'Kabel braided gold plated resolusi 4K@60Hz',
            ],
            [
                'code' => 'PRD-MOU-WRL',
                'name' => 'Wireless Optical Mouse Logitech M185',
                'category_id' => $catElec->id,
                'unit_id' => $pcs->id,
                'purchase_price' => 135000,
                'selling_price' => 170000,
                'stock' => 28,
                'min_stock' => 10,
                'description' => 'Mouse nirkabel 2.4GHz plug and play baterai tahan lama',
            ],
            [
                'code' => 'PRD-BOX-M01',
                'name' => 'Kardus Box Polos Ukuran Sedang 30x20x15cm',
                'category_id' => $catPack->id,
                'unit_id' => $pack->id,
                'purchase_price' => 32000,
                'selling_price' => 42000,
                'stock' => 60,
                'min_stock' => 20,
                'description' => 'Kardus packing single wall isi 10 pcs/pack',
            ],
            [
                'code' => 'PRD-STP-MAX',
                'name' => 'Stapler Heavy Duty MAX HD-50',
                'category_id' => $catAtk->id,
                'unit_id' => $pcs->id,
                'purchase_price' => 58000,
                'selling_price' => 72000,
                'stock' => 2,
                'min_stock' => 8, // Warning low stock!
                'description' => 'Stapler besi kokoh kapasitas hingga 50 lembar',
            ],
        ];

        $createdProducts = [];
        foreach ($productsData as $pData) {
            $createdProducts[$pData['code']] = Product::updateOrCreate(
                ['code' => $pData['code']],
                $pData
            );
        }

        // 6. Realistic Purchases
        $po1 = Purchase::updateOrCreate(
            ['purchase_number' => 'PO-202611-001'],
            [
                'supplier_id' => $sup2->id,
                'user_id' => $admin->id,
                'date' => now()->subDays(5)->format('Y-m-d'),
                'total_amount' => 4500000,
                'status' => 'received',
                'payment_status' => 'paid',
                'notes' => 'Pengadaan rutin stok kertas HVS untuk divisi cetak',
            ]
        );

        PurchaseItem::updateOrCreate(
            ['purchase_id' => $po1->id, 'product_id' => $createdProducts['PRD-A4-75']->id],
            [
                'quantity' => 100,
                'unit_price' => 45000,
                'subtotal' => 4500000,
            ]
        );

        $po2 = Purchase::updateOrCreate(
            ['purchase_number' => 'PO-202611-002'],
            [
                'supplier_id' => $sup3->id,
                'user_id' => $admin->id,
                'date' => now()->subDays(2)->format('Y-m-d'),
                'total_amount' => 3450000,
                'status' => 'received',
                'payment_status' => 'paid',
                'notes' => 'Restock perlengkapan IT & mouse wireless',
            ]
        );

        PurchaseItem::updateOrCreate(
            ['purchase_id' => $po2->id, 'product_id' => $createdProducts['PRD-MOU-WRL']->id],
            [
                'quantity' => 20,
                'unit_price' => 135000,
                'subtotal' => 2700000,
            ]
        );
        PurchaseItem::updateOrCreate(
            ['purchase_id' => $po2->id, 'product_id' => $createdProducts['PRD-TNR-85A']->id],
            [
                'quantity' => 2,
                'unit_price' => 375000,
                'subtotal' => 750000,
            ]
        );

        $po3 = Purchase::updateOrCreate(
            ['purchase_number' => 'PO-202611-003'],
            [
                'supplier_id' => $sup1->id,
                'user_id' => $admin->id,
                'date' => now()->format('Y-m-d'),
                'total_amount' => 1650000,
                'status' => 'ordered',
                'payment_status' => 'partial',
                'notes' => 'Pesanan restock lakban dan kardus packing',
            ]
        );

        // 7. Realistic Stock Movements
        StockMovement::create([
            'warehouse_id' => $defaultWarehouse->id,
            'product_id' => $createdProducts['PRD-A4-75']->id,
            'type' => 'in',
            'quantity' => 100,
            'reference_type' => 'Purchase',
            'reference_id' => $po1->id,
            'stock_before' => 40,
            'stock_after' => 140,
            'user_id' => $admin->id,
            'notes' => 'Penerimaan PO-202611-001 dari CV Mitra Abadi',
        ]);

        StockMovement::create([
            'warehouse_id' => $defaultWarehouse->id,
            'product_id' => $createdProducts['PRD-MOU-WRL']->id,
            'type' => 'in',
            'quantity' => 20,
            'reference_type' => 'Purchase',
            'reference_id' => $po2->id,
            'stock_before' => 8,
            'stock_after' => 28,
            'user_id' => $admin->id,
            'notes' => 'Penerimaan PO-202611-002 dari PT Digital Tech',
        ]);

        StockMovement::create([
            'warehouse_id' => $defaultWarehouse->id,
            'product_id' => $createdProducts['PRD-A4-75']->id,
            'type' => 'out',
            'quantity' => 15,
            'reference_type' => 'Usage',
            'reference_id' => null,
            'stock_before' => 155,
            'stock_after' => 140,
            'user_id' => $admin->id,
            'notes' => 'Pengeluaran kebutuhan operasional kantor cabang',
        ]);

        StockMovement::create([
            'warehouse_id' => $defaultWarehouse->id,
            'product_id' => $createdProducts['PRD-PLP-GEL']->id,
            'type' => 'out',
            'quantity' => 3,
            'reference_type' => 'Sale',
            'reference_id' => null,
            'stock_before' => 15,
            'stock_after' => 12,
            'user_id' => $admin->id,
            'notes' => 'Distribusi unit ATK departemen finance',
        ]);

        StockMovement::create([
            'warehouse_id' => $defaultWarehouse->id,
            'product_id' => $createdProducts['PRD-TNR-85A']->id,
            'type' => 'adjustment',
            'quantity' => -1,
            'reference_type' => 'Opname',
            'reference_id' => null,
            'stock_before' => 4,
            'stock_after' => 3,
            'user_id' => $admin->id,
            'notes' => 'Hasil stock opname bulanan: 1 cartridge cacat fisik',
        ]);
    }
}
