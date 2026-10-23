<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Sync Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php if (!empty($csrf['header']) && !empty($csrf['value'])): ?>
    <meta name="csrf-token-name" content="<?= htmlspecialchars($csrf['header']) ?>">
    <meta name="csrf-token-value" content="<?= htmlspecialchars($csrf['value']) ?>">
    <?php endif; ?>
    <style>
        body { background-color: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .card-custom { border-radius: 10px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .table-responsive { max-height: 480px; overflow-y: auto; }
        #logs-container { max-height: 280px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.5; }
        .badge-status { font-size: 0.8rem; }
    </style>
</head>
<body class="p-3 p-md-4">
<div class="container-fluid max-w-1200" style="max-width: 1100px;">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
        <div>
            <h4 class="mb-0 text-dark fw-bold"><i class="bi bi-arrow-repeat text-primary me-2"></i>Database Synchronization Dashboard</h4>
            <small class="text-muted">Kelola dan sinkronkan tabel database lokal dan remote secara aman</small>
        </div>
        <div>
            <span class="badge bg-secondary">v1.0.0</span>
        </div>
    </div>

    <!-- Alert status konfigurasi -->
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <div class="p-2 px-3 bg-white rounded border d-flex align-items-center">
                <i class="bi bi-globe2 fs-4 text-info me-3"></i>
                <div class="text-truncate">
                    <small class="text-muted d-block">Remote Target URL:</small>
                    <span class="fw-semibold <?= empty($remoteUrl) ? 'text-danger' : 'text-dark' ?>">
                        <?= !empty($remoteUrl) ? htmlspecialchars($remoteUrl) : 'Belum diatur (DB_SYNC_REMOTE_URL)' ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-2 px-3 bg-white rounded border d-flex align-items-center">
                <i class="bi bi-shield-lock fs-4 <?= $hasKey ? 'text-success' : 'text-danger' ?> me-3"></i>
                <div>
                    <small class="text-muted d-block">Security API Key:</small>
                    <?php if ($hasKey): ?>
                        <span class="badge bg-success">Terkonfigurasi</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Belum diatur (DB_SYNC_SECRET_KEY)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card card-custom bg-white p-4">
        <form id="syncForm">
            <?php if (!empty($csrf['name']) && !empty($csrf['value'])): ?>
            <input type="hidden" name="<?= htmlspecialchars($csrf['name']) ?>" value="<?= htmlspecialchars($csrf['value']) ?>">
            <?php endif; ?>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold">Daftar Tabel (<?= count($tables) ?>)</span>
                    <span id="selectedCount" class="badge bg-primary">0 dipilih</span>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0">Ubah Semua Arah:</label>
                    <select id="globalDirection" class="form-select form-select-sm" style="width: auto;" onchange="applyGlobalDirection(this.value)">
                        <option value="">-- Pilih Arah --</option>
                        <option value="local_to_remote">Local -> Remote</option>
                        <option value="remote_to_local">Remote -> Local</option>
                        <option value="both">Both (Two Way)</option>
                    </select>
                </div>
            </div>

            <!-- Tabel Data -->
            <div class="table-responsive border rounded mb-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 45px;" class="text-center">
                                <input type="checkbox" id="checkAll" class="form-check-input" onchange="toggleSelectAll(this)">
                            </th>
                            <th>Nama Tabel</th>
                            <th style="width: 240px;">Arah Sinkronisasi</th>
                            <th style="width: 120px;" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tables)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-1"></i> Tidak ada tabel yang ditemukan pada database saat ini.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($tables as $index => $table): ?>
                            <tr id="row-<?= htmlspecialchars($table) ?>">
                                <td class="text-center">
                                    <input type="checkbox" name="tables[]" value="<?= htmlspecialchars($table) ?>" class="form-check-input row-checkbox" onchange="updateSelectedCount()">
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><i class="bi bi-table me-2 text-secondary"></i><?= htmlspecialchars($table) ?></span>
                                </td>
                                <td>
                                    <select name="direction[<?= htmlspecialchars($table) ?>]" class="form-select form-select-sm row-direction">
                                        <option value="local_to_remote">Local &rarr; Remote</option>
                                        <option value="remote_to_local">Remote &rarr; Local</option>
                                        <option value="both">Both (Two Way)</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-secondary border badge-status" id="status-<?= htmlspecialchars($table) ?>">Menunggu</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Actions -->
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh Tabel
                </button>
                <button type="button" id="btnStart" onclick="startSync()" class="btn btn-primary px-4" <?= empty($tables) ? 'disabled' : '' ?>>
                    <i class="bi bi-play-fill me-1"></i> Mulai Sinkronisasi
                </button>
            </div>
        </form>

        <!-- Log Panel -->
        <div id="logsSection" class="mt-4" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold small text-muted"><i class="bi bi-terminal me-1"></i>Log Proses Eksekusi</span>
                <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted py-0" onclick="clearLogs()">Bersihkan Log</button>
            </div>
            <div id="logs-container" class="bg-dark text-light p-3 rounded">
                <pre id="logsContent" class="m-0 text-light" style="white-space: pre-wrap; word-break: break-all;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
const routePrefix = '<?= '/' . trim($routePrefix, '/') ?>';
const processUrl = routePrefix + '/process';

function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = master.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    document.getElementById('selectedCount').innerText = checked + ' dipilih';
    
    const all = document.querySelectorAll('.row-checkbox').length;
    const master = document.getElementById('checkAll');
    if (master) {
        master.checked = (checked === all && all > 0);
    }
}

function applyGlobalDirection(dir) {
    if (!dir) return;
    document.querySelectorAll('.row-direction').forEach(sel => {
        sel.value = dir;
    });
}

function appendLog(message, isError = false) {
    const logsSection = document.getElementById('logsSection');
    const logsContent = document.getElementById('logsContent');
    const container = document.getElementById('logs-container');
    
    logsSection.style.display = 'block';
    const timestamp = new Date().toLocaleTimeString();
    const prefix = isError ? '[ERROR] ' : '[INFO]  ';
    logsContent.innerText += `[${timestamp}] ${prefix}${message}\n`;
    container.scrollTop = container.scrollHeight;
}

function clearLogs() {
    document.getElementById('logsContent').innerText = '';
}

function setRowStatus(table, status, text, badgeClass) {
    const badge = document.getElementById('status-' + table);
    if (badge) {
        badge.className = 'badge badge-status ' + badgeClass;
        badge.innerText = text;
    }
}

async function startSync() {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        alert('Silakan pilih minimal satu tabel yang ingin disinkronkan.');
        return;
    }

    if (!confirm(`Apakah Anda yakin ingin memulai sinkronisasi untuk ${selected.length} tabel yang dipilih?`)) {
        return;
    }

    const form = document.getElementById('syncForm');
    const formData = new FormData(form);
    const btn = document.getElementById('btnStart');

    // UI Loading state
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sinkronisasi Berjalan...';
    
    selected.forEach(cb => {
        setRowStatus(cb.value, 'processing', 'Memproses...', 'bg-warning text-dark');
    });

    clearLogs();
    appendLog(`Memulai sinkronisasi untuk ${selected.length} tabel...`);

    const headers = {};
    const csrfHeader = document.querySelector('meta[name="csrf-token-name"]');
    const csrfValue = document.querySelector('meta[name="csrf-token-value"]');
    if (csrfHeader && csrfValue) {
        headers[csrfHeader.content] = csrfValue.content;
    }

    try {
        const response = await fetch(processUrl, {
            method: 'POST',
            body: formData,
            headers: headers
        });

        const data = await response.json();

        if (response.ok && data.status === 'success') {
            appendLog('Sinkronisasi selesai dengan sukses!');
            if (data.details) {
                for (const [tbl, res] of Object.entries(data.details)) {
                    setRowStatus(tbl, 'success', 'Selesai', 'bg-success text-white');
                    appendLog(`Tabel [${tbl}]: ` + JSON.stringify(res));
                }
            }
        } else {
            appendLog(data.message || 'Terdapat error atau sebagian tabel gagal disinkronkan.', true);
            if (data.details) {
                for (const [tbl, res] of Object.entries(data.details)) {
                    if (res.success) {
                        setRowStatus(tbl, 'success', 'Selesai', 'bg-success text-white');
                    } else {
                        setRowStatus(tbl, 'error', 'Gagal', 'bg-danger text-white');
                    }
                    appendLog(`Tabel [${tbl}]: ` + JSON.stringify(res), !res.success);
                }
            }
        }
    } catch (err) {
        appendLog('Terjadi kesalahan koneksi/server: ' + err.message, true);
        selected.forEach(cb => {
            setRowStatus(cb.value, 'error', 'Error', 'bg-danger text-white');
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Mulai Sinkronisasi';
    }
}
</script>
</body>
</html>
