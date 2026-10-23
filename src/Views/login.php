<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Database Sync Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body class="p-3">
<div class="card login-card bg-white p-4 p-md-5">
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 60px; height: 60px;">
            <i class="bi bi-shield-lock-fill fs-2"></i>
        </div>
        <h4 class="fw-bold mb-1">Database Sync</h4>
        <small class="text-muted">Masuk untuk mengakses dashboard sinkronisasi</small>
    </div>

    <?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger py-2 small d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
        <div><?= htmlspecialchars($errorMessage) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= '/' . trim($routePrefix, '/') ?>/login">
        <?php if (!empty($csrf['name']) && !empty($csrf['value'])): ?>
        <input type="hidden" name="<?= htmlspecialchars($csrf['name']) ?>" value="<?= htmlspecialchars($csrf['value']) ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Username</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-person text-secondary"></i></span>
                <input type="text" name="username" class="form-control" placeholder="admin" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-semibold">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-key text-secondary"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="form-text small text-muted mt-1">
                Gunakan kredensial <code>DB_SYNC_AUTH_PASSWORD</code> atau <code>DB_SYNC_SECRET_KEY</code> dari file .env.
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Dashboard
        </button>
    </form>
</div>
</body>
</html>
