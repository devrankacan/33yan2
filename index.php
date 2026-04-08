<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_mail'])) {
    header('Content-Type: application/json');

    $ad_soyad = htmlspecialchars(strip_tags(trim($_POST['ad_soyad'] ?? '')));
    $telefon  = htmlspecialchars(strip_tags(trim($_POST['telefon']  ?? '')));
    $mesaj    = htmlspecialchars(strip_tags(trim($_POST['mesaj']    ?? '')));

    if (empty($ad_soyad) || empty($telefon) || empty($mesaj)) {
        echo json_encode(['status' => 'error', 'message' => 'Tüm alanlar zorunludur.']);
        exit;
    }

    // Ayarlar dosyasından alıcı e-posta adresini oku
    $alici = 'info@33yan2.com'; // Buraya kendi e-posta adresinizi yazın
    $ayarlarDosyasi = __DIR__ . '/ayarlar.json';
    if (file_exists($ayarlarDosyasi)) {
        $ayarlar = json_decode(file_get_contents($ayarlarDosyasi), true);
        if (!empty($ayarlar['email'])) {
            $alici = $ayarlar['email'];
        }
    }

    $konu   = '33 YAN 2 | Yeni Müşteri Mesajı - ' . $ad_soyad;
    $icerik = "Ad Soyad: $ad_soyad\nTelefon: $telefon\n\nMesaj:\n$mesaj";
    $basliklar = "From: noreply@33yan2.com\r\nReply-To: noreply@33yan2.com\r\nX-Mailer: PHP/" . phpversion();

    $sonuc = mail($alici, $konu, $icerik, $basliklar);

    if ($sonuc) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Mail gönderilemedi.']);
    }
    exit;
}

// POST değilse index.html'e yönlendir
header('Location: index.html');
exit;
