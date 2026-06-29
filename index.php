<?php

session_start();

require_once __DIR__ . '/core/UserAuth.php';

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$basePath = $basePath === '/' ? '' : $basePath;
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($basePath !== '' && stripos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}

if ($requestPath === '') {
    $requestPath = '/';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strcasecmp($requestPath, '/login') === 0) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $user = UserAuth::authenticate($username, $password);

    if ($user) {
        UserAuth::loginSession($user);
        header('Location: ' . ($basePath === '' ? '/' : $basePath . '/'));
        exit;
    }

    $_SESSION['login_error'] = 'Username atau password salah.';
    header('Location: ' . ($basePath === '' ? '/' : $basePath . '/login'));
    exit;
}

if (stripos($requestPath, '/api') === 0) {
    require_once __DIR__ . '/controllers/ApiController.php';
    $apiPath = substr($requestPath, 4);
    $apiPath = $apiPath === '' ? '/' : $apiPath;
    $controller = new ApiController();
    $controller->handle($_SERVER['REQUEST_METHOD'], $apiPath);
    exit;
}

$accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
$requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) : '';
if ($requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false) {
    require_once __DIR__ . '/core/Response.php';
    Response::error('Endpoint AJAX tidak ditemukan: ' . $requestPath, 404);
}

$appBase = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
$isLoggedIn = !empty($_SESSION['admin_logged_in']);
$loginError = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Pembelian Tiket Manual</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo $appBase; ?>/assets/styles.css?v=20260629-6" rel="stylesheet">
</head>
<body class="<?php echo $isLoggedIn ? 'app-page' : 'login-page'; ?>">
<?php if (!$isLoggedIn): ?>
    <main class="login-shell">
        <section class="login-card">
            <div class="login-visual">
                <span class="brand-mark large">T</span>
                <p class="eyebrow">Dashboard Tiket</p>
                <h1>Masuk ke Sistem Penjualan Tiket</h1>
                <p>Kelola anggota, transaksi, laporan, dan dashboard penjualan dari satu halaman tanpa reload.</p>
            </div>
            <form id="loginForm" class="login-form" method="post" action="<?php echo $appBase; ?>/login">
                <div>
                    <p class="eyebrow">Admin Area</p>
                    <h2>Login</h2>
                </div>
<?php if ($loginError !== ''): ?>
                <div class="login-error"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
                <div>
                    <label class="form-label" for="loginUsername">Username</label>
                    <input class="form-control" id="loginUsername" name="username" autocomplete="username" value="admin" required>
                </div>
                <div>
                    <label class="form-label" for="loginPassword">Password</label>
                    <input class="form-control" id="loginPassword" name="password" type="password" autocomplete="current-password" value="admin123" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Masuk Dashboard</button>
                <p class="login-hint">Akun awal: admin / admin123. Kelola akun dari Master User.</p>
            </form>
        </section>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <button class="brand-mark brand-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-label="Tampilkan menu">T</button>
                <div class="brand-title">
                    <strong>Tiket Manual</strong>
                    <small>Admin Penjualan</small>
                </div>
            </div>
            <nav class="nav-menu">
                <button class="nav-link active" data-view="dashboardView" type="button"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span><span class="nav-text">Dashboard</span></button>
                <button class="nav-link" data-view="anggotaView" type="button"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Master Anggota</span></button>
                <button class="nav-link" data-view="tiketView" type="button"><span class="nav-icon"><i class="bi bi-ticket-perforated"></i></span><span class="nav-text">Jenis Tiket</span></button>
                <button class="nav-link" data-view="userView" type="button"><span class="nav-icon"><i class="bi bi-person-gear"></i></span><span class="nav-text">Master User</span></button>
                <button class="nav-link" data-view="pembelianView" type="button"><span class="nav-icon"><i class="bi bi-cart-check"></i></span><span class="nav-text">Pembelian Tiket</span></button>
                <button class="nav-link" data-view="laporanView" type="button"><span class="nav-icon"><i class="bi bi-file-earmark-bar-graph"></i></span><span class="nav-text">Laporan</span></button>
                <button class="nav-link logout-link" id="logoutBtn" type="button"><span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span><span class="nav-text">Logout</span></button>
            </nav>
        </aside>

        <main class="content">
            <section id="dashboardView" class="view-section active">
                <div class="hero-panel">
                    <div>
                        <p class="eyebrow">Ringkasan Real-Time</p>
                        <h2>Penjualan tiket hari ini dalam satu layar.</h2>
                    </div>
                    <button class="btn btn-primary" data-view-shortcut="pembelianView" type="button">Tambah Transaksi</button>
                </div>
                <div class="summary-grid">
                    <article class="summary-card">
                        <div>
                            <span>Total Penjualan Hari Ini</span>
                            <strong id="todayTotal">Rp 0</strong>
                        </div>
                        <span class="summary-icon" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
                    </article>
                    <article class="summary-card">
                        <div>
                            <span>Total Tiket Hari Ini</span>
                            <strong id="todayQty">0 Tiket</strong>
                        </div>
                        <span class="summary-icon" aria-hidden="true"><i class="bi bi-ticket-perforated"></i></span>
                    </article>
                    <article class="summary-card">
                        <div>
                            <span>Total Penjualan Bulan Ini</span>
                            <strong id="monthTotal">Rp 0</strong>
                        </div>
                        <span class="summary-icon" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                    </article>
                    <article class="summary-card">
                        <div>
                            <span>Total Tiket Bulan Ini</span>
                            <strong id="monthQty">0 Tiket</strong>
                        </div>
                        <span class="summary-icon" aria-hidden="true"><i class="bi bi-calendar2-week"></i></span>
                    </article>
                </div>

                <div class="chart-grid">
                    <section class="panel">
                        <div class="panel-heading">
                            <h2>Penjualan Harian</h2>
                        </div>
                        <canvas id="dailyChart" height="140"></canvas>
                    </section>
                    <section class="panel">
                        <div class="panel-heading">
                            <h2>Penjualan Bulanan</h2>
                        </div>
                        <canvas id="monthlyChart" height="140"></canvas>
                    </section>
                    <section class="panel wide">
                        <div class="panel-heading">
                            <h2>Tiket Terlaris</h2>
                        </div>
                        <canvas id="topTicketChart" height="110"></canvas>
                    </section>
                </div>
            </section>

            <section id="anggotaView" class="view-section">
                <div class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Master Anggota</h2>
                            <p>Kelola nomor anggota, nama, alamat, no HP, dan status aktif.</p>
                        </div>
                        <div class="panel-actions">
                            <button class="btn btn-light" id="importAnggotaBtn" type="button">Import Anggota</button>
                            <button class="btn btn-primary" id="addAnggotaBtn" type="button">Tambah Anggota</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="anggotaTable" class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th>No Anggota</th>
                                    <th>Nama</th>
                                    <th>No HP</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="tiketView" class="view-section">
                <div class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Jenis Tiket</h2>
                            <p>Kelola nama tiket, harga, dan status aktif.</p>
                        </div>
                        <button class="btn btn-primary" id="addTiketBtn" type="button">Tambah Tiket</button>
                    </div>
                    <div class="table-responsive">
                        <table id="tiketTable" class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Foto</th>
                                    <th>Nama Tiket</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="userView" class="view-section">
                <div class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Master User</h2>
                            <p>Kelola akun login dashboard dan status akses pengguna.</p>
                        </div>
                        <button class="btn btn-primary" id="addUserBtn" type="button">Tambah User</button>
                    </div>
                    <div class="table-responsive">
                        <table id="userTable" class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Status</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="pembelianView" class="view-section">
                <div class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Input Pembelian Tiket</h2>
                            <p>Pilih anggota dan tiket, total dihitung otomatis tanpa reload.</p>
                        </div>
                        <span class="pill" id="tanggalTransaksi"></span>
                    </div>
                    <form id="pembelianForm" class="form-grid">
                        <div class="field-span-2">
                            <label class="form-label" for="pembelianAnggota">Nomor Anggota</label>
                            <select class="form-select" id="pembelianAnggota" name="anggota_id" required></select>
                        </div>
                        <div>
                            <label class="form-label">Nama Anggota</label>
                            <input class="form-control" id="detailNama" readonly>
                        </div>
                        <div>
                            <label class="form-label">No HP</label>
                            <input class="form-control" id="detailHp" readonly>
                        </div>
                        <div class="field-span-2">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" id="detailAlamat" rows="2" readonly></textarea>
                        </div>
                        <div>
                            <label class="form-label" for="pembelianTiket">Jenis Tiket</label>
                            <select class="form-select" id="pembelianTiket" name="tiket_id" required></select>
                        </div>
                        <div>
                            <label class="form-label">Harga</label>
                            <input class="form-control" id="hargaTiket" readonly>
                        </div>
                        <div>
                            <label class="form-label">Stock Tersedia</label>
                            <input class="form-control" id="stockTiket" readonly>
                        </div>
                        <div>
                            <label class="form-label" for="qtyTiket">Qty</label>
                            <input class="form-control" id="qtyTiket" name="qty" type="number" min="1" value="1" required>
                        </div>
                        <div>
                            <label class="form-label">Subtotal</label>
                            <input class="form-control total-field" id="subtotalTiket" readonly>
                        </div>
                        <div class="field-span-2">
                            <label class="form-label" for="keteranganPembelian">Keterangan</label>
                            <textarea class="form-control" id="keteranganPembelian" name="keterangan" rows="3"></textarea>
                        </div>
                        <div class="field-actions">
                            <button class="btn btn-light" id="resetPembelianBtn" type="button">Reset</button>
                            <button class="btn btn-primary" type="submit">Simpan Transaksi</button>
                        </div>
                    </form>
                </div>
            </section>

            <section id="laporanView" class="view-section">
                <div class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Laporan Pembelian Tiket</h2>
                            <p>Filter laporan otomatis refresh menggunakan AJAX.</p>
                        </div>
                        <button class="btn btn-primary" id="exportLaporanBtn" type="button">Export Excel</button>
                    </div>
                    <div class="filter-grid">
                        <div>
                            <label class="form-label" for="filterBulan">Bulan</label>
                            <select class="form-select report-filter" id="filterBulan"></select>
                        </div>
                        <div>
                            <label class="form-label" for="filterTahun">Tahun</label>
                            <input class="form-control report-filter" id="filterTahun" type="number" min="2020">
                        </div>
                        <div>
                            <label class="form-label" for="filterAnggota">Nomor Anggota</label>
                            <select class="form-select report-filter" id="filterAnggota"></select>
                        </div>
                        <div>
                            <label class="form-label" for="filterNama">Nama Anggota</label>
                            <input class="form-control report-filter" id="filterNama" placeholder="Cari nama anggota">
                        </div>
                        <div>
                            <label class="form-label" for="filterTiket">Jenis Tiket</label>
                            <select class="form-select report-filter" id="filterTiket"></select>
                        </div>
                    </div>
                    <div class="report-summary">
                        <span><strong id="reportTransaksi">0</strong> Transaksi</span>
                        <span><strong id="reportTiket">0</strong> Tiket</span>
                        <span><strong id="reportPendapatan">Rp 0</strong> Pendapatan</span>
                    </div>
                    <div class="table-responsive">
                        <table id="laporanTable" class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>No Transaksi</th>
                                    <th>No Anggota</th>
                                    <th>Nama</th>
                                    <th>Jenis Tiket</th>
                                    <th>Harga</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="modal fade" id="anggotaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="anggotaForm">
                <div class="modal-header">
                    <h5 class="modal-title">Form Anggota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="anggotaId">
                    <label class="form-label" for="noAnggota">Nomor Anggota</label>
                    <input class="form-control mb-3" id="noAnggota" required>
                    <label class="form-label" for="namaAnggota">Nama Anggota</label>
                    <input class="form-control mb-3" id="namaAnggota" required>
                    <label class="form-label" for="alamatAnggota">Alamat</label>
                    <textarea class="form-control mb-3" id="alamatAnggota" rows="3"></textarea>
                    <label class="form-label" for="hpAnggota">No HP</label>
                    <input class="form-control mb-3" id="hpAnggota">
                    <label class="form-label" for="statusAnggota">Status Aktif</label>
                    <select class="form-select" id="statusAnggota">
                        <option value="1">Ya</option>
                        <option value="0">Tidak</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="importAnggotaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="importAnggotaForm">
                <div class="modal-header">
                    <h5 class="modal-title">Import Data Anggota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="anggotaFile">File CSV</label>
                    <input class="form-control" id="anggotaFile" name="file" type="file" accept=".csv,text/csv" required>
                    <p class="form-help">Format kolom: no_anggota, nama, alamat, no_hp, status. Baris header boleh dipakai.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="tiketModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="tiketForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Form Jenis Tiket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tiketId">
                    <label class="form-label" for="namaTiket">Nama Tiket</label>
                    <input class="form-control mb-3" id="namaTiket" required>
                    <label class="form-label" for="kategoriTiket">Kategori Tiket</label>
                    <input class="form-control mb-3" id="kategoriTiket" placeholder="Contoh: Reguler, VIP, Early Bird" required>
                    <label class="form-label" for="fotoTiket">Foto Tiket</label>
                    <input class="form-control mb-2" id="fotoTiket" type="file" accept="image/*">
                    <div class="ticket-photo-preview mb-3" id="fotoTiketPreview">Belum ada foto</div>
                    <label class="form-label" for="hargaTiketMaster">Harga Tiket</label>
                    <input class="form-control mb-3" id="hargaTiketMaster" type="number" min="1" step="0.01" required>
                    <label class="form-label" for="stockTiketMaster">Qty Stock Tiket</label>
                    <input class="form-control mb-3" id="stockTiketMaster" type="number" min="0" step="1" required>
                    <label class="form-label" for="statusTiket">Status Aktif</label>
                    <select class="form-select" id="statusTiket">
                        <option value="1">Ya</option>
                        <option value="0">Tidak</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title">Form User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="userId">
                    <label class="form-label" for="namaUser">Nama User</label>
                    <input class="form-control mb-3" id="namaUser" required>
                    <label class="form-label" for="usernameUser">Username</label>
                    <input class="form-control mb-3" id="usernameUser" autocomplete="off" required>
                    <label class="form-label" for="passwordUser">Password</label>
                    <input class="form-control mb-2" id="passwordUser" type="password" autocomplete="new-password">
                    <p class="form-help">Isi password untuk user baru. Saat edit, kosongkan jika password tidak diubah.</p>
                    <label class="form-label" for="statusUser">Status Aktif</label>
                    <select class="form-select" id="statusUser">
                        <option value="1">Ya</option>
                        <option value="0">Tidak</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <script>
        window.APP_BASE = "<?php echo $appBase; ?>";
        window.API_BASE = "<?php echo $appBase; ?>/api";
        window.IS_AUTHENTICATED = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
    </script>
<?php if ($isLoggedIn): ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
    <script src="<?php echo $appBase; ?>/assets/app.js?v=20260629-6"></script>
</body>
</html>
