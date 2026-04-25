<?php
session_start();
define('ADMIN_PASS', 'admin33'); // Şifreyi değiştirin!

// ===== AJAX HANDLER =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        if (($_POST['sifre'] ?? '') === ADMIN_PASS) {
            $_SESSION['admin'] = true;
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Şifre hatalı!']);
        }
        exit;
    }

    if (empty($_SESSION['admin'])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'Yetkisiz erişim!']);
        exit;
    }

    if ($action === 'save') {
        $which = ($_POST['file'] ?? '') === 'ayarlar' ? 'ayarlar.json' : 'menu.json';
        $path  = __DIR__ . '/' . $which;
        $decoded = json_decode($_POST['data'] ?? '', true);
        if ($decoded !== null) {
            $written = file_put_contents($path, json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($written !== false) {
                echo json_encode(['ok' => true]);
            } else {
                echo json_encode(['ok' => false, 'msg' => $which . ' yazılamadı! Sunucu dosya iznini kontrol edin.']);
            }
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Geçersiz JSON verisi!']);
        }
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'get_masalar') {
        $path = __DIR__ . '/masalar.json';
        $raw  = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        $data = (is_array($raw) && array_values($raw) === $raw) ? $raw : [];
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    if ($action === 'save_masalar') {
        $data = json_decode($_POST['data'] ?? '', true);
        if ($data !== null) {
            file_put_contents(__DIR__ . '/masalar.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Geçersiz veri']);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem']);
    exit;
}

$loggedIn = !empty($_SESSION['admin']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>33 YAN 2 | Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #111; color: #eee; font-family: Arial, sans-serif; min-height: 100vh; }

        /* ===== LOGIN ===== */
        .login-page { display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .login-box { background: #1a1a1a; padding: 40px; border-radius: 12px; border: 1px solid #D4AF37; width: 100%; max-width: 380px; }
        .login-box h2 { color: #D4AF37; text-align: center; margin-bottom: 8px; font-size: 1.5rem; }
        .login-box .sub { color: #888; text-align: center; font-size: 0.9rem; margin-bottom: 25px; }
        .login-box input[type=password] { width: 100%; padding: 13px; background: #000; border: 1px solid #444; color: #fff; border-radius: 6px; margin-bottom: 12px; font-size: 1rem; outline: none; transition: 0.2s; }
        .login-box input[type=password]:focus { border-color: #D4AF37; }
        .login-box button { width: 100%; padding: 13px; background: #D4AF37; color: #000; font-weight: bold; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .login-box button:hover { background: #c09a20; }
        #loginErr { color: #f55; text-align: center; margin-top: 12px; font-size: 0.95rem; min-height: 20px; }

        /* ===== ADMIN LAYOUT ===== */
        .admin-page { display: none; flex-direction: column; min-height: 100vh; }
        .admin-page.show { display: flex; }

        .adm-header { background: #1a1a1a; border-bottom: 2px solid #D4AF37; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 200; gap: 12px; }
        .adm-header .logo { color: #D4AF37; font-size: 1.2rem; font-weight: bold; white-space: nowrap; }
        .adm-header .logo span { color: #888; font-size: 0.8rem; font-weight: normal; }
        .adm-header .acts { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        #saveStatus { font-size: 0.9rem; font-weight: bold; white-space: nowrap; }
        #saveStatus.ok { color: #4caf50; }
        #saveStatus.err { color: #f55; }
        .btn-save { background: #D4AF37; color: #000; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.9rem; white-space: nowrap; }
        .btn-save:hover { background: #b8971e; }
        .btn-logout { background: #333; color: #ccc; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; white-space: nowrap; }
        .btn-logout:hover { background: #444; }

        .adm-tabs { background: #161616; border-bottom: 1px solid #333; display: flex; padding: 0 24px; }
        .adm-tab { padding: 13px 20px; cursor: pointer; color: #888; font-weight: bold; border-bottom: 3px solid transparent; margin-bottom: -1px; font-size: 0.9rem; transition: 0.2s; }
        .adm-tab.active { color: #D4AF37; border-bottom-color: #D4AF37; }
        .adm-tab:hover { color: #ccc; }

        .adm-body { flex: 1; padding: 24px; max-width: 1400px; width: 100%; margin: 0 auto; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ===== PAGE TITLE ===== */
        .page-title { color: #D4AF37; font-size: 1.2rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        /* ===== CATEGORY BLOCK ===== */
        .cat-block { background: #1c1c1c; border-radius: 8px; margin-bottom: 18px; border: 1px solid #2a2a2a; overflow: hidden; }
        .cat-head { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: #222; gap: 10px; }
        .cat-name-inp { background: transparent; border: none; border-bottom: 1px solid transparent; color: #D4AF37; font-size: 1.2rem; font-weight: bold; font-family: inherit; outline: none; flex: 1; padding: 2px 4px; transition: 0.2s; }
        .cat-name-inp:focus { border-bottom-color: #D4AF37; }
        .btn-cat-del { background: #c62828; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.85rem; white-space: nowrap; }
        .btn-cat-del:hover { background: #b71c1c; }

        .cat-tile-row { display: flex; gap: 15px; align-items: center; padding: 8px 18px 12px; background: #1e1e1e; border-bottom: 1px solid #2a2a2a; flex-wrap: wrap; }
        .cat-tile-row label { color: #666; font-size: 0.8rem; white-space: nowrap; }
        .cat-tile-row input[type=text] { background: #0d0d0d; border: 1px solid #333; color: #fff; padding: 5px 8px; border-radius: 4px; font-size: 0.85rem; width: 60px; outline: none; }
        .cat-tile-row select { background: #0d0d0d; border: 1px solid #333; color: #fff; padding: 5px 8px; border-radius: 4px; font-size: 0.85rem; outline: none; cursor: pointer; }

        .cat-body { padding: 12px 14px; }

        /* ===== SUBCAT BLOCK ===== */
        .subcat-block { border: 1px dashed #2a2a2a; border-left: 3px solid #1565c0; border-radius: 6px; margin-bottom: 12px; background: #161616; }
        .subcat-head { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; gap: 10px; }
        .subcat-name-inp { background: transparent; border: none; border-bottom: 1px solid transparent; color: #42a5f5; font-size: 1rem; font-weight: bold; font-family: inherit; outline: none; flex: 1; padding: 2px 4px; transition: 0.2s; }
        .subcat-name-inp:focus { border-bottom-color: #42a5f5; }
        .btn-subcat-del { background: #c62828; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.85rem; }
        .btn-subcat-del:hover { background: #b71c1c; }

        /* ===== ITEM ROW ===== */
        .items-wrap { padding: 0 12px 6px; }
        .item-row { display: flex; gap: 8px; margin-bottom: 7px; align-items: center; }
        .item-inp { background: #0a0a0a; border: 1px solid #1e1e1e; color: #fff; padding: 9px 12px; border-radius: 4px; font-size: 0.9rem; outline: none; font-family: inherit; transition: 0.2s; }
        .item-inp:focus { border-color: #444; }
        .inp-name { flex: 4; min-width: 0; }
        .inp-price { flex: 1; min-width: 70px; }
        .inp-desc { flex: 5; min-width: 0; }
        .btn-item-del { background: #c62828; color: #fff; border: none; width: 34px; height: 34px; border-radius: 4px; cursor: pointer; flex-shrink: 0; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; }
        .btn-item-del:hover { background: #b71c1c; }

        /* ===== ACTION BUTTONS ===== */
        .subcat-actions { padding: 2px 12px 10px; }
        .btn-add-item { background: #2e7d32; color: #fff; border: none; padding: 7px 14px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; }
        .btn-add-item:hover { background: #1b5e20; }
        .cat-add-subcat { padding: 0 14px 14px; }
        .btn-add-subcat { background: #0d47a1; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; }
        .btn-add-subcat:hover { background: #1a237e; }
        .btn-new-cat { display: block; width: 100%; padding: 16px; background: #1c1c1c; color: #D4AF37; border: 2px dashed #444; border-radius: 8px; cursor: pointer; font-size: 1rem; font-family: inherit; margin-top: 5px; transition: 0.2s; }
        .btn-new-cat:hover { border-color: #D4AF37; background: #222; }

        /* ===== SETTINGS ===== */
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; max-width: 750px; }
        .setting-group { background: #1c1c1c; border-radius: 8px; padding: 16px; border-left: 3px solid #D4AF37; }
        .setting-group label { display: block; color: #D4AF37; font-size: 0.82rem; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .setting-group input { width: 100%; background: #0a0a0a; border: 1px solid #333; color: #fff; padding: 10px 12px; border-radius: 4px; font-size: 0.95rem; outline: none; font-family: inherit; transition: 0.2s; }
        .setting-group input:focus { border-color: #D4AF37; }
        .btn-save-settings { background: #D4AF37; color: #000; border: none; padding: 12px 28px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.95rem; margin-top: 20px; }
        .btn-save-settings:hover { background: #b8971e; }
        #settingsStatus { margin-top: 12px; font-weight: bold; font-size: 0.9rem; min-height: 20px; }
        #settingsStatus.ok { color: #4caf50; }
        #settingsStatus.err { color: #f55; }

        /* ===== MISC ===== */
        .inp-col-header { display: flex; gap: 8px; padding: 0 12px 4px; color: #555; font-size: 0.75rem; }
        .inp-col-header span:nth-child(1) { flex: 4; }
        .inp-col-header span:nth-child(2) { flex: 1; min-width: 70px; }
        .inp-col-header span:nth-child(3) { flex: 5; }
        .inp-col-header span:nth-child(4) { width: 34px; flex-shrink: 0; }

        /* ===== KASA / MASA ===== */
        .kasa-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        .kasa-btns { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        #kasaStatus { font-size: 0.9rem; font-weight: bold; }
        #kasaStatus.ok { color: #4caf50; }
        #kasaStatus.err { color: #f55; }
        .btn-kasa { background: #1c1c1c; color: #D4AF37; border: 1px solid #444; padding: 9px 16px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; transition: 0.2s; white-space: nowrap; }
        .btn-kasa:hover { border-color: #D4AF37; }
        .btn-kasa.active { background: #D4AF37; color: #000; border-color: #D4AF37; }
        .btn-kasa-green { background: #1b5e20; color: #fff; border-color: #2e7d32; }
        .btn-kasa-green:hover { background: #2e7d32; border-color: #2e7d32; }

        #masaCanvas {
            position: relative;
            width: 100%;
            min-height: 520px;
            background: #0d0d0d;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            overflow: hidden;
        }
        #masaCanvas.duzenleme {
            background-image:
                linear-gradient(#1a1a1a 1px, transparent 1px),
                linear-gradient(90deg, #1a1a1a 1px, transparent 1px);
            background-size: 40px 40px;
        }
        #masaCanvas .bos-mesaj { color: #333; padding: 80px; text-align: center; font-size: 0.95rem; pointer-events: none; }

        .masa-box {
            position: absolute;
            width: 88px;
            height: 88px;
            background: #1c1c1c;
            border: 2px solid #2e7d32;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            user-select: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .masa-box:hover { box-shadow: 0 0 14px rgba(212,175,55,0.25); border-color: #D4AF37; }
        .masa-box.dragging { opacity: 0.85; z-index: 99; box-shadow: 0 6px 24px rgba(0,0,0,0.6); }
        .masa-box.duzenleme-mod { cursor: grab; }
        .masa-box.duzenleme-mod:active { cursor: grabbing; }
        .masa-ad { color: #D4AF37; font-weight: bold; font-size: 0.85rem; text-align: center; padding: 0 6px; line-height: 1.2; }
        .masa-bos { color: #3a3a3a; font-size: 0.7rem; margin-top: 4px; }
        .masa-del-btn {
            display: none;
            position: absolute;
            top: -9px; right: -9px;
            width: 22px; height: 22px;
            background: #c62828; color: #fff;
            border: none; border-radius: 50%;
            cursor: pointer; font-size: 0.75rem;
            align-items: center; justify-content: center;
            line-height: 1; z-index: 2;
        }
        #masaCanvas.duzenleme .masa-del-btn { display: flex; }

        @media (max-width: 600px) {
            .item-row { flex-wrap: wrap; }
            .inp-name, .inp-desc { flex: 1 1 100%; }
            .inp-price { flex: 1; }
            .adm-header { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<!-- ===== LOGIN PAGE ===== -->
<div class="login-page" id="loginPage" <?= $loggedIn ? 'style="display:none"' : '' ?>>
    <div class="login-box">
        <h2><i class="fas fa-lock"></i> Admin Girişi</h2>
        <p class="sub">33 YAN 2 Yönetim Paneli</p>
        <input type="password" id="sifre" placeholder="Admin şifresi giriniz" onkeydown="if(event.key==='Enter') doLogin()">
        <button onclick="doLogin()"><i class="fas fa-sign-in-alt"></i> Giriş Yap</button>
        <div id="loginErr"></div>
    </div>
</div>

<!-- ===== ADMIN PAGE ===== -->
<div class="admin-page <?= $loggedIn ? 'show' : '' ?>" id="adminPage">

    <div class="adm-header">
        <div class="logo">33 YAN 2 <span>| Admin Panel</span></div>
        <div class="acts">
            <span id="saveStatus"></span>
            <button class="btn-save" onclick="saveMenu()"><i class="fas fa-save"></i> Kaydet</button>
            <button class="btn-logout" onclick="doLogout()"><i class="fas fa-sign-out-alt"></i> Çıkış</button>
        </div>
    </div>

    <div class="adm-tabs">
        <div class="adm-tab active" onclick="switchTab('menu', this)"><i class="fas fa-utensils"></i> Menü Düzenle</div>
        <div class="adm-tab" onclick="switchTab('settings', this)"><i class="fas fa-cog"></i> Mekan Ayarları</div>
        <div class="adm-tab" onclick="switchTab('kasa', this)"><i class="fas fa-cash-register"></i> Kasa</div>
    </div>

    <div class="adm-body">

        <!-- Menü Tab -->
        <div class="tab-content active" id="tab-menu">
            <div class="page-title"><i class="fas fa-edit"></i> Veya Sistemden Elle Düzenle</div>
            <div id="menuEditor">
                <div style="text-align:center; padding:50px; color:#D4AF37;"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Menü yükleniyor...</div>
            </div>
            <button class="btn-new-cat" onclick="addCategory()">+ YENİ ANA KATEGORİ OLUŞTUR</button>
        </div>

        <!-- Ayarlar Tab -->
        <div class="tab-content" id="tab-settings">
            <div class="page-title"><i class="fas fa-cog"></i> Mekan Bilgileri</div>
            <div class="settings-grid">
                <div class="setting-group">
                    <label><i class="fas fa-map-marker-alt"></i> Adres</label>
                    <input type="text" id="set-adres" placeholder="Mekan adresi">
                </div>
                <div class="setting-group">
                    <label><i class="far fa-clock"></i> Çalışma Saatleri</label>
                    <input type="text" id="set-saatler" placeholder="Örn: Her Gün 08:00 - 02:00">
                </div>
                <div class="setting-group">
                    <label><i class="fas fa-wifi"></i> Wi-Fi Şifresi</label>
                    <input type="text" id="set-wifi" placeholder="Wi-Fi şifresi">
                </div>
                <div class="setting-group">
                    <label><i class="fab fa-instagram"></i> Instagram Kullanıcı Adı</label>
                    <input type="text" id="set-insta" placeholder="kullanici_adi (@ olmadan)">
                </div>
                <div class="setting-group">
                    <label><i class="fas fa-envelope"></i> E-posta (Form bildirimleri)</label>
                    <input type="email" id="set-email" placeholder="ornek@email.com">
                </div>
            </div>
            <button class="btn-save-settings" onclick="saveAyarlar()"><i class="fas fa-save"></i> Ayarları Kaydet</button>
            <div id="settingsStatus"></div>
        </div>

        <!-- Kasa Tab -->
        <div class="tab-content" id="tab-kasa">
            <div class="kasa-toolbar">
                <div class="page-title"><i class="fas fa-th"></i> Masalar</div>
                <div class="kasa-btns">
                    <span id="kasaStatus"></span>
                    <button class="btn-kasa" id="btnDuzenleme" onclick="toggleDuzenleme()">
                        <i class="fas fa-arrows-alt"></i> Masa Düzenle
                    </button>
                    <button class="btn-kasa btn-kasa-green" onclick="yeniMasaEkle()">
                        <i class="fas fa-plus"></i> Masa Ekle
                    </button>
                    <button class="btn-save" onclick="saveMasalar()">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                </div>
            </div>
            <div id="masaCanvas">
                <div class="bos-mesaj">Yükleniyor...</div>
            </div>
        </div>

    </div>
</div>

<script>
const LOGGED_IN = <?= $loggedIn ? 'true' : 'false' ?>;
let menuData = {};
let ayarlarData = {};

if (LOGGED_IN) loadAll();

// ===== DATA LOAD =====
async function loadAll() {
    try {
        const [mRes, aRes] = await Promise.all([
            fetch('menu.json?v=' + Date.now()),
            fetch('ayarlar.json?v=' + Date.now())
        ]);
        menuData = await mRes.json();
        ayarlarData = await aRes.json();
        renderMenu();
        renderAyarlar();
    } catch (e) {
        document.getElementById('menuEditor').innerHTML =
            '<div style="color:#f55;padding:20px;background:#1c1c1c;border-radius:8px;">Menü yüklenemedi: ' + e.message + '</div>';
    }
}

// ===== AUTH =====
async function doLogin() {
    const sifre = document.getElementById('sifre').value;
    const errEl = document.getElementById('loginErr');
    if (!sifre) { errEl.textContent = 'Şifre boş olamaz!'; return; }
    try {
        const fd = new FormData();
        fd.append('action', 'login');
        fd.append('sifre', sifre);
        const res = await fetch('admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            document.getElementById('loginPage').style.display = 'none';
            document.getElementById('adminPage').classList.add('show');
            loadAll();
        } else {
            errEl.textContent = data.msg;
        }
    } catch (e) {
        errEl.textContent = 'Bağlantı hatası!';
    }
}

async function doLogout() {
    const fd = new FormData();
    fd.append('action', 'logout');
    await fetch('admin.php', { method: 'POST', body: fd });
    location.reload();
}

// ===== TABS =====
function switchTab(name, el) {
    document.querySelectorAll('.adm-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
    if (name === 'kasa') loadKasa();
}

// ===== RENDER MENU =====
function renderMenu() {
    const container = document.getElementById('menuEditor');
    container.innerHTML = '';
    for (const [catName, catData] of Object.entries(menuData)) {
        container.appendChild(buildCatBlock(catName, catData));
    }
}

function buildCatBlock(catName, catData) {
    const block = document.createElement('div');
    block.className = 'cat-block';

    // Header
    const head = document.createElement('div');
    head.className = 'cat-head';
    const nameInp = document.createElement('input');
    nameInp.type = 'text';
    nameInp.className = 'cat-name-inp';
    nameInp.value = catName;
    nameInp.placeholder = 'Kategori adı';
    const delBtn = document.createElement('button');
    delBtn.className = 'btn-cat-del';
    delBtn.textContent = 'Sil';
    delBtn.onclick = () => { if (confirm('"' + catName + '" kategorisi silinsin mi?')) block.remove(); };
    head.appendChild(nameInp);
    head.appendChild(delBtn);

    // Tile settings
    const tileRow = document.createElement('div');
    tileRow.className = 'cat-tile-row';
    const colorOpts = [
        ['color-red','Kırmızı'],['color-black','Siyah'],['color-blue','Mavi'],['color-yellow','Sarı']
    ].map(([v,l]) => `<option value="${v}" ${catData.tileColor===v?'selected':''}>${l}</option>`).join('');
    tileRow.innerHTML =
        `<label>Sembol:</label><input type="text" class="tile-num-inp" value="${catData.tileNum||''}" maxlength="3" placeholder="1">` +
        `<label>Renk:</label><select class="tile-color-sel">${colorOpts}</select>`;

    // Body
    const body = document.createElement('div');
    body.className = 'cat-body';

    const subcats = catData.alt_kategoriler
        ? Object.entries(catData.alt_kategoriler)
        : catData.items ? [['Genel', catData.items]] : [];

    subcats.forEach(([subName, items]) => body.appendChild(buildSubcatBlock(subName, items)));

    // Add subcat
    const addSubDiv = document.createElement('div');
    addSubDiv.className = 'cat-add-subcat';
    const addSubBtn = document.createElement('button');
    addSubBtn.className = 'btn-add-subcat';
    addSubBtn.textContent = '+ Alt Kategori Ekle';
    addSubBtn.onclick = () => body.insertBefore(buildSubcatBlock('Yeni Alt Kategori', []), addSubDiv);
    addSubDiv.appendChild(addSubBtn);
    body.appendChild(addSubDiv);

    block.appendChild(head);
    block.appendChild(tileRow);
    block.appendChild(body);
    return block;
}

function buildSubcatBlock(subName, items) {
    const block = document.createElement('div');
    block.className = 'subcat-block';

    const head = document.createElement('div');
    head.className = 'subcat-head';
    const nameInp = document.createElement('input');
    nameInp.type = 'text';
    nameInp.className = 'subcat-name-inp';
    nameInp.value = subName;
    nameInp.placeholder = 'Alt kategori adı';
    const delBtn = document.createElement('button');
    delBtn.className = 'btn-subcat-del';
    delBtn.textContent = 'X';
    delBtn.onclick = () => { if (confirm('"' + subName + '" alt kategorisi silinsin mi?')) block.remove(); };
    head.appendChild(nameInp);
    head.appendChild(delBtn);

    // Column headers
    const colHeader = document.createElement('div');
    colHeader.className = 'inp-col-header';
    colHeader.innerHTML = '<span>Ürün Adı</span><span>Fiyat (₺)</span><span>Açıklama</span><span></span>';

    const itemsWrap = document.createElement('div');
    itemsWrap.className = 'items-wrap';
    items.forEach(item => itemsWrap.appendChild(buildItemRow(item)));

    const actDiv = document.createElement('div');
    actDiv.className = 'subcat-actions';
    const addBtn = document.createElement('button');
    addBtn.className = 'btn-add-item';
    addBtn.textContent = '+ Ürün Ekle';
    addBtn.onclick = () => itemsWrap.appendChild(buildItemRow({ ad: '', aciklama: '', fiyat: '' }));
    actDiv.appendChild(addBtn);

    block.appendChild(head);
    block.appendChild(colHeader);
    block.appendChild(itemsWrap);
    block.appendChild(actDiv);
    return block;
}

function buildItemRow(item) {
    const row = document.createElement('div');
    row.className = 'item-row';

    const nameInp = document.createElement('input');
    nameInp.type = 'text';
    nameInp.className = 'item-inp inp-name';
    nameInp.value = item.ad || '';
    nameInp.placeholder = 'Ürün adı';

    const priceInp = document.createElement('input');
    priceInp.type = 'number';
    priceInp.className = 'item-inp inp-price';
    priceInp.value = item.fiyat !== undefined ? item.fiyat : '';
    priceInp.placeholder = '0';
    priceInp.min = '0';

    const descInp = document.createElement('input');
    descInp.type = 'text';
    descInp.className = 'item-inp inp-desc';
    descInp.value = item.aciklama || '';
    descInp.placeholder = 'Açıklama (isteğe bağlı)';

    const delBtn = document.createElement('button');
    delBtn.className = 'btn-item-del';
    delBtn.innerHTML = '<i class="fas fa-trash"></i>';
    delBtn.onclick = () => row.remove();

    row.appendChild(nameInp);
    row.appendChild(priceInp);
    row.appendChild(descInp);
    row.appendChild(delBtn);
    return row;
}

// ===== ADD CATEGORY =====
function addCategory() {
    const container = document.getElementById('menuEditor');
    const newBlock = buildCatBlock('Yeni Kategori', {
        tileNum: '1', tileColor: 'color-red',
        alt_kategoriler: { 'Genel': [] }
    });
    container.appendChild(newBlock);
    newBlock.querySelector('.cat-name-inp').focus();
}

// ===== COLLECT DATA =====
function collectMenuData() {
    const data = {};
    document.querySelectorAll('#menuEditor .cat-block').forEach(catBlock => {
        const catName = catBlock.querySelector('.cat-name-inp').value.trim();
        if (!catName) return;
        const tileNum   = catBlock.querySelector('.tile-num-inp').value.trim();
        const tileColor = catBlock.querySelector('.tile-color-sel').value;
        const subcats   = {};
        catBlock.querySelectorAll('.subcat-block').forEach(subBlock => {
            const subName = subBlock.querySelector('.subcat-name-inp').value.trim();
            if (!subName) return;
            const items = [];
            subBlock.querySelectorAll('.item-row').forEach(row => {
                const ad       = row.querySelector('.inp-name').value.trim();
                const fiyat    = parseFloat(row.querySelector('.inp-price').value) || 0;
                const aciklama = row.querySelector('.inp-desc').value.trim();
                if (ad) items.push({ ad, aciklama, fiyat });
            });
            subcats[subName] = items;
        });
        data[catName] = { tileNum, tileColor, alt_kategoriler: subcats };
    });
    return data;
}

// ===== SAVE MENU =====
async function saveMenu() {
    const statusEl = document.getElementById('saveStatus');
    statusEl.className = '';
    statusEl.textContent = 'Kaydediliyor...';
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('file', 'menu');
    fd.append('data', JSON.stringify(collectMenuData()));
    try {
        const res  = await fetch('admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            statusEl.className = 'ok';
            statusEl.textContent = '✓ Kaydedildi!';
            setTimeout(() => statusEl.textContent = '', 3000);
        } else {
            statusEl.className = 'err';
            statusEl.textContent = '✗ ' + data.msg;
        }
    } catch (e) {
        statusEl.className = 'err';
        statusEl.textContent = '✗ Bağlantı hatası!';
    }
}

// ===== SETTINGS =====
function renderAyarlar() {
    document.getElementById('set-adres').value   = ayarlarData.adres     || '';
    document.getElementById('set-saatler').value = ayarlarData.saatler   || '';
    document.getElementById('set-wifi').value    = ayarlarData.wifi      || '';
    document.getElementById('set-insta').value   = ayarlarData.instagram || '';
    document.getElementById('set-email').value   = ayarlarData.email     || '';
}

// ===== KASA / MASA =====
let masalar = [];
let duzenlemeMode = false;
let dragState = null;

async function loadKasa() {
    const canvas = document.getElementById('masaCanvas');
    try {
        const fd = new FormData();
        fd.append('action', 'get_masalar');
        const res = await fetch('admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        masalar = Array.isArray(data.data) ? data.data : [];
        renderMasalar();
    } catch (e) {
        canvas.innerHTML = '<div class="bos-mesaj" style="color:#f55">Yüklenemedi: ' + e.message + '</div>';
    }
}

function renderMasalar() {
    const canvas = document.getElementById('masaCanvas');
    canvas.innerHTML = '';
    canvas.classList.toggle('duzenleme', duzenlemeMode);

    if (masalar.length === 0) {
        canvas.innerHTML = '<div class="bos-mesaj">Henüz masa eklenmedi.<br>Masa Düzenle modunda "Masa Ekle" butonunu kullanın.</div>';
        return;
    }

    masalar.forEach(masa => {
        const box = document.createElement('div');
        box.className = 'masa-box' + (duzenlemeMode ? ' duzenleme-mod' : '');
        box.style.left = (masa.x || 20) + 'px';
        box.style.top  = (masa.y || 20) + 'px';
        box.dataset.id = masa.id;

        const adEl = document.createElement('div');
        adEl.className = 'masa-ad';
        adEl.textContent = masa.ad;
        box.appendChild(adEl);

        const bosEl = document.createElement('div');
        bosEl.className = 'masa-bos';
        bosEl.textContent = 'Boş';
        box.appendChild(bosEl);

        const delBtn = document.createElement('button');
        delBtn.className = 'masa-del-btn';
        delBtn.innerHTML = '&times;';
        delBtn.title = 'Sil';
        delBtn.addEventListener('click', e => {
            e.stopPropagation();
            if (confirm('"' + masa.ad + '" silinsin mi?')) {
                masalar = masalar.filter(m => m.id !== masa.id);
                renderMasalar();
            }
        });
        box.appendChild(delBtn);

        box.addEventListener('mousedown', e => {
            if (!duzenlemeMode || e.target === delBtn) return;
            e.preventDefault();
            const canvasRect = canvas.getBoundingClientRect();
            dragState = {
                masa,
                box,
                offX: e.clientX - canvasRect.left - masa.x,
                offY: e.clientY - canvasRect.top  - masa.y
            };
            box.classList.add('dragging');
        });

        box.addEventListener('touchstart', e => {
            if (!duzenlemeMode || e.target === delBtn) return;
            const touch = e.touches[0];
            const canvasRect = canvas.getBoundingClientRect();
            dragState = {
                masa,
                box,
                offX: touch.clientX - canvasRect.left - masa.x,
                offY: touch.clientY - canvasRect.top  - masa.y
            };
            box.classList.add('dragging');
        }, { passive: true });

        canvas.appendChild(box);
    });
}

document.addEventListener('mousemove', e => {
    if (!dragState) return;
    moveDrag(e.clientX, e.clientY);
});

document.addEventListener('mouseup', () => {
    if (dragState) { dragState.box.classList.remove('dragging'); dragState = null; }
});

document.addEventListener('touchmove', e => {
    if (!dragState) return;
    moveDrag(e.touches[0].clientX, e.touches[0].clientY);
}, { passive: true });

document.addEventListener('touchend', () => {
    if (dragState) { dragState.box.classList.remove('dragging'); dragState = null; }
});

function moveDrag(cx, cy) {
    const canvas = document.getElementById('masaCanvas');
    const rect   = canvas.getBoundingClientRect();
    let nx = cx - rect.left - dragState.offX;
    let ny = cy - rect.top  - dragState.offY;
    nx = Math.max(0, Math.min(nx, canvas.offsetWidth  - 90));
    ny = Math.max(0, Math.min(ny, canvas.offsetHeight - 90));
    dragState.masa.x = Math.round(nx);
    dragState.masa.y = Math.round(ny);
    dragState.box.style.left = nx + 'px';
    dragState.box.style.top  = ny + 'px';
}

function toggleDuzenleme() {
    duzenlemeMode = !duzenlemeMode;
    const btn = document.getElementById('btnDuzenleme');
    btn.classList.toggle('active', duzenlemeMode);
    btn.innerHTML = duzenlemeMode
        ? '<i class="fas fa-check"></i> Düzenleme Açık'
        : '<i class="fas fa-arrows-alt"></i> Masa Düzenle';
    renderMasalar();
}

function yeniMasaEkle() {
    if (!duzenlemeMode) {
        alert('Önce "Masa Düzenle" modunu açın.');
        return;
    }
    const ad = prompt('Masa adı:', 'Masa ' + (masalar.length + 1));
    if (!ad || !ad.trim()) return;
    const idx = masalar.length;
    masalar.push({
        id: 'masa_' + Date.now(),
        ad: ad.trim(),
        x: 20 + (idx % 7) * 100,
        y: 20 + Math.floor(idx / 7) * 110
    });
    renderMasalar();
}

async function saveMasalar() {
    const fd = new FormData();
    fd.append('action', 'save_masalar');
    fd.append('data', JSON.stringify(masalar));
    try {
        const res  = await fetch('admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        const el   = document.getElementById('kasaStatus');
        el.textContent = data.ok ? '✓ Kaydedildi!' : '✗ ' + data.msg;
        el.className   = data.ok ? 'ok' : 'err';
        setTimeout(() => { el.textContent = ''; el.className = ''; }, 3000);
    } catch (e) {}
}

async function saveAyarlar() {
    const statusEl = document.getElementById('settingsStatus');
    statusEl.className = '';
    statusEl.textContent = 'Kaydediliyor...';
    const newData = {
        adres:     document.getElementById('set-adres').value,
        saatler:   document.getElementById('set-saatler').value,
        wifi:      document.getElementById('set-wifi').value,
        instagram: document.getElementById('set-insta').value,
        email:     document.getElementById('set-email').value
    };
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('file', 'ayarlar');
    fd.append('data', JSON.stringify(newData));
    try {
        const res  = await fetch('admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            statusEl.className = 'ok';
            statusEl.textContent = '✓ Ayarlar kaydedildi!';
            setTimeout(() => statusEl.textContent = '', 3000);
        } else {
            statusEl.className = 'err';
            statusEl.textContent = '✗ ' + data.msg;
        }
    } catch (e) {
        statusEl.className = 'err';
        statusEl.textContent = '✗ Bağlantı hatası!';
    }
}
</script>
</body>
</html>
