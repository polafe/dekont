<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_name('MTSK_SESSION');
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$ad_soyad = $_SESSION['ad_soyad'] ?? $_SESSION['kullanici_adi'] ?? 'Kullanıcı';
$rol = $_SESSION['rol'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <!-- Mobil: zoom'u engelle, tam genişlik, iOS güvenli alan desteği -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="description" content="MTSK Dekont Toplama ve Takip Sistemi">
  <meta name="theme-color" content="#0f172a">
  <!-- iOS PWA desteği -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="MTSK Dekont">
  <!-- Türkçe dil -->
  <meta http-equiv="content-language" content="tr">
  <title>MTSK Dekont Toplama Sistemi</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <script src="config.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
  <link rel="stylesheet" href="style.css">
</head>
<script>
  // PDF.js worker configuration
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>
<body>
<?php if (!$user_id): ?>
  <!-- LOGIN -->
  <div class="login-bg" id="loginOverlay">
    <div class="login-card">
      <div class="login-icon" style="background:linear-gradient(135deg, var(--accent), #fbbf24);"><i class="fas fa-file-invoice"></i></div>
      <h2>MTSK Dekont Sistemi</h2>
      <p class="sub" id="loginDuyuru">Devam etmek icin giris yapiniz</p>
      <div class="login-error" id="loginError"><i class="fas fa-exclamation-triangle"></i> Kullanici adi veya sifre yanlis!</div>
      <input type="text" id="loginUser" placeholder="Kullanici adi" onkeypress="if(event.key==='Enter')document.getElementById('loginPass').focus()">
      <input type="password" id="loginPass" placeholder="Sifre" onkeypress="if(event.key==='Enter')doLogin()">
      <button class="login-btn" onclick="doLogin()"><i class="fas fa-sign-in-alt"></i> Giriş Yap</button>
    </div>
  </div>
<?php else: ?>

  <!-- Admin Notification Panel -->
  <div class="admin-panel" id="adminPanel">
    <div class="admin-panel-title"><i class="fas fa-bell"></i> Bildirim</div>
    <div class="admin-panel-content" id="adminPanelContent"></div>
    <span class="admin-panel-close" onclick="closeAdminPanel()"><i class="fas fa-times"></i></span>
  </div>

  <div id="mainApp">
    <header class="header">
      <div class="header-inner">
        <a href="/" class="logo">
          <div class="logo-icon"><i class="fas fa-car"></i></div>
          <div>
            <div class="logo-text">MTSK DEKONT SİSTEMİ <span style="opacity:0.6;font-size:11px;margin-left:8px;font-weight:400;">v9.1</span></div>
            <div class="logo-sub">Dekont Toplama Sistemi</div>
          </div>
        </a>
        <div style="display:flex;align-items:center;gap:16px;">
          <nav class="nav-links">
            <a href="#" class="active">
              <i class="fas fa-file-invoice"></i> <span>Dekont Girişi</span>
            </a>
            <?php if ($rol === 'admin'): ?>
            <a href="panel_9xA82.php" id="adminNavLink">
              <i class="fas fa-lock"></i> <span>Yönetici</span>
            </a>
            <?php endif; ?>
          </nav>
          <div class="user-badge">
            <i class="fas fa-user"></i>
            <span class="name" id="userNameDisplay"><?php echo htmlspecialchars($ad_soyad); ?></span>
            <a href="?logout=1" class="logout-link" style="text-decoration:none; cursor:pointer;">Çıkış</a>
          </div>
        </div>
      </div>
    </header>

    <main class="main">

      <!-- DEKONT TAB -->
    <div id="tab-dekont" class="tab-content active">
      <form id="dekontForm" onsubmit="return submitDekont(event)">

        <div class="card">
          <div class="card-title"><i class="fas fa-users"></i> Kursiyer Sayıları</div>
          <div id="kursContainer" class="kurs-grid"></div>
          <div class="toplam-bar">
            <div>
              <div class="label">Toplam Kursiyer</div>
              <div class="value" id="toplamKursiyer">0</div>
            </div>
            <div style="text-align:right;">
              <div class="label">Minimum Dekont Tutari</div>
              <div class="value" id="minTutar">0 TL</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-title"><i class="fas fa-receipt"></i> Dekont Detayları</div>
          <div class="row-2">
            <div class="form-group">
              <label class="form-label">Dekont Tarihi</label>
              <input type="date" id="dekontTarihi" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label">Dekont Numarası (İsteğe Bağlı)</label>
              <input type="text" id="dekontNumarasi" class="form-control" placeholder="ornek: 12345 (bos birakabilirsiniz)">
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-title"><i class="fas fa-cloud-upload-alt"></i> PDF Dekont Yükle</div>
          <div class="file-drop" id="fileDrop" onclick="document.getElementById('dekontDosya').click()">
            <input type="file" id="dekontDosya" accept=".pdf" onchange="fileSelected(this)" required>
            <div class="file-drop-icon"><i class="fas fa-file-pdf"></i></div>
            <div class="file-drop-text">Tıklayın veya PDF dosyanızı sürükleyin</div>
            <div class="file-drop-hint">Maksimum 10MB, sadece .pdf formati</div>
            <div class="file-drop-name" id="fileName"></div>
          </div>
        </div>

        <!-- PDF önizleme özellikleri kullanıcı isteğiyle tamamen kaldırılmıştır. -->
        <div id="previewCard" style="display:none !important;" hidden>
          <span id="previewDeviceId"></span>
          <span id="previewFileInfo"></span>
          <canvas id="pdfPreviewCanvas" style="display:none !important;"></canvas>
          <button id="openPreviewBtn" style="display:none !important;"></button>
        </div>

        <div id="alertBox" class="alert"></div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <i class="fas fa-paper-plane"></i> Dekontu Gönder
        </button>

        <!-- DEKONTLARIM (önizleme altında) -->
        <div class="card" id="dekontlarimCard" style="margin-top:24px;">
          <div class="card-title"><i class="fas fa-folder-open"></i> Dekontlarım</div>
          <div id="dekontlarimEmpty" style="text-align:center;padding:24px;color:var(--gray-400);font-size:13px;display:none;">
            <i class="fas fa-inbox" style="font-size:28px;margin-bottom:8px;display:block;"></i>
            Henuz dekont yuklemediniz.
          </div>
          <div class="table-wrap">
            <table id="dekontlarimTable" style="display:none;">
              <thead>
                <tr>
                  <th>#</th><th>Kurum Adı</th><th>Tarih</th><th>Dekont No</th><th>Kursiyer</th><th>Tutar</th><th>PDF</th><th>İşlem</th><th>Cihaz ID</th>
                </tr>
              </thead>
              <tbody id="dekontlarimBody"></tbody>
            </table>
          </div>
        </div>
      </form>
    </div>

    </main>
  </div>
<?php endif; ?>

  <!-- Silme Onay Modali -->
  <div class="modal-bg" id="deleteModal">
    <div class="modal-box">
      <div class="modal-icon"><i class="fas fa-trash-alt"></i></div>
      <h3>Dekontu Sil</h3>
      <p>Bu dekontu silmek istediginizden emin misiniz? Bu islem geri alinamaz.</p>
      <div class="modal-actions">
        <button class="btn btn-outline-modal" onclick="closeDeleteModal()">Vazgec</button>
        <button class="btn btn-red-modal" onclick="confirmDeleteDekont()"><i class="fas fa-trash"></i> Sil</button>
      </div>
    </div>
  </div>

  <!-- Önizleme Modalı Kaldırıldı -->
  <div id="previewModal" style="display:none !important;" hidden>
    <canvas id="pdfPreviewModalCanvas"></canvas>
    <span id="previewModalDeviceId"></span>
    <span id="previewModalFileInfo"></span>
    <span id="previewPageInfo"></span>
    <span id="previewZoomInfo"></span>
  </div>

  <script>
    let kursiyerUcreti = 2000;
    let kurslar = [];
    let selectedPdfFile = null;
    let selectedPdfDoc = null;
    let selectedPdfDocPromise = null;
    let previewRenderToken = 0;
    let previewPage = 1;
    let previewScale = 1.1;
    let previewTotalPages = 0;
    let currentUser = <?php echo $user_id ? json_encode(['id' => $user_id, 'rol' => $rol, 'ad_soyad' => $ad_soyad]) : 'null'; ?>;

    function getDeviceId() {
      const key = 'mtsk_device_id';
      let deviceId = localStorage.getItem(key);
      if (!deviceId) {
        if (window.crypto && crypto.randomUUID) {
          deviceId = crypto.randomUUID();
        } else {
          deviceId = 'dev-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        }
        localStorage.setItem(key, deviceId);
      }
      return deviceId;
    }

    function updatePreviewLabels(file) {
      const deviceId = getDeviceId();
      const shortDeviceId = deviceId.length > 12 ? deviceId.slice(0, 12) + '…' : deviceId;
      const infoText = file ? `${file.name} • ${(file.size / 1024 / 1024).toFixed(2)} MB` : 'Seçili dosya yok';

      const label = document.getElementById('previewDeviceId');
      const modalLabel = document.getElementById('previewModalDeviceId');
      const info = document.getElementById('previewFileInfo');
      const modalInfo = document.getElementById('previewModalFileInfo');

      if (label) label.textContent = 'Cihaz kodu: ' + shortDeviceId;
      if (modalLabel) modalLabel.textContent = 'Cihaz kodu: ' + shortDeviceId;
      if (info) info.textContent = infoText;
      if (modalInfo) modalInfo.textContent = infoText;
    }

    function updatePreviewStats() {
      const pageInfo = document.getElementById('previewPageInfo');
      const zoomInfo = document.getElementById('previewZoomInfo');
      if (pageInfo) pageInfo.textContent = `Sayfa ${previewPage} / ${Math.max(previewTotalPages, 1)}`;
      if (zoomInfo) zoomInfo.textContent = `${Math.round(previewScale * 100)}%`;
    }

    async function readPdfData(file) {
      const reader = new FileReader();
      return await new Promise((resolve, reject) => {
        reader.onload = () => resolve(new Uint8Array(reader.result));
        reader.onerror = () => reject(new Error('PDF dosyasi okunamadi.'));
        reader.readAsArrayBuffer(file);
      });
    }

    async function loadPdfDocument(file) {
      const pdfData = await readPdfData(file);
      return await pdfjsLib.getDocument({ data: pdfData }).promise;
    }

    async function ensureSelectedPdfDoc() {
      if (selectedPdfDoc) return selectedPdfDoc;
      if (!selectedPdfDocPromise) return null;
      selectedPdfDoc = await selectedPdfDocPromise;
      previewTotalPages = selectedPdfDoc.numPages || 0;
      return selectedPdfDoc;
    }

    async function renderPdfPreview(file, canvas, pageNumber = 1, scale = 1.1) {
      if (!file || !canvas) return;
      const renderToken = ++previewRenderToken;
      const pdf = await ensureSelectedPdfDoc();
      if (!pdf) return;
      const safePage = Math.min(Math.max(pageNumber, 1), pdf.numPages || 1);
      const page = await pdf.getPage(safePage);
      const viewport = page.getViewport({ scale });
      if (renderToken !== previewRenderToken) return;

      const context = canvas.getContext('2d');
      const ratio = window.devicePixelRatio || 1;
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      canvas.style.width = viewport.width / ratio + 'px';
      canvas.style.height = viewport.height / ratio + 'px';
      await page.render({ canvasContext: context, viewport }).promise;
    }

    async function refreshPdfPreview(file) {
      const previewCard = document.getElementById('previewCard');
      const previewCanvas = document.getElementById('pdfPreviewCanvas');
      const openBtn = document.getElementById('openPreviewBtn');

      selectedPdfFile = file || null;
      selectedPdfDoc = null;
      selectedPdfDocPromise = null;
      previewPage = 1;
      previewScale = 1.1;
      previewTotalPages = 0;
      updatePreviewLabels(file);
      updatePreviewStats();

      if (!file) {
        // previewCard.style.display = 'none'; // Kullanıcı istemiyor
        openBtn.disabled = true;
        return;
      }

      // previewCard.style.display = 'block'; // Kullanıcı istemiyor
      openBtn.disabled = false;

      try {
        selectedPdfDocPromise = loadPdfDocument(file);
        selectedPdfDoc = await selectedPdfDocPromise;
        previewTotalPages = selectedPdfDoc.numPages || 0;
        previewPage = Math.min(previewPage, Math.max(previewTotalPages, 1));
        // renderPdfPreview kaldırıldı - kullanıcı istemiyor
        // await renderPdfPreview(file, previewCanvas, previewPage, previewScale);
        // updatePreviewStats();
      } catch (err) {
        const ctx = previewCanvas.getContext('2d');
        previewCanvas.width = 520;
        previewCanvas.height = 180;
        previewCanvas.style.width = '100%';
        previewCanvas.style.height = 'auto';
        ctx.clearRect(0, 0, previewCanvas.width, previewCanvas.height);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);
        ctx.fillStyle = '#475569';
        ctx.font = '16px Inter, sans-serif';
        ctx.fillText('PDF önizleme oluşturulamadı.', 20, 60);
        ctx.font = '12px Inter, sans-serif';
        ctx.fillText(err.message || 'Dosyayı tekrar seçin.', 20, 88);
      }
    }

    function openPreviewModal() {
      if (!selectedPdfFile) return;
      const modal = document.getElementById('previewModal');
      modal.classList.add('show');
      const modalCanvas = document.getElementById('pdfPreviewModalCanvas');
      renderPdfPreview(selectedPdfFile, modalCanvas, previewPage, previewScale).catch(() => {});
      updatePreviewStats();
    }

    function closePreviewModal() {
      document.getElementById('previewModal').classList.remove('show');
    }

    async function changePreviewPage(delta) {
      if (!selectedPdfFile || !selectedPdfDoc) return;
      const nextPage = previewPage + delta;
      if (nextPage < 1 || nextPage > previewTotalPages) return;
      previewPage = nextPage;
      updatePreviewStats();
      const previewCanvas = document.getElementById('pdfPreviewCanvas');
      const modalCanvas = document.getElementById('pdfPreviewModalCanvas');
      await renderPdfPreview(selectedPdfFile, previewCanvas, previewPage, previewScale).catch(() => {});
      await renderPdfPreview(selectedPdfFile, modalCanvas, previewPage, previewScale).catch(() => {});
    }

    async function changePreviewZoom(delta) {
      if (!selectedPdfFile || !selectedPdfDoc) return;
      previewScale = Math.min(2.4, Math.max(0.7, previewScale + delta));
      updatePreviewStats();
      const previewCanvas = document.getElementById('pdfPreviewCanvas');
      const modalCanvas = document.getElementById('pdfPreviewModalCanvas');
      await renderPdfPreview(selectedPdfFile, previewCanvas, previewPage, previewScale).catch(() => {});
      await renderPdfPreview(selectedPdfFile, modalCanvas, previewPage, previewScale).catch(() => {});
    }

    async function resetPreviewView() {
      if (!selectedPdfFile || !selectedPdfDoc) return;
      previewPage = 1;
      previewScale = 1.1;
      updatePreviewStats();
      const previewCanvas = document.getElementById('pdfPreviewCanvas');
      const modalCanvas = document.getElementById('pdfPreviewModalCanvas');
      await renderPdfPreview(selectedPdfFile, previewCanvas, previewPage, previewScale).catch(() => {});
      await renderPdfPreview(selectedPdfFile, modalCanvas, previewPage, previewScale).catch(() => {});
    }

    function getKursIcon(name) {
      const n = name.toLowerCase();
      if (n.includes('otomobil') || n.includes('b ')) return ['fa-car', 'oto'];
      if (n.includes('motosiklet') || n.includes('a,a1') || n.includes('a-a1')) return ['fa-motorcycle', 'moto'];
      if (n.includes('kamyon') || n.includes('tir') || n.includes('ce') || n.includes('c ')) return ['fa-truck', 'kamyon'];
      if (n.includes('otobus') || n.includes('minibus') || n.includes('d ')) return ['fa-bus', 'bus'];
      return ['fa-id-card', 'moto'];
    }

    async function init() {
      try {
        let ayarlar = {};
        try {
          const ayarlarRes = await fetch(API_CONFIG.url('ayarlar'));
          if (ayarlarRes.ok) {
            ayarlar = await ayarlarRes.json();
          }
        } catch(e) {
          console.warn('[init] Ayarlar yuklenemedi:', e);
        }

        kursiyerUcreti = parseFloat(ayarlar.kursiyer_ucreti || 2000);
        window._gecmisGunSiniri = parseInt(ayarlar.gecmis_gun_siniri || 10);

        const dEl = document.getElementById('loginDuyuru');
        if (dEl && ayarlar.duyuru_metni) {
          dEl.innerHTML = ayarlar.duyuru_metni;
          dEl.style.color = 'var(--red)';
          dEl.style.fontWeight = '600';
        }

        const container = document.getElementById('kursContainer');

        try {
          const kurslarRes = await fetch(API_CONFIG.url('kurslar'));
          if (!kurslarRes.ok) throw new Error('HTTP ' + kurslarRes.status);
          kurslar = await kurslarRes.json();

          if (!Array.isArray(kurslar) || kurslar.length === 0) {
            container.innerHTML = '<div style="color:var(--gray-400);font-size:13px;padding:12px;text-align:center;"><i class="fas fa-info-circle"></i> Henüz kurs tanımlanmamış. Yönetici panelinden kurs ekleyiniz.</div>';
          } else {
            container.innerHTML = '';
            kurslar.forEach((k) => {
              const icon = getKursIcon(k.kurs_adi);
              const div = document.createElement('div');
              div.className = 'kurs-item';
              div.innerHTML = `
                <div class="kurs-icon ${icon[1]}"><i class="fas ${icon[0]}"></i></div>
                <div class="kurs-name">${k.kurs_adi}</div>
                <input type="number" class="kurs-input" min="0" value="0" data-kurs="${k.kurs_adi}"
                       onchange="updateToplam()" oninput="updateToplam()">
              `;
              container.appendChild(div);
            });
          }
        } catch(e) {
          console.error('[init] Kurslar yuklenemedi:', e);
          container.innerHTML = '<div style="color:var(--red);font-size:13px;padding:12px;text-align:center;background:var(--red-light);border-radius:8px;"><i class="fas fa-exclamation-triangle"></i> Kurs listesi yüklenemedi. Sayfayı yenileyiniz.</div>';
        }

        // Drag & drop
        const drop = document.getElementById('fileDrop');
        if (drop) {
          drop.addEventListener('dragover', e => { e.preventDefault(); drop.style.borderColor='var(--accent)'; drop.style.background='var(--accent-light)'; });
          drop.addEventListener('dragleave', e => { e.preventDefault(); drop.style.borderColor=''; drop.style.background=''; });
          drop.addEventListener('drop', e => {
            e.preventDefault(); drop.style.borderColor=''; drop.style.background='';
            if (e.dataTransfer.files.length && e.dataTransfer.files[0].type === 'application/pdf') {
              document.getElementById('dekontDosya').files = e.dataTransfer.files;
              fileSelected(document.getElementById('dekontDosya'));
            }
          });
        }

      } catch(err) {
        console.error('[init] Kritik hata:', err);
        const container = document.getElementById('kursContainer');
        if (container) {
          container.innerHTML = '<div style="color:var(--red);font-size:13px;padding:12px;text-align:center;background:var(--red-light);border-radius:8px;"><i class="fas fa-exclamation-triangle"></i> Sayfa yüklenirken hata oluştu. Lütfen sayfayı yenileyiniz.</div>';
        }
      }
    }

    function updateToplam() {
      const inputs = document.querySelectorAll('.kurs-input');
      let toplam = 0;
      inputs.forEach(inp => toplam += parseInt(inp.value) || 0);
      document.getElementById('toplamKursiyer').textContent = toplam;
      const min = toplam * kursiyerUcreti;
      document.getElementById('minTutar').textContent = min.toLocaleString('tr-TR') + ' TL';
    }

    async function fileSelected(input) {
      const drop = document.getElementById('fileDrop');
      const nameEl = document.getElementById('fileName');
      if (input.files.length > 0) {
        drop.classList.add('has-file');
        nameEl.textContent = input.files[0].name;
        await refreshPdfPreview(input.files[0]);
      } else {
        drop.classList.remove('has-file');
        nameEl.textContent = '';
        await refreshPdfPreview(null);
      }
    }


    function showAlert(msg, type) {
      const el = document.getElementById('alertBox');
      el.className = 'alert alert-' + type;
      el.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg;
      el.style.display = 'flex';
      setTimeout(() => { el.style.display = 'none'; }, 15000);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showAdminPanel(title, message) {
      const panel = document.getElementById('adminPanel');
      const content = document.getElementById('adminPanelContent');
      content.innerHTML = message;
      panel.classList.add('show');
      setTimeout(() => { closeAdminPanel(); }, 5000);
    }

    function closeAdminPanel() {
      const panel = document.getElementById('adminPanel');
      panel.classList.remove('show');
    }

    async function submitDekont(e) {
      e.preventDefault();
      const btn = document.getElementById('submitBtn');
      
      const fileInput = document.getElementById('dekontDosya');
      if (fileInput.files.length === 0) return showAlert('Lütfen bir PDF dosyası seçin.', 'error');

      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kontrol Ediliyor...';

      try {
        const settings = await fetch(API_CONFIG.url('ayarlar')).then(r => r.json());
        const rawIbanStr = settings.gecerli_iban || 'TR28 0001 0002 5383 8581 0250 01';
        
        // Virgülle ayrılmış IBAN'ları temizle ve diziye at (boşluksuz + küçük harf)
        const validIbans = rawIbanStr.split(',').map(v => v.replace(/\s/g, '').toLowerCase()).filter(v => v);
        
        // Her IBAN için (eğer 16 veya daha uzunsa) son 16 haneyi (Hesap No) hesapla
        const validAccounts = validIbans.map(iban => {
           return iban.length >= 16 ? iban.slice(-16) : iban;
        });
        
        const inputs = document.querySelectorAll('.kurs-input');
        let toplamKursiyer = 0;
        inputs.forEach(inp => toplamKursiyer += parseInt(inp.value) || 0);
        
        const minVal = toplamKursiyer * kursiyerUcreti;

        // PDF KONTROLÜ
        const pdfFile = fileInput.files[0];
        const pdfText = await extractPdfText(pdfFile);

        // Görüntü tabanlı PDF kontrolü
        if (pdfText === '__IMAGE_PDF__') {
          showAlert('Hata: Yüklediğiniz PDF taranmış (görüntü) bir dosya olduğu için metin okunamıyor. Lütfen banka internet şubesinden veya mobil uygulamadan PDF formatında dekont indirerek tekrar deneyin.', 'error');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-paper-plane"></i> Dekontu Gonder';
          return false;
        }

        // Hem boşluklu hem boşuksuz metin üzerinde IBAN ara
        // (PDF.js bazı bankalarda IBAN'ı parçalı çıkartabiliyor)
        const spacedText  = (window._lastPdfSpacedText  || pdfText);
        const concatText  = (window._lastPdfConcatText  || pdfText.replace(/\s/g,''));
        const normalizedText = concatText.toLowerCase();  // boşluksuz, küçük harf
        const normalizedSpaced = spacedText.replace(/\s+/g,' ').toLowerCase(); // tek boşluklu

        // IBAN karşılaştırması: boşluksuz concat üzerinde yap (parçalı IBAN sorununu çözer)
        const hasValidIban = validIbans.some(iban => {
          // 1) concat metin (en güvenilir)
          if (normalizedText.includes(iban)) return true;
          // 2) Boşluklu metinde de ara (bazı PDF'lerde tek blok)
          if (normalizedSpaced.replace(/\s/g,'').includes(iban)) return true;
          // 3) IBAN parçaları arası tek boşlukla ara (TR28 0001 0002 ... formatı)
          const ibanSpaced = iban.replace(/(.{4})/g,'$1 ').trim();
          if (normalizedSpaced.includes(ibanSpaced.toLowerCase())) return true;
          return false;
        });
        const hasValidAccount = validAccounts.some(acc => normalizedText.includes(acc));

        // IBAN Kontrolü
        if (!hasValidIban && !hasValidAccount) {
          showAlert(`Hata: Dekont üzerindeki IBAN/Hesap No sisteme kayıtlı olanlardan hiçbiriyle eşleşmedi.`, 'error');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-paper-plane"></i> Dekontu Gonder';
          return false;
        }

        // Tutar: minimum dekont tutarından otomatik alınır (kullanıcı girmez)
        const tutar = minVal;

        // PDF'den maksimum tutarı bul (hem boşluklu hem concat metin üzerinde dene)
        let pdfMaxAmount = findMaxAmountInText(spacedText);
        if (pdfMaxAmount === null) {
          pdfMaxAmount = findMaxAmountInText(concatText);
        }
        if (pdfMaxAmount === null) {
          showAlert(`Hata: Dekont üzerinde geçerli bir tutar bulunamadı. Lütfen dekont dosyasını kontrol edin.`, 'error');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-paper-plane"></i> Dekontu Gonder';
          return false;
        }

        // Kuruş kısmını yok say (örnek: 95000,99 -> 95000)
        const pdfMaxAmountTl = Math.floor(pdfMaxAmount);
        if (pdfMaxAmountTl <= 0) {
          showAlert(`Hata: Dekont üzerinde geçerli bir TL tutarı bulunamadı.`, 'error');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-paper-plane"></i> Dekontu Gonder';
          return false;
        }

        // PDF'teki tutar minimum tutardan düşükse KESINLIKLE reddet
        // (pdfMaxAmount her zaman güvenilir para formatındaki sayılardır)
        if (minVal > 0 && pdfMaxAmountTl < minVal) {
          showAlert(
            `Hata: Dekonttaki tutar (${pdfMaxAmountTl.toLocaleString('tr-TR')} TL) ` +
            `sisteme girilen kursiyer sayısına göre gerekli minimum tutarın ` +
            `(${minVal.toLocaleString('tr-TR')} TL) altında olduğundan dekont kabul edilemez.`,
            'error'
          );
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-paper-plane"></i> Dekontu Gönder';
          return false;
        }

        // Tarih PDF içinde geçiyor mu? (boşluksuz concat üzerinde ara)
        const dekontTarihVal = document.getElementById('dekontTarihi').value;
        if (dekontTarihVal) {
           const [y, m, d] = dekontTarihVal.split('-');
           const t1 = `${d}.${m}.${y}`;
           const t2 = `${d}/${m}/${y}`;
           const t3 = `${d}-${m}-${y}`;
           const t4 = `${d} ${m} ${y}`;
           const dateFound =
               normalizedText.includes(t1.replace(/\s/g,'')) ||
               normalizedText.includes(t2.replace(/\s/g,'')) ||
               normalizedText.includes(t3.replace(/\s/g,'')) ||
               normalizedText.includes(t4.replace(/\s/g,'')) ||
               normalizedSpaced.includes(t1.toLowerCase()) ||
               normalizedSpaced.includes(t2.toLowerCase());
           if (!dateFound) {
               showAlert(`Hata: Girdiğiniz tarih (${t1}) dekont üzerinde bulunamadı!`, 'error');
               btn.disabled = false;
               btn.innerHTML = '<i class="fas fa-paper-plane"></i> Dekontu Gonder';
               return false;
           }
        }

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gonderiliyor...';
        const kurslarData = [];
        inputs.forEach(inp => {
          const val = parseInt(inp.value) || 0;
          kurslarData.push({ kurs_adi: inp.dataset.kurs, kursiyer_sayisi: val });
        });

        // Tarih kontrolü: bugün ve en fazla N gün geriye (ayardan dinamik)
        if (dekontTarihVal) {
          const gunSiniri = (window._gecmisGunSiniri > 0) ? window._gecmisGunSiniri : 10;
          const bugun = new Date();
          bugun.setHours(0, 0, 0, 0);
          const limitTarih = new Date(bugun);
          limitTarih.setDate(limitTarih.getDate() - gunSiniri);
          const secilen = new Date(dekontTarihVal);
          secilen.setHours(0, 0, 0, 0);
          if (secilen > bugun) {
            showAlert('İleri tarihli dekont kabul edilmez!', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Dekontu Gönder';
            return false;
          }
          if (secilen < limitTarih) {
            showAlert(`Dekont tarihi en fazla ${gunSiniri} gün geriye dönük olabilir!`, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Dekontu Gönder';
            return false;
          }
        }

        // PDF'e kurum adı ve kursiyer bilgisi damgala
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PDF Hazirlaniyor...';
        const stampedFile = await stampPdfWithInfo(
          pdfFile,
          currentUser ? currentUser.ad_soyad : '',
          kurslarData,
          toplamKursiyer
        );

        const formData = new FormData();
        formData.append('dekont_tarihi', document.getElementById('dekontTarihi').value);
        formData.append('dekont_numarasi', document.getElementById('dekontNumarasi').value);
        formData.append('dekont_tutari', tutar);
        formData.append('dekont_pdf_tutari', pdfMaxAmountTl);
        formData.append('kurslar_json', JSON.stringify(kurslarData));
        formData.append('kullanici_id', currentUser ? currentUser.id : '');
        formData.append('device_id', getDeviceId());
        formData.append('dekont_dosya', stampedFile);

        const res = await fetch(API_CONFIG.url('dekont'), { method: 'POST', body: formData });
        const data = await res.json();

        if (res.ok) {
          showAlert('Dekont basariyla gonderildi!', 'success');
          document.getElementById('dekontForm').reset();
          document.getElementById('fileDrop').classList.remove('has-file');
          document.getElementById('fileName').textContent = '';
          await refreshPdfPreview(null);
          document.querySelectorAll('.kurs-input').forEach(inp => inp.value = '0');
          updateToplam();
          loadDekontlarim();
        } else {
          showAlert(data.error || 'Bir hata olustu', 'error');
        }
      } catch (err) {
        showAlert('Baglanti hatasi: ' + err.message, 'error');
      }

      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Dekontu Gonder';
      return false;
    }

    // ===== DEKONTLARIM =====
    async function loadDekontlarim() {
      if (!currentUser) return;
      try {
        const deviceId = encodeURIComponent(getDeviceId());
        const data = await fetch(`${API_CONFIG.url('dekontlar')}?kullanici_id=${currentUser.id}&rol=${currentUser.rol}&device_id=${deviceId}`).then(r => r.json());
        const tbody = document.getElementById('dekontlarimBody');
        const table = document.getElementById('dekontlarimTable');
        const empty = document.getElementById('dekontlarimEmpty');
        tbody.innerHTML = '';

        if (data.length === 0) {
          table.style.display = 'none';
          empty.style.display = 'block';
          return;
        }

        table.style.display = 'table';
        empty.style.display = 'none';

        data.forEach((d, i) => {
          const ks = d.kurslar || [];
          const topK = ks.reduce((s, k) => s + k.kursiyer_sayisi, 0);
          tbody.innerHTML += `<tr>
            <td>${i + 1}</td>
            <td class="td-bold">${d.ad_soyad || currentUser.ad_soyad || '-'}</td>
            <td>${d.dekont_tarihi.split('-').reverse().join('/')}</td>
            <td class="td-bold">${d.dekont_numarasi}</td>
            <td><span class="tag tag-green">${topK}</span></td>
            <td class="td-bold">${d.dekont_tutari.toLocaleString('tr-TR')} TL</td>
            <td><a href="uploads/${d.dekont_dosya}" target="_blank" class="btn-sm btn-sm-blue"><i class="fas fa-eye"></i> Goruntule</a></td>
            <td><button type="button" class="btn-sm btn-sm-red" onclick="showDeleteModal(${d.id})"><i class="fas fa-trash"></i> Sil</button></td>
            <td><span class="tag tag-amber" title="${d.cihaz_kimligi || '-'}" style="font-size:10px;letter-spacing:0.5px;font-family:monospace;">${d.cihaz_kimligi ? d.cihaz_kimligi.slice(0,8) + '…' : '-'}</span></td>
          </tr>`;
        });
      } catch (err) {
        console.error('Dekontlar yuklenemedi:', err);
      }
    }

    let deleteDekontId = null;
    function showDeleteModal(id) {
      deleteDekontId = id;
      document.getElementById('deleteModal').classList.add('show');
    }
    function closeDeleteModal() {
      deleteDekontId = null;
      document.getElementById('deleteModal').classList.remove('show');
    }
    async function confirmDeleteDekont() {
      if (!deleteDekontId) return;
      try {
        const res = await fetch(`${API_CONFIG.url('dekont')}/${deleteDekontId}`, { method: 'DELETE' });
        if (res.ok) {
          showAlert('Dekont basariyla silindi.', 'success');
          loadDekontlarim();
        } else {
          const d = await res.json();
          showAlert(d.error || 'Silme hatasi', 'error');
        }
      } catch (err) {
        showAlert('Baglanti hatasi: ' + err.message, 'error');
      }
      closeDeleteModal();
    }

    /**
     * PDF'den metin çıkartır. İki versiyon üretir:
     * - spaced: kelimeleri boşlukla birleştir  (tutar karşılaştırması için)
     * - concat : tüm boşlukları sil           (IBAN karşılaştırması için)
     * Görüntü tabanlı PDF'lerde (taranmış) boş string döner ve caller uyarır.
     */
    async function extractPdfText(file) {
      const reader = new FileReader();
      const pdfData = await new Promise((resolve) => {
        reader.onload = () => resolve(new Uint8Array(reader.result));
        reader.readAsArrayBuffer(file);
      });

      const pdf = await pdfjsLib.getDocument({ data: pdfData }).promise;
      let spacedText = '';
      let concatText = '';

      for (let i = 1; i <= pdf.numPages; i++) {
        const page = await pdf.getPage(i);
        const content = await page.getTextContent();

        // Her span'ı topla; hasEOL olan span'ların ardına yeni satır ekle
        const strParts = [];
        for (const item of content.items) {
          if (item.str !== undefined) {
            strParts.push(item.str);
            if (item.hasEOL) strParts.push('\n');
            else strParts.push(' ');
          }
        }
        const pageText = strParts.join('');
        spacedText += pageText + '\n';
        // Concat: tüm whitespace'leri sil
        concatText += pageText.replace(/\s+/g, '') + '\n';
      }

      // PDF.js result objesine her iki versiyonu da ekle
      spacedText = spacedText.trim();
      concatText = concatText.trim();

      // Görüntü PDF tespiti: metin neredeyse hiç yoksa uyar
      const visibleChars = spacedText.replace(/\s/g, '').length;
      if (visibleChars < 10) {
        // PDF görüntü tabanlı olabilir — yine de dön ama özel bayrağı ekle
        spacedText = '__IMAGE_PDF__';
        concatText = '__IMAGE_PDF__';
      }

      // Her iki varyantı da döndür (geriye dönük uyumluluk için spacedText'i döndürüyoruz)
      // Ancak globallar üzerinden concatText'e de erişilebilecek
      window._lastPdfSpacedText = spacedText;
      window._lastPdfConcatText = concatText;
      return spacedText;
    }

    function normalizeAmount(m) {
      let clean = String(m || '').trim().replace(/\s+/g, '');
      if (clean.includes('.') && clean.includes(',')) {
        if (clean.lastIndexOf('.') > clean.lastIndexOf(',')) {
          clean = clean.replace(/,/g, ''); // 1,234.56 -> 1234.56
        } else {
          clean = clean.replace(/\./g, '').replace(',', '.'); // 1.234,56 -> 1234.56
        }
      } else if (clean.includes(',')) {
        const decimalDigits = clean.length - clean.indexOf(',') - 1;
        if (decimalDigits === 3) {
          clean = clean.replace(/,/g, ''); // 24,000 -> 24000
        } else {
          clean = clean.replace(',', '.'); // 24000,50 -> 24000.50
        }
      } else if (clean.includes('.')) {
        const decimalDigits = clean.length - clean.indexOf('.') - 1;
        if (decimalDigits === 3) {
          clean = clean.replace(/\./g, ''); // 24.000 -> 24000
        }
      }
      return parseFloat(clean);
    }

    function extractCandidateAmounts(text) {
      // Artik serbest sayilari ayiklamiyoruz. 
      // Cunku hesap numaralari, fis numaralari vs sahte buyuk tutarlar uretebiliyor.
      return [];
    }

    function findMaxAmountInText(text) {
      if (!text || text === '__IMAGE_PDF__') return null;

      const results = [];
      const lower = text.toLowerCase();

      // KURAL 1: Sonunda TL, TRY veya ₺ (Turk Lirasi sembolu) gecen sayilar
      // Ornek: 60.000,00 TL | 35.000 TRY | 1500 tl
      const currencyRegex = /((?:\d{1,3}(?:[.,\s]\d{3})+|\d{1,8})(?:[.,]\d{1,2})?)\s*(?:TL|TRY|\u20BA)\b/gi;
      let match;
      while ((match = currencyRegex.exec(text)) !== null) {
        let val = normalizeAmount(match[1]);
        if (!isNaN(val) && val >= 10 && val <= 9999999) {
          results.push(val);
        }
      }

      // KURAL 2: Basinda Islem Tutari, Havale Tutari, Tutar vb gecen sayilar
      // Ornek: İslem Tutari : 60.000,00 | Tutar: 60.000
      const prefixRegex = /(?:havale\s*tutar[\u0131i]?|i[\u015fs]lem\s*tutar[\u0131i]?|tutar[\u0131i]?|toplam|mebla[\u011fğ]|[öo]deme)[^0-9]{0,30}((?:\d{1,3}(?:[.,\s]\d{3})+|\d{1,8})(?:[.,]\d{1,2})?)/gi;
      while ((match = prefixRegex.exec(lower)) !== null) {
        let val = normalizeAmount(match[1]);
        if (!isNaN(val) && val >= 10 && val <= 9999999) {
           results.push(val);
        }
      }

      // Eger gecerli tutarlar bulunduysa, en buyugunu dondur.
      // (Bazi dekontlarda 'Toplam: 60.000 TL', 'Ucret: 2.5 TL' gibi seyler oldugu icin maksimumu aliyoruz)
      if (results.length > 0) {
        return Math.max(...results);
      }

      // Hicbir eslesme bulunamadiysa, guvensiz olan siradan sayilar KABUL EDILMEZ.
      return null;
    }

        function findAmountInText(text, targetAmount, exactMatch = false) {
      // Sayıları temizle ve bul
      // Örnek: "30.000,00" -> 30000
      const matches = text.match(/\d+([.,]\d+)*/g) || [];
      for (const m of matches) {
        const val = normalizeAmount(m);
        if (!isNaN(val)) {
           if (exactMatch && val === targetAmount) return true;
           if (!exactMatch && val >= targetAmount) return true;
        }
      }
      return false;
    }

    // ===== TÜRKÇE KARAKTER DÖNÜŞÜMÜ =====
    function normalizeTurkish(str) {
      return String(str || '')
        .replace(/ğ/g,'g').replace(/Ğ/g,'G')
        .replace(/ü/g,'u').replace(/Ü/g,'U')
        .replace(/ş/g,'s').replace(/Ş/g,'S')
        .replace(/ı/g,'i').replace(/İ/g,'I')
        .replace(/ö/g,'o').replace(/Ö/g,'O')
        .replace(/ç/g,'c').replace(/Ç/g,'C');
    }

    // ===== PDF DAMGALAMA (kurum adı + kursiyer) =====
    async function stampPdfWithInfo(file, kurumAdi, kurslarData, toplamKursiyer) {
      try {
        if (typeof PDFLib === 'undefined') return file;
        const { PDFDocument, StandardFonts, rgb } = PDFLib;

        const arrayBuffer = await file.arrayBuffer();
        const pdfDoc = await PDFDocument.load(arrayBuffer, { ignoreEncryption: true });
        const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);

        // Metin satırları
        const line1 = normalizeTurkish('Kurum: ' + (kurumAdi || '-'));
        const aktifKurslar = (kurslarData || []).filter(k => (k.kursiyer_sayisi || 0) > 0);
        const kursDetay = aktifKurslar
          .map(k => normalizeTurkish(k.kurs_adi) + ': ' + k.kursiyer_sayisi)
          .join(' | ');
        // Eğer birden fazla kurs varsa detayını da yanına alırız
        let line2 = normalizeTurkish('(Kursiyer Sayisi: ' + toplamKursiyer + ')');
        if (aktifKurslar.length > 1) {
            line2 += '  [ ' + kursDetay + ' ]';
        }

        const pages = pdfDoc.getPages();
        for (const page of pages) {
          const { width, height } = page.getSize();
          const fs1 = 24;           // kurum adı (punto büyütüldü, eski 20)
          const fs2 = 18;           // kursiyer formatı (punto büyütüldü, eski 14)
          const lh  = 30;           // satır yüksekliği
          const bY  = 65;           // sayfa altından mesafe
          const boxH = lh * 2 + 18;

          // Hafif kırmızı arka plan şeridi
          page.drawRectangle({
            x: 0,
            y: bY - 8,
            width: width,
            height: boxH,
            color: rgb(1.0, 0.93, 0.93),
            opacity: 0.90,
          });

          // Satır 1 — Kurum adı (ortalı, 20pt, kırmızı, kalın)
          const w1 = fontBold.widthOfTextAtSize(line1, fs1);
          page.drawText(line1, {
            x: Math.max(8, (width - w1) / 2),
            y: bY + lh + 2,
            size: fs1,
            font: fontBold,
            color: rgb(0.85, 0.05, 0.05),
          });

          // Satır 2 — Kursiyer sayısı (ortalı, 14pt, kırmızı, kalın)
          const w2 = fontBold.widthOfTextAtSize(line2, fs2);
          page.drawText(line2, {
            x: Math.max(8, (width - w2) / 2),
            y: bY + 2,
            size: fs2,
            font: fontBold,
            color: rgb(0.85, 0.05, 0.05),
          });
        }

        const newBytes = await pdfDoc.save();
        const newBlob  = new Blob([newBytes], { type: 'application/pdf' });
        return new File([newBlob], file.name, { type: 'application/pdf' });

      } catch (err) {
        console.warn('[stampPdfWithInfo] Hata, orijinal dosya kullaniliyor:', err);
        return file; // hata olursa orijinal dosyayı kullan
      }
    }


    function doLogin() {
      const kadi = document.getElementById('loginUser').value.trim();
      const sifre = document.getElementById('loginPass').value.trim();

      if (!kadi || !sifre) {
        document.getElementById('loginError').textContent = '✋ Kullanici adi ve sifre gerekli';
        document.getElementById('loginError').style.display = 'block';
        return;
      }

      const loginUrl = API_CONFIG.url('login');
      console.log('[Login] POST', loginUrl, {kullanici_adi: kadi, sifre: '***'});

      fetch(loginUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ kullanici_adi: kadi, sifre: sifre })
      }).then(async r => {
        console.log('[Login Response]', r.status, r.statusText);
        if (r.ok) return r.json();
        const errData = await r.json().catch(()=>({}));
        throw new Error(errData.error || 'HTTP ' + r.status);
      }).then(data => {
        window.location.reload();
      }).catch(err => {
        console.error('[Login Error]', err);
        document.getElementById('loginError').textContent = '❌ Hata: ' + err.message;
        document.getElementById('loginError').style.display = 'block';
      });
    }

    // Checking session and initial loading
    if (currentUser) {
      init();
      loadDekontlarim();
    } else {
      fetch(API_CONFIG.url('ayarlar')).then(r=>r.json()).then(ayarlar => {
        const dEl = document.getElementById('loginDuyuru');
        if (ayarlar.duyuru_metni) {
          dEl.innerHTML = ayarlar.duyuru_metni;
          dEl.style.color = 'var(--red)';
          dEl.style.fontWeight = '600';
        }
      }).catch(console.error);
    }
  </script>
</body>
</html>
