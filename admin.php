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
            $written = file_put_contents(__DIR__ . '/masalar.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($written !== false) {
                echo json_encode(['ok' => true]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'masalar.json yazılamadı! Sunucu dosya iznini kontrol edin.']);
            }
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Geçersiz veri']);
        }
        exit;
    }

    if ($action === 'get_adisyonlar') {
        $path = __DIR__ . '/adisyonlar.json';
        $data = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        echo json_encode(['ok' => true, 'data' => $data ?: (object)[]]);
        exit;
    }

    if ($action === 'save_adisyon') {
        $masa_id = $_POST['masa_id'] ?? '';
        $items   = json_decode($_POST['items'] ?? '[]', true) ?: [];
        $acilis   = $_POST['acilis']   ?? date('d.m.Y H:i');
        $acilisTs = isset($_POST['acilisTs']) ? (int)$_POST['acilisTs'] : (time() * 1000);
        $path     = __DIR__ . '/adisyonlar.json';
        $adisyonlar = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        if ($masa_id) {
            if (empty($items)) {
                unset($adisyonlar[$masa_id]);
            } else {
                if (empty($adisyonlar[$masa_id])) {
                    $adisyonlar[$masa_id] = ['acilis' => $acilis, 'acilisTs' => $acilisTs, 'items' => $items];
                } else {
                    $adisyonlar[$masa_id]['items'] = $items;
                }
            }
            $written = file_put_contents($path, json_encode($adisyonlar, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($written !== false) {
                echo json_encode(['ok' => true]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'adisyonlar.json yazılamadı! Sunucu dosya iznini kontrol edin.']);
            }
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Masa ID gerekli']);
        }
        exit;
    }

    if ($action === 'upload_logo') {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $mime    = mime_content_type($_FILES['logo']['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($mime, $allowed)) {
                $dest = __DIR__ . '/logo.jpg';
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    echo json_encode(['ok' => true]);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Dosya kaydedilemedi! Klasör iznini kontrol edin.']);
                }
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Sadece resim dosyası yüklenebilir.']);
            }
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Dosya seçilmedi veya çok büyük.']);
        }
        exit;
    }

    if ($action === 'close_adisyon') {
        $masa_id = $_POST['masa_id'] ?? '';
        $path = __DIR__ . '/adisyonlar.json';
        $adisyonlar = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        unset($adisyonlar[$masa_id]);
        $written = file_put_contents($path, json_encode($adisyonlar, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($written !== false) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'adisyonlar.json yazılamadı! Sunucu dosya iznini kontrol edin.']);
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
        .logo-upload-wrap { display: flex; flex-direction: column; gap: 8px; }
        .logo-preview { width: 100px; height: 100px; object-fit: contain; border-radius: 8px; border: 1px solid #333; background: #0a0a0a; }
        .logo-preview.gizli { display: none; }
        .logo-file-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-dosya-sec { background: #2a2a2a; color: #ccc; border: 1px solid #444; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-family: inherit; }
        .btn-dosya-sec:hover { background: #333; }
        .logo-dosya-adi { color: #555; font-size: 0.8rem; }
        .btn-logo-yukle { background: #D4AF37; color: #000; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.85rem; font-family: inherit; display: none; }
        .btn-logo-yukle:hover { background: #b8971e; }
        #logoStatus { font-size: 0.85rem; min-height: 18px; }
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
        #tab-kasa { display: none; flex-direction: column; height: calc(100vh - 110px); padding: 0 !important; }
        #tab-kasa.active { display: flex; }

        .kasa-top-bar { padding: 10px 16px; border-bottom: 1px solid #222; flex-shrink: 0; display: flex; align-items: center; gap: 10px; }
        .btn-duzenle { background: #1c1c1c; color: #D4AF37; border: 1px solid #333; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 0.82rem; font-family: inherit; transition: 0.2s; }
        .btn-duzenle:hover, .btn-duzenle.active { background: #D4AF37; color: #000; border-color: #D4AF37; }
        #kasaStatus { font-size: 0.82rem; font-weight: bold; }
        #kasaStatus.ok { color: #4caf50; }
        #kasaStatus.err { color: #f55; }

        #masaCanvas {
            position: relative;
            flex: 1;
            background: #0a0a0a;
            overflow: hidden;
        }
        #masaCanvas.duzenleme {
            background-image:
                linear-gradient(#141414 1px, transparent 1px),
                linear-gradient(90deg, #141414 1px, transparent 1px);
            background-size: 40px 40px;
        }
        #masaCanvas .bos-mesaj { color: #2a2a2a; padding: 80px; text-align: center; font-size: 0.95rem; pointer-events: none; }

        .masa-box {
            position: absolute;
            width: 115px;
            background: #0d1a0d;
            border: 2px solid #2e7d32;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 8px 8px;
            gap: 4px;
            cursor: pointer;
            user-select: none;
            transition: box-shadow 0.2s;
        }
        .masa-box:hover { box-shadow: 0 0 16px rgba(46,125,50,0.4); }
        .masa-box.dolu { background: #1a0808; border-color: #c62828; }
        .masa-box.dolu:hover { box-shadow: 0 0 16px rgba(198,40,40,0.4); }
        .masa-box.dragging { opacity: 0.8; z-index: 99; }
        .masa-box.duzenleme-mod { cursor: grab; }
        .masa-box.duzenleme-mod:active { cursor: grabbing; }

        .masa-ikon { font-size: 1.7rem; color: #2e7d32; }
        .masa-box.dolu .masa-ikon { color: #c62828; }
        .masa-ad { color: #D4AF37; font-weight: bold; font-size: 0.88rem; text-align: center; }
        .masa-tutar { color: #D4AF37; font-size: 0.82rem; font-weight: bold; }
        .masa-durum { font-size: 0.68rem; }
        .masa-acik  { color: #e57373; }
        .masa-kapali{ color: #388e3c; }
        .masa-action-btn {
            width: 100%; margin-top: 4px;
            padding: 5px 0;
            border: none; border-radius: 5px;
            font-size: 0.75rem; font-weight: bold; font-family: inherit;
            cursor: pointer;
            background: #2e7d32; color: #fff;
        }
        .masa-box.dolu .masa-action-btn { background: #c62828; }
        .masa-del-btn {
            display: none;
            position: absolute;
            top: -8px; right: -8px;
            width: 20px; height: 20px;
            background: #b71c1c; color: #fff;
            border: none; border-radius: 50%;
            cursor: pointer; font-size: 0.7rem;
            align-items: center; justify-content: center;
            z-index: 2;
        }
        #masaCanvas.duzenleme .masa-del-btn { display: flex; }

        .kasa-bottom-bar { display: flex; align-items: center; gap: 8px; padding: 10px 16px; background: #111; border-top: 1px solid #222; flex-shrink: 0; flex-wrap: wrap; }
        .btn-bottom { border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.82rem; font-weight: bold; font-family: inherit; }
        .btn-bottom-gold  { background: #D4AF37; color: #000; }
        .btn-bottom-gray  { background: #2a2a2a; color: #aaa; }
        .btn-bottom-gray:hover { background: #333; }
        .masa-sayisi-text { color: #444; font-size: 0.8rem; margin-left: 4px; }

        /* ===== ADİSYON MODAL ===== */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.88); z-index: 500; align-items: center; justify-content: center; padding: 12px; }
        .modal-overlay.show { display: flex; }
        .adisyon-modal { background: #1a1a1a; border: 1px solid #333; border-radius: 10px; width: 100%; max-width: 920px; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden; }

        /* Header */
        .adisyon-header { background: #222; border-bottom: 1px solid #333; padding: 12px 16px; display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
        .adisyon-header-title { color: #D4AF37; font-size: 1rem; font-weight: bold; flex: 1; }
        .adisyon-header-toplam { color: #D4AF37; font-size: 1.1rem; font-weight: bold; white-space: nowrap; }
        .btn-masa-aktar { background: #1565c0; color: #fff; border: none; padding: 7px 13px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-family: inherit; white-space: nowrap; }
        .btn-masa-aktar:hover { background: #1976d2; }
        .btn-modal-close { background: #333; color: #aaa; border: none; width: 30px; height: 30px; border-radius: 6px; cursor: pointer; font-size: 1rem; flex-shrink: 0; }
        .btn-modal-close:hover { background: #444; color: #fff; }

        /* Body */
        .adisyon-body { display: flex; flex: 1; overflow: hidden; min-height: 0; }

        /* Sol panel - Sipariş */
        .siparis-panel { width: 38%; border-right: 1px solid #2a2a2a; display: flex; flex-direction: column; overflow: hidden; }
        .siparis-title { padding: 10px 14px; border-bottom: 1px solid #2a2a2a; color: #D4AF37; font-size: 0.85rem; font-weight: bold; flex-shrink: 0; }
        .siparis-items { flex: 1; overflow-y: auto; padding: 6px 8px; }
        .siparis-items::-webkit-scrollbar { width: 3px; }
        .siparis-items::-webkit-scrollbar-thumb { background: #333; }
        .siparis-bos { color: #444; text-align: center; padding: 40px 12px; font-size: 0.85rem; }
        .siparis-row { display: flex; align-items: center; gap: 6px; background: #222; border-radius: 6px; padding: 7px 8px; margin-bottom: 4px; }
        .siparis-row-ad { flex: 1; color: #ddd; font-size: 0.82rem; min-width: 0; }
        .siparis-zaman { display: inline-flex; align-items: center; gap: 3px; color: #999; font-size: 0.72rem; margin-left: 6px; }
        .siparis-adet { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        .btn-adet { background: #2a2a2a; color: #fff; border: none; width: 24px; height: 24px; border-radius: 4px; cursor: pointer; font-size: 0.95rem; line-height: 1; }
        .btn-adet:hover { background: #3a3a3a; }
        .siparis-adet-num { color: #D4AF37; font-weight: bold; min-width: 20px; text-align: center; font-size: 0.85rem; }
        .siparis-row-fiyat { color: #D4AF37; font-weight: bold; font-size: 0.8rem; min-width: 46px; text-align: right; flex-shrink: 0; }

        /* Alt butonlar */
        .siparis-footer { padding: 10px 10px 8px; border-top: 1px solid #2a2a2a; flex-shrink: 0; }
        .siparis-toplam { color: #D4AF37; font-weight: bold; font-size: 0.92rem; margin-bottom: 8px; }
        .btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 5px; }
        .btn-act { padding: 9px 4px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: bold; font-family: inherit; }
        .btn-kaydet  { background: #1565c0; color: #fff; }
        .btn-tahsilat{ background: #e65100; color: #fff; }
        .btn-nakit   { background: #2e7d32; color: #fff; }
        .btn-kart    { background: #00838f; color: #fff; }
        .btn-iban    { background: #6a1b9a; color: #fff; }
        .btn-komple  { background: #b71c1c; color: #fff; }
        .btn-kapat   { width: 100%; padding: 8px; background: #2a2a2a; color: #777; border: none; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-family: inherit; }
        .btn-kapat:hover { background: #333; color: #aaa; }

        /* Sağ panel - Menü */
        .menu-panel { width: 62%; display: flex; flex-direction: column; overflow: hidden; }
        .menu-cats { display: flex; gap: 6px; padding: 10px 12px; border-bottom: 1px solid #2a2a2a; flex-shrink: 0; flex-wrap: wrap; }
        .menu-cat-btn { background: #2a2a2a; color: #888; border: none; padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 0.8rem; font-family: inherit; white-space: nowrap; transition: 0.15s; }
        .menu-cat-btn.active { background: #D4AF37; color: #111; font-weight: bold; }
        .menu-items-list { flex: 1; overflow-y: auto; padding: 10px 12px; }
        .menu-items-list::-webkit-scrollbar { width: 3px; }
        .menu-items-list::-webkit-scrollbar-thumb { background: #333; }
        .menu-subcat-label { color: #666; font-size: 0.7rem; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; padding: 6px 2px 4px; border-bottom: 1px solid #2a2a2a; margin-bottom: 6px; }
        .menu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 12px; }
        .menu-item-card { background: #222; border: 1px solid #2a2a2a; border-radius: 6px; padding: 10px 8px; cursor: pointer; font-family: inherit; text-align: center; transition: 0.15s; }
        .menu-item-card:hover { background: #2a2a2a; border-color: #D4AF37; }
        .card-ad   { color: #ccc; font-size: 0.82rem; margin-bottom: 5px; line-height: 1.3; }
        .card-fiyat{ color: #D4AF37; font-weight: bold; font-size: 0.88rem; }

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
            <div class="page-title">
                <i class="fas fa-edit"></i> Veya Sistemden Elle Düzenle
                <span id="saveStatus" style="margin-left:auto"></span>
                <button class="btn-save" onclick="saveMenu()"><i class="fas fa-save"></i> Kaydet</button>
            </div>
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
            <div class="setting-group" style="max-width:320px; margin-top:8px;">
                <label><i class="fas fa-image"></i> Mekan Logosu</label>
                <div class="logo-upload-wrap">
                    <img id="logoPreview" class="logo-preview" src="logo.jpg?v=1" alt="Logo" onerror="this.classList.add('gizli')">
                    <input type="file" id="logoFileInp" accept="image/*" style="display:none" onchange="logoSecildi(this)">
                    <div class="logo-file-row">
                        <button class="btn-dosya-sec" onclick="document.getElementById('logoFileInp').click()"><i class="fas fa-folder-open"></i> Dosya Seç</button>
                        <span class="logo-dosya-adi" id="logoDosyaAdi">Seçilmedi</span>
                    </div>
                    <button class="btn-logo-yukle" id="btnLogoYukle" onclick="logoYukle()"><i class="fas fa-upload"></i> Yükle</button>
                    <div id="logoStatus"></div>
                </div>
            </div>
            <button class="btn-save-settings" onclick="saveAyarlar()"><i class="fas fa-save"></i> Ayarları Kaydet</button>
            <div id="settingsStatus"></div>
        </div>

        <!-- ADİSYON MODAL -->
<div class="modal-overlay" id="adisyonModal">
    <div class="adisyon-modal">
        <div class="adisyon-header">
            <div class="adisyon-header-title"><i class="fas fa-receipt"></i> <span id="adisyonMasaAdi"></span> Adisyonu</div>
            <div class="adisyon-header-toplam" id="adisyonHeaderToplam">0,00 ₺</div>
            <button class="btn-masa-aktar" onclick="masayiAktarAc()"><i class="fas fa-exchange-alt"></i> Masayı Aktar</button>
            <button class="btn-modal-close" onclick="closeAdisyonModal()">×</button>
        </div>
        <!-- Masa aktar paneli -->
        <div id="aktarPanel" style="display:none; background:#1e1e1e; border-bottom:1px solid #333; padding:10px 16px;">
            <div style="color:#D4AF37; font-size:0.82rem; margin-bottom:8px;">Hangi masaya aktarılsın?</div>
            <div id="aktarMasaListesi" style="display:flex; gap:8px; flex-wrap:wrap;"></div>
        </div>
        <div class="adisyon-body">
            <!-- Sol: Sipariş listesi -->
            <div class="siparis-panel">
                <div class="siparis-title"><i class="fas fa-list"></i> Sipariş Listesi</div>
                <div class="siparis-items" id="sepetItems"></div>
                <div class="siparis-footer">
                    <div class="siparis-toplam">TOPLAM: <span id="sepetToplam">0,00 ₺</span></div>
                    <div class="btn-grid">
                        <button class="btn-act btn-kaydet"  onclick="masayaKaydet()"><i class="fas fa-save"></i> Masaya Kaydet</button>
                        <button class="btn-act btn-tahsilat" onclick="kismiTahsilat()">+ Kısmi Tahsilat Al</button>
                    </div>
                    <div class="btn-grid">
                        <button class="btn-act btn-nakit" onclick="hesabiKapat('nakit')"><i class="fas fa-money-bill"></i> Nakit</button>
                        <button class="btn-act btn-kart"  onclick="hesabiKapat('kart')"><i class="fas fa-credit-card"></i> Kart</button>
                    </div>
                    <div class="btn-grid">
                        <button class="btn-act btn-iban"   onclick="hesabiKapat('iban')"><i class="fas fa-university"></i> İBAN</button>
                        <button class="btn-act btn-komple" onclick="kompleIptal()"><i class="fas fa-trash"></i> Komple İptal</button>
                    </div>
                    <button class="btn-kapat" onclick="closeAdisyonModal()">✕ Pencereyi Kapat</button>
                </div>
            </div>
            <!-- Sağ: Menü -->
            <div class="menu-panel">
                <div class="menu-cats" id="menuCatBtns"></div>
                <div class="menu-items-list" id="menuItemsList"></div>
            </div>
        </div>
    </div>
</div>

        <!-- Kasa Tab -->
        <div class="tab-content" id="tab-kasa">
            <div class="kasa-top-bar">
                <button class="btn-duzenle" id="btnDuzenleme" onclick="toggleDuzenleme()">
                    <i class="fas fa-arrows-alt"></i> Krokiyi / İsimleri Düzenle (Sürükle-Bırak)
                </button>
                <span id="kasaStatus"></span>
            </div>
            <div id="masaCanvas">
                <div class="bos-mesaj">Yükleniyor...</div>
            </div>
            <div class="kasa-bottom-bar">
                <button class="btn-bottom btn-bottom-gold" onclick="yeniMasaEkle()"><i class="fas fa-plus"></i> Yeni Masa Ekle</button>
                <button class="btn-bottom btn-bottom-gray" onclick="sonMasayiSil()">— Son Masayı Sil</button>
                <button class="btn-bottom btn-bottom-gray" onclick="saveMasalar()"><i class="fas fa-save"></i> Kroikiyi Kaydet</button>
                <span class="masa-sayisi-text" id="masaSayisiText"></span>
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
    if (name === 'kasa' && !kasaYuklendi) { loadKasa(); kasaYuklendi = true; }
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
let adisyonlar = {};
let duzenlemeMode = false;
let dragState = null;
let dragMoved = false;
let aktifMasaId = null;
let aktifKategori = null;
let menuArama = '';
let kasaYuklendi = false;

async function loadKasa() {
    const canvas = document.getElementById('masaCanvas');
    try {
        const fd1 = new FormData(); fd1.append('action', 'get_masalar');
        const fd2 = new FormData(); fd2.append('action', 'get_adisyonlar');
        const [r1, r2] = await Promise.all([
            fetch('admin.php', { method: 'POST', body: fd1 }),
            fetch('admin.php', { method: 'POST', body: fd2 })
        ]);
        const d1 = await r1.json();
        const d2 = await r2.json();
        masalar    = Array.isArray(d1.data) ? d1.data : [];
        adisyonlar = (d2.data && typeof d2.data === 'object') ? d2.data : {};
        renderMasalar();
    } catch (e) {
        canvas.innerHTML = '<div class="bos-mesaj" style="color:#f55">Yüklenemedi: ' + e.message + '</div>';
    }
}

function renderMasalar() {
    const canvas = document.getElementById('masaCanvas');
    canvas.innerHTML = '';
    canvas.classList.toggle('duzenleme', duzenlemeMode);

    document.getElementById('masaSayisiText').textContent = '(Şu an toplam ' + masalar.length + ' masa var)';

    if (masalar.length === 0) {
        canvas.innerHTML = '<div class="bos-mesaj">Henüz masa eklenmedi.<br>"Yeni Masa Ekle" butonunu kullanın.</div>';
        return;
    }

    masalar.forEach(masa => {
        const adisyon = adisyonlar[masa.id];
        const dolu    = adisyon && adisyon.items && adisyon.items.length > 0;

        const box = document.createElement('div');
        box.className = 'masa-box' + (dolu ? ' dolu' : '') + (duzenlemeMode ? ' duzenleme-mod' : '');
        box.style.left = (masa.x || 20) + 'px';
        box.style.top  = (masa.y || 20) + 'px';
        box.dataset.id = masa.id;

        // İkon
        const ikonEl = document.createElement('div');
        ikonEl.className = 'masa-ikon';
        ikonEl.innerHTML = '<i class="fas fa-users"></i>';
        box.appendChild(ikonEl);

        // Ad
        const adEl = document.createElement('div');
        adEl.className = 'masa-ad';
        adEl.textContent = masa.ad;
        box.appendChild(adEl);

        // Tutar
        const tutarEl = document.createElement('div');
        tutarEl.className = 'masa-tutar';
        tutarEl.textContent = dolu
            ? adisyon.items.reduce((s, i) => s + i.fiyat * i.adet, 0).toFixed(2).replace('.', ',') + ' ₺'
            : '0 ₺';
        box.appendChild(tutarEl);

        // Durum
        const durumEl = document.createElement('div');
        durumEl.className = 'masa-durum ' + (dolu ? 'masa-acik' : 'masa-kapali');
        durumEl.textContent = dolu ? 'Açık · ' + dakikaHesapla(adisyon.acilisTs) : 'Kapalı';
        box.appendChild(durumEl);

        // Aksiyon butonu
        const actionBtn = document.createElement('button');
        actionBtn.className = 'masa-action-btn';
        actionBtn.textContent = dolu ? 'Adisyonu Aç' : 'Masayı Aç';
        actionBtn.addEventListener('click', e => { e.stopPropagation(); if (!dragMoved) openAdisyon(masa.id); });
        box.appendChild(actionBtn);

        // Sil butonu (sadece düzenleme modunda görünür)
        const delBtn = document.createElement('button');
        delBtn.className = 'masa-del-btn';
        delBtn.innerHTML = '&times;';
        delBtn.addEventListener('click', e => {
            e.stopPropagation();
            if (confirm('"' + masa.ad + '" silinsin mi?')) {
                masalar = masalar.filter(m => m.id !== masa.id);
                renderMasalar();
            }
        });
        box.appendChild(delBtn);

        box.addEventListener('click', e => {
            if (e.target === delBtn || e.target === actionBtn || dragMoved) return;
            openAdisyon(masa.id);
        });

        box.addEventListener('mousedown', e => {
            if (!duzenlemeMode || e.target === delBtn) return;
            dragMoved = false;
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
    setTimeout(() => { dragMoved = false; }, 0);
});

document.addEventListener('touchmove', e => {
    if (!dragState) return;
    moveDrag(e.touches[0].clientX, e.touches[0].clientY);
}, { passive: true });

document.addEventListener('touchend', () => {
    if (dragState) { dragState.box.classList.remove('dragging'); dragState = null; }
});

function moveDrag(cx, cy) {
    dragMoved = true;
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

function sonMasayiSil() {
    if (!masalar.length) return;
    const son = masalar[masalar.length - 1];
    if (confirm('"' + son.ad + '" silinsin mi?')) {
        masalar.pop();
        renderMasalar();
    }
}

function yeniMasaEkle() {
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

setInterval(() => {
    if (document.getElementById('tab-kasa').classList.contains('active')) renderMasalar();
}, 60000);

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

// ===== ADİSYON =====
function openAdisyon(masaId) {
    aktifMasaId   = masaId;
    aktifKategori = null;
    menuArama     = '';
    const masa = masalar.find(m => m.id === masaId);
    document.getElementById('adisyonMasaAdi').textContent = masa ? masa.ad : '';
    renderMenuPanel();
    renderSepet();
    document.getElementById('adisyonModal').classList.add('show');
}

function closeAdisyonModal() {
    const items = adisyonlar[aktifMasaId]?.items || [];
    if (items.some(i => !i.kaydedildi) && !confirm('Kaydedilmemiş ürünler var. Kaydetmeden kapatmak istediğinize emin misiniz?')) return;
    document.getElementById('adisyonModal').classList.remove('show');
    aktifMasaId = null;
    renderMasalar();
}

function simdiSaat() {
    const d = new Date();
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

function renderMenuPanel() {
    const cats = Object.keys(menuData);
    if (!aktifKategori && cats.length > 0) aktifKategori = cats[0];

    const catsEl = document.getElementById('menuCatBtns');
    catsEl.innerHTML = '';
    cats.forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'menu-cat-btn' + (aktifKategori === cat ? ' active' : '');
        btn.textContent = cat;
        btn.onclick = () => { aktifKategori = cat; renderMenuPanel(); };
        catsEl.appendChild(btn);
    });

    const itemsEl = document.getElementById('menuItemsList');
    itemsEl.innerHTML = '';
    if (!aktifKategori || !menuData[aktifKategori]) return;

    const catData = menuData[aktifKategori];
    const subcats = catData.alt_kategoriler
        ? Object.entries(catData.alt_kategoriler)
        : catData.items ? [['', catData.items]] : [];

    subcats.forEach(([subAd, items]) => {
        if (!items || !items.length) return;
        if (subAd) {
            const lbl = document.createElement('div');
            lbl.className = 'menu-subcat-label';
            lbl.textContent = subAd;
            itemsEl.appendChild(lbl);
        }
        const grid = document.createElement('div');
        grid.className = 'menu-grid';
        items.forEach(item => {
            const card = document.createElement('button');
            card.className = 'menu-item-card';
            card.innerHTML = '<div class="card-ad">' + item.ad + '</div><div class="card-fiyat">' + Number(item.fiyat).toFixed(2) + ' ₺</div>';
            card.onclick = () => addToAdisyon(item);
            grid.appendChild(card);
        });
        itemsEl.appendChild(grid);
    });
}

function addToAdisyon(item) {
    if (!aktifMasaId) return;
    if (!adisyonlar[aktifMasaId]) {
        adisyonlar[aktifMasaId] = { acilis: new Date().toLocaleString('tr-TR'), acilisTs: Date.now(), items: [] };
    }
    const items = adisyonlar[aktifMasaId].items;
    // Aynı ürün daha önce kaydedilmemişse (henüz "Masaya Kaydet" basılmamışsa) o satıra eklenir;
    // kaydedilmiş bir siparişe yeni ekleme yapılırsa farklı saatte ayrı satır olarak görünsün diye yeni satır açılır.
    const var_ = items.find(i => i.ad === item.ad && i.fiyat === item.fiyat && !i.kaydedildi);
    if (var_) {
        var_.adet++;
    } else {
        items.push({ ad: item.ad, fiyat: item.fiyat, adet: 1, zaman: simdiSaat(), kaydedildi: false });
    }
    renderSepet();
}

function changeAdet(idx, delta) {
    if (!aktifMasaId || !adisyonlar[aktifMasaId]) return;
    adisyonlar[aktifMasaId].items[idx].adet += delta;
    if (adisyonlar[aktifMasaId].items[idx].adet <= 0) adisyonlar[aktifMasaId].items.splice(idx, 1);
    if (!adisyonlar[aktifMasaId].items.length) delete adisyonlar[aktifMasaId];
    renderSepet();
}

function renderSepet() {
    const sepetEl  = document.getElementById('sepetItems');
    const toplamEl = document.getElementById('sepetToplam');
    const hdrEl    = document.getElementById('adisyonHeaderToplam');
    const items    = adisyonlar[aktifMasaId]?.items || [];
    if (!items.length) {
        sepetEl.innerHTML = '<div class="siparis-bos">Henüz ürün eklenmedi</div>';
        toplamEl.textContent = '0,00 ₺';
        hdrEl.textContent    = '0,00 ₺';
        return;
    }
    sepetEl.innerHTML = '';
    let toplam = 0;
    items.forEach((item, idx) => {
        toplam += item.fiyat * item.adet;
        const row = document.createElement('div');
        row.className = 'siparis-row';
        row.innerHTML =
            '<div class="siparis-row-ad">' + item.ad + (item.zaman ? '<span class="siparis-zaman"><i class="far fa-clock"></i> ' + item.zaman + '</span>' : '') + '</div>' +
            '<div class="siparis-adet">' +
                '<button class="btn-adet" onclick="changeAdet(' + idx + ',-1)">−</button>' +
                '<span class="siparis-adet-num">' + item.adet + '</span>' +
                '<button class="btn-adet" onclick="changeAdet(' + idx + ',1)">+</button>' +
            '</div>' +
            '<div class="siparis-row-fiyat">' + (item.fiyat * item.adet).toFixed(2) + ' ₺</div>';
        sepetEl.appendChild(row);
    });
    const fmt = toplam.toFixed(2).replace('.', ',') + ' ₺';
    toplamEl.textContent = fmt;
    hdrEl.textContent    = fmt;
}

function dakikaHesapla(acilisTs) {
    if (!acilisTs) return '';
    const dk = Math.floor((Date.now() - acilisTs) / 60000);
    if (dk < 1)  return 'az önce';
    if (dk < 60) return dk + ' dk';
    const sa = Math.floor(dk / 60);
    const kalan = dk % 60;
    return sa + ' sa' + (kalan > 0 ? ' ' + kalan + ' dk' : '');
}

function masayiAktarAc() {
    const panel   = document.getElementById('aktarPanel');
    const listeEl = document.getElementById('aktarMasaListesi');
    if (panel.style.display !== 'none') { panel.style.display = 'none'; return; }
    listeEl.innerHTML = '';
    const digerMasalar = masalar.filter(m => m.id !== aktifMasaId);
    if (!digerMasalar.length) { alert('Başka masa yok.'); return; }
    digerMasalar.forEach(masa => {
        const btn = document.createElement('button');
        const dolu = adisyonlar[masa.id]?.items?.length > 0;
        btn.style.cssText = 'background:' + (dolu ? '#3a1a00' : '#1c1c1c') + ';color:' + (dolu ? '#ff8f00' : '#D4AF37') + ';border:1px solid #444;padding:7px 14px;border-radius:6px;cursor:pointer;font-size:0.82rem;font-family:inherit;';
        btn.textContent = masa.ad + (dolu ? ' (dolu)' : '');
        btn.onclick = () => masayiAktarYap(masa.id);
        listeEl.appendChild(btn);
    });
    panel.style.display = 'block';
}

async function masayiAktarYap(hedefId) {
    const hedef = masalar.find(m => m.id === hedefId);
    if (adisyonlar[hedefId]?.items?.length) {
        if (!confirm('"' + hedef.ad + '" masasında zaten adisyon var. Üzerine eklensin mi?')) return;
    }
    const kaynakItems = adisyonlar[aktifMasaId]?.items || [];
    if (!kaynakItems.length) { alert('Aktarılacak ürün yok.'); return; }

    if (!adisyonlar[hedefId]) {
        adisyonlar[hedefId] = { acilis: new Date().toLocaleString('tr-TR'), items: [] };
    }
    kaynakItems.forEach(item => {
        const var_ = adisyonlar[hedefId].items.find(i => i.ad === item.ad && i.fiyat === item.fiyat);
        if (var_) { var_.adet += item.adet; } else { adisyonlar[hedefId].items.push({ ...item }); }
    });
    delete adisyonlar[aktifMasaId];

    // Kaydet
    const fd1 = new FormData(); fd1.append('action','close_adisyon'); fd1.append('masa_id', aktifMasaId);
    const fd2 = new FormData(); fd2.append('action','save_adisyon'); fd2.append('masa_id', hedefId);
    fd2.append('items', JSON.stringify(adisyonlar[hedefId].items));
    fd2.append('acilis', adisyonlar[hedefId].acilis);
    try { await Promise.all([fetch('admin.php',{method:'POST',body:fd1}), fetch('admin.php',{method:'POST',body:fd2})]); } catch(e) {}

    document.getElementById('aktarPanel').style.display = 'none';
    closeAdisyonModal();
}

async function masayaKaydet() {
    const items = adisyonlar[aktifMasaId]?.items || [];
    const oncekiKaydedildi = items.map(i => i.kaydedildi);
    items.forEach(i => i.kaydedildi = true);

    const el = document.getElementById('sepetToplam');
    const orig = el.textContent;
    el.textContent = 'Kaydediliyor...';
    const sonuc = await autoSaveAdisyon();

    if (sonuc.ok) {
        renderSepet();
        document.getElementById('sepetToplam').textContent = '✓ Kaydedildi';
        setTimeout(() => { document.getElementById('sepetToplam').textContent = orig; }, 1500);
    } else {
        items.forEach((i, idx) => i.kaydedildi = oncekiKaydedildi[idx]);
        alert('✗ Sipariş sunucuya kaydedilemedi: ' + (sonuc.msg || 'Bilinmeyen hata') + '\nLütfen sunucu dosya izinlerini (adisyonlar.json) kontrol edin.');
        document.getElementById('sepetToplam').textContent = orig;
    }
}

function kismiTahsilat() {
    const tutar = prompt('Tahsil edilen tutar (₺):');
    if (!tutar || isNaN(parseFloat(tutar))) return;
    alert('Kısmi tahsilat kaydedildi: ' + parseFloat(tutar).toFixed(2) + ' ₺');
}

function kompleIptal() {
    if (!aktifMasaId) return;
    if (!confirm('Tüm adisyon iptal edilsin mi?')) return;
    delete adisyonlar[aktifMasaId];
    const fd = new FormData();
    fd.append('action', 'close_adisyon');
    fd.append('masa_id', aktifMasaId);
    fetch('admin.php', { method: 'POST', body: fd }).catch(() => {});
    closeAdisyonModal();
}

async function autoSaveAdisyon() {
    if (!aktifMasaId) return { ok: false, msg: 'Masa seçili değil' };
    const fd = new FormData();
    fd.append('action', 'save_adisyon');
    fd.append('masa_id', aktifMasaId);
    fd.append('items', JSON.stringify(adisyonlar[aktifMasaId]?.items || []));
    if (adisyonlar[aktifMasaId]) {
        fd.append('acilis',   adisyonlar[aktifMasaId].acilis);
        fd.append('acilisTs', adisyonlar[aktifMasaId].acilisTs || Date.now());
    }
    try {
        const res = await fetch('admin.php', { method: 'POST', body: fd });
        return await res.json();
    } catch (e) {
        return { ok: false, msg: 'Bağlantı hatası!' };
    }
}

async function hesabiKapat(odemeYontemi) {
    if (!aktifMasaId) return;
    const masa   = masalar.find(m => m.id === aktifMasaId);
    const items  = adisyonlar[aktifMasaId]?.items || [];
    if (!items.length) { alert('Adisyonda ürün yok.'); return; }
    const toplam = items.reduce((s, i) => s + i.fiyat * i.adet, 0);
    const yontem = { nakit: 'Nakit', kart: 'Kart', iban: 'İBAN' }[odemeYontemi] || odemeYontemi;
    if (!confirm((masa?.ad || 'Masa') + ' hesabı ' + yontem + ' ile kapatılsın mı?\nToplam: ' + toplam.toFixed(2) + ' ₺')) return;
    delete adisyonlar[aktifMasaId];
    const fd = new FormData();
    fd.append('action', 'close_adisyon');
    fd.append('masa_id', aktifMasaId);
    try { await fetch('admin.php', { method: 'POST', body: fd }); } catch (e) {}
    closeAdisyonModal();
}

function logoSecildi(inp) {
    if (!inp.files[0]) return;
    document.getElementById('logoDosyaAdi').textContent = inp.files[0].name;
    document.getElementById('btnLogoYukle').style.display = 'inline-block';
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('logoPreview');
        img.src = e.target.result;
        img.classList.remove('gizli');
    };
    reader.readAsDataURL(inp.files[0]);
}

async function logoYukle() {
    const inp = document.getElementById('logoFileInp');
    if (!inp.files[0]) return;
    const statusEl = document.getElementById('logoStatus');
    statusEl.style.color = '#888';
    statusEl.textContent = 'Yükleniyor...';
    const fd = new FormData();
    fd.append('action', 'upload_logo');
    fd.append('logo', inp.files[0]);
    try {
        const res  = await fetch('admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            statusEl.style.color = '#4caf50';
            statusEl.textContent = '✓ Logo güncellendi!';
            document.getElementById('logoPreview').src = 'logo.jpg?v=' + Date.now();
            document.getElementById('btnLogoYukle').style.display = 'none';
            document.getElementById('logoDosyaAdi').textContent = 'Seçilmedi';
            inp.value = '';
        } else {
            statusEl.style.color = '#f55';
            statusEl.textContent = '✗ ' + data.msg;
        }
    } catch (e) {
        statusEl.style.color = '#f55';
        statusEl.textContent = '✗ Bağlantı hatası';
    }
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
