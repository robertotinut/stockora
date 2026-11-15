<?php

return [
    'roles' => [
        'owner' => [
            'label' => 'Pemilik (Owner)',
            'description' => 'Otoritas penuh workspace termasuk pengelolaan tim, peran, dan transfer kepemilikan.',
        ],
        'admin' => [
            'label' => 'Admin',
            'description' => 'Akses administratif luas pada operasional, laporan, dan tim (kecuali tata kelola owner).',
        ],
        'manager' => [
            'label' => 'Manajer',
            'description' => 'Penanggung jawab operasional untuk persetujuan inventaris, pengadaan, dan audit stok.',
        ],
        'staff' => [
            'label' => 'Staf',
            'description' => 'Pelaksana operasional gudang harian untuk GRN, transfer, pengeluaran, dan draf penyesuaian.',
        ],
        'viewer' => [
            'label' => 'Pengamat (Viewer)',
            'description' => 'Akses baca-saja untuk melihat dasbor, posisi stok, dan laporan inventaris.',
        ],
    ],

    'modules' => [
        'dashboard' => 'Dasbor',
        'products' => 'Produk',
        'categories' => 'Kategori',
        'units' => 'Satuan & Konversi',
        'warehouses' => 'Gudang',
        'warehouse_stock' => 'Stok Gudang & Audit',
        'stock_movements' => 'Mutasi Stok',
        'stock_adjustments' => 'Penyesuaian Stok',
        'suppliers' => 'Pemasok',
        'purchase_orders' => 'Pesanan Pembelian (PO)',
        'grn' => 'Penerimaan Barang (GRN)',
        'stock_transfers' => 'Transfer Stok',
        'stock_issues' => 'Pengeluaran Stok',
        'inventory_reports' => 'Laporan Inventaris',
        'team_management' => 'Manajemen Tim',
        'company_settings' => 'Pengaturan Perusahaan',
    ],

    'actions' => [
        'view' => 'Lihat',
        'create' => 'Buat',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'approve' => 'Setujui / Posting / Reverse',
        'export' => 'Ekspor',
        'manage' => 'Kelola / Konfigurasi',
    ],
];
