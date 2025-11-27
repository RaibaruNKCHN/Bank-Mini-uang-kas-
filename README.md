# Bank-Mini (Aplikasi Uang Kas Sekolah)

aplikasi MiniBank untuk pengelolaan saldo dan transaksi kas pada lingkungan sekolah.

🎯 Tujuan
- Menyediakan antarmuka sederhana untuk melihat saldo, riwayat transaksi, dan mengelola akun siswa/staff.
- Fokus pada kemudahan penggunaan, keamanan operasi sensitif (mis. penghapusan akun), dan tampilan responsif untuk perangkat mobile.

🔗 Web demo / test

Klik tombol berikut untuk membuka aplikasi test (web):

<p>
  <a href="https://minibank-testapp.page.gd/" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:10px 16px;border-radius:8px;background:#0b74de;color:#fff;text-decoration:none;font-weight:600">⚡ Buka Webtest: minibank-testapp.page.gd</a>
</p>

---

📦 Ringkasan Fitur (fungsi utama)

1. Keamanan Hapus Akun
	- Fungsi: Mengamankan operasi penghapusan akun agar hanya bisa dilakukan oleh admin secara sengaja.
	- Implementasi: hanya menerima `POST`, verifikasi CSRF, cek peran `is_admin()`, mencegah self-delete, dan mencatat aksi ke log audit.

2. UX / Interaksi Tabel & Modal
	- Fungsi: Mengurangi kemungkinan penghapusan tak sengaja dan mempermudah interaksi pada perangkat kecil.
	- Implementasi: tombol aksi di tabel tampil sebagai ikon saja (icon-only) dengan `aria-label` untuk aksesibilitas; konfirmasi menggunakan modal in-app (`window.showConfirm()`); tombol hapus dipindah ke modal Update User.

3. Tampilan Transaksi Responsif
	- Fungsi: Menampilkan 5 transaksi terakhir secara ringkas dan mudah dibaca di desktop maupun mobile.
	- Implementasi: setiap entri transaksi dirender sebagai `.dt-item` (grid dua kolom di desktop; stack di mobile) dan tanggal dipindah di bawah pada layar sempit.

4. Refactor & Restrukturisasi
	- Fungsi: Mempermudah pemeliharaan dan pemisahan tanggung-jawab kode.
	- Implementasi: rapihkan struktur folder menjadi `minibank/assets/*` untuk aset front-end, `minibank/api/*` untuk endpoint, dan pemisahan fungsi di `minibank/assets/js/accounts.js`.

5. Icons & Aksesibilitas
	- Fungsi: Mempertahankan makna aksi saat teks disembunyikan (icon-only) dan mendukung pembaca layar.
	- Implementasi: SVG icon ditambahkan untuk `detail`, `update`, `hapus`; atribut `aria-label` ditambahkan pada tombol aksi.

---

🛠 Bahasa & Peran Kode 

- PHP: Backend API dan halaman server-rendered. Semua endpoint REST-like sederhana berada di `minibank/api/*` (mis. `user/hapus_user.php`, `transactions/fetch_rows.php`). Bertanggung jawab pada validasi sisi server, CSRF check, dan query database.
- JavaScript (vanilla): Interaksi klien, fetch/AJAX, modal in-app, pengikatan event, dan Partial UI refresh. File utama: `minibank/assets/js/accounts.js`, `auth.js`, `dashboard.js`.
- HTML/CSS: Markup halaman dan styling; `minibank/assets/css/style.css` berisi aturan responsive, tombol icon-only, dan styling modal konfirmasi.
- SQL: Skrip database/seed berada di `minibank/database/bmsmk.sql`.
- Shell / git hooks: `minibank/.githooks/` berisi hook pre-commit kecil bila tersedia.

---


🧩 Struktur 

```
bank_mini/
├─ minibank/
│  ├─ api/
│  │  ├─ admin/
│  │  │  ├─ admin_actions.php
│  │  │  └─ update_settings.php
│  │  ├─ auth/
│  │  │  └─ login.php
│  │  ├─ error/
│  │  │  └─ report.php
│  │  ├─ transactions/
│  │  │  ├─ fetch_rows.php
│  │  │  ├─ transaksi.php
│  │  │  └─ hapus_histori.php
│  │  └─ user/
│  │     ├─ create_user.php
│  │     ├─ fetch_users.php
│  │     ├─ get_detail.php
│  │     ├─ get_user.php
│  │     ├─ hapus_user.php
│  │     ├─ search_accounts.php
│  │     └─ update_user.php
│  ├─ assets/
│  │  ├─ css/
│  │  │  └─ style.css
│  │  ├─ js/
│  │  │  ├─ accounts.js
│  │  │  ├─ auth.js
│  │  │  └─ dashboard.js
│  │  └─ images/
│  │     ├─ logo.png
│  │     ├─ logo2.png
│  │     └─ logo62.png
│  ├─ admin/
│  │  └─ kelola_akun.php
│  ├─ auth/
│  │  ├─ login.php
│  │  ├─ logout.php
│  │  ├─ register.php
│  │  └─ dashboard.php
│  └─ includes/
│     ├─ config.php
│     ├─ db.php
│     ├─ error_handler.php
│     ├─ filter_bar.php
│     └─ settings.json
├─ database/
│  └─ bmsmk.sql
├─ .githooks/
│  └─ pre-commit 
└─ README.md
```
 

© edited by LTZ24


