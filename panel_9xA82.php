<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_name('MTSK_SESSION');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
  header("Location: login.php");
  exit;
}
$ad_soyad = $_SESSION['ad_soyad'] ?? $_SESSION['kullanici_adi'] ?? 'Yönetici';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="description" content="MTSK Yönetici Paneli">
  <meta name="theme-color" content="#0f172a">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="MTSK Yönetici">
  <meta http-equiv="content-language" content="tr">
  <title>MTSK Yönetici Paneli</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <script src="config.js?v=2"></script>
  <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
  <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- MAIN APP -->
  <div id="mainApp">
    <header class="header">
      <div class="header-inner">
        <a href="/" class="logo">
          <div class="logo-icon"><i class="fas fa-car-side"></i></div>
          <div>
            <div class="logo-text">MTSK DEKONT SİSTEMİ</div>
             <div class="logo-sub">Yönetici Paneli <span style="opacity:0.7;font-size:11px;margin-left:8px;">v9.1</span></div>
          </div>
        </a>
        <div style="display:flex;align-items:center;gap:16px;">
          <nav class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> <span>Ana Sayfa</span></a>
            <a href="#" class="active" onclick="switchTab('dekontlar')"><i class="fas fa-file-invoice"></i> <span>Dekontlar</span></a>
            <a href="#" onclick="switchTab('kurslar')"><i class="fas fa-graduation-cap"></i> <span>Kurslar</span></a>
            <a href="#" onclick="switchTab('kullanicilar')"><i class="fas fa-users"></i> <span>Kullanıcılar</span></a>
            <a href="#" onclick="switchTab('ayarlar')"><i class="fas fa-cog"></i> <span>Ayarlar</span></a>
          </nav>
          <div class="user-badge" style="background:var(--gray-100); padding:6px 14px; border-radius:50px; display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--text); border:1px solid var(--gray-200);">
            <i class="fas fa-user-circle" style="color:var(--accent); font-size:16px;"></i>
            <span class="name"><?php echo htmlspecialchars($ad_soyad); ?></span>
            <span style="color:var(--gray-300);">|</span>
            <a href="login.php?logout=1" style="text-decoration:none; color:var(--red); cursor:pointer;"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
          </div>
        </div>
      </div>
    </header>

    <main class="main">
      <!-- DEKONTLAR TAB -->
      <div id="tab-dekontlar" class="tab-panel active">
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon i1"><i class="fas fa-file-invoice"></i></div>
            <div><div class="stat-num" id="sDekont">0</div><div class="stat-label">Dekont</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon i2"><i class="fas fa-user-graduate"></i></div>
            <div><div class="stat-num" id="sKursiyer">0</div><div class="stat-label">Toplam Kursiyer</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon i3"><i class="fas fa-lira-sign"></i></div>
            <div><div class="stat-num" id="sTutar">0</div><div class="stat-label">Toplam TL</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon i4"><i class="fas fa-users"></i></div>
            <div><div class="stat-num" id="sKullanici">0</div><div class="stat-label">Kurum Sayısı</div></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-list"></i> Tüm Dekontlar</div>
            <div class="actions-bar">
              <button class="btn btn-green" onclick="downloadExcel()"><i class="fas fa-file-excel"></i> Excel Indir</button>
              <button class="btn btn-blue" onclick="downloadMergedPdf()"><i class="fas fa-file-pdf"></i> Tüm Dekontları Tek PDF Yap</button>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th><th>Kurum Adı</th><th>Kurslar</th><th>Kursiyer</th>
                  <th>Tutar</th><th>Dekont No</th><th>Tarih</th><th>PDF</th><th>İşlem</th>
                </tr>
              </thead>
              <tbody id="dekontTable"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- KURSLAR TAB -->
      <div id="tab-kurslar" class="tab-panel">
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-graduation-cap"></i> Kurs Yönetimi</div>
          </div>
          <div class="add-row">
            <input type="text" id="yeniKurs" placeholder="Yeni kurs adi giriniz..." onkeypress="if(event.key==='Enter')addKurs()">
            <button class="btn btn-accent" onclick="addKurs()"><i class="fas fa-plus"></i> Kurs Ekle</button>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>#</th><th>Kurs Adi</th><th style="width:100px;">Islem</th></tr></thead>
              <tbody id="kursTable"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- AYARLAR TAB -->
      <div id="tab-ayarlar" class="tab-panel">
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-cog"></i> Sistem Ayarları</div>
          </div>
          <div class="setting-row">
            <div class="field">
              <label><i class="fas fa-lira-sign"></i> Kursiyer Ucreti (TL)</label>
              <input type="number" id="kursiyerUcreti" placeholder="2000">
            </div>
            <button class="btn btn-accent" onclick="saveUcret()"><i class="fas fa-save"></i> Kaydet</button>
          </div>
          <div class="setting-row">
            <div class="field">
              <label><i class="fas fa-key"></i> Yonetici Sifresi</label>
              <input type="password" id="yeniSifre" placeholder="Yeni sifre giriniz...">
            </div>
            <button class="btn btn-accent" onclick="saveSifre()"><i class="fas fa-save"></i> Degistir</button>
          </div>
          <div class="setting-row">
            <div class="field" style="align-items: flex-start;">
              <label style="margin-top: 10px;"><i class="fas fa-bullhorn"></i> Duyuru Metni</label>
              <textarea id="duyuruMetni" placeholder="Örn: Dekontlarınızı cuma gününe kadar ekleyiniz." rows="4" style="width:100%; max-width: 400px; padding:10px; border:1.5px solid var(--gray-200); border-radius:var(--radius-sm); font-size:13px; font-family:inherit; resize:vertical;"></textarea>
            </div>
            <button class="btn btn-accent" onclick="saveAyar('duyuru_metni', 'duyuruMetni')"><i class="fas fa-save"></i> Kaydet</button>
          </div>
          <div class="setting-row">
            <div class="field">
              <label><i class="fas fa-university"></i> Geçerli IBAN'lar (Virgülle Ayırın)</label>
              <input type="text" id="gecerliIban" placeholder="TR28.., TR32.. (Çoklu eklenebilir)">
            </div>
            <button class="btn btn-accent" onclick="saveAyar('gecerli_iban', 'gecerliIban')"><i class="fas fa-save"></i> Kaydet</button>
          </div>
          <div class="setting-row">
            <div class="field">
              <label><i class="fas fa-calendar-minus"></i> Geriye Dönük Dekont Süresi (Gün)</label>
              <input type="number" id="gecmisGunSiniri" min="1" max="365" placeholder="Örn: 10" style="max-width:160px;">
            </div>
            <button class="btn btn-accent" onclick="saveAyar('gecmis_gun_siniri', 'gecmisGunSiniri')"><i class="fas fa-save"></i> Kaydet</button>
          </div>
          <p style="font-size:12px;color:var(--gray-400);margin-top:-10px;padding:0 4px;">
            <i class="fas fa-info-circle"></i> Kullanıcılar bu kadar gün öncesine ait dekont yükleyebilir. Varsayılan: <b>10 gün</b>.
          </p>
        </div>
      </div>
      <!-- KULLANICILAR TAB -->
      <div id="tab-kullanicilar" class="tab-panel">
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-users"></i> Kullanıcı Yönetimi</div>
          </div>
          
          <!-- Excel Toplu Ekleme -->
          <div style="display:flex; justify-content:space-between; margin-bottom: 20px; border-bottom: 1px solid var(--gray-200); padding-bottom: 15px;">
            <div style="display:flex; gap:10px; align-items:center;">
              <input type="file" id="excelKullaniciFile" accept=".xlsx, .xls, .csv" style="font-size:13px; padding:5px; border:1px solid var(--gray-200); border-radius:4px;">
              <button class="btn btn-blue" onclick="importExcel()" id="btnImportExcel"><i class="fas fa-file-excel"></i> Excel'den Toplu Ekle</button>
            </div>
            <div style="font-size:12px; color:var(--gray-500); line-height:1.4;">
              <b>Şablon:</b><br>
              A Sütunu: Ad Soyad<br>
              B Sütunu: Kullanıcı Adı<br>
              C Sütunu: Şifre (En az 8 karakter)
            </div>
          </div>

          <!-- Kullanici Ekleme -->
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;margin-bottom:20px;align-items:end;">
            <div>
              <label style="display:block;margin-bottom:4px;font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Ad Soyad</label>
              <input type="text" id="yeniAdSoyad" placeholder="Ad Soyad" style="width:100%;padding:10px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);font-size:13px;font-family:inherit;background:var(--gray-50);">
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Kullanici Adi</label>
              <input type="text" id="yeniKullaniciAdi" placeholder="Kullanici adi" style="width:100%;padding:10px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);font-size:13px;font-family:inherit;background:var(--gray-50);">
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Sifre</label>
              <input type="text" id="yeniKullaniciSifre" placeholder="Sifre" style="width:100%;padding:10px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);font-size:13px;font-family:inherit;background:var(--gray-50);">
            </div>
            <button class="btn btn-accent" onclick="addKullanici()"><i class="fas fa-plus"></i> Ekle</button>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>#</th><th>Ad Soyad</th><th>Kullanıcı Adı</th><th>Rol</th><th>Durum</th><th>İşlem</th></tr>
              </thead>
              <tbody id="kullaniciTable"></tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>

  <!-- Delete Modal -->
  <div class="modal-bg" id="deleteModal">
    <div class="modal-box">
      <div class="modal-icon"><i class="fas fa-trash-alt"></i></div>
      <h3>Silme Onayi</h3>
      <p>Bu kaydi kalici olarak silmek istediginizden emin misiniz?</p>
      <div class="modal-actions">
        <button class="btn btn-outline" onclick="closeModal()">Vazgec</button>
        <button class="btn btn-red" style="background:var(--red);color:white;" onclick="confirmDelete()"><i class="fas fa-trash"></i> Sil</button>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal-bg edit-modal" id="editModal">
    <div class="modal-box">
      <div class="modal-icon"><i class="fas fa-edit"></i></div>
      <h3>Dekont Bilgilerini Duzenle</h3>
      <p style="margin-bottom:20px;">Tarih ve dekont numarasini degistirebilirsiniz.</p>
      <input type="hidden" id="editDekontId">
      <div class="edit-field">
        <label>Dekont Tarihi</label>
        <input type="date" id="editTarih">
      </div>
      <div class="edit-field">
        <label>Dekont Numarasi</label>
        <input type="text" id="editNumara" placeholder="Dekont numarasi">
      </div>
      <div id="editError" style="color:var(--red);font-size:13px;font-weight:500;margin-bottom:12px;display:none;"></div>
      <div class="modal-actions">
        <button class="btn btn-outline" onclick="closeEditModal()">Vazgec</button>
        <button class="btn btn-amber" onclick="saveEdit()"><i class="fas fa-save"></i> Kaydet</button>
      </div>
    </div>
  </div>

  <script>
    let deleteCallback = null;
    let currentUser = { id: <?php echo $_SESSION['user_id']; ?>, rol: '<?php echo $_SESSION['rol']; ?>', ad_soyad: 'Admin' };

    function loadAll() { loadDekontlar(); loadKurslar(); loadAyarlar(); loadKullanicilar(); }

    async function loadDekontlar() {
      const data = await fetch(`${API_CONFIG.url('dekontlar')}?kullanici_id=${currentUser.id}&rol=${currentUser.rol}`).then(r => r.json());
      const tbody = document.getElementById('dekontTable');
      tbody.innerHTML = '';
      let tT = 0, tK = 0; const ms = new Set();

      data.forEach((d, i) => {
        const ks = d.kurslar || [];
        const topK = ks.reduce((s, k) => s + k.kursiyer_sayisi, 0);
        tT += d.dekont_tutari; tK += topK; ms.add(d.ad_soyad);
        const kt = ks.filter(k => k.kursiyer_sayisi > 0).map(k => `${k.kurs_adi}: ${k.kursiyer_sayisi}`).join(', ');

        tbody.innerHTML += `<tr>
          <td>${i+1}</td>
          <td class="td-bold">${d.ad_soyad || '-'}</td>
          <td><span class="tag tag-amber">${kt||'-'}</span></td>
          <td><span class="tag tag-green">${topK}</span></td>
          <td class="td-bold">${d.dekont_tutari.toLocaleString('tr-TR')} TL</td>
          <td>${d.dekont_numarasi}</td>
          <td>${d.dekont_tarihi.split('-').reverse().join('/')}</td>
          <td><a href="uploads/${d.dekont_dosya}" target="_blank" class="td-link"><i class="fas fa-external-link-alt"></i></a></td>
          <td style="display:flex;gap:4px;">
            <button class="btn btn-amber" style="padding:6px 10px;" onclick="openEdit(${d.id},'${d.dekont_tarihi}','${d.dekont_numarasi}')"><i class="fas fa-edit"></i></button>
            <button class="btn btn-red" onclick="showModal('dekont',${d.id})"><i class="fas fa-trash"></i></button>
          </td>
        </tr>`;
      });

      document.getElementById('sDekont').textContent = data.length;
      document.getElementById('sKursiyer').textContent = tK;
      document.getElementById('sTutar').textContent = tT.toLocaleString('tr-TR');
      document.getElementById('sKullanici').textContent = ms.size;
    }

    async function loadKurslar() {
      const data = await fetch(API_CONFIG.url('kurslar')).then(r => r.json());
      const tbody = document.getElementById('kursTable');
      tbody.innerHTML = '';
      data.forEach((k, i) => {
        tbody.innerHTML += `<tr>
          <td>${i+1}</td><td>${k.kurs_adi}</td>
          <td><button class="btn btn-red" onclick="showModal('kurs',${k.id})"><i class="fas fa-trash"></i></button></td>
        </tr>`;
      });
    }

    async function loadAyarlar() {
      const data = await fetch(API_CONFIG.url('ayarlar')).then(r => r.json());
      document.getElementById('kursiyerUcreti').value = data.kursiyer_ucreti || 2000;
      if (document.getElementById('duyuruMetni')) document.getElementById('duyuruMetni').value = data.duyuru_metni || '';
      if (document.getElementById('sonTarih')) document.getElementById('sonTarih').value = data.son_tarih || '';
      if (document.getElementById('gecerliIban')) document.getElementById('gecerliIban').value = data.gecerli_iban || '';
      if (document.getElementById('gecmisGunSiniri')) document.getElementById('gecmisGunSiniri').value = data.gecmis_gun_siniri || 10;
    }

    async function loadKullanicilar() {
      const data = await fetch(API_CONFIG.url('kullanicilar')).then(r => r.json());
      const tbody = document.getElementById('kullaniciTable');
      tbody.innerHTML = '';
      data.forEach((u, i) => {
        const rolTag = u.rol === 'admin'
          ? '<span class="tag" style="background:#ede9fe;color:#6d28d9;">Yonetici</span>'
          : '<span class="tag tag-amber">Kullanici</span>';
        const durumTag = u.aktif
          ? '<span class="tag tag-green">Aktif</span>'
          : '<span class="tag" style="background:var(--red-light);color:var(--red);">Pasif</span>';
        tbody.innerHTML += `<tr>
          <td>${i+1}</td>
          <td class="td-bold">${u.ad_soyad}</td>
          <td>${u.kullanici_adi}</td>
          <td>${rolTag}</td>
          <td>${durumTag}</td>
          <td style="display:flex;gap:4px;">
            ${u.aktif
              ? `<button class="btn btn-outline" style="padding:6px 10px;font-size:11px;" onclick="toggleKullanici(${u.id},false)"><i class="fas fa-ban"></i> Pasif</button>`
              : `<button class="btn btn-green" style="padding:6px 10px;font-size:11px;" onclick="toggleKullanici(${u.id},true)"><i class="fas fa-check"></i> Aktif</button>`
            }
            ${u.id !== 1 ? `<button class="btn btn-red" onclick="showModal('kullanici',${u.id})"><i class="fas fa-trash"></i></button>` : ''}
          </td>
        </tr>`;
      });
    }

    async function addKurs() {
      const v = document.getElementById('yeniKurs').value.trim();
      if (!v) return;
      const r = await fetch(API_CONFIG.url('kurslar'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({kurs_adi:v}) });
      if (r.ok) { document.getElementById('yeniKurs').value = ''; loadKurslar(); }
      else { const d = await r.json(); alert(d.error); }
    }

    async function saveUcret() {
      await fetch(API_CONFIG.url('ayarlar'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({anahtar:'kursiyer_ucreti', deger:document.getElementById('kursiyerUcreti').value}) });
      alert('Kursiyer ucreti guncellendi!');
    }

    async function saveAyar(anahtar, inputId) {
      const v = document.getElementById(inputId).value;
      await fetch(API_CONFIG.url('ayarlar'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({anahtar: anahtar, deger: v}) });
      alert('Ayar başarıyla kaydedildi!');
    }

    async function saveSifre() {
      const v = document.getElementById('yeniSifre').value;
      if (!v || v.length < 8) return alert('Güvenliğiniz için şifre en az 8 karakter olmalıdır!');
      await fetch(`${API_CONFIG.url('kullanicilar')}/${currentUser.id}`, { 
        method:'PUT', 
        headers:{'Content-Type':'application/json'}, 
        body: JSON.stringify({sifre: v, ad_soyad: currentUser.ad_soyad, rol: currentUser.rol}) 
      });
      document.getElementById('yeniSifre').value = '';
      alert('Yonetici sifresi başarıyla guncellendi!');
    }

    function showModal(type, id) {
      document.getElementById('deleteModal').classList.add('show');
      deleteCallback = async () => {
        const urls = { dekont:`${API_CONFIG.url('dekont')}/${id}`, kurs:`${API_CONFIG.url('kurslar')}/${id}`, kullanici:`${API_CONFIG.url('kullanicilar')}/${id}` };
        await fetch(urls[type], { method:'DELETE' });
        closeModal(); loadAll();
      };
    }
    function closeModal() { document.getElementById('deleteModal').classList.remove('show'); deleteCallback = null; }
    function confirmDelete() { if (deleteCallback) deleteCallback(); }

    function openEdit(id, tarih, numara) {
      document.getElementById('editDekontId').value = id;
      document.getElementById('editTarih').value = tarih; // Input type date expects YYYY-MM-DD
      document.getElementById('editNumara').value = numara;
      document.getElementById('editError').style.display = 'none';
      document.getElementById('editModal').classList.add('show');
    }
    function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

    async function saveEdit() {
      const id = document.getElementById('editDekontId').value;
      const tarih = document.getElementById('editTarih').value;
      const numara = document.getElementById('editNumara').value;
      const r = await fetch(`${API_CONFIG.url('dekont')}/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ dekont_tarihi: tarih, dekont_numarasi: numara })
      });
      if (r.ok) {
        closeEditModal();
        loadDekontlar();
      } else {
        const d = await r.json();
        const err = document.getElementById('editError');
        err.textContent = d.error;
        err.style.display = 'block';
      }
    }

    async function addKullanici() {
      const ad = document.getElementById('yeniAdSoyad').value.trim();
      const kadi = document.getElementById('yeniKullaniciAdi').value.trim();
      const sifre = document.getElementById('yeniKullaniciSifre').value.trim();
      if (!ad || !kadi || !sifre) return alert('Tum alanlari doldurunuz!');
      if (sifre.length < 8) return alert('Şifre en az 8 karakter olmalıdır!');

      const r = await fetch(API_CONFIG.url('kullanicilar'), {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ kullanici_adi: kadi, sifre: sifre, ad_soyad: ad, rol: 'kullanici' })
      });
      if (r.ok) {
        document.getElementById('yeniAdSoyad').value = '';
        document.getElementById('yeniKullaniciAdi').value = '';
        document.getElementById('yeniKullaniciSifre').value = '';
        loadKullanicilar();
      } else {
        const d = await r.json(); alert(d.error);
      }
    }

    async function importExcel() {
      const fileInput = document.getElementById('excelKullaniciFile');
      if (fileInput.files.length === 0) return alert('Lütfen bir Excel (.xlsx) dosyası seçin.');

      const file = fileInput.files[0];
      const reader = new FileReader();

      reader.onload = async function(e) {
        try {
          const data = new Uint8Array(e.target.result);
          const workbook = XLSX.read(data, { type: 'array' });
          if (!workbook.SheetNames || workbook.SheetNames.length === 0) {
            return alert('Excel dosyasında sayfa bulunamadı.');
          }
          const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
          const rows = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });

          if (!rows || rows.length < 2) return alert('Dosyada geçerli veri bulunamadı. Lütfen ilk satırda başlıklar, sonraki satırlarda veri olduğundan emin olun.');

          const usersToAdd = [];
          for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            if (!row || row.length < 3) continue;
            
            const ad = row[0]?.toString().trim();
            const kadi = row[1]?.toString().trim();
            const sifre = row[2]?.toString().trim();
            
            if (ad && kadi && sifre) {
               usersToAdd.push({ ad_soyad: ad, kullanici_adi: kadi, sifre: sifre });
            }
          }

          if (usersToAdd.length === 0) return alert('Geçerli kullanıcı bulunamadı (Ad, Kullanıcı Adı ve Şifre dolu olmalı).');
          
          if (!confirm(`Toplam ${usersToAdd.length} kullanıcı eklenecek. Onaylıyor musunuz?`)) return;

          const btn = document.getElementById('btnImportExcel');
          const oldText = btn.innerHTML;
          btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
          btn.disabled = true;

          const response = await fetch(API_CONFIG.url('kullanicilar/toplu'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(usersToAdd)
          });
          
          btn.innerHTML = oldText;
          btn.disabled = false;

          const text = await response.text();
          let result;
          try {
            result = JSON.parse(text);
          } catch(e) {
            console.error('JSON parse error:', text);
            return alert('Sunucudan geçersiz bir yanıt geldi (JSON bekleniyordu). Lütfen PHP hatalarını kontrol edin.');
          }
          
          if (response.ok) {
             alert(`Toplu ekleme tamamlandı! \n\nBaşarılı kayıt: ${result.basarili} \nHatalı veya zaten var olan: ${result.hatali}`);
             fileInput.value = '';
             loadKullanicilar();
          } else {
             alert('Hata: ' + result.error);
          }
        } catch(err) {
          alert('Excel okuma hatası: ' + err.message);
        }
      };
      reader.readAsArrayBuffer(file);
    }

    async function toggleKullanici(id, aktif) {
      await fetch(`${API_CONFIG.url('kullanicilar')}/${id}`, {
        method: 'PUT', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ aktif: aktif })
      });
      loadKullanicilar();
    }

    async function downloadExcel() {
      try {
        const data = await fetch(`${API_CONFIG.url('dekontlar')}?kullanici_id=${currentUser.id}&rol=${currentUser.rol}`).then(r => r.json());
        if (!data || data.length === 0) return alert('İndirilecek dekont verisi bulunamadı!');
        
        let basliklar = ["ID", "Kurum Adı", "Kurslar", "Toplam Kursiyer", "Dekont Tutarı (TL)", "Dekont Numarası", "Tarih"];
        let satirlar = data.map(d => {
          const ks = d.kurslar || [];
          const topK = ks.reduce((s, k) => s + k.kursiyer_sayisi, 0);
          const kt = ks.filter(k => k.kursiyer_sayisi > 0).map(k => `${k.kurs_adi}: ${k.kursiyer_sayisi}`).join(', ');
          return [d.id, d.ad_soyad, kt, topK, d.dekont_tutari, d.dekont_numarasi, d.dekont_tarihi.split('-').reverse().join('/')];
        });
        
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet([basliklar, ...satirlar]);
        
        // Sütun genişlikleri ayarı
        ws['!cols'] = [{wch:5}, {wch:35}, {wch:40}, {wch:15}, {wch:15}, {wch:20}, {wch:12}];
        
        XLSX.utils.book_append_sheet(wb, ws, "Dekontlar");
        XLSX.writeFile(wb, `Dekont_Raporu_${new Date().toISOString().slice(0,10)}.xlsx`);
      } catch (err) {
        alert('Excel oluşturulurken hata: ' + err.message);
      }
    }

    async function downloadMergedPdf() {
      try {
        const data = await fetch(`${API_CONFIG.url('dekontlar')}?kullanici_id=${currentUser.id}&rol=${currentUser.rol}`).then(r => r.json());
        if (!data || data.length === 0) return alert('Birleştirilecek dekont PDF\'i bulunamadı!');
        
        const btn = document.querySelector('button[onclick="downloadMergedPdf()"]');
        const oldText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Birleştiriliyor...';
        btn.disabled = true;
        
        const { PDFDocument } = PDFLib;
        const mergedPdf = await PDFDocument.create();
        let birlesmeGerekli = false;

        for (const d of data) {
          if (!d.dekont_dosya) continue;
          try {
            // Relative path used: uploads/filename
            const fetchRes = await fetch('../uploads/' + d.dekont_dosya);
            if (!fetchRes.ok) throw new Error('Dosya yüklenemedi: ' + d.dekont_dosya);
            const pdfBytes = await fetchRes.arrayBuffer();
            const srcPdf = await PDFDocument.load(pdfBytes);
            const copiedPages = await mergedPdf.copyPages(srcPdf, srcPdf.getPageIndices());
            copiedPages.forEach((page) => mergedPdf.addPage(page));
            birlesmeGerekli = true;
          } catch (err) {
            console.error('Birlestirme hatasi (' + d.dekont_dosya + '):', err);
          }
        }
        
        btn.innerHTML = oldText;
        btn.disabled = false;

        if (!birlesmeGerekli) return alert('Geçerli veya okunabilir PDF dosyası bulunamadı!');
        
        const mergedBytes = await mergedPdf.save();
        const blob = new Blob([mergedBytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Tum_Dekontlar_${new Date().toISOString().slice(0,10)}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
      } catch (err) {
        alert('Toplu PDF oluşturulurken hata: ' + err.message);
        btn.innerHTML = oldText;
        btn.disabled = false;
      }
    }

    function switchTab(tab) {
      document.querySelectorAll('.tab-panel').forEach(el => el.classList.remove('active'));
      document.getElementById('tab-' + tab).classList.add('active');
      document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
      if (event && event.target) event.target.closest('a').classList.add('active');
    }

    // Sayfa yüklendiğinde load All cagrilir
    window.onload = () => {
       loadAll();
    };
  </script>
</body>
</html>
