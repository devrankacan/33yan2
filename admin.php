<?php
session_start();
define('ADMIN_PASS', 'admin33');

$MASALAR_FILE = __DIR__ . '/masalar.json';
$ADISYON_FILE = __DIR__ . '/adisyonlar.json';
$RAPOR_FILE   = __DIR__ . '/raporlar.json';
$MENU_FILE    = __DIR__ . '/menu.json';
$AYARLAR_FILE = __DIR__ . '/ayarlar.json';

function readJson($f, $d = []) {
    if (!file_exists($f)) return $d;
    $v = json_decode(file_get_contents($f), true);
    return $v !== null ? $v : $d;
}
function writeJson($f, $d) {
    return file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}

// masalar.json yoksa varsayılan 7 masa oluştur
if (!file_exists($MASALAR_FILE)) {
    $pos = [[780,130],[1000,270],[760,330],[440,440],[220,530],[440,330],[220,140]];
    $m = ['masalar' => []];
    for ($i = 1; $i <= 7; $i++)
        $m['masalar'][] = ['id' => $i, 'ad' => 'Masa ' . $i, 'x' => $pos[$i-1][0], 'y' => $pos[$i-1][1]];
    writeJson($MASALAR_FILE, $m);
}
if (!file_exists($ADISYON_FILE)) file_put_contents($ADISYON_FILE, '{}');
if (!file_exists($RAPOR_FILE))   file_put_contents($RAPOR_FILE, '[]');

// ===== AJAX =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_POST['action'] ?? '';

    if ($act === 'login') {
        if (($_POST['sifre'] ?? '') === ADMIN_PASS) { $_SESSION['admin'] = true; echo json_encode(['ok' => true]); }
        else echo json_encode(['ok' => false, 'msg' => 'Şifre hatalı!']);
        exit;
    }
    if ($act === 'logout') { session_destroy(); echo json_encode(['ok' => true]); exit; }
    if (empty($_SESSION['admin'])) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Yetkisiz']); exit; }

    switch ($act) {
        case 'get_tables':
            $ms = readJson($MASALAR_FILE, ['masalar' => []]);
            $ad = readJson($ADISYON_FILE, []);
            $out = [];
            foreach ($ms['masalar'] as $m) {
                $id = (string)$m['id'];
                $out[] = ['id' => $m['id'], 'ad' => $m['ad'], 'x' => $m['x'] ?? 100, 'y' => $m['y'] ?? 100,
                          'acik' => !empty($ad[$id]['acik']), 'toplam' => $ad[$id]['toplam'] ?? 0];
            }
            echo json_encode(['ok' => true, 'masalar' => $out, 'count' => count($out)]);
            break;

        case 'get_adisyon':
            $id = (string)($_POST['masa_id'] ?? '');
            $ad = readJson($ADISYON_FILE, []);
            echo json_encode(['ok' => true, 'adisyon' => $ad[$id] ?? ['acik' => false, 'urunler' => [], 'toplam' => 0]]);
            break;

        case 'open_table':
            $id = (string)($_POST['masa_id'] ?? '');
            $ad = readJson($ADISYON_FILE, []);
            if (empty($ad[$id]['acik'])) {
                $ad[$id] = ['acik' => true, 'acilis' => date('Y-m-d H:i:s'), 'urunler' => [], 'toplam' => 0];
                writeJson($ADISYON_FILE, $ad);
            }
            echo json_encode(['ok' => true]);
            break;

        case 'add_item':
            $id    = (string)($_POST['masa_id'] ?? '');
            $adn   = trim($_POST['ad'] ?? '');
            $fiyat = floatval($_POST['fiyat'] ?? 0);
            $ad = readJson($ADISYON_FILE, []);
            if (empty($ad[$id]['acik'])) { echo json_encode(['ok' => false, 'msg' => 'Masa kapalı!']); exit; }
            $ad[$id]['urunler'][] = ['ad' => $adn, 'fiyat' => $fiyat, 'adet' => 1];
            $t = 0; foreach ($ad[$id]['urunler'] as $u) $t += $u['fiyat'] * $u['adet'];
            $ad[$id]['toplam'] = $t;
            writeJson($ADISYON_FILE, $ad);
            echo json_encode(['ok' => true, 'toplam' => $t]);
            break;

        case 'inc_item':
            $id  = (string)($_POST['masa_id'] ?? '');
            $idx = intval($_POST['idx'] ?? -1);
            $ad  = readJson($ADISYON_FILE, []);
            if (!isset($ad[$id]['urunler'][$idx])) { echo json_encode(['ok' => false, 'msg' => 'Yok!']); exit; }
            $ad[$id]['urunler'][$idx]['adet']++;
            $t = 0; foreach ($ad[$id]['urunler'] as $u) $t += $u['fiyat'] * $u['adet'];
            $ad[$id]['toplam'] = $t;
            writeJson($ADISYON_FILE, $ad);
            echo json_encode(['ok' => true, 'toplam' => $t]);
            break;

        case 'remove_item':
            $id  = (string)($_POST['masa_id'] ?? '');
            $idx = intval($_POST['idx'] ?? -1);
            $ad  = readJson($ADISYON_FILE, []);
            if (!isset($ad[$id]['urunler'][$idx])) { echo json_encode(['ok' => false, 'msg' => 'Yok!']); exit; }
            if ($ad[$id]['urunler'][$idx]['adet'] > 1) $ad[$id]['urunler'][$idx]['adet']--;
            else array_splice($ad[$id]['urunler'], $idx, 1);
            $ad[$id]['urunler'] = array_values($ad[$id]['urunler']);
            $t = 0; foreach ($ad[$id]['urunler'] as $u) $t += $u['fiyat'] * $u['adet'];
            $ad[$id]['toplam'] = $t;
            writeJson($ADISYON_FILE, $ad);
            echo json_encode(['ok' => true, 'toplam' => $t]);
            break;

        case 'close_bill':
            $id    = (string)($_POST['masa_id'] ?? '');
            $mad   = trim($_POST['masa_ad'] ?? 'Masa');
            $odeme = trim($_POST['odeme'] ?? 'nakit');
            $ad    = readJson($ADISYON_FILE, []);
            if (empty($ad[$id]['acik'])) { echo json_encode(['ok' => false, 'msg' => 'Masa zaten kapalı!']); exit; }
            $rep = readJson($RAPOR_FILE, []);
            $rep[] = ['masa_id' => $id, 'masa_ad' => $mad, 'acilis' => $ad[$id]['acilis'],
                      'kapanis' => date('Y-m-d H:i:s'), 'urunler' => $ad[$id]['urunler'],
                      'toplam' => $ad[$id]['toplam'], 'odeme' => $odeme];
            writeJson($RAPOR_FILE, $rep);
            unset($ad[$id]);
            writeJson($ADISYON_FILE, $ad);
            echo json_encode(['ok' => true]);
            break;

        case 'kismi_tahsilat':
            $id     = (string)($_POST['masa_id'] ?? '');
            $mad    = trim($_POST['masa_ad'] ?? 'Masa');
            $miktar = floatval($_POST['miktar'] ?? 0);
            $odeme  = trim($_POST['odeme'] ?? 'nakit');
            $ad     = readJson($ADISYON_FILE, []);
            if (empty($ad[$id]['acik'])) { echo json_encode(['ok' => false, 'msg' => 'Masa kapalı!']); exit; }
            if ($miktar <= 0) { echo json_encode(['ok' => false, 'msg' => 'Geçersiz miktar!']); exit; }
            $rep = readJson($RAPOR_FILE, []);
            $rep[] = ['masa_id' => $id, 'masa_ad' => $mad, 'acilis' => $ad[$id]['acilis'],
                      'kapanis' => date('Y-m-d H:i:s'), 'urunler' => [],
                      'toplam' => $miktar, 'odeme' => $odeme, 'kismi' => true];
            writeJson($RAPOR_FILE, $rep);
            echo json_encode(['ok' => true]);
            break;

        case 'komple_iptal':
            $id = (string)($_POST['masa_id'] ?? '');
            $ad = readJson($ADISYON_FILE, []);
            unset($ad[$id]);
            writeJson($ADISYON_FILE, $ad);
            echo json_encode(['ok' => true]);
            break;

        case 'save_layout':
            $data = json_decode($_POST['masalar'] ?? '[]', true);
            if ($data === null) { echo json_encode(['ok' => false, 'msg' => 'Hata']); exit; }
            $ms = readJson($MASALAR_FILE, ['masalar' => []]);
            $pm = [];
            foreach ($data as $d) $pm[$d['id']] = $d;
            foreach ($ms['masalar'] as &$m)
                if (isset($pm[$m['id']])) { $m['x'] = $pm[$m['id']]['x']; $m['y'] = $pm[$m['id']]['y']; }
            writeJson($MASALAR_FILE, $ms);
            echo json_encode(['ok' => true]);
            break;

        case 'rename_table':
            $mid = intval($_POST['masa_id'] ?? 0);
            $adn = trim($_POST['ad'] ?? '');
            if (!$adn) { echo json_encode(['ok' => false, 'msg' => 'İsim boş!']); exit; }
            $ms = readJson($MASALAR_FILE, ['masalar' => []]);
            foreach ($ms['masalar'] as &$m) if ($m['id'] === $mid) { $m['ad'] = $adn; break; }
            writeJson($MASALAR_FILE, $ms);
            echo json_encode(['ok' => true]);
            break;

        case 'add_table':
            $ms  = readJson($MASALAR_FILE, ['masalar' => []]);
            $ids = array_column($ms['masalar'], 'id');
            $nid = empty($ids) ? 1 : max($ids) + 1;
            $ms['masalar'][] = ['id' => $nid, 'ad' => 'Masa ' . $nid, 'x' => 50, 'y' => 50];
            writeJson($MASALAR_FILE, $ms);
            echo json_encode(['ok' => true, 'id' => $nid, 'ad' => 'Masa ' . $nid]);
            break;

        case 'remove_table':
            $ms = readJson($MASALAR_FILE, ['masalar' => []]);
            if (empty($ms['masalar'])) { echo json_encode(['ok' => false, 'msg' => 'Masa yok!']); exit; }
            $last = end($ms['masalar']);
            $ad   = readJson($ADISYON_FILE, []);
            if (!empty($ad[(string)$last['id']]['acik'])) { echo json_encode(['ok' => false, 'msg' => 'Son masa açık, önce hesabı kapatın!']); exit; }
            array_pop($ms['masalar']);
            writeJson($MASALAR_FILE, $ms);
            echo json_encode(['ok' => true]);
            break;

        case 'reset_layout':
            $ms = readJson($MASALAR_FILE, ['masalar' => []]);
            foreach ($ms['masalar'] as $i => &$m) { $m['x'] = 50 + ($i % 3) * 220; $m['y'] = 50 + floor($i / 3) * 180; }
            writeJson($MASALAR_FILE, $ms);
            echo json_encode(['ok' => true]);
            break;

        case 'get_reports':
            $rep = readJson($RAPOR_FILE, []);
            $flt = $_POST['filter'] ?? 'today';
            $out = [];
            foreach ($rep as $r) {
                $ts = strtotime($r['kapanis'] ?? '');
                if ($flt === 'today' && date('Y-m-d', $ts) !== date('Y-m-d')) continue;
                if ($flt === 'week'  && $ts < strtotime('-7 days'))  continue;
                if ($flt === 'month' && $ts < strtotime('-30 days')) continue;
                $out[] = $r;
            }
            $t = array_sum(array_column($out, 'toplam'));
            echo json_encode(['ok' => true, 'raporlar' => array_reverse($out), 'toplam' => $t]);
            break;

        case 'save_menu':
            $d = json_decode($_POST['data'] ?? '', true);
            if ($d !== null) { $w = file_put_contents($MENU_FILE, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); echo json_encode($w !== false ? ['ok' => true] : ['ok' => false, 'msg' => 'Yazılamadı!']); }
            else echo json_encode(['ok' => false, 'msg' => 'Geçersiz JSON!']);
            break;

        case 'save_ayarlar':
            $d = json_decode($_POST['data'] ?? '', true);
            if ($d !== null) { $w = file_put_contents($AYARLAR_FILE, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); echo json_encode($w !== false ? ['ok' => true] : ['ok' => false, 'msg' => 'Yazılamadı!']); }
            else echo json_encode(['ok' => false, 'msg' => 'Geçersiz JSON!']);
            break;

        case 'transfer_table':
            $from = (string)($_POST['from_id'] ?? '');
            $to   = (string)($_POST['to_id']   ?? '');
            if (!$from || !$to || $from === $to) { echo json_encode(['ok'=>false,'msg'=>'Geçersiz masa!']); exit; }
            $ad = readJson($ADISYON_FILE, []);
            if (empty($ad[$from]['acik'])) { echo json_encode(['ok'=>false,'msg'=>'Kaynak masa kapalı!']); exit; }
            if (empty($ad[$to]['acik'])) {
                $ad[$to] = ['acik'=>true,'acilis'=>date('Y-m-d H:i:s'),'urunler'=>[],'toplam'=>0];
            }
            foreach ($ad[$from]['urunler'] as $urun) {
                $found = false;
                foreach ($ad[$to]['urunler'] as &$u) {
                    if ($u['ad']===$urun['ad'] && $u['fiyat']==$urun['fiyat']) { $u['adet']+=$urun['adet']; $found=true; break; }
                }
                if (!$found) $ad[$to]['urunler'][] = $urun;
            }
            $t = 0; foreach ($ad[$to]['urunler'] as $u) $t += $u['fiyat']*$u['adet'];
            $ad[$to]['toplam'] = $t;
            unset($ad[$from]);
            writeJson($ADISYON_FILE, $ad);
            echo json_encode(['ok'=>true]);
            break;

        case 'upload_logo':
            header('Content-Type: application/json; charset=utf-8');
            if (empty($_FILES['logo'])) { echo json_encode(['ok'=>false,'msg'=>'Dosya yok!']); exit; }
            $file = $_FILES['logo'];
            $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
            if (!in_array($file['type'], $allowed)) { echo json_encode(['ok'=>false,'msg'=>'Geçersiz dosya türü!']); exit; }
            if ($file['size'] > 2*1024*1024) { echo json_encode(['ok'=>false,'msg'=>'Dosya 2MB\'dan büyük olmamalı!']); exit; }
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fname = 'logo.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $fname)) {
                $logoUrl = 'uploads/' . $fname;
                $ayarlar = readJson($AYARLAR_FILE, []);
                $ayarlar['logo'] = $logoUrl;
                writeJson($AYARLAR_FILE, $ayarlar);
                echo json_encode(['ok'=>true,'url'=>$logoUrl]);
            } else {
                echo json_encode(['ok'=>false,'msg'=>'Yükleme başarısız!']);
            }
            exit;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem']);
    }
    exit;
}

$loggedIn = !empty($_SESSION['admin']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>33 YAN 2 | KASA POS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#111;color:#eee;font-family:Arial,sans-serif;min-height:100vh;}

/* LOGIN */
.login-wrap{display:flex;justify-content:center;align-items:center;min-height:100vh;}
.login-box{background:#1a1a1a;padding:40px;border-radius:12px;border:1px solid #D4AF37;width:100%;max-width:360px;text-align:center;}
.login-box h2{color:#D4AF37;margin-bottom:6px;font-size:1.4rem;}
.login-box p{color:#888;font-size:.9rem;margin-bottom:22px;}
.login-box input{width:100%;padding:12px;background:#000;border:1px solid #444;color:#fff;border-radius:6px;margin-bottom:12px;font-size:1rem;outline:none;}
.login-box input:focus{border-color:#D4AF37;}
.login-box button{width:100%;padding:12px;background:#D4AF37;color:#000;font-weight:bold;border:none;border-radius:6px;cursor:pointer;font-size:1rem;}
.login-box button:hover{background:#c09a20;}
#loginErr{color:#f55;margin-top:10px;font-size:.9rem;min-height:18px;}

/* HEADER */
.pos-header{background:#1a1a1a;border-bottom:2px solid #D4AF37;padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:54px;position:sticky;top:0;z-index:300;}
.pos-logo{color:#D4AF37;font-size:1.1rem;font-weight:bold;white-space:nowrap;}
.pos-nav{display:flex;gap:4px;align-items:center;}
.nav-btn{background:transparent;border:none;color:#888;padding:8px 14px;cursor:pointer;font-size:.85rem;font-weight:bold;border-bottom:3px solid transparent;height:54px;transition:.2s;}
.nav-btn:hover{color:#ccc;}
.nav-btn.active{color:#D4AF37;border-bottom-color:#D4AF37;}
.nav-btn-logout{background:#c62828;color:#fff;border:none;padding:8px 14px;cursor:pointer;font-size:.85rem;font-weight:bold;border-radius:6px;margin-left:8px;}
.nav-btn-logout:hover{background:#b71c1c;}

/* TABS */
.tab-pane{display:none;}
.tab-pane.active{display:block;}

/* MASALAR */
.krokiy-toolbar{background:#1c1c1c;padding:10px 16px;border-bottom:1px solid #222;}
.btn-drag{background:#2a2a2a;color:#ccc;border:1px solid #444;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:.85rem;}
.btn-drag:hover{border-color:#D4AF37;color:#D4AF37;}
.btn-drag.drag-active{background:#D4AF37;color:#000;border-color:#D4AF37;}
#krokiyArea{position:relative;background:#141414;min-height:calc(100vh - 54px - 44px - 58px);overflow:hidden;border-bottom:1px solid #222;}
.masa-card{position:absolute;width:130px;background:#1e1e1e;border-radius:10px;padding:12px 10px 10px;text-align:center;border:2px solid #333;transition:border-color .2s;user-select:none;}
.masa-card.masa-bos{border-color:#2e7d32;}
.masa-card.masa-acik{border-color:#c62828;}
.masa-icon{font-size:1.6rem;margin-bottom:6px;}
.masa-ad{color:#fff;font-weight:bold;font-size:.95rem;margin-bottom:4px;min-height:22px;}
.masa-tutar{color:#D4AF37;font-size:.9rem;margin-bottom:8px;font-weight:bold;}
.btn-masa-ac{background:#2e7d32;color:#fff;border:none;padding:6px 10px;border-radius:5px;cursor:pointer;font-size:.8rem;width:100%;}
.btn-masa-ac:hover{background:#1b5e20;}
.btn-masa-adisyon{background:#c62828;color:#fff;border:none;padding:6px 10px;border-radius:5px;cursor:pointer;font-size:.8rem;width:100%;}
.btn-masa-adisyon:hover{background:#b71c1c;}
.masa-footer{background:#1c1c1c;padding:10px 16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;border-top:1px solid #2a2a2a;}
.btn-ftr{border:none;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:.82rem;font-weight:bold;}
.btn-add-m{background:#D4AF37;color:#000;}
.btn-add-m:hover{background:#b8971e;}
.btn-rm-m{background:#333;color:#ccc;}
.btn-rm-m:hover{background:#444;}
.btn-reset-m{background:#333;color:#ccc;}
.btn-reset-m:hover{background:#444;}
.masa-count{color:#666;font-size:.82rem;margin-left:4px;}

/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:500;display:none;align-items:center;justify-content:center;padding:12px;}
.modal-box{background:#181818;border-radius:12px;border:1px solid #D4AF37;width:100%;max-width:1000px;max-height:90vh;display:flex;flex-direction:column;}
.modal-header{background:#222;padding:14px 18px;border-radius:12px 12px 0 0;display:flex;align-items:center;gap:12px;border-bottom:1px solid #333;}
.modal-header span:first-child{color:#D4AF37;font-weight:bold;font-size:1.1rem;flex:1;}
.modal-toplam{color:#fff;font-size:1rem;font-weight:bold;}
.modal-close{background:#444;color:#fff;border:none;width:32px;height:32px;border-radius:6px;cursor:pointer;font-size:1rem;}
.modal-close:hover{background:#c62828;}
.modal-body{display:flex;flex:1;overflow:hidden;}

/* ORDER PANEL */
.order-panel{width:42%;border-right:1px solid #2a2a2a;display:flex;flex-direction:column;padding:14px;}
.order-panel h4{color:#D4AF37;margin-bottom:12px;font-size:.95rem;}
.order-items{flex:1;overflow-y:auto;}
.order-empty{color:#555;text-align:center;padding:30px;font-size:.9rem;}
.order-item{display:flex;align-items:center;gap:6px;padding:7px 0;border-bottom:1px solid #222;}
.btn-qty{background:#333;color:#fff;border:none;width:26px;height:26px;border-radius:4px;cursor:pointer;font-size:1rem;flex-shrink:0;}
.btn-qty:hover{background:#555;}
.order-item-qty{color:#D4AF37;font-weight:bold;min-width:20px;text-align:center;font-size:.9rem;}
.order-item-name{flex:1;font-size:.88rem;color:#ddd;}
.order-item-price{color:#fff;font-size:.88rem;white-space:nowrap;font-weight:bold;}
.order-footer{border-top:1px solid #333;padding-top:12px;margin-top:10px;}
.order-total-row{color:#888;font-size:.9rem;margin-bottom:10px;}
.order-total-row strong{color:#D4AF37;font-size:1.1rem;}
.btn-close-bill{width:100%;background:#D4AF37;color:#000;border:none;padding:12px;border-radius:7px;font-weight:bold;cursor:pointer;font-size:.95rem;}
.btn-close-bill:hover{background:#b8971e;}

/* MENU PANEL */
.menu-panel{flex:1;display:flex;flex-direction:column;padding:14px;}
.menu-panel h4{color:#D4AF37;margin-bottom:10px;font-size:.95rem;}
.menu-search input{width:100%;background:#0a0a0a;border:1px solid #333;color:#fff;padding:8px 10px;border-radius:6px;font-size:.9rem;outline:none;margin-bottom:10px;}
.menu-search input:focus{border-color:#D4AF37;}
.menu-panel-inner{flex:1;overflow-y:auto;}
.menu-cat{margin-bottom:8px;}
.menu-cat-head{background:#222;color:#D4AF37;padding:8px 12px;border-radius:6px;cursor:pointer;font-weight:bold;font-size:.88rem;}
.menu-cat-head:hover{background:#2a2a2a;}
.menu-cat-body{padding:4px 0;}
.menu-subcat-head{color:#666;font-size:.78rem;padding:5px 12px 2px;text-transform:uppercase;letter-spacing:.5px;}
.menu-item-btn{display:flex;justify-content:space-between;align-items:center;padding:7px 12px;cursor:pointer;border-radius:5px;font-size:.88rem;}
.menu-item-btn:hover{background:#222;}
.menu-item-price{color:#D4AF37;font-weight:bold;white-space:nowrap;margin-left:10px;}

/* RAPORLAR */
.rapor-toolbar{background:#1c1c1c;padding:12px 16px;border-bottom:1px solid #222;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.rapor-flt{background:#2a2a2a;color:#888;border:1px solid #333;padding:7px 14px;border-radius:6px;cursor:pointer;font-size:.85rem;}
.rapor-flt:hover{border-color:#888;}
.rapor-flt.active{background:#D4AF37;color:#000;border-color:#D4AF37;font-weight:bold;}
.rapor-toplam{margin-left:auto;color:#888;font-size:.9rem;}
.rapor-toplam strong{color:#D4AF37;font-size:1rem;}
.rapor-list{padding:16px;}
.rapor-empty{color:#555;text-align:center;padding:40px;}
.rapor-row{background:#1c1c1c;border-radius:8px;margin-bottom:10px;border:1px solid #2a2a2a;overflow:hidden;}
.rapor-row-main{display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer;}
.rapor-row-main:hover{background:#222;}
.rapor-masa{font-weight:bold;color:#D4AF37;flex:1;}
.rapor-kapanis{color:#666;font-size:.82rem;}
.rapor-tutar{color:#fff;font-weight:bold;white-space:nowrap;}
.rapor-row-main i{color:#444;margin-left:4px;}
.rapor-detail{padding:8px 16px 12px;border-top:1px solid #222;}
.rapor-urun{display:flex;gap:10px;color:#aaa;font-size:.85rem;padding:3px 0;}
.rapor-urun span:first-child{flex:1;}
.rapor-urun span:nth-child(2){color:#666;}
.rapor-urun span:last-child{color:#D4AF37;font-weight:bold;}

/* MENU EDITOR (from admin.php) */
.tab-toolbar{background:#1c1c1c;padding:12px 20px;border-bottom:1px solid #222;display:flex;align-items:center;gap:12px;}
.tab-title{color:#D4AF37;font-weight:bold;flex:1;}
.menu-editor{padding:16px;max-width:1200px;}
.cat-block{background:#1c1c1c;border-radius:8px;margin-bottom:16px;border:1px solid #2a2a2a;overflow:hidden;}
.cat-head{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#222;gap:10px;}
.cat-name-inp{background:transparent;border:none;border-bottom:1px solid transparent;color:#D4AF37;font-size:1.1rem;font-weight:bold;font-family:inherit;outline:none;flex:1;padding:2px 4px;}
.cat-name-inp:focus{border-bottom-color:#D4AF37;}
.cat-tile-row{display:flex;gap:12px;align-items:center;padding:8px 16px;background:#1e1e1e;border-bottom:1px solid #2a2a2a;flex-wrap:wrap;}
.cat-tile-row label{color:#666;font-size:.8rem;}
.cat-tile-row input[type=text]{background:#0d0d0d;border:1px solid #333;color:#fff;padding:4px 8px;border-radius:4px;font-size:.85rem;width:55px;outline:none;}
.cat-tile-row select{background:#0d0d0d;border:1px solid #333;color:#fff;padding:4px 8px;border-radius:4px;font-size:.85rem;outline:none;cursor:pointer;}
.cat-body{padding:10px 12px;}
.subcat-block{border:1px dashed #2a2a2a;border-left:3px solid #1565c0;border-radius:6px;margin-bottom:10px;background:#161616;}
.subcat-head{display:flex;justify-content:space-between;align-items:center;padding:9px 12px;gap:10px;}
.subcat-name-inp{background:transparent;border:none;border-bottom:1px solid transparent;color:#42a5f5;font-size:.95rem;font-weight:bold;font-family:inherit;outline:none;flex:1;padding:2px 4px;}
.subcat-name-inp:focus{border-bottom-color:#42a5f5;}
.items-wrap{padding:0 12px 6px;}
.inp-col-header{display:flex;gap:8px;padding:0 12px 4px;color:#555;font-size:.75rem;}
.inp-col-header span:nth-child(1){flex:4;}
.inp-col-header span:nth-child(2){flex:1;min-width:65px;}
.inp-col-header span:nth-child(3){flex:5;}
.item-row{display:flex;gap:7px;margin-bottom:6px;align-items:center;}
.item-inp{background:#0a0a0a;border:1px solid #1e1e1e;color:#fff;padding:8px 10px;border-radius:4px;font-size:.88rem;outline:none;}
.item-inp:focus{border-color:#444;}
.inp-name{flex:4;min-width:0;}
.inp-price{flex:1;min-width:65px;}
.inp-desc{flex:5;min-width:0;}
.btn-del{background:#c62828;color:#fff;border:none;width:32px;height:32px;border-radius:4px;cursor:pointer;flex-shrink:0;font-size:.8rem;display:flex;align-items:center;justify-content:center;}
.btn-del:hover{background:#b71c1c;}
.subcat-actions{padding:2px 12px 10px;}
.btn-add-item{background:#2e7d32;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:.82rem;}
.btn-add-item:hover{background:#1b5e20;}
.cat-add-subcat{padding:0 12px 12px;}
.btn-add-subcat{background:#0d47a1;color:#fff;border:none;padding:7px 14px;border-radius:4px;cursor:pointer;font-size:.82rem;}
.btn-add-subcat:hover{background:#1a237e;}
.btn-new-cat{display:block;width:100%;max-width:1200px;margin:0 16px 16px;padding:14px;background:#1c1c1c;color:#D4AF37;border:2px dashed #444;border-radius:8px;cursor:pointer;font-size:.95rem;}
.btn-new-cat:hover{border-color:#D4AF37;}
.btn-save-menu{background:#D4AF37;color:#000;border:none;padding:9px 18px;border-radius:6px;font-weight:bold;cursor:pointer;font-size:.88rem;}
.btn-save-menu:hover{background:#b8971e;}
#menuSaveStatus{font-size:.88rem;font-weight:bold;}
#menuSaveStatus.ok{color:#4caf50;}
#menuSaveStatus.err{color:#f55;}

/* SETTINGS */
.settings-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:14px;max-width:700px;padding:16px;}
.setting-group{background:#1c1c1c;border-radius:8px;padding:14px;border-left:3px solid #D4AF37;}
.setting-group label{display:block;color:#D4AF37;font-size:.8rem;font-weight:bold;margin-bottom:7px;text-transform:uppercase;}
.setting-group input{width:100%;background:#0a0a0a;border:1px solid #333;color:#fff;padding:9px 11px;border-radius:4px;font-size:.92rem;outline:none;}
.setting-group input:focus{border-color:#D4AF37;}
.btn-save-ayarlar{margin:0 16px 16px;background:#D4AF37;color:#000;border:none;padding:11px 26px;border-radius:6px;font-weight:bold;cursor:pointer;font-size:.92rem;}
.btn-save-ayarlar:hover{background:#b8971e;}
#ayarlarStatus{padding:0 16px;font-weight:bold;font-size:.9rem;}
#ayarlarStatus.ok{color:#4caf50;}
#ayarlarStatus.err{color:#f55;}

/* PAYMENT BUTTONS */
.pay-btns{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:10px;}
.btn-pay{border:none;padding:10px 6px;border-radius:6px;cursor:pointer;font-size:.8rem;font-weight:bold;display:flex;align-items:center;justify-content:center;gap:5px;transition:.15s;}
.btn-pay:hover{filter:brightness(1.15);}
.btn-masaya-kaydet{background:#1565c0;color:#fff;}
.btn-kismi{background:#e65100;color:#fff;}
.btn-nakit{background:#2e7d32;color:#fff;}
.btn-kart{background:#00838f;color:#fff;}
.btn-iban{background:#6a1b9a;color:#fff;}
.btn-iptal{background:#c62828;color:#fff;}
.btn-kapat{background:#424242;color:#ccc;grid-column:1/-1;}

/* MENU GRID */
.menu-cat-tabs{display:flex;gap:6px;padding-bottom:10px;flex-wrap:wrap;border-bottom:1px solid #222;margin-bottom:10px;}
.menu-cat-tab{background:#2a2a2a;color:#888;border:1px solid #333;padding:6px 16px;border-radius:20px;cursor:pointer;font-size:.82rem;font-weight:bold;white-space:nowrap;transition:.15s;}
.menu-cat-tab:hover{border-color:#888;color:#ccc;}
.menu-cat-tab.active{background:#D4AF37;color:#000;border-color:#D4AF37;}
.menu-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.menu-grid-item{background:#1e1e1e;border:1px solid #2a2a2a;border-radius:8px;padding:12px 8px;cursor:pointer;text-align:center;transition:.15s;}
.menu-grid-item:hover{background:#2a2a2a;border-color:#D4AF37;}
.menu-grid-item-name{color:#ddd;font-size:.85rem;margin-bottom:5px;line-height:1.3;}
.menu-grid-item-price{color:#D4AF37;font-weight:bold;font-size:.92rem;}
.menu-subcat-title{grid-column:1/-1;color:#D4AF37;font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px dashed #333;padding-bottom:5px;margin-top:8px;font-weight:bold;}

/* RAPOR ODEME BADGE */
.odeme-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.75rem;font-weight:bold;margin-left:6px;}
.odeme-nakit{background:#2e7d32;color:#fff;}
.odeme-kart{background:#00838f;color:#fff;}
.odeme-iban{background:#6a1b9a;color:#fff;}
.odeme-kismi{background:#e65100;color:#fff;}

/* TRANSFER UI */
.btn-transfer-open{background:#1565c0;color:#fff;border:none;padding:7px 12px;border-radius:6px;cursor:pointer;font-size:.82rem;white-space:nowrap;}
.btn-transfer-open:hover{background:#0d47a1;}
.transfer-ui{background:#1a1a1a;border-bottom:1px solid #2a2a2a;padding:10px 18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.transfer-ui span{color:#888;font-size:.88rem;}
.transfer-ui select{background:#0a0a0a;border:1px solid #333;color:#fff;padding:7px 10px;border-radius:6px;font-size:.88rem;outline:none;flex:1;min-width:140px;}
.btn-do-transfer{background:#2e7d32;color:#fff;border:none;padding:7px 14px;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:bold;}
.btn-do-transfer:hover{background:#1b5e20;}
.btn-cancel-transfer{background:#333;color:#ccc;border:none;padding:7px 12px;border-radius:6px;cursor:pointer;font-size:.85rem;}
.btn-cancel-transfer:hover{background:#444;}

/* LOGO SECTION */
.logo-section{background:#1c1c1c;border-radius:8px;margin:16px;padding:18px;border-left:3px solid #D4AF37;max-width:680px;}
.logo-section-title{color:#D4AF37;font-weight:bold;font-size:.9rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;}
.logo-tabs{display:flex;gap:6px;margin-bottom:14px;}
.logo-tab{background:#2a2a2a;color:#888;border:1px solid #333;padding:7px 16px;border-radius:6px;cursor:pointer;font-size:.85rem;}
.logo-tab.active{background:#D4AF37;color:#000;border-color:#D4AF37;font-weight:bold;}
#logoUrlSection input{width:100%;background:#0a0a0a;border:1px solid #333;color:#fff;padding:10px 12px;border-radius:6px;font-size:.92rem;outline:none;margin-bottom:6px;}
#logoUrlSection input:focus{border-color:#D4AF37;}
#logoUrlSection small{color:#555;font-size:.8rem;}
.file-pick-btn{display:inline-block;background:#2a2a2a;color:#ccc;border:1px solid #444;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:.85rem;}
.file-pick-btn:hover{border-color:#D4AF37;color:#D4AF37;}
.btn-upload-logo{background:#D4AF37;color:#000;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:bold;margin-left:6px;}
.btn-upload-logo:hover{background:#b8971e;}
.logo-preview-wrap{margin-top:14px;}
#logoPreview{max-width:140px;max-height:140px;border-radius:10px;border:1px solid #333;object-fit:contain;background:#0a0a0a;}

@media(max-width:650px){
  .modal-body{flex-direction:column;}
  .order-panel{width:100%;border-right:none;border-bottom:1px solid #2a2a2a;}
  .pos-nav{overflow-x:auto;}
}
</style>
</head>
<body>

<!-- LOGIN -->
<div id="loginPage" <?= $loggedIn ? 'style="display:none"' : '' ?>>
  <div class="login-wrap">
    <div class="login-box">
      <h2><i class="fas fa-cash-register"></i> KASA POS</h2>
      <p>33 YAN 2 Yönetim Sistemi</p>
      <input type="password" id="sifre" placeholder="Şifre giriniz" onkeydown="if(event.key==='Enter')doLogin()">
      <button onclick="doLogin()"><i class="fas fa-sign-in-alt"></i> Giriş Yap</button>
      <div id="loginErr"></div>
    </div>
  </div>
</div>

<!-- POS APP -->
<div id="posApp" <?= $loggedIn ? '' : 'style="display:none"' ?>>

  <header class="pos-header">
    <div class="pos-logo"><i class="fas fa-cash-register"></i> 33 YAN 2 | KASA POS</div>
    <nav class="pos-nav">
      <button class="nav-btn active" onclick="showTab('masalar',this)"><i class="fas fa-th-large"></i> Masalar</button>
      <button class="nav-btn" onclick="showTab('raporlar',this)"><i class="fas fa-chart-bar"></i> Raporlar</button>
      <button class="nav-btn" onclick="showTab('menu',this)"><i class="fas fa-utensils"></i> Menü Düzenle</button>
      <button class="nav-btn" onclick="showTab('ayarlar',this)"><i class="fas fa-cog"></i> Ayarlar</button>
      <button class="nav-btn-logout" onclick="doLogout()"><i class="fas fa-power-off"></i> Çıkış</button>
    </nav>
  </header>

  <!-- MASALAR -->
  <div id="tab-masalar" class="tab-pane active">
    <div class="krokiy-toolbar">
      <button id="dragToggleBtn" class="btn-drag" onclick="toggleDrag()">
        <i class="fas fa-arrows-alt"></i> Krokiyi / İsimleri Düzenle (Sürükle-Bırak)
      </button>
    </div>
    <div id="krokiyArea"></div>
    <div class="masa-footer">
      <button class="btn-ftr btn-add-m" onclick="addMasa()"><i class="fas fa-plus"></i> Yeni Masa Ekle</button>
      <button class="btn-ftr btn-rm-m"  onclick="removeMasa()"><i class="fas fa-minus"></i> Son Masayı Sil</button>
      <button class="btn-ftr btn-reset-m" onclick="resetKrokiy()"><i class="fas fa-redo"></i> Krokiyi Sıfırla</button>
      <span id="masaCount" class="masa-count"></span>
    </div>
  </div>

  <!-- RAPORLAR -->
  <div id="tab-raporlar" class="tab-pane">
    <div class="rapor-toolbar">
      <button class="rapor-flt active" onclick="loadReports('today',this)">Bugün</button>
      <button class="rapor-flt" onclick="loadReports('week',this)">Bu Hafta</button>
      <button class="rapor-flt" onclick="loadReports('month',this)">Bu Ay</button>
      <button class="rapor-flt" onclick="loadReports('all',this)">Tümü</button>
      <div class="rapor-toplam">Toplam: <strong id="raporToplam">0,00 ₺</strong></div>
    </div>
    <div id="raporList" class="rapor-list"></div>
  </div>

  <!-- MENU EDITOR -->
  <div id="tab-menu" class="tab-pane">
    <div class="tab-toolbar">
      <span class="tab-title"><i class="fas fa-edit"></i> Menü Düzenle</span>
      <button class="btn-save-menu" onclick="saveMenu()"><i class="fas fa-save"></i> Kaydet</button>
      <span id="menuSaveStatus"></span>
    </div>
    <div id="menuEditor" class="menu-editor">
      <div style="padding:30px;color:#D4AF37;text-align:center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
    </div>
    <button class="btn-new-cat" onclick="addCategory()">+ YENİ KATEGORİ EKLE</button>
  </div>

  <!-- AYARLAR -->
  <div id="tab-ayarlar" class="tab-pane">
    <div class="tab-toolbar"><span class="tab-title"><i class="fas fa-cog"></i> Mekan Ayarları</span></div>

    <!-- LOGO -->
    <div class="logo-section">
      <div class="logo-section-title"><i class="fas fa-image"></i> Logo</div>
      <div class="logo-tabs">
        <button id="btnLogoUrl"  class="logo-tab active" onclick="setLogoMode('url')">URL ile</button>
        <button id="btnLogoFile" class="logo-tab"        onclick="setLogoMode('file')">Dosya Yükle</button>
      </div>
      <div id="logoUrlSection">
        <input type="text" id="set-logo-url" placeholder="https://site.com/logo.png">
        <small>URL'yi kaydetmek için alttaki "Ayarları Kaydet" butonunu kullanın.</small>
      </div>
      <div id="logoFileSection" style="display:none">
        <label class="file-pick-btn"><i class="fas fa-upload"></i> Dosya Seç
          <input type="file" id="set-logo-file" accept="image/*" onchange="previewLogo(this)" style="display:none">
        </label>
        <button onclick="uploadLogo()" class="btn-upload-logo"><i class="fas fa-cloud-upload-alt"></i> Yükle</button>
        <span id="uploadLogoStatus" style="font-size:.85rem;margin-left:8px;"></span>
      </div>
      <div class="logo-preview-wrap">
        <img id="logoPreview" src="" alt="Logo önizleme" style="display:none">
        <span id="logoPreviewEmpty" style="color:#555;font-size:.85rem;">Henüz logo yok</span>
      </div>
    </div>

    <div class="settings-grid">
      <div class="setting-group"><label><i class="fas fa-store"></i> Mekan Adı</label><input type="text" id="set-mekan-adi" placeholder="33 YAN 2"></div>
      <div class="setting-group"><label><i class="fas fa-tag"></i> Alt Başlık</label><input type="text" id="set-altyazi" placeholder="OKEY &amp; ÇAY SALONU"></div>
      <div class="setting-group"><label><i class="fas fa-map-marker-alt"></i> Adres</label><input type="text" id="set-adres" placeholder="Mekan adresi"></div>
      <div class="setting-group"><label><i class="far fa-clock"></i> Çalışma Saatleri</label><input type="text" id="set-saatler" placeholder="Her Gün 08:00 - 02:00"></div>
      <div class="setting-group"><label><i class="fas fa-wifi"></i> Wi-Fi Şifresi</label><input type="text" id="set-wifi"></div>
      <div class="setting-group"><label><i class="fab fa-instagram"></i> Instagram</label><input type="text" id="set-insta" placeholder="kullanici_adi"></div>
      <div class="setting-group"><label><i class="fas fa-envelope"></i> E-posta</label><input type="email" id="set-email"></div>
    </div>
    <button class="btn-save-ayarlar" onclick="saveAyarlar()"><i class="fas fa-save"></i> Ayarları Kaydet</button>
    <div id="ayarlarStatus"></div>
  </div>

</div><!-- /posApp -->

<!-- ADİSYON MODAL -->
<div id="adisyonModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <span id="modalMasaAd">Masa</span>
      <span id="modalMasaToplam" class="modal-toplam">0,00 ₺</span>
      <button class="btn-transfer-open" onclick="toggleTransferUI()"><i class="fas fa-exchange-alt"></i> Masayı Aktar</button>
      <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>
    <div id="transferUI" style="display:none" class="transfer-ui">
      <span>Aktar:</span>
      <select id="transferSelect"></select>
      <button onclick="doTransfer()" class="btn-do-transfer"><i class="fas fa-check"></i> Aktar</button>
      <button onclick="toggleTransferUI()" class="btn-cancel-transfer">İptal</button>
    </div>
    <div class="modal-body">
      <div class="order-panel">
        <h4><i class="fas fa-receipt"></i> Sipariş Listesi</h4>
        <div id="orderItems" class="order-items"></div>
        <div class="order-footer">
          <div class="order-total-row">TOPLAM: <strong id="orderTotal">0,00 ₺</strong></div>
          <div class="pay-btns">
            <button class="btn-pay btn-masaya-kaydet" onclick="closeModal()"><i class="fas fa-save"></i> Masaya Kaydet</button>
            <button class="btn-pay btn-kismi" onclick="kismiTahsilat()"><i class="fas fa-plus"></i> Kısmi Tahsilat Al</button>
            <button class="btn-pay btn-nakit" onclick="closeBill('nakit')"><i class="fas fa-money-bill-wave"></i> Nakit</button>
            <button class="btn-pay btn-kart" onclick="closeBill('kart')"><i class="fas fa-credit-card"></i> Kart</button>
            <button class="btn-pay btn-iban" onclick="closeBill('iban')"><i class="fas fa-university"></i> IBAN</button>
            <button class="btn-pay btn-iptal" onclick="kompleIptal()"><i class="fas fa-trash"></i> Komple İptal</button>
            <button class="btn-pay btn-kapat" onclick="closeModal()"><i class="fas fa-times"></i> Pencereyi Kapat</button>
          </div>
        </div>
      </div>
      <div class="menu-panel">
        <div class="menu-cat-tabs" id="menuCatTabs"></div>
        <div id="menuPanel" class="menu-panel-inner"></div>
      </div>
    </div>
  </div>
</div>

<script>
const LOGGED_IN = <?= $loggedIn ? 'true' : 'false' ?>;
let menuData = {}, ayarlarData = {}, masalarData = [];
let currentMasaId = null, currentMasaAd = '';
let isDragMode = false, dragging = null, dragOX = 0, dragOY = 0;

if (LOGGED_IN) initApp();

// ===== CORE =====
async function post(action, extra = {}) {
    const fd = new FormData();
    fd.append('action', action);
    for (const [k, v] of Object.entries(extra)) fd.append(k, v);
    const res = await fetch('admin.php', { method: 'POST', body: fd });
    return res.json();
}

async function initApp() {
    await loadMenuAndAyarlar();
    await loadTables();
}

// ===== AUTH =====
async function doLogin() {
    const sifre = document.getElementById('sifre').value;
    const errEl = document.getElementById('loginErr');
    if (!sifre) { errEl.textContent = 'Şifre boş olamaz!'; return; }
    const d = await post('login', { sifre });
    if (d.ok) {
        document.getElementById('loginPage').style.display = 'none';
        document.getElementById('posApp').style.display = '';
        initApp();
    } else {
        errEl.textContent = d.msg;
    }
}

async function doLogout() {
    await post('logout');
    location.reload();
}

// ===== TABS =====
function showTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
    if (name === 'raporlar') loadReports('today', document.querySelector('.rapor-flt.active'));
    if (name === 'menu')     renderMenuEditor();
    if (name === 'ayarlar')  renderAyarlar();
}

// ===== TABLES =====
async function loadTables() {
    const d = await post('get_tables');
    if (!d.ok) return;
    masalarData = d.masalar;
    renderKrokiy();
    document.getElementById('masaCount').textContent = `(Şu an toplam ${d.count} masa var)`;
}

function renderKrokiy() {
    const area = document.getElementById('krokiyArea');
    area.innerHTML = '';
    masalarData.forEach(m => area.appendChild(createMasaCard(m)));
}

function createMasaCard(m) {
    const card = document.createElement('div');
    card.className = 'masa-card ' + (m.acik ? 'masa-acik' : 'masa-bos');
    card.style.left = m.x + 'px';
    card.style.top  = m.y + 'px';
    card.dataset.id = m.id;
    card.dataset.ad = m.ad;

    const icon  = m.acik ? '<i class="fas fa-users" style="color:#e53935"></i>' : '<i class="fas fa-users" style="color:#43a047"></i>';
    const total = m.acik ? fmtMoney(m.toplam) : '0 ₺';
    const btn   = m.acik
        ? `<button class="btn-masa-adisyon" onclick="handleMasaClick(${m.id},'${esc(m.ad)}',true)">Adisyonu Aç</button>`
        : `<button class="btn-masa-ac"      onclick="handleMasaClick(${m.id},'${esc(m.ad)}',false)">Masayı Aç</button>`;

    card.innerHTML = `<div class="masa-icon">${icon}</div>
        <div class="masa-ad">${esc(m.ad)}</div>
        <div class="masa-tutar">${total}</div>
        ${btn}`;

    card.addEventListener('mousedown', onDragStart);
    return card;
}

async function handleMasaClick(id, ad, isOpen) {
    if (isDragMode) return;
    if (!isOpen) {
        if (!confirm(`"${ad}" masasını açmak istiyor musunuz?`)) return;
        await post('open_table', { masa_id: id });
    }
    await openAdisyon(id, ad);
    await loadTables();
}

// ===== ADİSYON MODAL =====
async function openAdisyon(masa_id, masa_ad) {
    currentMasaId = masa_id;
    currentMasaAd = masa_ad;
    document.getElementById('modalMasaAd').textContent = masa_ad + ' Adisyonu';
    document.getElementById('adisyonModal').style.display = 'flex';
    await refreshAdisyon();
    renderMenuPanel();
}

async function refreshAdisyon() {
    const d = await post('get_adisyon', { masa_id: currentMasaId });
    if (!d.ok) return;
    const items  = d.adisyon.urunler || [];
    const toplam = d.adisyon.toplam  || 0;
    const totalStr = fmtMoney(toplam);
    document.getElementById('orderTotal').textContent      = totalStr;
    document.getElementById('modalMasaToplam').textContent = totalStr;
    const container = document.getElementById('orderItems');
    if (!items.length) {
        container.innerHTML = '<div class="order-empty">Henüz ürün eklenmedi</div>';
        return;
    }
    container.innerHTML = items.map((u, i) => `
        <div class="order-item">
            <button class="btn-qty" onclick="changeQty(${i},-1)">−</button>
            <span class="order-item-qty">${u.adet}</span>
            <button class="btn-qty" onclick="changeQty(${i},1)">+</button>
            <span class="order-item-name">${esc(u.ad)}</span>
            <span class="order-item-price">${fmtMoney(u.fiyat * u.adet)}</span>
        </div>`).join('');
}

async function changeQty(idx, delta) {
    if (delta > 0) {
        await post('inc_item', { masa_id: currentMasaId, idx });
    } else {
        await post('remove_item', { masa_id: currentMasaId, idx });
    }
    await refreshAdisyon();
}

function renderMenuPanel() {
    const tabs  = document.getElementById('menuCatTabs');
    const panel = document.getElementById('menuPanel');
    tabs.innerHTML = ''; panel.innerHTML = '';
    const cats = Object.keys(menuData);
    if (!cats.length) return;
    cats.forEach((catName, i) => {
        const btn = document.createElement('button');
        btn.className = 'menu-cat-tab' + (i === 0 ? ' active' : '');
        btn.textContent = catName;
        btn.onclick = () => {
            document.querySelectorAll('.menu-cat-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            showMenuCategory(catName);
        };
        tabs.appendChild(btn);
    });
    showMenuCategory(cats[0]);
}

function showMenuCategory(catName) {
    const panel  = document.getElementById('menuPanel');
    const catData = menuData[catName];
    if (!catData) return;
    panel.innerHTML = '';
    const subcats = catData.alt_kategoriler || {};
    const grid = document.createElement('div');
    grid.className = 'menu-grid';
    for (const [subName, items] of Object.entries(subcats)) {
        const title = document.createElement('div');
        title.className = 'menu-subcat-title';
        title.textContent = subName;
        grid.appendChild(title);
        (items || []).forEach(item => {
            const el = document.createElement('div');
            el.className = 'menu-grid-item';
            el.innerHTML = `<div class="menu-grid-item-name">${esc(item.ad)}</div><div class="menu-grid-item-price">${fmtMoney(item.fiyat)}</div>`;
            el.onclick = () => addToOrder(item.ad, item.fiyat);
            grid.appendChild(el);
        });
    }
    panel.appendChild(grid);
}

async function addToOrder(ad, fiyat) {
    await post('add_item', { masa_id: currentMasaId, ad, fiyat, adet: 1 });
    await refreshAdisyon();
}


async function closeBill(odeme) {
    const labels = {nakit:'Nakit', kart:'Kart', iban:'IBAN'};
    if (!confirm(`"${currentMasaAd}" hesabı ${labels[odeme]||odeme} ile kapatılsın mı?`)) return;
    const d = await post('close_bill', { masa_id: currentMasaId, masa_ad: currentMasaAd, odeme });
    if (d.ok) { closeModal(); await loadTables(); }
    else alert(d.msg);
}

async function kismiTahsilat() {
    const miktar = prompt('Tahsil edilecek tutarı girin (₺):');
    if (miktar === null) return;
    const m = parseFloat(miktar);
    if (!m || m <= 0) { alert('Geçersiz tutar!'); return; }
    const odeme = prompt('Ödeme yöntemi: nakit / kart / iban') || 'nakit';
    const d = await post('kismi_tahsilat', { masa_id: currentMasaId, masa_ad: currentMasaAd, miktar: m, odeme });
    if (d.ok) { alert(`${fmtMoney(m)} tahsil edildi. Masa açık kaldı.`); }
    else alert(d.msg);
}

async function kompleIptal() {
    if (!confirm(`"${currentMasaAd}" adisyonu iptal edilsin mi? Kayıt tutulmaz.`)) return;
    const d = await post('komple_iptal', { masa_id: currentMasaId });
    if (d.ok) { closeModal(); await loadTables(); }
    else alert(d.msg);
}

function closeModal() {
    document.getElementById('adisyonModal').style.display = 'none';
    document.getElementById('transferUI').style.display = 'none';
    currentMasaId = null; currentMasaAd = '';
}

// ===== TRANSFER =====
function toggleTransferUI() {
    const ui = document.getElementById('transferUI');
    const open = ui.style.display === 'none';
    ui.style.display = open ? 'flex' : 'none';
    if (open) {
        const sel = document.getElementById('transferSelect');
        sel.innerHTML = masalarData
            .filter(m => String(m.id) !== String(currentMasaId))
            .map(m => `<option value="${m.id}">${esc(m.ad)}${m.acik ? ' (Dolu)' : ' (Boş)'}</option>`)
            .join('');
    }
}

async function doTransfer() {
    const toId  = document.getElementById('transferSelect').value;
    const toAd  = document.getElementById('transferSelect').selectedOptions[0]?.text || '';
    if (!toId) return;
    if (!confirm(`"${currentMasaAd}" siparişi "${toAd}" masasına aktarılsın mı?`)) return;
    const d = await post('transfer_table', { from_id: currentMasaId, to_id: toId });
    if (d.ok) { closeModal(); await loadTables(); }
    else alert(d.msg);
}

// ===== DRAG =====
function onDragStart(e) {
    if (!isDragMode || e.target.tagName === 'BUTTON') return;
    dragging = e.currentTarget;
    const cRect = dragging.getBoundingClientRect();
    dragOX = e.clientX - cRect.left;
    dragOY = e.clientY - cRect.top;
    dragging.style.zIndex = 100;
    e.preventDefault();
}
document.addEventListener('mousemove', e => {
    if (!dragging) return;
    const aRect = document.getElementById('krokiyArea').getBoundingClientRect();
    let x = Math.max(0, Math.min(e.clientX - aRect.left - dragOX, aRect.width  - dragging.offsetWidth));
    let y = Math.max(0, Math.min(e.clientY - aRect.top  - dragOY, aRect.height - dragging.offsetHeight));
    dragging.style.left = x + 'px';
    dragging.style.top  = y + 'px';
});
document.addEventListener('mouseup', () => {
    if (dragging) { dragging.style.zIndex = ''; dragging = null; }
});

function toggleDrag() {
    isDragMode = !isDragMode;
    const btn   = document.getElementById('dragToggleBtn');
    const cards = document.querySelectorAll('.masa-card');
    if (isDragMode) {
        btn.innerHTML = '<i class="fas fa-save"></i> Düzeni Kaydet (Tıkla)';
        btn.classList.add('drag-active');
        cards.forEach(c => {
            c.style.cursor = 'grab';
            c.querySelector('.masa-ad').contentEditable = true;
            c.querySelector('.masa-ad').style.outline = '1px dashed #D4AF37';
            c.querySelector('button').style.display = 'none';
        });
    } else {
        btn.innerHTML = '<i class="fas fa-arrows-alt"></i> Krokiyi / İsimleri Düzenle (Sürükle-Bırak)';
        btn.classList.remove('drag-active');
        cards.forEach(c => {
            c.style.cursor = '';
            const adEl = c.querySelector('.masa-ad');
            adEl.contentEditable = false;
            adEl.style.outline = '';
            c.querySelector('button').style.display = '';
        });
        saveLayout();
    }
}

async function saveLayout() {
    const cards = document.querySelectorAll('.masa-card');
    const masalar = [], renames = [];
    cards.forEach(c => {
        const id    = parseInt(c.dataset.id);
        const newAd = c.querySelector('.masa-ad').textContent.trim();
        masalar.push({ id, x: parseInt(c.style.left), y: parseInt(c.style.top) });
        if (newAd !== c.dataset.ad) renames.push(post('rename_table', { masa_id: id, ad: newAd }));
    });
    await Promise.all([post('save_layout', { masalar: JSON.stringify(masalar) }), ...renames]);
    await loadTables();
}

async function addMasa()    { const d = await post('add_table');    if (d.ok) await loadTables(); else alert(d.msg); }
async function removeMasa() { if (!confirm('Son masayı silmek istiyor musunuz?')) return; const d = await post('remove_table'); if (d.ok) await loadTables(); else alert(d.msg); }
async function resetKrokiy(){ if (!confirm('Krokiyi sıfırlamak istiyor musunuz?')) return; await post('reset_layout'); await loadTables(); }

// ===== RAPORLAR =====
async function loadReports(filter, btn) {
    document.querySelectorAll('.rapor-flt').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    const d = await post('get_reports', { filter });
    document.getElementById('raporToplam').textContent = fmtMoney(d.toplam || 0);
    const list = document.getElementById('raporList');
    if (!d.raporlar || !d.raporlar.length) { list.innerHTML = '<div class="rapor-empty">Bu dönemde kayıt yok</div>'; return; }
    const odemeLabels = {nakit:'Nakit',kart:'Kart',iban:'IBAN',kismi:'Kısmi'};
    list.innerHTML = d.raporlar.map((r, i) => {
        const badge = r.odeme ? `<span class="odeme-badge odeme-${r.odeme}">${odemeLabels[r.odeme]||r.odeme}${r.kismi?' (Kısmi)':''}</span>` : '';
        const detay = r.kismi
            ? `<div class="rapor-urun" style="color:#e65100"><span>Kısmi tahsilat</span><span></span><span>${fmtMoney(r.toplam)}</span></div>`
            : (r.urunler||[]).map(u=>`<div class="rapor-urun"><span>${esc(u.ad)}</span><span>x${u.adet}</span><span>${fmtMoney(u.fiyat*u.adet)}</span></div>`).join('');
        return `<div class="rapor-row">
          <div class="rapor-row-main" onclick="toggleDetail(${i})">
            <span class="rapor-masa">${esc(r.masa_ad)}${badge}</span>
            <span class="rapor-kapanis">${r.kapanis}</span>
            <span class="rapor-tutar">${fmtMoney(r.toplam)}</span>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="rapor-detail" id="rd${i}" style="display:none">${detay}</div>
        </div>`;
    }).join('');
}
function toggleDetail(i) { const el = document.getElementById('rd'+i); el.style.display = el.style.display==='none'?'':'none'; }

// ===== MENU EDITOR =====
async function loadMenuAndAyarlar() {
    try {
        const [mRes, aRes] = await Promise.all([fetch('menu.json?v='+Date.now()), fetch('ayarlar.json?v='+Date.now())]);
        menuData    = await mRes.json();
        ayarlarData = await aRes.json();
    } catch(e) { console.error(e); }
}

function renderMenuEditor() {
    const container = document.getElementById('menuEditor');
    container.innerHTML = '';
    for (const [catName, catData] of Object.entries(menuData))
        container.appendChild(buildCatBlock(catName, catData));
}

function buildCatBlock(catName, catData) {
    const block = document.createElement('div'); block.className = 'cat-block';
    const head  = document.createElement('div'); head.className = 'cat-head';
    const nInp  = document.createElement('input'); nInp.type='text'; nInp.className='cat-name-inp'; nInp.value=catName; nInp.placeholder='Kategori adı';
    const dBtn  = document.createElement('button'); dBtn.className='btn-del'; dBtn.innerHTML='<i class="fas fa-trash"></i>';
    dBtn.onclick = () => { if (confirm('"'+catName+'" silinsin mi?')) block.remove(); };
    head.appendChild(nInp); head.appendChild(dBtn);

    const tileRow = document.createElement('div'); tileRow.className='cat-tile-row';
    const colorOpts = [['color-red','Kırmızı'],['color-black','Siyah'],['color-blue','Mavi'],['color-yellow','Sarı']]
        .map(([v,l])=>`<option value="${v}" ${catData.tileColor===v?'selected':''}>${l}</option>`).join('');
    tileRow.innerHTML = `<label>Sembol:</label><input type="text" class="tile-num-inp" value="${esc(catData.tileNum||'')}" maxlength="3" placeholder="1"><label>Renk:</label><select class="tile-color-sel">${colorOpts}</select>`;

    const body = document.createElement('div'); body.className='cat-body';
    const subcats = catData.alt_kategoriler ? Object.entries(catData.alt_kategoriler) : catData.items ? [['Genel',catData.items]] : [];
    subcats.forEach(([sn, items]) => body.appendChild(buildSubcatBlock(sn, items)));

    const addSubDiv = document.createElement('div'); addSubDiv.className='cat-add-subcat';
    const addSubBtn = document.createElement('button'); addSubBtn.className='btn-add-subcat'; addSubBtn.textContent='+ Alt Kategori Ekle';
    addSubBtn.onclick = () => body.insertBefore(buildSubcatBlock('Yeni Alt Kategori',[]), addSubDiv);
    addSubDiv.appendChild(addSubBtn); body.appendChild(addSubDiv);

    block.appendChild(head); block.appendChild(tileRow); block.appendChild(body);
    return block;
}

function buildSubcatBlock(subName, items) {
    const block = document.createElement('div'); block.className='subcat-block';
    const head  = document.createElement('div'); head.className='subcat-head';
    const nInp  = document.createElement('input'); nInp.type='text'; nInp.className='subcat-name-inp'; nInp.value=subName; nInp.placeholder='Alt kategori adı';
    const dBtn  = document.createElement('button'); dBtn.className='btn-del'; dBtn.textContent='X';
    dBtn.onclick = () => { if (confirm('"'+subName+'" silinsin mi?')) block.remove(); };
    head.appendChild(nInp); head.appendChild(dBtn);

    const colH = document.createElement('div'); colH.className='inp-col-header';
    colH.innerHTML='<span>Ürün Adı</span><span>Fiyat (₺)</span><span>Açıklama</span><span></span>';
    const wrap = document.createElement('div'); wrap.className='items-wrap';
    (items||[]).forEach(item => wrap.appendChild(buildItemRow(item)));

    const actDiv = document.createElement('div'); actDiv.className='subcat-actions';
    const addBtn = document.createElement('button'); addBtn.className='btn-add-item'; addBtn.textContent='+ Ürün Ekle';
    addBtn.onclick = () => wrap.appendChild(buildItemRow({ad:'',aciklama:'',fiyat:''}));
    actDiv.appendChild(addBtn);

    block.appendChild(head); block.appendChild(colH); block.appendChild(wrap); block.appendChild(actDiv);
    return block;
}

function buildItemRow(item) {
    const row   = document.createElement('div'); row.className='item-row';
    const nInp  = document.createElement('input'); nInp.type='text'; nInp.className='item-inp inp-name'; nInp.value=item.ad||''; nInp.placeholder='Ürün adı';
    const pInp  = document.createElement('input'); pInp.type='number'; pInp.className='item-inp inp-price'; pInp.value=item.fiyat!==undefined?item.fiyat:''; pInp.placeholder='0'; pInp.min='0';
    const dInp  = document.createElement('input'); dInp.type='text'; dInp.className='item-inp inp-desc'; dInp.value=item.aciklama||''; dInp.placeholder='Açıklama (isteğe bağlı)';
    const delBtn = document.createElement('button'); delBtn.className='btn-del'; delBtn.innerHTML='<i class="fas fa-trash"></i>'; delBtn.onclick=()=>row.remove();
    row.appendChild(nInp); row.appendChild(pInp); row.appendChild(dInp); row.appendChild(delBtn);
    return row;
}

function addCategory() {
    const c = document.getElementById('menuEditor');
    const b = buildCatBlock('Yeni Kategori', {tileNum:'1',tileColor:'color-red',alt_kategoriler:{'Genel':[]}});
    c.appendChild(b); b.querySelector('.cat-name-inp').focus();
}

function collectMenuData() {
    const data = {};
    document.querySelectorAll('#menuEditor .cat-block').forEach(cb => {
        const cn = cb.querySelector('.cat-name-inp').value.trim(); if (!cn) return;
        const tn = cb.querySelector('.tile-num-inp').value.trim();
        const tc = cb.querySelector('.tile-color-sel').value;
        const sc = {};
        cb.querySelectorAll('.subcat-block').forEach(sb => {
            const sn = sb.querySelector('.subcat-name-inp').value.trim(); if (!sn) return;
            const items = [];
            sb.querySelectorAll('.item-row').forEach(row => {
                const ad = row.querySelector('.inp-name').value.trim();
                const f  = parseFloat(row.querySelector('.inp-price').value) || 0;
                const ac = row.querySelector('.inp-desc').value.trim();
                if (ad) items.push({ad, aciklama:ac, fiyat:f});
            });
            sc[sn] = items;
        });
        data[cn] = {tileNum:tn, tileColor:tc, alt_kategoriler:sc};
    });
    return data;
}

async function saveMenu() {
    const st = document.getElementById('menuSaveStatus');
    st.className=''; st.textContent='Kaydediliyor...';
    const d = await post('save_menu', { data: JSON.stringify(collectMenuData()) });
    st.className = d.ok ? 'ok' : 'err';
    st.textContent = d.ok ? '✓ Kaydedildi!' : '✗ ' + d.msg;
    if (d.ok) { menuData = collectMenuData(); setTimeout(() => st.textContent='', 3000); }
}

// ===== AYARLAR =====
function renderAyarlar() {
    document.getElementById('set-mekan-adi').value = ayarlarData.mekan_adi || '';
    document.getElementById('set-altyazi').value   = ayarlarData.altyazi   || '';
    document.getElementById('set-adres').value     = ayarlarData.adres     || '';
    document.getElementById('set-saatler').value   = ayarlarData.saatler   || '';
    document.getElementById('set-wifi').value      = ayarlarData.wifi      || '';
    document.getElementById('set-insta').value     = ayarlarData.instagram || '';
    document.getElementById('set-email').value     = ayarlarData.email     || '';
    // Logo
    const logo = ayarlarData.logo || '';
    document.getElementById('set-logo-url').value = logo;
    const prev = document.getElementById('logoPreview');
    const empty = document.getElementById('logoPreviewEmpty');
    if (logo) { prev.src = logo + '?v=' + Date.now(); prev.style.display = ''; empty.style.display = 'none'; }
    else       { prev.style.display = 'none'; empty.style.display = ''; }
}

async function saveAyarlar() {
    const st = document.getElementById('ayarlarStatus');
    st.className=''; st.textContent='Kaydediliyor...';
    const logoUrl = document.getElementById('logoUrlSection').style.display !== 'none'
        ? document.getElementById('set-logo-url').value.trim()
        : (ayarlarData.logo || '');
    const data = {
        mekan_adi: document.getElementById('set-mekan-adi').value,
        altyazi:   document.getElementById('set-altyazi').value,
        adres:     document.getElementById('set-adres').value,
        saatler:   document.getElementById('set-saatler').value,
        wifi:      document.getElementById('set-wifi').value,
        instagram: document.getElementById('set-insta').value,
        email:     document.getElementById('set-email').value,
        logo:      logoUrl
    };
    const d = await post('save_ayarlar', { data: JSON.stringify(data) });
    st.className = d.ok ? 'ok' : 'err';
    st.textContent = d.ok ? '✓ Kaydedildi!' : '✗ ' + d.msg;
    if (d.ok) { ayarlarData = data; renderAyarlar(); setTimeout(() => st.textContent='', 3000); }
}

// ===== LOGO =====
function setLogoMode(mode) {
    document.getElementById('logoUrlSection').style.display  = mode === 'url'  ? '' : 'none';
    document.getElementById('logoFileSection').style.display = mode === 'file' ? '' : 'none';
    document.getElementById('btnLogoUrl').classList.toggle('active',  mode === 'url');
    document.getElementById('btnLogoFile').classList.toggle('active', mode === 'file');
}

function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const prev = document.getElementById('logoPreview');
        prev.src = e.target.result; prev.style.display = '';
        document.getElementById('logoPreviewEmpty').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

async function uploadLogo() {
    const fileInp = document.getElementById('set-logo-file');
    const st = document.getElementById('uploadLogoStatus');
    if (!fileInp.files || !fileInp.files[0]) { st.style.color='#f55'; st.textContent='Dosya seçilmedi!'; return; }
    st.style.color='#888'; st.textContent='Yükleniyor...';
    const fd = new FormData();
    fd.append('action', 'upload_logo');
    fd.append('logo', fileInp.files[0]);
    try {
        const res  = await fetch('admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            st.style.color='#4caf50'; st.textContent='✓ Yüklendi!';
            ayarlarData.logo = data.url;
            const prev = document.getElementById('logoPreview');
            prev.src = data.url + '?v=' + Date.now(); prev.style.display='';
            document.getElementById('logoPreviewEmpty').style.display='none';
            setTimeout(() => st.textContent='', 3000);
        } else {
            st.style.color='#f55'; st.textContent='✗ ' + data.msg;
        }
    } catch(e) { st.style.color='#f55'; st.textContent='✗ Bağlantı hatası!'; }
}

// ===== UTILS =====
function fmtMoney(n) { return (parseFloat(n)||0).toFixed(2).replace('.',',') + ' ₺'; }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
</body>
</html>

