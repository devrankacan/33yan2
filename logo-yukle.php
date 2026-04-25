<?php
$password = 'admin33';
$msg = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['sifre'] ?? '') !== $password) {
        $msg = 'Şifre hatalı!'; $type = 'err';
    } elseif (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== 0) {
        $codes = [1=>'Dosya çok büyük (php.ini)',2=>'Dosya çok büyük (form)',3=>'Kısmi yükleme',4=>'Dosya seçilmedi'];
        $msg = 'Dosya hatası: ' . ($codes[$_FILES['logo']['error'] ?? 4] ?? 'Bilinmeyen'); $type = 'err';
    } else {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        $mime = mime_content_type($_FILES['logo']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $msg = 'Sadece resim yüklenebilir. Gelen tip: ' . $mime; $type = 'err';
        } elseif (move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/logo.jpg')) {
            $msg = 'Logo başarıyla yüklendi!'; $type = 'ok';
        } else {
            $err = error_get_last();
            $msg = 'Kaydetme hatası: ' . ($err['message'] ?? 'bilinmiyor'); $type = 'err';
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"><title>Logo Yükle</title></head>
<body style="background:#111;color:#eee;font-family:sans-serif;padding:40px;max-width:400px">
<h2 style="color:#D4AF37">Logo Yükle</h2>
<?php if ($msg): ?>
<p style="color:<?= $type==='ok'?'#4caf50':'#f55' ?>;font-weight:bold"><?= htmlspecialchars($msg) ?></p>
<?php if ($type==='ok'): ?>
<p>Bu sayfayı kapattıktan sonra <strong>logo-yukle.php</strong> dosyasını silin.</p>
<?php endif; ?>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data">
    <p><input type="password" name="sifre" placeholder="Admin şifresi" required
        style="width:100%;padding:10px;background:#222;color:#fff;border:1px solid #444;border-radius:4px"></p>
    <p><input type="file" name="logo" accept="image/*" required style="color:#fff"></p>
    <p><button type="submit"
        style="width:100%;padding:12px;background:#D4AF37;color:#000;font-weight:bold;border:none;border-radius:4px;cursor:pointer;font-size:1rem">
        Yükle
    </button></p>
</form>
</body>
</html>
