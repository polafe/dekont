CREATE TABLE IF NOT EXISTS kurslar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kurs_adi VARCHAR(255) NOT NULL UNIQUE,
    sira INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mtsk_listesi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mtsk_adi VARCHAR(255) NOT NULL UNIQUE,
    sira INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kullanicilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(100) NOT NULL UNIQUE,
    sifre VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(255) NOT NULL,
    rol VARCHAR(50) NOT NULL DEFAULT 'kullanici',
    aktif TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dekontlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dekont_tarihi DATE NOT NULL,
    dekont_numarasi VARCHAR(255) NOT NULL,
    dekont_tutari DOUBLE NOT NULL,
    dekont_dosya VARCHAR(500) NOT NULL,
    kullanici_id INT,
    cihaz_kimligi VARCHAR(128) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dekont_kurslar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dekont_id INT NOT NULL,
    kurs_adi VARCHAR(255) NOT NULL,
    kursiyer_sayisi INT NOT NULL DEFAULT 0,
    FOREIGN KEY (dekont_id) REFERENCES dekontlar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ayarlar (
    anahtar VARCHAR(100) PRIMARY KEY,
    deger TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO kurslar (id, kurs_adi, sira) VALUES 
(1, 'B Otomobil', 0), 
(2, 'A,A1,A2 Motosiklet', 1), 
(3, 'C Kamyon / CE Tır', 2), 
(4, 'D Otobüs D1 Minibüs', 3);

INSERT IGNORE INTO mtsk_listesi (id, mtsk_adi, sira) VALUES 
(1, 'BALIKLIGÖL MTSK', 0), (2, 'BAYRAMOĞLU MTSK', 1), (3, 'BİLBAN MTSK', 2),
(4, 'DİZAYN MTSK', 3), (5, 'EMİRHAN MTSK', 4), (6, 'FERHAT MTSK', 5),
(7, 'FIRATHAN 2 MTSK', 6), (8, 'HALİLİYE ÇAMLICA MTSK', 7), (9, 'HALİLİYE ÖZ DAMLA MTSK', 8),
(10, 'İMA MTSK', 9), (11, 'İPEKYOL MTSK', 10), (12, 'KAPTAN MTSK', 11),
(13, 'KIZILELMA MTSK', 12), (14, 'MERT MTSK', 13), (15, 'ÖZ BAHÇELİEVLER MTSK', 14),
(16, 'SARRAF MTSK', 15), (17, 'ŞANLI MTSK', 16), (18, 'ŞANLIURFA ÇAĞDAŞ MTSK', 17),
(19, 'ŞANLIURFA DOĞAN MTSK', 18), (20, 'ŞANLIURFA MTSK', 19), (21, 'ŞANLIURFA UMUT MTSK', 20),
(22, 'TUNA AKSOY MTSK', 21), (23, 'URFA ANADOLU MTSK', 22), (24, 'URFA HEDEF MTSK', 23),
(25, 'URFA KARDELEN MTSK', 24), (26, 'YALÇIN MTSK', 25), (27, 'YENİŞEHİR MTSK', 26),
(28, 'YILDIZTEKİN MTSK', 27);

INSERT IGNORE INTO ayarlar (anahtar, deger) VALUES ('kursiyer_ucreti', '2000');
INSERT IGNORE INTO ayarlar (anahtar, deger) VALUES ('gecmis_gun_siniri', '10');

-- Yönetici giriş bilgileri hash olarak saklanır.
INSERT IGNORE INTO kullanicilar (id, kullanici_adi, sifre, ad_soyad, rol) VALUES 
(1, 'dekont6363', '$2y$10$fjk43bloksGT4ClDibR92e/POHSL51NZgN4Ug13hnrGftIapZuvpm', 'Yonetici', 'admin');
