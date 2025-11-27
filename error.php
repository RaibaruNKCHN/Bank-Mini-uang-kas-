<?php
// Simple, responsive error display page
// Expects a base64-encoded JSON payload in GET param `e`.
// Payload keys: message, file, line, trace, debug

$encoded = $_GET['e'] ?? '';
$payload = null;
if ($encoded) {
    $decoded = base64_decode($encoded);
    $payload = json_decode($decoded, true);
}

// compute base url for "Beranda" links (robust: derive project folder from DOCUMENT_ROOT)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Attempt to compute the web-accessible folder of this script relative to DOCUMENT_ROOT
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$scriptDirPath = str_replace('\\', '/', realpath(dirname(__FILE__)));
$basePath = '';
if ($docRoot !== false) {
  $docRoot = str_replace('\\', '/', $docRoot);
  if (strpos($scriptDirPath, $docRoot) === 0) {
    $basePath = substr($scriptDirPath, strlen($docRoot));
  }
}
// ensure leading slash and trailing slash
if ($basePath === '' || $basePath === false) $basePath = '/';
if ($basePath[0] !== '/') $basePath = '/' . $basePath;
$basePath = rtrim($basePath, '/') . '/';
$baseUrl = $protocol . $host . $basePath;

$message = $payload['message'] ?? 'Terjadi kesalahan tak terduga pada aplikasi.';
$file = $payload['file'] ?? null;
$line = $payload['line'] ?? null;
$trace = $payload['trace'] ?? null;
$debug = isset($payload['debug']) ? (bool)$payload['debug'] : (isset($_GET['debug']) && $_GET['debug'] === '1');
$log_id = $payload['log_id'] ?? null;

function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Error — Aplikasi</title>
  <style>
    :root{--bg:#fbfdff;--card:#ffffff;--accent:#0b5cff;--muted:#6b7280;--danger:#c53030}
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; background:linear-gradient(180deg,#f7f9ff, #fbfdff);color:#111}
    .wrap{max-width:980px;margin:36px auto;padding:20px}
    .card{background:var(--card);border-radius:12px;box-shadow:0 6px 22px rgba(12,24,40,0.06);padding:20px}
    .headline{display:flex;align-items:center;gap:14px}
    .icon{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,var(--accent),#2b6ef6);display:flex;align-items:center;justify-content:center;color:white;font-weight:700}
    h1{margin:0;font-size:18px}
    p.lead{margin:10px 0;color:var(--muted)}
    .summary{display:flex;flex-wrap:wrap;gap:12px;margin-top:12px}
    .meta{flex:1 1 220px;background:#f8fafc;padding:12px;border-radius:8px;color:#0f172a}
    .meta b{display:block;color:var(--muted);font-size:12px}
    .actions{margin-top:16px;display:flex;gap:8px}
    .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:none;background:var(--accent);color:white;text-decoration:none}
    .btn.ghost{background:transparent;color:var(--accent);box-shadow:inset 0 0 0 1px rgba(11,92,255,0.12)}
    .details{margin-top:16px;background:#0b1220;color:#e6eefc;padding:12px;border-radius:8px;font-family:monospace;font-size:13px;display:none;white-space:pre-wrap}
    .muted{color:var(--muted);font-size:13px}
    @media (max-width:640px){.wrap{margin:16px;padding:12px}.headline{gap:10px}.icon{width:44px;height:44px}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="headline">
        <div class="icon">!</div>
        <div>
          <h1>Terjadi Kesalahan</h1>
          <p class="lead">Sistem mendeteksi sebuah error. Silakan muat ulang halaman atau hubungi administrator jika masalah berlanjut.</p>
        </div>
      </div>

      <div class="summary">
        <div class="meta">
          <div class="muted">Ringkasan</div>
          <div style="margin-top:8px;font-weight:600"><?php echo esc($message); ?></div>
        </div>

        <div class="meta">
          <div class="muted">Lokasi</div>
          <div style="margin-top:8px"><?php echo $file ? esc($file) . ( $line ? (':'.esc($line)) : '' ) : '<span class="muted">Tidak tersedia</span>'; ?></div>
        </div>

        <div class="meta">
          <div class="muted">Waktu</div>
          <div style="margin-top:8px"><?php echo date('Y-m-d H:i:s'); ?></div>
        </div>
      </div>

      <div class="actions">
        <a class="btn" href="javascript:location.reload();">Muat Ulang</a>
        <a class="btn ghost" id="toggleDebug" href="#">Tampilkan Detail</a>
        <a class="btn ghost" href="<?php echo esc($baseUrl . 'minibank/auth/dashboard.php'); ?>">Beranda</a>
      </div>

      <div id="debugDetails" class="details" aria-hidden="true">
        <?php if ($debug && ($file || $trace)): ?>
          <div><strong>File:</strong> <?php echo esc($file); ?>:<?php echo esc($line); ?></div>
          <hr>
          <div><?php echo nl2br(esc($trace)); ?></div>
        <?php else: ?>
          <div class="muted">Detail teknis disembunyikan. Tambahkan <code>?debug=1</code> ke URL untuk menampilkan (jika diizinkan).</div>
        <?php endif; ?>
      </div>
      <?php if ($log_id): ?>
        <div style="margin-top:12px;text-align:right;color:var(--muted);font-size:13px">Log ID: <strong><?php echo esc($log_id); ?></strong> — berikan ID ini ke administrator untuk pemeriksaan.</div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    (function(){
      const btn = document.getElementById('toggleDebug');
      const det = document.getElementById('debugDetails');
      btn.addEventListener('click', function(e){ e.preventDefault();
        if (det.style.display === 'block') { det.style.display = 'none'; btn.textContent = 'Tampilkan Detail'; det.setAttribute('aria-hidden','true'); }
        else { det.style.display = 'block'; btn.textContent = 'Sembunyikan Detail'; det.setAttribute('aria-hidden','false'); }
      });
    })();
  </script>
</body>
</html>
