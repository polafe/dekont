const fs = require('fs');

const path = 'c:\\\\Users\\\\Aile\\\\Desktop\\\\dekont.wuaze.com\\\\index.php';
let content = fs.readFileSync(path, 'utf8');

const oldCode = `        const line2 = normalizeTurkish('Toplam Kursiyer: ' + toplamKursiyer)
                      + (kursDetay ? '  (' + kursDetay + ')' : '');

        const pages = pdfDoc.getPages();
        for (const page of pages) {
          const { width, height } = page.getSize();
          const fs1 = 20;           // kurum adı — 20 punto
          const fs2 = 14;           // kursiyer — biraz küçük ama yine kalın
          const lh  = 28;           // satır yüksekliği (büyük font için arttırıldı)
          const bY  = 60;           // sayfa altından mesafe (yukarı kaydırıldı)
          const boxH = lh * 2 + 14;`;

const newCode = `        // Birden fazla kurs turu varsa kurs_adi detayini da yanina alırız
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
          const bY  = 65;           // sayfa altından mesafe (yukarı kaydırıldı)
          const boxH = lh * 2 + 18;`;

// Normalize crlf
content = content.replace(/\r\n/g, '\n');
const fixedOld = oldCode.replace(/\r\n/g, '\n');

if (content.includes(fixedOld)) {
   content = content.replace(fixedOld, newCode);
   fs.writeFileSync(path, content, 'utf8');
   console.log('BASARILI');
} else {
   console.log('BULUNAMADI');
}
