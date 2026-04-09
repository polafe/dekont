<?php
// Hata bastirma (InfinityFree html injection onlemek icin)
error_reporting(0);
ini_set('display_errors', '0');
set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Sunucu Hatasi: Lutfen sistem yoneticisine basvurun.']);
    exit;
});

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_name('MTSK_SESSION');
session_start();

// BASİT CSRF / REFERER KONTROLÜ
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $serverHost = $_SERVER['HTTP_HOST'];
        if ($refererHost !== $serverHost) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Güvenlik İhlali: Geçersiz istek kaynağı.']);
            exit;
        }
    }
}
require_once 'db.php';

function bootstrap_admin_from_env(PDO $conn) {
    $adminUsername = trim((string)get_env_variable('ADMIN_USERNAME', ''));
    $adminPasswordHash = trim((string)get_env_variable('ADMIN_PASSWORD_HASH', ''));

    if ($adminUsername === '' || $adminPasswordHash === '') {
        return;
    }

    // Sadece password_hash tarafindan uretilen bcrypt/argon hash'lerini kabul et.
    if (strpos($adminPasswordHash, '$2y$') !== 0 && strpos($adminPasswordHash, '$argon2') !== 0) {
        return;
    }

    $stmt = $conn->prepare("SELECT id FROM kullanicilar WHERE rol = 'admin' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        $stmt = $conn->prepare("UPDATE kullanicilar SET kullanici_adi = ?, sifre = ?, aktif = 1 WHERE id = ?");
        $stmt->execute([$adminUsername, $adminPasswordHash, $admin['id']]);
    } else {
        $stmt = $conn->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre, ad_soyad, rol, aktif) VALUES (?, ?, 'Yonetici', 'admin', 1)");
        $stmt->execute([$adminUsername, $adminPasswordHash]);
    }
}

bootstrap_admin_from_env($conn);

function ensure_dekont_device_column(PDO $conn) {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM dekontlar LIKE 'cihaz_kimligi'");
        if (!$stmt->fetch()) {
            $conn->exec("ALTER TABLE dekontlar ADD COLUMN cihaz_kimligi VARCHAR(128) DEFAULT NULL AFTER kullanici_id");
        }
    } catch (PDOException $e) {
        // Eski tabloda kolon yoksa eklemeyi dener; basarisiz olursa mevcut akis devam eder.
    }
}

ensure_dekont_device_column($conn);

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://cdnjs.cloudflare.com https://unpkg.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src \'self\' data:;');
header('Referrer-Policy: same-origin');
header('X-XSS-Protection: 1; mode=block');

$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin !== '') {
    $originHost = parse_url($requestOrigin, PHP_URL_HOST);
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($originHost && ($originHost === $currentHost || $originHost === 'localhost' || $originHost === '127.0.0.1')) {
        header('Access-Control-Allow-Origin: ' . $requestOrigin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Yardımcı fonksiyonlar
function check_auth($required_rol = null) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Lütfen giriş yapın']);
        exit();
    }

    // Periyodik oturum yenileme (30 dakikada bir)
    if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at'] > 1800)) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
    
    if ($required_rol && $_SESSION['rol'] !== $required_rol && $_SESSION['rol'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden', 'message' => 'Bu işlem için yetkiniz yok']);
        exit();
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

// Yardımcı fonksiyonlar
function get_post_data() {
    $data = json_decode(file_get_contents('php://input'), true);
    return $data ?: $_POST;
}

function send_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function safe_id($id) {
    return is_numeric($id) ? (int)$id : null;
}

function is_strong_password($password) {
    // Sadece minimum 8 karakter kontrolü yapıyoruz (Kullanıcı arayüzündeki talimatla uyumlu)
    return is_string($password) && strlen($password) >= 8;
}

function get_login_limit(PDO $conn, $limitKey, $maxAttempts = 5, $lockSeconds = 900) {
    $stmt = $conn->prepare("SELECT attempts, last_attempt FROM login_limits WHERE ip = ?");
    $stmt->execute([$limitKey]);
    $limit = $stmt->fetch();

    if (!$limit) {
        return 0;
    }

    $diff = time() - strtotime($limit['last_attempt']);
    if ((int)$limit['attempts'] >= $maxAttempts && $diff < $lockSeconds) {
        return $lockSeconds - $diff;
    }

    if ($diff >= $lockSeconds) {
        $conn->prepare("DELETE FROM login_limits WHERE ip = ?")->execute([$limitKey]);
    }

    return 0;
}

function register_failed_login(PDO $conn, $limitKey) {
    $conn->prepare("INSERT INTO login_limits (ip, attempts, last_attempt) VALUES (?, 1, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = CURRENT_TIMESTAMP")
        ->execute([$limitKey]);
}

// ROUTING
switch ($action) {
    // LOGIN
    case 'login':
        if ($method !== 'POST') send_json(['error' => 'Method not allowed'], 405);
        $data = get_post_data();
        $user_adi = trim($data['kullanici_adi'] ?? '');
        $sifre = $data['sifre'] ?? '';

        if ($user_adi === '' || $sifre === '') {
            send_json(['error' => 'Kullanici adi ve sifre zorunludur'], 400);
        }

        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
        $ip = explode(',', $ip)[0];
        $ipLimitKey = 'i:' . sha1($ip);
        $userLimitKey = 'u:' . sha1(strtolower($user_adi) . '|' . $ip);

        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS login_limits (
                ip VARCHAR(45) PRIMARY KEY,
                attempts INT DEFAULT 1,
                last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $blockedIpSeconds = get_login_limit($conn, $ipLimitKey, 8, 900);
            $blockedUserSeconds = get_login_limit($conn, $userLimitKey, 5, 900);
            $waitSeconds = max($blockedIpSeconds, $blockedUserSeconds);
            if ($waitSeconds > 0) {
                send_json(['error' => 'Cok fazla hatali giris yapildi. Lutfen ' . $waitSeconds . ' saniye sonra tekrar deneyin.'], 429);
            }
        } catch(PDOException $e) {
            // Tablo oluşturma veya limit okuma hatası durumunda işlemi durdurma, sadece devam et.
        }

        $stmt = $conn->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi = ? AND aktif = 1");
        $stmt->execute([$user_adi]);
        $user = $stmt->fetch();

        if ($user && password_verify($sifre, $user['sifre'])) {
            // Süre dolmuş mu kontrolü
            if ($user['rol'] !== 'admin') {
                $st_ayar = $conn->query("SELECT deger FROM ayarlar WHERE anahtar = 'son_tarih'");
                $son_tarih = $st_ayar->fetchColumn();
                if ($son_tarih && time() > strtotime($son_tarih)) {
                    send_json(['error' => 'Dekont yükleme süresi dolmuştur! Artık giriş yapamazsınız.'], 403);
                }
            }

            // OTURUMU BAŞLAT
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['kullanici_adi'] = $user['kullanici_adi'];
            $_SESSION['ad_soyad'] = $user['ad_soyad'];
            $_SESSION['created_at'] = time(); // Oturum ömrü kontrolü için

            try {
                // Başarılı giriş: logu temizle
                $conn->prepare("DELETE FROM login_limits WHERE ip IN (?, ?)")->execute([$ipLimitKey, $userLimitKey]);
            } catch(PDOException $e) {}

            send_json([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'kullanici_adi' => $user['kullanici_adi'],
                    'ad_soyad' => $user['ad_soyad'],
                    'rol' => $user['rol']
                ]
            ]);
        } else {
            // Hatalı giriş
            sleep(1); // Brute force araçlarını yavaşlatır
            try {
                register_failed_login($conn, $ipLimitKey);
                register_failed_login($conn, $userLimitKey);
            } catch(PDOException $e) {}
            
            send_json(['error' => 'Kullanici adi veya sifre yanlis'], 401);
        }
        break;

    // KULLANICILAR TOPLU EKLEME
    case 'kullanicilar/toplu':
        if ($method === 'POST') {
            check_auth('admin');
            $data = get_post_data();
            $basarili = 0;
            $hatali = 0;
            
            if (is_array($data)) {
                $conn->beginTransaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre, ad_soyad, rol) VALUES (?, ?, ?, 'kullanici')");
                    $chk = $conn->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi = ?");
                    
                    foreach ($data as $u) {
                        $ad = trim($u['ad_soyad'] ?? '');
                        $kad = trim($u['kullanici_adi'] ?? '');
                        $sifre = $u['sifre'] ?? '';
                        if (!is_strong_password($sifre) || empty($ad) || empty($kad)) { 
                            $hatali++; continue; 
                        }
                        
                        $chk->execute([$kad]);
                        if ($chk->fetch()) { 
                            $hatali++; continue; 
                        }

                        $pw = password_hash($sifre, PASSWORD_DEFAULT);
                        $stmt->execute([$kad, $pw, $ad]);
                        $basarili++;
                    }
                    $conn->commit();
                    send_json(['success' => true, 'basarili' => $basarili, 'hatali' => $hatali]);
                } catch(Exception $e) {
                    $conn->rollBack();
                    send_json(['error' => 'Kayıt sırasında hata oluştu.'], 500);
                }
            } else {
                send_json(['error' => 'Geçersiz veri formatı'], 400);
            }
        } else {
            send_json(['error' => 'Method not allowed'], 405);
        }
        break;

    // KULLANICILAR (CRUD)
    case (preg_match('/^kullanicilar(\/(\d+))?$/', $action, $matches) ? true : false):
        $uid = isset($matches[2]) ? safe_id($matches[2]) : null;

        if ($method === 'GET') {
            check_auth();
            if ($uid) {
                $stmt = $conn->prepare("SELECT id, kullanici_adi, ad_soyad, rol, aktif, created_at FROM kullanicilar WHERE id = ?");
                $stmt->execute([$uid]);
                send_json($stmt->fetch());
            } else {
                check_auth('admin');
                $stmt = $conn->query("SELECT id, kullanici_adi, ad_soyad, rol, aktif, created_at FROM kullanicilar ORDER BY id");
                send_json($stmt->fetchAll());
            }
        } elseif ($method === 'POST') {
            check_auth('admin');
            $data = get_post_data();
            $sifre = $data['sifre'] ?? '';
            if (!is_strong_password($sifre)) send_json(['error' => 'Sifre en az 8 karakter olmali ve buyuk-kucuk harf ile rakam icermelidir!'], 400);
            $pw = password_hash($sifre, PASSWORD_DEFAULT);
            try {
                $stmt = $conn->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre, ad_soyad, rol) VALUES (?, ?, ?, ?)");
                $stmt->execute([trim($data['kullanici_adi'] ?? ''), $pw, trim($data['ad_soyad'] ?? ''), $data['rol'] ?? 'kullanici']);
                send_json(['success' => true]);
            } catch (PDOException $e) {
                send_json(['error' => 'Bu kullanıcı adı zaten mevcut veya hata oluştu'], 400);
            }
        } elseif ($method === 'PUT' && $uid) {
            check_auth('admin');
            $data = get_post_data();
            $stmt = $conn->prepare("SELECT sifre FROM kullanicilar WHERE id = ?");
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
            if (!$user) send_json(['error' => 'Kullanıcı bulunamadı'], 404);

            $newPw = $user['sifre'];
            if (!empty($data['sifre'])) {
                if (!is_strong_password($data['sifre'])) send_json(['error' => 'Yeni sifre en az 8 karakter olmali ve buyuk-kucuk harf ile rakam icermelidir!'], 400);
                $newPw = password_hash($data['sifre'], PASSWORD_DEFAULT);
            }
            $stmt = $conn->prepare("UPDATE kullanicilar SET sifre = ?, ad_soyad = ?, rol = ?, aktif = ? WHERE id = ?");
            $stmt->execute([$newPw, $data['ad_soyad'], $data['rol'], isset($data['aktif']) ? (int)$data['aktif'] : 1, $uid]);
            send_json(['success' => true]);
        } elseif ($method === 'DELETE' && $uid) {
            check_auth('admin');
            if ($uid == 1) send_json(['error' => 'Ana yönetici silinemez'], 400);
            $stmt = $conn->prepare("DELETE FROM kullanicilar WHERE id = ?");
            $stmt->execute([$uid]);
            send_json(['success' => true]);
        }
        break;

    // KURSLAR (CRUD)
    case (preg_match('/^kurslar(\/(\d+))?$/', $action, $matches) ? true : false):
        $kid = isset($matches[2]) ? safe_id($matches[2]) : null;
        if ($method === 'GET') {
            $stmt = $conn->query("SELECT * FROM kurslar ORDER BY sira");
            send_json($stmt->fetchAll());
        } elseif ($method === 'POST') {
            $data = get_post_data();
            $stmt = $conn->query("SELECT COALESCE(MAX(sira), 0) + 1 FROM kurslar");
            $maxSira = $stmt->fetchColumn();
            $stmt = $conn->prepare("INSERT INTO kurslar (kurs_adi, sira) VALUES (?, ?)");
            $stmt->execute([trim($data['kurs_adi'] ?? ''), $maxSira]);
            send_json(['success' => true]);
        } elseif ($method === 'DELETE' && $kid) {
            $stmt = $conn->prepare("DELETE FROM kurslar WHERE id = ?");
            $stmt->execute([$kid]);
            send_json(['success' => true]);
        }
        break;

    // MTSK LISTESI (CRUD)
    case (preg_match('/^mtsk(\/(\d+))?$/', $action, $matches) ? true : false):
        $mid = isset($matches[2]) ? safe_id($matches[2]) : null;
        if ($method === 'GET') {
            $stmt = $conn->query("SELECT * FROM mtsk_listesi ORDER BY sira");
            send_json($stmt->fetchAll());
        } elseif ($method === 'POST') {
            $data = get_post_data();
            $stmt = $conn->query("SELECT COALESCE(MAX(sira), 0) + 1 FROM mtsk_listesi");
            $maxSira = $stmt->fetchColumn();
            $stmt = $conn->prepare("INSERT INTO mtsk_listesi (mtsk_adi, sira) VALUES (?, ?)");
            $stmt->execute([trim($data['mtsk_adi'] ?? ''), $maxSira]);
            send_json(['success' => true]);
        } elseif ($method === 'DELETE' && $mid) {
            $stmt = $conn->prepare("DELETE FROM mtsk_listesi WHERE id = ?");
            $stmt->execute([$mid]);
            send_json(['success' => true]);
        }
        break;

    // AYARLAR
    case 'ayarlar':
        if ($method === 'GET') {
            $stmt = $conn->query("SELECT * FROM ayarlar");
            $result = [];
            foreach ($stmt->fetchAll() as $row) {
                $result[$row['anahtar']] = $row['deger'];
            }
            send_json($result);
        } elseif ($method === 'POST') {
            check_auth('admin');
            $data = get_post_data();
            $stmt = $conn->prepare("INSERT INTO ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)");
            $stmt->execute([$data['anahtar'] ?? '', $data['deger'] ?? '']);
            send_json(['success' => true]);
        }
        break;

    // DEKONT SUBMISSION
    case 'dekont':
        check_auth();
        if ($method === 'POST') {
            if (!isset($_FILES['dekont_dosya'])) send_json(['error' => 'Dosya seçilmedi'], 400);
            
            $file = $_FILES['dekont_dosya'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            
            // MIME Type ve Extension Kontrolü (Güvenlik Planı Madde 4)
            $allowed_mimes = ['application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (strtolower($ext) !== 'pdf' || !in_array($mime_type, $allowed_mimes)) {
                send_json(['error' => 'Sadece geçerli PDF dosyası yüklenebilir!'], 400);
            }

            // PDF İçinde PHP veya Script Taraması (Basit)
            $content = file_get_contents($file['tmp_name']);
            if (preg_match('/<\?php|eval\(|base64_decode|system\(|shell_exec/i', $content)) {
                send_json(['error' => 'Güvenlik hatası: Geçersiz PDF içeriği!'], 400);
            }

            $filename = uniqid() . '.pdf';
            $uploadPath = __DIR__ . '/uploads/' . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                send_json(['error' => 'Dosya yükleme hatası'], 500);
            }

            $dekont_tarihi = $_POST['dekont_tarihi'] ?? '';
            $dekont_numarasi = $_POST['dekont_numarasi'] ?? '';
            $dekont_tutari = (double)($_POST['dekont_tutari'] ?? 0);
            $dekont_pdf_tutari = (double)($_POST['dekont_pdf_tutari'] ?? 0);
            $kullanici_id = safe_id($_POST['kullanici_id'] ?? null);
            $cihaz_kimligi = substr(trim((string)($_POST['device_id'] ?? $_POST['cihaz_kimligi'] ?? '')), 0, 128);
            $kurslar_json = json_decode($_POST['kurslar_json'] ?? '[]', true);

            if ($dekont_pdf_tutari <= 0) {
                @unlink($uploadPath);
                send_json(['error' => 'PDF tutari dogrulanamadi. Sayfayi yenileyip tekrar deneyin.'], 400);
            }

            // Dekont tutar doğrulaması
            if ($dekont_tutari <= 0) {
                @unlink($uploadPath);
                send_json(['error' => 'Dekont tutarı 0 ile buyuk olmalidir'], 400);
            }

            // Kursiyer sayısını topla
            $toplamKursiyer = 0;
            foreach ($kurslar_json as $k) {
                $toplamKursiyer += (int)($k['kursiyer_sayisi'] ?? 0);
            }

            // Minimum tutar kontrolü
            $stmt = $conn->prepare("SELECT deger FROM ayarlar WHERE anahtar = 'kursiyer_ucreti'");
            $stmt->execute();
            $ucretRow = $stmt->fetch();
            $kursiyerUcreti = (double)($ucretRow['deger'] ?? 2000);
            $minTutar = $toplamKursiyer * $kursiyerUcreti;

            if ($toplamKursiyer > 0 && $dekont_tutari < $minTutar) {
                @unlink($uploadPath);
                send_json(['error' => 'Dekont tutari en az ' . (int)$minTutar . ' TL olmalidir. Toplam ' . $toplamKursiyer . ' kursiyer x ' . (int)$kursiyerUcreti . ' TL'], 400);
            }

            // Istemciden gelen PDF tutari ile capraz dogrulama yap
            if ($toplamKursiyer > 0 && $dekont_pdf_tutari < $minTutar) {
                @unlink($uploadPath);
                send_json(['error' => 'PDF dekont tutari minimum tutardan dusuk. PDF: ' . (int)$dekont_pdf_tutari . ' TL, minimum: ' . (int)$minTutar . ' TL'], 400);
            }

            if ($dekont_tutari > $dekont_pdf_tutari) {
                @unlink($uploadPath);
                send_json(['error' => 'Girilen tutar, PDF dekont tutarini asamaz. PDF: ' . (int)$dekont_pdf_tutari . ' TL'], 400);
            }

            try {
                $conn->beginTransaction();
                $stmt = $conn->prepare("INSERT INTO dekontlar (dekont_tarihi, dekont_numarasi, dekont_tutari, dekont_dosya, kullanici_id, cihaz_kimligi) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$dekont_tarihi, $dekont_numarasi, $dekont_tutari, $filename, $kullanici_id, $cihaz_kimligi !== '' ? $cihaz_kimligi : null]);
                $dekontId = $conn->lastInsertId();

                foreach ($kurslar_json as $k) {
                    $ks = (int)($k['kursiyer_sayisi'] ?? 0);
                    if ($ks > 0) {
                        $stmt = $conn->prepare("INSERT INTO dekont_kurslar (dekont_id, kurs_adi, kursiyer_sayisi) VALUES (?, ?, ?)");
                        $stmt->execute([$dekontId, $k['kurs_adi'] ?? '', $ks]);
                    }
                }
                $conn->commit();
                send_json(['success' => true, 'id' => (int)$dekontId]);
            } catch (Exception $e) {
                $conn->rollBack();
                @unlink($uploadPath);
                send_json(['error' => 'Veritabanı hatası: ' . $e->getMessage()], 500);
            }
        }
        break;

    // DEKONTLAR LISTESI
    case 'dekontlar':
        check_auth();
        if ($method === 'GET') {
            $rol = $_GET['rol'] ?? '';
            $kullanici_id = safe_id($_GET['kullanici_id'] ?? null);
            $cihaz_kimligi = trim((string)($_GET['device_id'] ?? $_GET['cihaz_kimligi'] ?? ''));

            $sql = "SELECT d.*, k.ad_soyad FROM dekontlar d LEFT JOIN kullanicilar k ON d.kullanici_id = k.id";
            $params = [];

            if ($rol !== 'admin') {
                // Non-admin: kullanici_id VE cihaz_kimligi zorunlu — ikisi de eşleşmeli
                if (!$kullanici_id || $cihaz_kimligi === '') {
                    send_json([]);
                }
                $sql .= " WHERE d.kullanici_id = ? AND d.cihaz_kimligi = ?";
                $params[] = $kullanici_id;
                $params[] = $cihaz_kimligi;
            }
            // Admin: filtre yok, tüm dekontlar

            $sql .= " ORDER BY d.created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $dekontlar = $stmt->fetchAll();

            foreach ($dekontlar as &$d) {
                $stmt = $conn->prepare("SELECT * FROM dekont_kurslar WHERE dekont_id = ?");
                $stmt->execute([$d['id']]);
                $d['kurslar'] = $stmt->fetchAll();
            }
            send_json($dekontlar);
        }
        break;

    // DELETE DEKONT
    case (preg_match('/^dekont\/(\d+)$/', $action, $matches) ? true : false):
        $id = $matches[1];
        if ($method === 'DELETE') {
            $stmt = $conn->prepare("SELECT dekont_dosya FROM dekontlar WHERE id = ?");
            $stmt->execute([$id]);
            $dekont = $stmt->fetch();
            if ($dekont) {
                $path = __DIR__ . '/uploads/' . $dekont['dekont_dosya'];
                if (file_exists($path)) @unlink($path);
                $stmt = $conn->prepare("DELETE FROM dekontlar WHERE id = ?");
                $stmt->execute([$id]);
            }
            send_json(['success' => true]);
        }
        break;

    // ADMIN EXCEL DOWNLOAD
    case 'admin/excel':
        check_auth('admin');
        if ($method === 'GET') {
            $stmt = $conn->query("SELECT d.*, k.ad_soyad FROM dekontlar d LEFT JOIN kullanicilar k ON d.kullanici_id = k.id ORDER BY d.created_at DESC");
            $dekontlar = $stmt->fetchAll();

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="dekont_raporu_' . date('Y-m-d_H-i-s') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Excel UTF-8 BOM
            fputcsv($output, ['ID', 'Kurum Adı', 'Kurslar', 'Toplam Kursiyer', 'Dekont Tutarı', 'Dekont Numarası', 'Tarih'], ';');

            foreach ($dekontlar as $d) {
                $st2 = $conn->prepare("SELECT * FROM dekont_kurslar WHERE dekont_id = ?");
                $st2->execute([$d['id']]);
                $kurslar = $st2->fetchAll();
                
                $kursString = [];
                $toplamKursiyer = 0;
                foreach ($kurslar as $k) {
                    if ($k['kursiyer_sayisi'] > 0) {
                        $kursString[] = $k['kurs_adi'] . ': ' . $k['kursiyer_sayisi'];
                        $toplamKursiyer += $k['kursiyer_sayisi'];
                    }
                }
                
                fputcsv($output, [
                    $d['id'],
                    $d['ad_soyad'],
                    implode(', ', $kursString),
                    $toplamKursiyer,
                    $d['dekont_tutari'],
                    $d['dekont_numarasi'],
                    date('d/m/Y', strtotime($d['dekont_tarihi']))
                ], ';');
            }
            fclose($output);
            exit;
        }
        break;

    // ADMIN ZIP PDF
    case 'admin/merged-pdf':
        check_auth('admin');
        if ($method === 'GET') {
            if (!class_exists('ZipArchive')) {
                die("Sunucunuzda ZipArchive eklentisi yuku degil.");
            }
            $zip = new ZipArchive();
            $zipName = __DIR__ . '/uploads/dekont_pdfler_' . time() . '.zip';
            
            if ($zip->open($zipName, ZipArchive::CREATE) !== TRUE) {
                die("Sunucu ZIP dosyasi olusturamadi (Klasor izinlerini kontrol edin).");
            }
            
            $stmt = $conn->query("SELECT dekont_dosya, ad_soyad, dekont_tarihi FROM dekontlar d LEFT JOIN kullanicilar k ON d.kullanici_id = k.id");
            $files = $stmt->fetchAll();
            $count = 0;
            
            foreach ($files as $f) {
                $path = __DIR__ . '/uploads/' . $f['dekont_dosya'];
                if (file_exists($path) && is_file($path)) {
                    // PDF ismine kurum adi eklendi
                    $kurum = preg_replace('/[^A-Za-z0-9_\-]/u', '_', $f['ad_soyad']);
                    $newName = $kurum . '_' . $f['dekont_tarihi'] . '_' . $f['dekont_dosya'];
                    $zip->addFile($path, $newName);
                    $count++;
                }
            }
            
            $zip->close();
            
            if ($count === 0 || !file_exists($zipName)) {
                die("Eklenecek PDF bulunamadi!");
            }
            
            header('Content-Type: application/zip');
            header('Content-disposition: attachment; filename="dekont_pdfler_'.date('Y-m-d').'.zip"');
            header('Content-Length: ' . filesize($zipName));
            readfile($zipName);
            @unlink($zipName);
            exit;
        }
        break;

    default:
        send_json(['error' => 'Invalid action: ' . $action], 404);
        break;
}
?>
