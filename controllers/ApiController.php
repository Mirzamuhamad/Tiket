<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SpreadsheetExporter.php';
require_once __DIR__ . '/../core/UserAuth.php';

class ApiController
{
    public function handle($method, $path)
    {
        try {
            if ($method === 'POST' && $path === '/auth/login') {
                $this->login();
            } elseif ($method === 'POST' && $path === '/auth/logout') {
                $this->logout();
            } elseif ($method === 'GET' && $path === '/auth/status') {
                $this->authStatus();
            } elseif (!$this->isAuthenticated()) {
                Response::error('Sesi login sudah habis. Silakan login ulang.', 401);
            } elseif ($method === 'GET' && $path === '/anggota') {
                $this->anggotaList();
            } elseif ($method === 'GET' && $path === '/anggota/detail') {
                $this->anggotaDetail();
            } elseif ($method === 'POST' && $path === '/anggota/save') {
                $this->anggotaSave();
            } elseif ($method === 'POST' && $path === '/anggota/import') {
                $this->anggotaImport();
            } elseif ($method === 'DELETE' && $path === '/anggota/delete') {
                $this->anggotaDelete();
            } elseif ($method === 'GET' && $path === '/tiket') {
                $this->tiketList();
            } elseif ($method === 'GET' && $path === '/tiket/detail') {
                $this->tiketDetail();
            } elseif ($method === 'POST' && $path === '/tiket/save') {
                $this->tiketSave();
            } elseif ($method === 'DELETE' && $path === '/tiket/delete') {
                $this->tiketDelete();
            } elseif ($method === 'GET' && $path === '/users') {
                $this->userList();
            } elseif ($method === 'POST' && $path === '/users/save') {
                $this->userSave();
            } elseif ($method === 'DELETE' && $path === '/users/delete') {
                $this->userDelete();
            } elseif ($method === 'POST' && $path === '/pembelian/save') {
                $this->pembelianSave();
            } elseif ($method === 'GET' && $path === '/dashboard/summary') {
                $this->dashboardSummary();
            } elseif ($method === 'GET' && $path === '/dashboard/chart') {
                $this->dashboardChart();
            } elseif ($method === 'GET' && $path === '/laporan') {
                $this->laporan(false);
            } elseif ($method === 'GET' && $path === '/laporan/export') {
                $this->laporan(true);
            } else {
                Response::error('Endpoint tidak ditemukan.', 404);
            }
        } catch (PDOException $exception) {
            Response::error('Database error: ' . $exception->getMessage(), 500);
        } catch (Exception $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    private function isAuthenticated()
    {
        if (empty($_SESSION['admin_logged_in'])) {
            return false;
        }
        if (empty($_SESSION['admin_user_id']) && !empty($_SESSION['admin_name'])) {
            $user = Database::fetch(
                'SELECT id, nama, username FROM users WHERE username = ? AND status = 1',
                array($_SESSION['admin_name'])
            );
            if ($user) {
                $_SESSION['admin_user_id'] = (int) $user['id'];
                $_SESSION['admin_display_name'] = $user['nama'];
                $_SESSION['admin_name'] = $user['username'];
            }
        }
        return true;
    }

    private function login()
    {
        $input = $this->input();
        $username = isset($input['username']) ? trim($input['username']) : '';
        $password = isset($input['password']) ? (string) $input['password'] : '';
        $user = UserAuth::authenticate($username, $password);

        if ($user) {
            UserAuth::loginSession($user);
            Response::ok(array(
                'id' => (int) $user['id'],
                'nama' => $user['nama'],
                'username' => $user['username'],
            ), 'Login berhasil.');
        }

        Response::error('Username atau password salah.', 401);
    }

    private function logout()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        Response::ok(array(), 'Logout berhasil.');
    }

    private function authStatus()
    {
        Response::ok(array(
            'authenticated' => $this->isAuthenticated(),
            'id' => isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null,
            'nama' => isset($_SESSION['admin_display_name']) ? $_SESSION['admin_display_name'] : null,
            'username' => isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : null,
        ));
    }

    private function input()
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
        return $_POST;
    }

    private function required($value, $label)
    {
        if ($value === null || trim((string) $value) === '') {
            throw new Exception($label . ' wajib diisi.');
        }
        return trim((string) $value);
    }

    private function anggotaList()
    {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $activeOnly = isset($_GET['active']) && $_GET['active'] === '1';
        $where = array();
        $params = array();

        if ($search !== '') {
            $where[] = '(no_anggota LIKE ? OR nama LIKE ? OR no_hp LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($activeOnly) {
            $where[] = 'status = 1';
        }

        $sql = 'SELECT id, no_anggota, nama, alamat, no_hp, status, created_at FROM anggota';
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY nama ASC';

        Response::ok(Database::fetchAll($sql, $params));
    }

    private function anggotaDetail()
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $noAnggota = isset($_GET['no_anggota']) ? trim($_GET['no_anggota']) : '';

        if ($id > 0) {
            $row = Database::fetch('SELECT * FROM anggota WHERE id = ?', array($id));
        } elseif ($noAnggota !== '') {
            $row = Database::fetch('SELECT * FROM anggota WHERE no_anggota = ?', array($noAnggota));
        } else {
            throw new Exception('Parameter anggota tidak valid.');
        }

        if (!$row) {
            Response::error('Anggota tidak ditemukan.', 404);
        }
        Response::ok($row);
    }

    private function anggotaSave()
    {
        $input = $this->input();
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $noAnggota = $this->required(isset($input['no_anggota']) ? $input['no_anggota'] : null, 'Nomor anggota');
        $nama = $this->required(isset($input['nama']) ? $input['nama'] : null, 'Nama anggota');
        $alamat = isset($input['alamat']) ? trim($input['alamat']) : '';
        $noHp = isset($input['no_hp']) ? trim($input['no_hp']) : '';
        $status = isset($input['status']) ? (int) $input['status'] : 1;

        if ($id > 0) {
            Database::execute(
                'UPDATE anggota SET no_anggota = ?, nama = ?, alamat = ?, no_hp = ?, status = ? WHERE id = ?',
                array($noAnggota, $nama, $alamat, $noHp, $status, $id)
            );
        } else {
            Database::execute(
                'INSERT INTO anggota (no_anggota, nama, alamat, no_hp, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                array($noAnggota, $nama, $alamat, $noHp, $status)
            );
            $id = (int) Database::pdo()->lastInsertId();
        }

        Response::ok(Database::fetch('SELECT * FROM anggota WHERE id = ?', array($id)), 'Data anggota berhasil disimpan.');
    }

    private function anggotaImport()
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File import wajib dipilih.');
        }

        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('File import tidak bisa dibaca.');
        }

        $pdo = Database::pdo();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rowNumber = 0;

        $pdo->beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $rowNumber++;
                if (count($row) === 1 && strpos($row[0], ';') !== false) {
                    $row = str_getcsv($row[0], ';');
                }

                $row = array_map('trim', $row);
                if ($rowNumber === 1 && isset($row[0]) && strtolower($row[0]) === 'no_anggota') {
                    continue;
                }

                $noAnggota = isset($row[0]) ? trim($row[0]) : '';
                $nama = isset($row[1]) ? trim($row[1]) : '';
                $alamat = isset($row[2]) ? trim($row[2]) : '';
                $noHp = isset($row[3]) ? trim($row[3]) : '';
                $statusRaw = isset($row[4]) ? strtolower(trim($row[4])) : '1';
                $status = in_array($statusRaw, array('0', 'tidak', 'nonaktif', 'inactive'), true) ? 0 : 1;

                if ($noAnggota === '' || $nama === '') {
                    $skipped++;
                    continue;
                }

                $existing = Database::fetch('SELECT id FROM anggota WHERE no_anggota = ?', array($noAnggota));
                if ($existing) {
                    Database::execute(
                        'UPDATE anggota SET nama = ?, alamat = ?, no_hp = ?, status = ? WHERE id = ?',
                        array($nama, $alamat, $noHp, $status, $existing['id'])
                    );
                    $updated++;
                } else {
                    Database::execute(
                        'INSERT INTO anggota (no_anggota, nama, alamat, no_hp, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                        array($noAnggota, $nama, $alamat, $noHp, $status)
                    );
                    $created++;
                }
            }
            fclose($handle);
            $pdo->commit();
        } catch (Exception $exception) {
            fclose($handle);
            $pdo->rollBack();
            throw $exception;
        }

        Response::ok(array(
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ), 'Import anggota selesai.');
    }

    private function anggotaDelete()
    {
        $input = $this->input();
        $id = isset($input['id']) ? (int) $input['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
        if ($id <= 0) {
            throw new Exception('ID anggota tidak valid.');
        }
        Database::execute('DELETE FROM anggota WHERE id = ?', array($id));
        Response::ok(array('id' => $id), 'Data anggota berhasil dihapus.');
    }

    private function tiketList()
    {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $activeOnly = isset($_GET['active']) && $_GET['active'] === '1';
        $where = array();
        $params = array();

        if ($search !== '') {
            $where[] = 'nama_tiket LIKE ?';
            $params[] = '%' . $search . '%';
        }
        if ($activeOnly) {
            $where[] = 'status = 1';
        }

        $sql = 'SELECT id, nama_tiket, kategori, foto, harga, stock, status FROM jenis_tiket';
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY nama_tiket ASC';

        Response::ok(Database::fetchAll($sql, $params));
    }

    private function tiketDetail()
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            throw new Exception('ID tiket tidak valid.');
        }
        $row = Database::fetch('SELECT * FROM jenis_tiket WHERE id = ?', array($id));
        if (!$row) {
            Response::error('Jenis tiket tidak ditemukan.', 404);
        }
        Response::ok($row);
    }

    private function tiketSave()
    {
        $input = $this->input();
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $nama = $this->required(isset($input['nama_tiket']) ? $input['nama_tiket'] : null, 'Nama tiket');
        $kategori = $this->required(isset($input['kategori']) ? $input['kategori'] : null, 'Kategori tiket');
        $harga = isset($input['harga']) ? (float) $input['harga'] : 0;
        $stock = isset($input['stock']) ? (int) $input['stock'] : 0;
        $status = isset($input['status']) ? (int) $input['status'] : 1;

        if ($harga <= 0) {
            throw new Exception('Harga tiket harus lebih dari 0.');
        }
        if ($stock < 0) {
            throw new Exception('Stock tiket tidak boleh minus.');
        }

        $foto = $this->ticketPhotoUpload();

        if ($id > 0) {
            if ($foto !== null) {
                Database::execute(
                    'UPDATE jenis_tiket SET nama_tiket = ?, kategori = ?, foto = ?, harga = ?, stock = ?, status = ? WHERE id = ?',
                    array($nama, $kategori, $foto, $harga, $stock, $status, $id)
                );
            } else {
                Database::execute(
                    'UPDATE jenis_tiket SET nama_tiket = ?, kategori = ?, harga = ?, stock = ?, status = ? WHERE id = ?',
                    array($nama, $kategori, $harga, $stock, $status, $id)
                );
            }
        } else {
            Database::execute(
                'INSERT INTO jenis_tiket (nama_tiket, kategori, foto, harga, stock, status) VALUES (?, ?, ?, ?, ?, ?)',
                array($nama, $kategori, $foto, $harga, $stock, $status)
            );
            $id = (int) Database::pdo()->lastInsertId();
        }

        Response::ok(Database::fetch('SELECT * FROM jenis_tiket WHERE id = ?', array($id)), 'Data tiket berhasil disimpan.');
    }

    private function ticketPhotoUpload()
    {
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload foto tiket gagal.');
        }
        if ($_FILES['foto']['size'] > 3 * 1024 * 1024) {
            throw new Exception('Ukuran foto maksimal 3 MB.');
        }

        $tmpName = $_FILES['foto']['tmp_name'];
        $imageInfo = @getimagesize($tmpName);
        if (!$imageInfo) {
            throw new Exception('File foto harus berupa gambar.');
        }

        $mimeMap = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        );
        $mime = isset($imageInfo['mime']) ? $imageInfo['mime'] : '';
        if (!isset($mimeMap[$mime])) {
            throw new Exception('Format foto yang didukung: JPG, PNG, WEBP, GIF.');
        }

        $uploadDir = __DIR__ . '/../uploads/tickets';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $filename = 'ticket-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $mimeMap[$mime];
        $target = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $target)) {
            throw new Exception('Foto tiket tidak bisa disimpan.');
        }

        return 'uploads/tickets/' . $filename;
    }

    private function tiketDelete()
    {
        $input = $this->input();
        $id = isset($input['id']) ? (int) $input['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
        if ($id <= 0) {
            throw new Exception('ID tiket tidak valid.');
        }
        Database::execute('DELETE FROM jenis_tiket WHERE id = ?', array($id));
        Response::ok(array('id' => $id), 'Data tiket berhasil dihapus.');
    }

    private function userList()
    {
        $rows = Database::fetchAll(
            'SELECT id, nama, username, status, created_at, updated_at FROM users ORDER BY nama ASC'
        );
        Response::ok($rows);
    }

    private function userSave()
    {
        $input = $this->input();
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $nama = $this->required(isset($input['nama']) ? $input['nama'] : null, 'Nama user');
        $username = $this->required(isset($input['username']) ? $input['username'] : null, 'Username');
        $password = isset($input['password']) ? (string) $input['password'] : '';
        $status = isset($input['status']) ? (int) $input['status'] : 1;
        $currentUserId = isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : 0;

        if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
            throw new Exception('Username minimal 3 karakter dan hanya boleh huruf, angka, titik, underscore, atau strip.');
        }
        if ($id === 0 && trim($password) === '') {
            throw new Exception('Password wajib diisi untuk user baru.');
        }
        if (trim($password) !== '' && strlen($password) < 6) {
            throw new Exception('Password minimal 6 karakter.');
        }
        if ($id > 0 && $id === $currentUserId && $status !== 1) {
            throw new Exception('User yang sedang login tidak bisa dinonaktifkan.');
        }

        $existing = Database::fetch(
            'SELECT id FROM users WHERE username = ? AND id <> ?',
            array($username, $id)
        );
        if ($existing) {
            throw new Exception('Username sudah dipakai.');
        }

        if ($id > 0) {
            if (trim($password) !== '') {
                Database::execute(
                    'UPDATE users SET nama = ?, username = ?, password_hash = ?, status = ?, updated_at = NOW() WHERE id = ?',
                    array($nama, $username, password_hash($password, PASSWORD_DEFAULT), $status, $id)
                );
            } else {
                Database::execute(
                    'UPDATE users SET nama = ?, username = ?, status = ?, updated_at = NOW() WHERE id = ?',
                    array($nama, $username, $status, $id)
                );
            }
        } else {
            Database::execute(
                'INSERT INTO users (nama, username, password_hash, status, created_at) VALUES (?, ?, ?, ?, NOW())',
                array($nama, $username, password_hash($password, PASSWORD_DEFAULT), $status)
            );
            $id = (int) Database::pdo()->lastInsertId();
        }

        if ($id === $currentUserId) {
            $_SESSION['admin_name'] = $username;
            $_SESSION['admin_display_name'] = $nama;
        }

        Response::ok(
            Database::fetch('SELECT id, nama, username, status, created_at, updated_at FROM users WHERE id = ?', array($id)),
            'Data user berhasil disimpan.'
        );
    }

    private function userDelete()
    {
        $input = $this->input();
        $id = isset($input['id']) ? (int) $input['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
        $currentUserId = isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : 0;
        if ($id <= 0) {
            throw new Exception('ID user tidak valid.');
        }
        if ($id === $currentUserId) {
            throw new Exception('User yang sedang login tidak bisa dihapus.');
        }

        $activeCount = Database::fetch('SELECT COUNT(*) total FROM users WHERE status = 1');
        $target = Database::fetch('SELECT status FROM users WHERE id = ?', array($id));
        if (!$target) {
            throw new Exception('User tidak ditemukan.');
        }
        if ((int) $target['status'] === 1 && (int) $activeCount['total'] <= 1) {
            throw new Exception('Minimal harus ada satu user aktif.');
        }

        Database::execute('DELETE FROM users WHERE id = ?', array($id));
        Response::ok(array('id' => $id), 'Data user berhasil dihapus.');
    }

    private function pembelianSave()
    {
        $input = $this->input();
        $anggotaId = isset($input['anggota_id']) ? (int) $input['anggota_id'] : 0;
        $tiketId = isset($input['tiket_id']) ? (int) $input['tiket_id'] : 0;
        $qty = isset($input['qty']) ? (int) $input['qty'] : 0;
        $keterangan = isset($input['keterangan']) ? trim($input['keterangan']) : '';

        if ($anggotaId <= 0) {
            throw new Exception('Anggota wajib dipilih.');
        }
        if ($tiketId <= 0) {
            throw new Exception('Jenis tiket wajib dipilih.');
        }
        if ($qty <= 0) {
            throw new Exception('Qty harus lebih dari 0.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $anggota = Database::fetch('SELECT id FROM anggota WHERE id = ? AND status = 1', array($anggotaId));
            $tiket = Database::fetch('SELECT id, harga, stock FROM jenis_tiket WHERE id = ? AND status = 1 FOR UPDATE', array($tiketId));
            if (!$anggota) {
                throw new Exception('Anggota tidak aktif atau tidak ditemukan.');
            }
            if (!$tiket) {
                throw new Exception('Jenis tiket tidak aktif atau tidak ditemukan.');
            }
            if ((int) $tiket['stock'] < $qty) {
                throw new Exception('Stock tiket tidak mencukupi. Stock tersedia: ' . (int) $tiket['stock'] . '.');
            }

            $harga = (float) $tiket['harga'];
            $subtotal = $harga * $qty;
            $noTransaksi = $this->nextNoTransaksi();
            $stmt = $pdo->prepare('INSERT INTO pembelian_tiket (no_transaksi, tanggal, anggota_id, total_bayar, keterangan, created_at) VALUES (?, NOW(), ?, ?, ?, NOW())');
            $stmt->execute(array($noTransaksi, $anggotaId, $subtotal, $keterangan));
            $pembelianId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO pembelian_tiket_detail (pembelian_id, tiket_id, harga, qty, subtotal) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(array($pembelianId, $tiketId, $harga, $qty, $subtotal));
            $stmt = $pdo->prepare('UPDATE jenis_tiket SET stock = stock - ? WHERE id = ?');
            $stmt->execute(array($qty, $tiketId));
            $pdo->commit();

            Response::ok(array(
                'id' => $pembelianId,
                'no_transaksi' => $noTransaksi,
                'total_bayar' => $subtotal,
            ), 'Data berhasil disimpan.');
        } catch (Exception $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function nextNoTransaksi()
    {
        $prefix = 'TRX' . date('Ymd');
        $row = Database::fetch(
            'SELECT no_transaksi FROM pembelian_tiket WHERE no_transaksi LIKE ? ORDER BY no_transaksi DESC LIMIT 1 FOR UPDATE',
            array($prefix . '%')
        );
        $next = 1;
        if ($row && isset($row['no_transaksi'])) {
            $next = ((int) substr($row['no_transaksi'], -4)) + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function dashboardSummary()
    {
        $today = Database::fetch(
            'SELECT COALESCE(SUM(d.subtotal), 0) total, COALESCE(SUM(d.qty), 0) qty
             FROM pembelian_tiket p
             JOIN pembelian_tiket_detail d ON d.pembelian_id = p.id
             WHERE DATE(p.tanggal) = CURDATE()'
        );
        $month = Database::fetch(
            'SELECT COALESCE(SUM(d.subtotal), 0) total, COALESCE(SUM(d.qty), 0) qty
             FROM pembelian_tiket p
             JOIN pembelian_tiket_detail d ON d.pembelian_id = p.id
             WHERE YEAR(p.tanggal) = YEAR(CURDATE()) AND MONTH(p.tanggal) = MONTH(CURDATE())'
        );

        Response::ok(array(
            'today_total' => (float) $today['total'],
            'today_qty' => (int) $today['qty'],
            'month_total' => (float) $month['total'],
            'month_qty' => (int) $month['qty'],
        ));
    }

    private function dashboardChart()
    {
        $daily = Database::fetchAll(
            'SELECT DATE_FORMAT(p.tanggal, "%d %b") label, SUM(d.subtotal) total
             FROM pembelian_tiket p
             JOIN pembelian_tiket_detail d ON d.pembelian_id = p.id
             WHERE YEAR(p.tanggal) = YEAR(CURDATE()) AND MONTH(p.tanggal) = MONTH(CURDATE())
             GROUP BY DATE(p.tanggal)
             ORDER BY DATE(p.tanggal)'
        );
        $monthly = Database::fetchAll(
            'SELECT DATE_FORMAT(p.tanggal, "%b") label, SUM(d.subtotal) total
             FROM pembelian_tiket p
             JOIN pembelian_tiket_detail d ON d.pembelian_id = p.id
             WHERE YEAR(p.tanggal) = YEAR(CURDATE())
             GROUP BY MONTH(p.tanggal), DATE_FORMAT(p.tanggal, "%b")
             ORDER BY MONTH(p.tanggal)'
        );
        $topTickets = Database::fetchAll(
            'SELECT t.nama_tiket label, SUM(d.qty) total
             FROM pembelian_tiket_detail d
             JOIN jenis_tiket t ON t.id = d.tiket_id
             GROUP BY t.id, t.nama_tiket
             ORDER BY total DESC
             LIMIT 10'
        );

        Response::ok(array(
            'daily' => $daily,
            'monthly' => $monthly,
            'top_tickets' => $topTickets,
        ));
    }

    private function laporan($export)
    {
        $result = $this->laporanData();
        if (!$export) {
            Response::ok($result);
        }

        $month = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? str_pad((int) $_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
        $year = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');
        $filename = 'Pembelian_Tiket_' . $year . $month . '.xlsx';
        $rows = array();
        foreach ($result['rows'] as $row) {
            $rows[] = array(
                'tanggal' => $row['tanggal'],
                'no_transaksi' => $row['no_transaksi'],
                'no_anggota' => $row['no_anggota'],
                'nama_anggota' => $row['nama_anggota'],
                'jenis_tiket' => $row['jenis_tiket'],
                'harga' => $row['harga'],
                'qty' => $row['qty'],
                'total' => $row['total'],
                'keterangan' => $row['keterangan'],
            );
        }
        SpreadsheetExporter::download($filename, array(
            'Tanggal',
            'Nomor Transaksi',
            'Nomor Anggota',
            'Nama Anggota',
            'Jenis Tiket',
            'Harga',
            'Qty',
            'Total',
            'Keterangan',
        ), $rows);
    }

    private function laporanData()
    {
        $where = array();
        $params = array();

        if (isset($_GET['bulan']) && $_GET['bulan'] !== '') {
            $where[] = 'MONTH(p.tanggal) = ?';
            $params[] = (int) $_GET['bulan'];
        }
        if (isset($_GET['tahun']) && $_GET['tahun'] !== '') {
            $where[] = 'YEAR(p.tanggal) = ?';
            $params[] = (int) $_GET['tahun'];
        }
        if (isset($_GET['anggota_id']) && (int) $_GET['anggota_id'] > 0) {
            $where[] = 'a.id = ?';
            $params[] = (int) $_GET['anggota_id'];
        }
        if (isset($_GET['nama_anggota']) && trim($_GET['nama_anggota']) !== '') {
            $where[] = 'a.nama LIKE ?';
            $params[] = '%' . trim($_GET['nama_anggota']) . '%';
        }
        if (isset($_GET['tiket_id']) && (int) $_GET['tiket_id'] > 0) {
            $where[] = 't.id = ?';
            $params[] = (int) $_GET['tiket_id'];
        }

        $sql = 'SELECT
                    DATE_FORMAT(p.tanggal, "%Y-%m-%d %H:%i") tanggal,
                    p.no_transaksi,
                    a.no_anggota,
                    a.nama nama_anggota,
                    t.nama_tiket jenis_tiket,
                    d.harga,
                    d.qty,
                    d.subtotal total,
                    p.keterangan
                FROM pembelian_tiket p
                JOIN anggota a ON a.id = p.anggota_id
                JOIN pembelian_tiket_detail d ON d.pembelian_id = p.id
                JOIN jenis_tiket t ON t.id = d.tiket_id';

        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.tanggal DESC, p.id DESC';

        $rows = Database::fetchAll($sql, $params);
        $summary = array('total_transaksi' => 0, 'total_tiket' => 0, 'total_pendapatan' => 0);
        $seen = array();
        foreach ($rows as $row) {
            $seen[$row['no_transaksi']] = true;
            $summary['total_tiket'] += (int) $row['qty'];
            $summary['total_pendapatan'] += (float) $row['total'];
        }
        $summary['total_transaksi'] = count($seen);

        return array(
            'rows' => $rows,
            'summary' => $summary,
        );
    }
}
