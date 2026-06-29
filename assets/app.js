(function () {
    const state = {
        anggota: [],
        tiket: [],
        users: [],
        selectedHarga: 0,
        selectedStock: 0,
        charts: {},
    };

    const rupiah = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    });

    const dateFormatter = new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'full',
        timeStyle: 'short',
    });

    let anggotaModal = null;
    let importAnggotaModal = null;
    let tiketModal = null;
    let userModal = null;

    function appBase() {
        if (window.APP_BASE) {
            return window.APP_BASE.replace(/\/$/, '');
        }

        const script = document.querySelector('script[src*="/assets/app.js"]');
        if (script) {
            const src = script.getAttribute('src').split('?')[0];
            return src.replace(/\/assets\/app\.js$/, '').replace(/\/$/, '');
        }

        const match = window.location.pathname.match(/^(.*?)(?:\/(?:dashboard|login|pembelian|anggota|tiket|user|users|laporan))?\/?$/);
        return match && match[1] ? match[1].replace(/\/$/, '') : '';
    }

    function apiUrl(path) {
        if (path.indexOf('/api') === 0 && window.API_BASE) {
            return window.API_BASE.replace(/\/$/, '') + path.substring(4);
        }
        return appBase() + path;
    }

    function assetUrl(path) {
        if (!path) {
            return '';
        }
        if (/^https?:\/\//i.test(path)) {
            return path;
        }
        return appBase() + '/' + String(path).replace(/^\/+/, '');
    }

    function apiFallbackUrl(path) {
        const script = document.querySelector('script[src*="/assets/app.js"]');
        if (!script) {
            return null;
        }

        const src = script.getAttribute('src').split('?')[0];
        const base = src.replace(/\/assets\/app\.js$/, '').replace(/\/$/, '');
        const url = base + path;
        return url === apiUrl(path) ? null : url;
    }

    function api(path, options) {
        const request = Object.assign({
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
        }, options || {});

        if (request.body instanceof FormData) {
            request.headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
        }

        return fetchJson(apiUrl(path), request).catch((error) => {
            const fallback = apiFallbackUrl(path);
            if (error && error.code === 'NON_JSON_RESPONSE' && fallback) {
                return fetchJson(fallback, request);
            }
            throw error;
        });
    }

    function fetchJson(url, request) {
        return fetch(url, request).then((response) => {
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                return response.text().then((text) => {
                    const preview = text.replace(/\s+/g, ' ').trim().slice(0, 120);
                    const error = new Error('API tidak mengembalikan JSON dari ' + url + '. Response: ' + preview);
                    error.code = 'NON_JSON_RESPONSE';
                    error.status = response.status;
                    error.url = url;
                    throw error;
                });
            }

            return response.json().then((json) => {
                if (!response.ok || !json.success) {
                    if (response.status === 401 && window.IS_AUTHENTICATED) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Sesi habis',
                            text: json.message || 'Silakan login ulang.',
                            confirmButtonColor: '#f97316',
                        }).then(() => window.location.reload());
                    }
                    throw new Error(json.message || 'Request gagal.');
                }
                return json.data;
            });
        });
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function statusBadge(status) {
        return Number(status) === 1
            ? '<span class="status-pill">Aktif</span>'
            : '<span class="status-pill inactive">Tidak Aktif</span>';
    }

    function notifySuccess(message) {
        if (typeof Swal === 'undefined') {
            alert(message);
            return;
        }
        Swal.fire({
            icon: 'success',
            title: message,
            timer: 1500,
            showConfirmButton: false,
        });
    }

    function notifyError(error) {
        if (typeof Swal === 'undefined') {
            alert((error && error.message) || error || 'Terjadi kesalahan.');
            return;
        }
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: error.message || error,
            confirmButtonColor: '#f97316',
        });
    }

    function destroyDataTable(selector) {
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().clear().destroy();
        }
    }

    function initLogin() {
        const form = document.getElementById('loginForm');
        const username = document.getElementById('loginUsername');
        const password = document.getElementById('loginPassword');
        if (!form || !username || !password) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Memproses...';
            }

            api('/api/auth/login', {
                method: 'POST',
                body: JSON.stringify({
                    username: username.value,
                    password: password.value,
                }),
            }).then(() => {
                window.location.href = apiUrl('/');
            }).catch((error) => {
                if (error && error.code === 'NON_JSON_RESPONSE') {
                    form.submit();
                    return;
                }
                notifyError(error);
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Masuk Dashboard';
                }
            });
        });
    }

    function switchView(viewId) {
        $('.view-section').removeClass('active');
        $('#' + viewId).addClass('active');
        $('.nav-menu .nav-link').removeClass('active');
        $('.nav-menu .nav-link[data-view="' + viewId + '"]').addClass('active');

        if (window.innerWidth <= 900) {
            $('body').removeClass('mobile-menu-open');
            $('#sidebarToggle').attr('aria-expanded', 'false');
        }

        if (viewId === 'dashboardView') {
            refreshDashboard();
        }
        if (viewId === 'laporanView') {
            refreshLaporan();
        }
    }

    function initNavigation() {
        $('[data-view], [data-view-shortcut]').on('click', function () {
            switchView($(this).data('view') || $(this).data('view-shortcut'));
        });

        $('#sidebarToggle').on('click', function () {
            if (window.innerWidth < 992) {
                $('body').toggleClass('mobile-menu-open');
                $(this).attr('aria-expanded', $('body').hasClass('mobile-menu-open') ? 'true' : 'false');
                return;
            }
            $('body').toggleClass('sidebar-collapsed');
        });

        $(window).on('resize', function () {
            if (window.innerWidth >= 992) {
                $('body').removeClass('mobile-menu-open');
                $('#sidebarToggle').attr('aria-expanded', 'false');
            }
        });

        $('#logoutBtn').on('click', function () {
            Swal.fire({
                icon: 'question',
                title: 'Logout dari sistem?',
                text: 'Sesi login saat ini akan diakhiri.',
                showCancelButton: true,
                confirmButtonText: 'Logout',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#f97316',
            }).then((result) => {
                if (!result.isConfirmed) return;
                api('/api/auth/logout', { method: 'POST', body: JSON.stringify({}) })
                    .then(() => window.location.reload())
                    .catch(notifyError);
            });
        });
    }

    function setDataTable(selector) {
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().clear().destroy();
        }
        $(selector).DataTable({
            pageLength: 10,
            lengthChange: false,
            autoWidth: false,
            responsive: true,
            language: {
                search: 'Cari:',
                zeroRecords: 'Data tidak ditemukan',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Belum ada data',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
            },
        });
    }

    function loadAnggota() {
        return api('/api/anggota').then((rows) => {
            state.anggota = rows;
            renderAnggotaTable();
            renderAnggotaOptions();
        });
    }

    function renderAnggotaTable() {
        destroyDataTable('#anggotaTable');
        const rows = state.anggota.map((item) => `
            <tr>
                <td>${escapeHtml(item.no_anggota)}</td>
                <td>
                    <strong>${escapeHtml(item.nama)}</strong>
                    <div class="text-muted small">${escapeHtml(item.alamat || '-')}</div>
                </td>
                <td>${escapeHtml(item.no_hp || '-')}</td>
                <td>${statusBadge(item.status)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-light edit-anggota" data-id="${item.id}" type="button">Edit</button>
                        <button class="btn btn-sm btn-outline-danger delete-anggota" data-id="${item.id}" type="button">Hapus</button>
                    </div>
                </td>
            </tr>
        `).join('');
        $('#anggotaTable tbody').html(rows);
        setDataTable('#anggotaTable');
    }

    function renderAnggotaOptions() {
        const activeOptions = state.anggota
            .filter((item) => Number(item.status) === 1)
            .map((item) => `<option value="${item.id}">${escapeHtml(item.no_anggota)} - ${escapeHtml(item.nama)}</option>`)
            .join('');
        const allOptions = state.anggota
            .map((item) => `<option value="${item.id}">${escapeHtml(item.no_anggota)} - ${escapeHtml(item.nama)}</option>`)
            .join('');
        $('#pembelianAnggota').html('<option value="">Pilih anggota</option>' + activeOptions);
        $('#filterAnggota').html('<option value="">Semua anggota</option>' + allOptions);
    }

    function loadTiket() {
        return api('/api/tiket').then((rows) => {
            state.tiket = rows;
            renderTiketTable();
            renderTiketOptions();
        });
    }

    function loadUsers() {
        return api('/api/users').then((rows) => {
            state.users = rows;
            renderUserTable();
        });
    }

    function renderTiketTable() {
        destroyDataTable('#tiketTable');
        const rows = state.tiket.map((item) => `
            <tr>
                <td>
                    ${item.foto ? `<img class="ticket-thumb" src="${assetUrl(item.foto)}" alt="${escapeHtml(item.nama_tiket)}">` : '<span class="ticket-thumb empty">No Foto</span>'}
                </td>
                <td>${escapeHtml(item.nama_tiket)}</td>
                <td>${escapeHtml(item.kategori || '-')}</td>
                <td>${rupiah.format(Number(item.harga))}</td>
                <td><span class="stock-badge ${Number(item.stock) <= 0 ? 'empty' : ''}">${escapeHtml(item.stock || 0)}</span></td>
                <td>${statusBadge(item.status)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-light edit-tiket" data-id="${item.id}" type="button">Edit</button>
                        <button class="btn btn-sm btn-outline-danger delete-tiket" data-id="${item.id}" type="button">Hapus</button>
                    </div>
                </td>
            </tr>
        `).join('');
        $('#tiketTable tbody').html(rows);
        setDataTable('#tiketTable');
    }

    function renderTiketOptions() {
        const activeOptions = state.tiket
            .filter((item) => Number(item.status) === 1 && Number(item.stock) > 0)
            .map((item) => `<option value="${item.id}">${escapeHtml(item.nama_tiket)} - ${escapeHtml(item.kategori || 'Umum')} (Stock ${escapeHtml(item.stock)})</option>`)
            .join('');
        const allOptions = state.tiket
            .map((item) => `<option value="${item.id}">${escapeHtml(item.nama_tiket)}</option>`)
            .join('');
        $('#pembelianTiket').html('<option value="">Pilih tiket</option>' + activeOptions);
        $('#filterTiket').html('<option value="">Semua tiket</option>' + allOptions);
    }

    function renderUserTable() {
        destroyDataTable('#userTable');
        const rows = state.users.map((item) => `
            <tr>
                <td>
                    <strong>${escapeHtml(item.nama)}</strong>
                    <div class="text-muted small">ID: ${escapeHtml(item.id)}</div>
                </td>
                <td>${escapeHtml(item.username)}</td>
                <td>${statusBadge(item.status)}</td>
                <td>${escapeHtml(item.created_at || '-')}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-light edit-user" data-id="${item.id}" type="button">Edit</button>
                        <button class="btn btn-sm btn-outline-danger delete-user" data-id="${item.id}" type="button">Hapus</button>
                    </div>
                </td>
            </tr>
        `).join('');
        $('#userTable tbody').html(rows);
        setDataTable('#userTable');
    }

    function initMasterForms() {
        $('#addAnggotaBtn').on('click', function () {
            $('#anggotaForm')[0].reset();
            $('#anggotaId').val('');
            anggotaModal.show();
        });

        $('#importAnggotaBtn').on('click', function () {
            $('#importAnggotaForm')[0].reset();
            importAnggotaModal.show();
        });

        $('#importAnggotaForm').on('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            api('/api/anggota/import', {
                method: 'POST',
                body: formData,
            }).then((result) => {
                importAnggotaModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Import anggota selesai',
                    html: `Baru: <strong>${result.created}</strong><br>Update: <strong>${result.updated}</strong><br>Lewati: <strong>${result.skipped}</strong>`,
                    confirmButtonColor: '#f97316',
                });
                return refreshRealtimeData({ anggota: true, laporan: true });
            }).catch(notifyError);
        });

        $('#anggotaTable').on('click', '.edit-anggota', function () {
            const item = state.anggota.find((row) => String(row.id) === String($(this).data('id')));
            if (!item) return;
            $('#anggotaId').val(item.id);
            $('#noAnggota').val(item.no_anggota);
            $('#namaAnggota').val(item.nama);
            $('#alamatAnggota').val(item.alamat);
            $('#hpAnggota').val(item.no_hp);
            $('#statusAnggota').val(item.status);
            anggotaModal.show();
        });

        $('#anggotaTable').on('click', '.delete-anggota', function () {
            const id = $(this).data('id');
            Swal.fire({
                icon: 'warning',
                title: 'Hapus anggota?',
                text: 'Data anggota akan dihapus dari master.',
                showCancelButton: true,
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#f97316',
            }).then((result) => {
                if (!result.isConfirmed) return;
                api('/api/anggota/delete', {
                    method: 'DELETE',
                    body: JSON.stringify({ id: id }),
                }).then(() => {
                    notifySuccess('Anggota berhasil dihapus');
                    return refreshRealtimeData({ anggota: true, laporan: true });
                }).catch(notifyError);
            });
        });

        $('#anggotaForm').on('submit', function (event) {
            event.preventDefault();
            api('/api/anggota/save', {
                method: 'POST',
                body: JSON.stringify({
                    id: $('#anggotaId').val(),
                    no_anggota: $('#noAnggota').val(),
                    nama: $('#namaAnggota').val(),
                    alamat: $('#alamatAnggota').val(),
                    no_hp: $('#hpAnggota').val(),
                    status: $('#statusAnggota').val(),
                }),
            }).then(() => {
                anggotaModal.hide();
                notifySuccess('Anggota berhasil disimpan');
                return refreshRealtimeData({ anggota: true, laporan: true });
            }).catch(notifyError);
        });

        $('#addTiketBtn').on('click', function () {
            $('#tiketForm')[0].reset();
            $('#tiketId').val('');
            $('#fotoTiketPreview').html('Belum ada foto');
            tiketModal.show();
        });

        $('#addUserBtn').on('click', function () {
            $('#userForm')[0].reset();
            $('#userId').val('');
            $('#passwordUser').attr('required', 'required');
            userModal.show();
        });

        $('#fotoTiket').on('change', function () {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) {
                $('#fotoTiketPreview').html('Belum ada foto');
                return;
            }
            const reader = new FileReader();
            reader.onload = function (event) {
                $('#fotoTiketPreview').html(`<img src="${event.target.result}" alt="Preview foto tiket">`);
            };
            reader.readAsDataURL(file);
        });

        $('#tiketTable').on('click', '.edit-tiket', function () {
            const item = state.tiket.find((row) => String(row.id) === String($(this).data('id')));
            if (!item) return;
            $('#tiketId').val(item.id);
            $('#namaTiket').val(item.nama_tiket);
            $('#kategoriTiket').val(item.kategori || '');
            $('#hargaTiketMaster').val(item.harga);
            $('#stockTiketMaster').val(item.stock);
            $('#statusTiket').val(item.status);
            $('#fotoTiket').val('');
            $('#fotoTiketPreview').html(item.foto ? `<img src="${assetUrl(item.foto)}" alt="${escapeHtml(item.nama_tiket)}">` : 'Belum ada foto');
            tiketModal.show();
        });

        $('#tiketTable').on('click', '.delete-tiket', function () {
            const id = $(this).data('id');
            Swal.fire({
                icon: 'warning',
                title: 'Hapus tiket?',
                text: 'Jenis tiket akan dihapus dari master.',
                showCancelButton: true,
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#f97316',
            }).then((result) => {
                if (!result.isConfirmed) return;
                api('/api/tiket/delete', {
                    method: 'DELETE',
                    body: JSON.stringify({ id: id }),
                }).then(() => {
                    notifySuccess('Tiket berhasil dihapus');
                    return refreshRealtimeData({ tiket: true, dashboard: true, laporan: true });
                }).catch(notifyError);
            });
        });

        $('#tiketForm').on('submit', function (event) {
            event.preventDefault();
            const formData = new FormData();
            formData.append('id', $('#tiketId').val());
            formData.append('nama_tiket', $('#namaTiket').val());
            formData.append('kategori', $('#kategoriTiket').val());
            formData.append('harga', $('#hargaTiketMaster').val());
            formData.append('stock', $('#stockTiketMaster').val());
            formData.append('status', $('#statusTiket').val());
            const file = $('#fotoTiket')[0].files[0];
            if (file) {
                formData.append('foto', file);
            }
            api('/api/tiket/save', {
                method: 'POST',
                body: formData,
            }).then(() => {
                tiketModal.hide();
                notifySuccess('Tiket berhasil disimpan');
                return refreshRealtimeData({ tiket: true, dashboard: true, laporan: true });
            }).catch(notifyError);
        });

        $('#userTable').on('click', '.edit-user', function () {
            const item = state.users.find((row) => String(row.id) === String($(this).data('id')));
            if (!item) return;
            $('#userId').val(item.id);
            $('#namaUser').val(item.nama);
            $('#usernameUser').val(item.username);
            $('#passwordUser').val('').removeAttr('required');
            $('#statusUser').val(item.status);
            userModal.show();
        });

        $('#userTable').on('click', '.delete-user', function () {
            const id = $(this).data('id');
            Swal.fire({
                icon: 'warning',
                title: 'Hapus user?',
                text: 'Akun login ini akan dihapus dari master user.',
                showCancelButton: true,
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#f97316',
            }).then((result) => {
                if (!result.isConfirmed) return;
                api('/api/users/delete', {
                    method: 'DELETE',
                    body: JSON.stringify({ id: id }),
                }).then(() => {
                    notifySuccess('User berhasil dihapus');
                    return refreshRealtimeData({ users: true });
                }).catch(notifyError);
            });
        });

        $('#userForm').on('submit', function (event) {
            event.preventDefault();
            api('/api/users/save', {
                method: 'POST',
                body: JSON.stringify({
                    id: $('#userId').val(),
                    nama: $('#namaUser').val(),
                    username: $('#usernameUser').val(),
                    password: $('#passwordUser').val(),
                    status: $('#statusUser').val(),
                }),
            }).then(() => {
                userModal.hide();
                notifySuccess('User berhasil disimpan');
                $('#passwordUser').removeAttr('required');
                return refreshRealtimeData({ users: true });
            }).catch(notifyError);
        });
    }

    function initPembelian() {
        $('#tanggalTransaksi').text(dateFormatter.format(new Date()));

        $('#pembelianAnggota').on('change', function () {
            const id = $(this).val();
            if (!id) {
                setAnggotaDetail({});
                return;
            }
            api('/api/anggota/detail?id=' + encodeURIComponent(id))
                .then(setAnggotaDetail)
                .catch(notifyError);
        });

        $('#pembelianTiket').on('change', function () {
            const id = $(this).val();
            if (!id) {
                state.selectedHarga = 0;
                state.selectedStock = 0;
                calculateSubtotal();
                return;
            }
            api('/api/tiket/detail?id=' + encodeURIComponent(id)).then((item) => {
                state.selectedHarga = Number(item.harga);
                state.selectedStock = Number(item.stock);
                $('#hargaTiket').val(rupiah.format(state.selectedHarga));
                $('#stockTiket').val(state.selectedStock + ' Tiket');
                $('#qtyTiket').attr('max', state.selectedStock);
                calculateSubtotal();
            }).catch(notifyError);
        });

        $('#qtyTiket').on('input', calculateSubtotal);
        $('#resetPembelianBtn').on('click', resetPembelianForm);

        $('#pembelianForm').on('submit', function (event) {
            event.preventDefault();
            api('/api/pembelian/save', {
                method: 'POST',
                body: JSON.stringify({
                    anggota_id: $('#pembelianAnggota').val(),
                    tiket_id: $('#pembelianTiket').val(),
                    qty: $('#qtyTiket').val(),
                    keterangan: $('#keteranganPembelian').val(),
                }),
            }).then((result) => {
                Swal.fire({
                    icon: 'success',
                    title: 'Data berhasil disimpan',
                    html: 'Nomor transaksi: <strong>' + escapeHtml(result.no_transaksi) + '</strong>',
                    confirmButtonColor: '#f97316',
                });
                resetPembelianForm();
                return refreshRealtimeData({ tiket: true, dashboard: true, laporan: true });
            }).catch(notifyError);
        });
    }

    function setAnggotaDetail(item) {
        $('#detailNama').val(item.nama || '');
        $('#detailAlamat').val(item.alamat || '');
        $('#detailHp').val(item.no_hp || '');
    }

    function calculateSubtotal() {
        const qty = Math.max(Number($('#qtyTiket').val()) || 0, 0);
        $('#subtotalTiket').val(rupiah.format(state.selectedHarga * qty));
        if (!state.selectedHarga) {
            $('#hargaTiket').val('');
            $('#stockTiket').val('');
        }
    }

    function resetPembelianForm() {
        $('#pembelianForm')[0].reset();
        $('#qtyTiket').val(1);
        $('#qtyTiket').removeAttr('max');
        state.selectedHarga = 0;
        state.selectedStock = 0;
        setAnggotaDetail({});
        calculateSubtotal();
    }

    function refreshDashboard() {
        const summaryRequest = api('/api/dashboard/summary').then((summary) => {
            $('#todayTotal').text(rupiah.format(Number(summary.today_total)));
            $('#todayQty').text(Number(summary.today_qty) + ' Tiket');
            $('#monthTotal').text(rupiah.format(Number(summary.month_total)));
            $('#monthQty').text(Number(summary.month_qty) + ' Tiket');
        });

        const chartRequest = api('/api/dashboard/chart').then((charts) => {
            drawChart('dailyChart', 'line', charts.daily, 'Total Penjualan', '#f97316');
            drawChart('monthlyChart', 'bar', charts.monthly, 'Total Penjualan', '#fb923c');
            drawChart('topTicketChart', 'bar', charts.top_tickets, 'Qty Terjual', '#c2410c', true);
        });

        return Promise.all([summaryRequest, chartRequest]).catch(notifyError);
    }

    function drawChart(id, type, rows, label, color, horizontal) {
        const ctx = document.getElementById(id);
        if (!ctx) return;
        if (state.charts[id]) {
            state.charts[id].destroy();
        }

        const labels = rows.length ? rows.map((row) => row.label) : ['Belum ada data'];
        const values = rows.length ? rows.map((row) => Number(row.total)) : [0];

        state.charts[id] = new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: values,
                    borderColor: color,
                    backgroundColor: color + '33',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: type === 'line',
                }],
            },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1dfcf' } },
                    x: { beginAtZero: true, grid: { display: false } },
                },
            },
        });
    }

    function initLaporan() {
        const monthNames = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];
        const current = new Date();
        $('#filterBulan').html(monthNames.map((name, index) => {
            const value = index + 1;
            return `<option value="${value}">${name}</option>`;
        }).join('')).val(current.getMonth() + 1);
        $('#filterTahun').val(current.getFullYear());

        let timer = null;
        $('.report-filter').on('change input', function () {
            clearTimeout(timer);
            timer = setTimeout(refreshLaporan, 250);
        });

        $('#exportLaporanBtn').on('click', function () {
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = apiUrl('/api/laporan/export?' + laporanParams());
            document.body.appendChild(iframe);
            setTimeout(() => iframe.remove(), 30000);
        });
    }

    function laporanParams() {
        return new URLSearchParams({
            bulan: $('#filterBulan').val() || '',
            tahun: $('#filterTahun').val() || '',
            anggota_id: $('#filterAnggota').val() || '',
            nama_anggota: $('#filterNama').val() || '',
            tiket_id: $('#filterTiket').val() || '',
        }).toString();
    }

    function refreshLaporan() {
        return api('/api/laporan?' + laporanParams()).then((result) => {
            $('#reportTransaksi').text(result.summary.total_transaksi);
            $('#reportTiket').text(result.summary.total_tiket);
            $('#reportPendapatan').text(rupiah.format(Number(result.summary.total_pendapatan)));
            renderLaporanTable(result.rows);
        }).catch(notifyError);
    }

    function renderLaporanTable(rows) {
        destroyDataTable('#laporanTable');
        const html = rows.map((item) => `
            <tr>
                <td>${escapeHtml(item.tanggal)}</td>
                <td>${escapeHtml(item.no_transaksi)}</td>
                <td>${escapeHtml(item.no_anggota)}</td>
                <td>${escapeHtml(item.nama_anggota)}</td>
                <td>${escapeHtml(item.jenis_tiket)}</td>
                <td>${rupiah.format(Number(item.harga))}</td>
                <td>${escapeHtml(item.qty)}</td>
                <td>${rupiah.format(Number(item.total))}</td>
            </tr>
        `).join('');
        $('#laporanTable tbody').html(html);
        setDataTable('#laporanTable');
    }

    function refreshRealtimeData(options) {
        const config = Object.assign({
            anggota: false,
            tiket: false,
            users: false,
            dashboard: false,
            laporan: false,
        }, options || {});
        let queue = Promise.resolve();

        if (config.anggota) {
            queue = queue.then(loadAnggota);
        }
        if (config.tiket) {
            queue = queue.then(loadTiket);
        }
        if (config.users) {
            queue = queue.then(loadUsers);
        }
        if (config.dashboard) {
            queue = queue.then(refreshDashboard);
        }
        if (config.laporan) {
            queue = queue.then(refreshLaporan);
        }

        return queue;
    }

    function bootApp() {
        anggotaModal = new bootstrap.Modal(document.getElementById('anggotaModal'));
        importAnggotaModal = new bootstrap.Modal(document.getElementById('importAnggotaModal'));
        tiketModal = new bootstrap.Modal(document.getElementById('tiketModal'));
        userModal = new bootstrap.Modal(document.getElementById('userModal'));

        initNavigation();
        initMasterForms();
        initPembelian();
        initLaporan();
        loadAnggota()
            .then(loadTiket)
            .then(loadUsers)
            .then(() => {
                calculateSubtotal();
                refreshDashboard();
            })
            .catch(notifyError);
    }

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }
        callback();
    }

    ready(function () {
        if (!window.IS_AUTHENTICATED) {
            initLogin();
            return;
        }
        if (typeof $ === 'undefined' || typeof bootstrap === 'undefined') {
            alert('Library dashboard belum termuat. Periksa koneksi CDN atau refresh halaman.');
            return;
        }
        bootApp();
    });
})();
