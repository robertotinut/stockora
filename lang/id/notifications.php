<?php

return [
    'invitation' => [
        'subject' => 'Anda diundang bergabung ke :company di Stockora',
        'greeting' => 'Halo!',
        'line1' => ':inviter mengundang Anda untuk bergabung ke **:company** di Stockora dengan peran **:role**.',
        'personal_note' => 'Catatan pribadi: ":note"',
        'action' => 'Tinjau & Terima Undangan',
        'expires' => 'Undangan ini berlaku hingga :date.',
        'ignore' => 'Jika Anda tidak mengharapkan undangan ini, Anda dapat mengabaikan email ini.',
    ],

    'welcome' => [
        'subject' => 'Workspace :company Anda di Stockora telah siap',
        'greeting' => 'Selamat datang di Stockora, :name!',
        'line1' => 'Workspace perusahaan khusus telah disiapkan untuk **:company** (`:code`).',
        'action' => 'Atur Kata Sandi & Masuk',
        'expires' => 'Tautan aktivasi sekali pakai ini berlaku selama 60 menit.',
        'ignore' => 'Jika Anda tidak meminta pembuatan akun ini, segera hubungi dukungan Stockora.',
    ],

    'password_reset' => [
        'subject' => 'Atur Ulang Kata Sandi Stockora Anda',
        'greeting' => 'Halo :name,',
        'line1' => 'Kami menerima permintaan untuk mengatur ulang kata sandi akun Stockora Anda (:email).',
        'action' => 'Atur Ulang Kata Sandi',
        'expires' => 'Tautan atur ulang kata sandi ini berlaku selama :minutes menit dan hanya dapat digunakan satu kali.',
        'ignore' => 'Jika Anda tidak meminta pengaturan ulang kata sandi, Anda dapat mengabaikan email ini dengan aman — kata sandi Anda tidak berubah.',
    ],
];
