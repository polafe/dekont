<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_name('MTSK_SESSION');
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['user_id']) && $_SESSION['rol'] === 'admin') {
    header("Location: panel_9xA82.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Yonetici Girisi</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body style="background:var(--gray-50); display:flex; align-items:center; justify-content:center; height:100vh; margin:0;">
  <div class="login-card" style="position:static; transform:none; display:flex;">
    <div class="login-icon" style="background:linear-gradient(135deg, var(--accent), #fbbf24);"><i class="fas fa-user-shield"></i></div>
    <h2>Yonetici Girisi</h2>
    <p class="sub">Yonetici hesabinizla giris yapiniz</p>
    <div class="login-error" id="loginError" style="display:none;"><i class="fas fa-exclamation-triangle"></i> Kullanici adi veya sifre yanlis!</div>
    <form id="loginForm" onsubmit="event.preventDefault(); doLogin();" style="width:100%;">
      <input type="text" id="loginUser" placeholder="Kullanici adi" required style="width:100%; box-sizing:border-box;">
      <input type="password" id="loginPassword" placeholder="Sifre" required style="width:100%; box-sizing:border-box;">
      <button type="submit" class="login-btn" id="loginBtn" style="width:100%;"><i class="fas fa-sign-in-alt"></i> Giris Yap</button>
      <a href="index.php" style="display:block; text-align:center; margin-top:15px; font-size:13px; color:var(--accent); text-decoration:none; font-weight:500;">Ana Sayfaya Don</a>
    </form>
  </div>
  <script src="config.js"></script>
  <script>
    function doLogin() {
      const kadi = document.getElementById('loginUser').value;
      const pw = document.getElementById('loginPassword').value;
      const btn = document.getElementById('loginBtn');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bekleyiniz...';
      
      fetch(API_CONFIG.url('login'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ kullanici_adi: kadi, sifre: pw })
      }).then(async r => {
        let data = {};
        try { data = await r.json(); } catch(e) {}
        return {status: r.status, ok: r.ok, body: data};
      })
      .then(res => {
        if (!res.ok) throw new Error(res.body.error || 'Giris basarisiz (HTTP ' + res.status + ')');
        if (!res.body || !res.body.user) throw new Error('Sunucudan beklenen kullanici bilgisi donmedi!');
        if (res.body.user.rol !== 'admin') throw new Error('Yönetici rolü gereklidir');
        sessionStorage.setItem('adminUser', JSON.stringify(res.body.user));
        window.location.href = 'panel_9xA82.php';
      }).catch(err => {
        const errBox = document.getElementById('loginError');
        errBox.textContent = '❌ Hata: ' + err.message;
        errBox.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Giris Yap';
      });
    }
  </script>
</body>
</html>
