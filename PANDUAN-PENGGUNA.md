# Panduan Pengguna HR Dashboard

Panduan langkah demi langkah untuk lima peran: **Karyawan**, **Supervisor**, **Manajemen**, **Admin HR**, dan **Admin Sistem**.
Bagian A berlaku untuk semua orang: memasang aplikasi autentikator (2FA) dan WireGuard (VPN). Bagian terakhir memuat alur
onboarding karyawan yang sudah ada, dari data sampai bisa masuk, dan penanganan masalah.

**Admin Sistem: isi dua alamat ini sekali sebelum panduan dibagikan** (cari dan ganti di seluruh berkas):

| Penanda | Isi dengan | Dipakai oleh |
|---|---|---|
| `[ALAMAT PUBLIK]` | alamat publik aplikasi, mis. `https://hr.contoh.id` | semua orang, tanpa VPN |
| `[ALAMAT VPN]` | alamat lewat VPN, mis. `https://hr-vpn.contoh.id` | Supervisor, Manajemen, Admin HR, Admin Sistem, setelah VPN menyala |

---

## Daftar isi

- [Ringkasan: siapa memakai apa](#ringkasan-siapa-memakai-apa)
- [Bagian A. Persiapan perangkat (semua peran)](#bagian-a-persiapan-perangkat-semua-peran)
- [Bagian 1. Karyawan](#bagian-1-karyawan)
- [Bagian 2. Supervisor](#bagian-2-supervisor)
- [Bagian 3. Manajemen](#bagian-3-manajemen)
- [Bagian 4. Admin HR](#bagian-4-admin-hr)
- [Bagian 5. Admin Sistem](#bagian-5-admin-sistem)
- [Bagian 6. Onboarding karyawan yang sudah ada: urutan lengkap](#bagian-6-onboarding-karyawan-yang-sudah-ada-urutan-lengkap)
- [Bagian 7. Masalah yang sering terjadi](#bagian-7-masalah-yang-sering-terjadi)
- [Lampiran. Pesan siap pakai](#lampiran-pesan-siap-pakai)

---

## Ringkasan: siapa memakai apa

Aplikasi punya **dua pintu masuk**:

- **Pintu publik** (`[ALAMAT PUBLIK]`): bisa dibuka dari mana saja, tanpa VPN. Isinya hanya halaman milik karyawan sendiri:
  Beranda, Pengajuan, Profil saya, Pengaturan, dan Notifikasi. **Pintu ini sama untuk semua orang**: Admin HR atau
  Manajemen yang masuk lewat sini juga hanya melihat halaman karyawan biasa.
- **Pintu VPN** (`[ALAMAT VPN]`): hanya bisa dibuka saat WireGuard menyala. Di sinilah pekerjaan di luar urusan pribadi
  dilakukan: menyetujui pengajuan, melihat direktori, rekrutmen, offboarding, absensi, dan panel admin.

| Peran | Pintu | WireGuard | Autentikator (2FA) | Cara masuk pertama kali |
|---|---|---|---|---|
| Karyawan | Publik | Tidak perlu | Dianjurkan | Daftar sendiri (NIK + ID karyawan), lalu diaktifkan HR |
| Supervisor | Publik untuk urusan pribadi, VPN untuk menyetujui | Perlu | **Wajib** | Daftar sendiri, lalu diaktifkan HR; peran diberikan Admin Sistem |
| Manajemen | VPN | Perlu | **Wajib** | Daftar sendiri atau akun dibuat Admin Sistem |
| Admin HR | VPN | Perlu | **Wajib** | Akun pertama dibuat Admin Sistem; berikutnya bisa daftar sendiri |
| Admin Sistem | VPN | Perlu | **Wajib** | Akun awal dibuat saat server disiapkan |

**Siapa yang disebut "wajib" 2FA:** siapa pun yang punya peran di atas Karyawan, atau punya bawahan, atau memimpin
departemen, atau ditunjuk menyetujui sesuatu. Seorang karyawan biasa yang kelak ditunjuk menjadi atasan juga menjadi
wajib. Setelah 2FA aktif, orang yang wajib tidak bisa mematikannya sendiri.

Sesi masuk berakhir setelah **8 jam tanpa aktivitas**, dan paling lama **24 jam** meski terus dipakai. Setelah itu masuk
lagi dengan kata sandi (dan kode 2FA).

---

## Bagian A. Persiapan perangkat (semua peran)

### A.1 Aplikasi autentikator dan 2FA

2FA artinya setiap masuk, selain kata sandi, Anda memasukkan **kode 6 digit** yang berubah tiap 30 detik dan hanya ada di
ponsel Anda.

**Pasang aplikasinya (sekali saja)**

1. Di ponsel, pasang **Google Authenticator** dari Play Store (Android) atau App Store (iPhone). Microsoft Authenticator
   atau Authy juga bisa.
2. Pastikan jam ponsel diatur **otomatis**. Jam yang melenceng membuat kode selalu ditolak.

**Aktifkan 2FA di akun Anda**

1. Masuk ke aplikasi (Bagian 1.2).
2. Buka **Pengaturan** > **Keamanan**. Bagi akun yang wajib 2FA, halaman ini terbuka sendiri dan semua menu lain terkunci
   sampai 2FA aktif.
3. Di bagian **Autentikasi dua langkah (2FA)**, klik **Aktifkan 2FA**. Konfirmasi kata sandi bila diminta.
4. Jendela **Aktifkan 2FA** muncul dengan kode QR. Di aplikasi autentikator: ketuk **+** (Tambah), pilih **Pindai kode
   QR**, lalu arahkan kamera ke layar.
   - Memakai ponsel yang sama untuk membuka aplikasi dan autentikator? Pilih **atau masukkan kode secara manual**, salin
     kunci pengaturannya, lalu tempel di autentikator ("Masukkan kunci pengaturan").
5. Klik **Lanjut**. Di jendela **Verifikasi kode**, ketik 6 digit yang tampil di autentikator, lalu klik **Konfirmasi**.
6. **Simpan kode pemulihan** yang tampil (foto layar atau catat, simpan di tempat aman). Kode ini dipakai bila ponsel
   hilang, dan setiap kode hanya berlaku sekali.

**Masuk dengan 2FA**

1. Isi NIK, ID karyawan, dan kata sandi, lalu **Masuk**.
2. Di layar **Kode autentikasi**, ketik 6 digit dari autentikator. Selesai.
3. Tidak punya ponsel? Klik **masuk dengan kode pemulihan** dan ketik salah satu kode pemulihan Anda.

**Ponsel hilang atau diganti dan tidak ada kode pemulihan:** hubungi Admin Sistem untuk **Atur ulang 2FA** (Bagian 5.7).
Setelah itu Anda masuk lagi dan memasang ulang dari langkah 1 di atas.

### A.2 WireGuard (VPN)

Hanya untuk Supervisor, Manajemen, Admin HR, dan Admin Sistem. Karyawan biasa tidak memerlukannya.

**Yang Anda terima dari Admin Sistem:** satu **kode QR** (untuk ponsel) atau satu **berkas `.conf`** (untuk laptop). Itu
adalah kunci akses pribadi Anda: jangan dibagikan, jangan dikirim ke grup chat. Satu perangkat, satu kunci. Bila
punya ponsel dan laptop, minta dua kunci.

**Ponsel (Android atau iPhone)**

1. Pasang aplikasi **WireGuard** dari Play Store atau App Store.
2. Buka aplikasinya, ketuk **+**, pilih **Pindai dari kode QR**, arahkan ke kode QR dari Admin Sistem.
3. Beri nama (mis. nama Anda) dan izinkan penambahan konfigurasi VPN bila ponsel meminta.
4. Geser tombol di samping nama itu sampai **menyala**. Ikon kunci atau VPN muncul di bilah status.
5. Buka peramban dan masuk ke `[ALAMAT VPN]`.

**Laptop (Windows atau Mac)**

1. Unduh aplikasi WireGuard dari `wireguard.com/install`, lalu pasang.
2. Klik **Import tunnel(s) from file** dan pilih berkas `.conf` dari Admin Sistem.
3. Klik **Activate**. Bila berhasil, status berubah menjadi **Active** dan **Latest handshake** menampilkan waktu.
4. Buka peramban dan masuk ke `[ALAMAT VPN]`.

**Kebiasaan yang benar**

- Nyalakan VPN hanya saat mengerjakan hal yang butuh VPN (menyetujui, direktori, rekrutmen, panel admin), lalu matikan.
- VPN hanya membuka aplikasi HR. Internet lainnya tetap lewat jaringan biasa.
- Ponsel atau laptop hilang? Segera hubungi Admin Sistem agar kuncinya dihapus.
- Status **Active** tetapi halaman tidak terbuka dan **Latest handshake** kosong: kunci atau alamat server salah. Hubungi
  Admin Sistem (lihat Bagian 7).

### A.3 Memasang aplikasi ke layar utama dan menyalakan notifikasi

Aplikasi ini bisa dipasang seperti aplikasi biasa, dan memberi notifikasi di layar ponsel saat ada pengajuan atau
keputusan.

- **Android (Chrome):** buka alamat aplikasi, ketuk menu **⋮**, pilih **Instal aplikasi** (atau **Tambahkan ke layar
  utama**).
- **iPhone dan iPad (Safari):** buka alamat aplikasi, ketuk **Bagikan**, pilih **Tambah ke Layar Utama**, lalu buka
  aplikasi **dari ikon itu** (bukan dari Safari).
- Alamat publik dan alamat VPN adalah **dua aplikasi terpisah**, dua ikon. Karyawan biasa cukup yang publik. Yang
  memakai VPN memasang keduanya bila perlu.

**Menyalakan notifikasi:** masuk, buka **Pengaturan** > **Notifikasi**, lalu aktifkan **Notifikasi di perangkat ini** dan
izinkan saat peramban meminta. Setiap perangkat diaktifkan sendiri-sendiri. Gunakan tombol uji bila ada. Semua
pemberitahuan tetap tersimpan di halaman **Notifikasi** meski notifikasi perangkat mati. Di iPhone, notifikasi hanya
bisa diaktifkan **setelah** aplikasi dipasang ke layar utama.

---

## Bagian 1. Karyawan

### 1.1 Mendaftar untuk pertama kali

Anda tidak membuat profil sendiri. **HR sudah mendaftarkan data Anda**; Anda hanya "mengklaim" akunnya dengan NIK dan ID
karyawan yang sama persis dengan data itu.

**Yang perlu disiapkan:** NIK 16 digit Anda dan **ID karyawan** (nomor induk) dari HR. Tidak perlu VPN.

1. Buka `[ALAMAT PUBLIK]`, klik **Daftar**.
2. Isi **NIK** (16 digit, angka saja) dan **ID karyawan**.
3. Pilih **Kata sandi**, lalu ulangi di **Ulangi kata sandi**. Syaratnya: minimal **12 karakter**, mengandung huruf besar
   dan kecil, angka, dan simbol, serta bukan kata sandi yang pernah bocor di internet. Contoh pola: tiga kata acak
   ditambah angka dan simbol.
4. **Email** boleh dikosongkan. Email tidak dipakai untuk masuk.
5. Klik **Daftar**.

**Setelah mendaftar:** akun Anda berstatus **menunggu aktivasi HR**. Anda belum bisa masuk. HR mendapat pemberitahuan dan
akan memeriksa lalu mengaktifkannya. Bila sudah diaktifkan, Anda bisa masuk (1.2). Tanyakan ke HR bila belum aktif
setelah satu hari kerja.

**Pesan "Data tidak cocok atau akun sudah terdaftar. Hubungi HR.":** aplikasi sengaja memberi pesan yang sama untuk semua
penyebab: NIK atau ID karyawan tidak sama dengan data HR, akun sudah pernah didaftarkan, atau profil Anda berstatus
sudah keluar. Jangan coba berulang kali. Hubungi HR, minta mereka memeriksa NIK dan ID karyawan di profil Anda.

### 1.2 Masuk

1. Buka `[ALAMAT PUBLIK]`.
2. Isi **NIK**, **ID karyawan**, dan **Kata sandi**, lalu klik **Masuk**.
3. Jika 2FA aktif, ketik kode 6 digit dari autentikator (A.1).

Centang **Ingat NIK dan ID karyawan di perangkat ini** bila perangkatnya milik pribadi. Hanya NIK dan ID yang diingat;
kata sandi selalu diketik. Jangan dicentang di komputer bersama.

Salah kata sandi berkali-kali menutup akses sementara (batas 5 percobaan per menit). Tunggu semenit, lalu coba lagi dengan
teliti.

**Lupa kata sandi:** hubungi HR. Tidak ada tombol "lupa kata sandi" lewat email. HR meminta Admin Sistem membuat **kata
sandi sementara**. Saat Anda masuk dengan kata sandi itu, aplikasi langsung meminta Anda membuat kata sandi baru.

### 1.3 Mengaktifkan 2FA (dianjurkan)

Ikuti A.1. Untuk karyawan biasa 2FA tidak wajib, tetapi sangat dianjurkan karena akun ini memuat data pribadi.

### 1.4 Beranda

**Beranda** adalah halaman awal dengan ringkasan dan pintasan (mis. untuk mengajukan cuti). Menu samping (atau bilah bawah
di ponsel) berisi **Beranda** dan **Pengajuan**; **Pengaturan** dan **Notifikasi** ada di pojok akun dan lonceng. Isi menu
bertambah sesuai hak Anda.

### 1.5 Mengajukan cuti, izin, atau sakit

1. Buka **Pengajuan** > **Ajukan** (atau pintasan di Beranda).
2. **Karyawan:** terisi nama Anda (Anda hanya dapat mengajukan untuk diri sendiri).
3. Pilih **Jenis pengajuan**. Di bawahnya tertulis batas maksimal hari kerja per pengajuan bila ada.
4. Isi **Hari pertama** dan **Hari terakhir**. Untuk izin beberapa jam, isi juga bagian jam yang tersedia di bawah tanggal.
5. Isi **Catatan** (alasan).
6. **Lampiran** (opsional; **wajib untuk Sakit**): PDF, JPG, atau PNG, maksimal 5 berkas @ 5 MB. Contoh: surat dokter.
7. Klik **Kirim pengajuan**.

**Aturan yang perlu diketahui**

- Hanya **hari kerja** yang dihitung. Akhir pekan dan hari libur tidak termasuk.
- Tanggal yang sudah lewat **tidak boleh**, kecuali jenis itu punya aturan "boleh mundur" (tertera di bawah kolom tanggal).
- Jika pengajuan bentrok dengan pengajuan Anda yang lain, formulir menampilkan peringatan langsung. Pengajuan yang
  bentrok ditolak.
- **Sakit:** diisi untuk hari yang sudah lewat, saat Anda sudah ditandai **Alpa**. Pengajuan sakit dikirim ke **HR**, dan
  bila disetujui, Alpa pada hari itu berubah menjadi Sakit. Lampiran wajib.
- **Jenis lain (cuti, izin):** dikirim ke **atasan langsung** Anda untuk disetujui. HR dapat melihat dan diberi tahu, tetapi
  tidak menyetujui. Bila Anda belum punya atasan langsung di data, HR yang memutuskan.
- Tidak ada saldo cuti di aplikasi: yang tercatat adalah cuti yang sudah diambil.

### 1.6 Memantau pengajuan

1. Buka **Pengajuan**. Pilih filter **Semua**, **Menunggu**, **Disetujui**, atau **Ditolak**.
2. Klik sebuah baris untuk membuka rinciannya dan **Alur persetujuan** (siapa yang sedang ditunggu).
3. Keputusan muncul juga di **Notifikasi** dan, bila diaktifkan, di layar ponsel.

**Pengajuan ditolak:** buka, baca **alasan penolakan**, lalu klik **Ubah & ajukan ulang**, perbaiki, dan kirim lagi.

**Pengajuan yang masih menunggu atau sudah disetujui tidak bisa diubah** (supaya tidak berubah di depan penyetujunya).
Bila salah isi, hubungi atasan Anda (atau HR) agar menolaknya, lalu ajukan ulang.

### 1.7 Melihat dan mengubah data Anda

1. Buka **Profil saya**. Tab yang tersedia: **Ringkasan**, **Absensi** (bila modul absensi aktif), **Pengajuan**,
   **Dokumen**, dan **Tim** (bila Anda punya bawahan). Tab yang terlihat bergantung pada hak Anda.
2. Untuk mengubah data (alamat, telepon, kontak darurat, rekening, foto, dan sejenisnya), klik **Ajukan perubahan data**.
3. Ubah isian yang perlu, lalu kirim. **Data baru berlaku setelah HR menyetujui.**
4. Hanya satu permintaan boleh terbuka dalam satu waktu. Klik **Tarik permintaan** bila ingin membatalkan dan
   mengajukan yang baru.
5. Jika ditolak, alasannya tampil di halaman yang sama.

**Jabatan, atasan, gaji, dan data kepegawaian lain hanya HR yang dapat mengubah.**

### 1.8 Notifikasi

Ikon lonceng dan halaman **Notifikasi** memuat semua pemberitahuan: keputusan pengajuan, perubahan data, dan lainnya. Klik
sebuah pemberitahuan untuk langsung membuka halaman terkait. Ada tombol untuk menandai semua sudah dibaca. Untuk
notifikasi di layar ponsel, lihat A.3.

### 1.9 Pengaturan akun

Menu **Pengaturan** berisi:
- **Keamanan:** ubah kata sandi (isi kata sandi saat ini, lalu yang baru dua kali) dan kelola 2FA.
- **Notifikasi:** nyalakan notifikasi di perangkat ini.
- **Tampilan:** terang atau gelap.

### 1.10 Bila Anda berhenti bekerja

HR memproses offboarding. **Akun dinonaktifkan sehari setelah hari kerja terakhir** (supaya Anda bisa bekerja sampai hari
itu). Pengajuan Anda yang masih menunggu dibatalkan otomatis. Bila Anda kembali bekerja, HR dapat mengaktifkan
profil Anda lagi.

---

## Bagian 2. Supervisor

Supervisor adalah karyawan yang punya bawahan: orang-orang yang **atasan langsungnya** adalah Anda di data HR.
**Persetujuan pengajuan mengikuti atasan langsung di data**, bukan sekadar nama peran. Siapa pun yang ditetapkan HR sebagai
atasan langsung seseorang otomatis dapat menyetujui pengajuan orang itu dan melihat tim mereka.

### 2.1 Persiapan satu kali

1. **Daftar dan diaktifkan HR** seperti karyawan biasa (Bagian 1.1). Anda tetap memakai pintu publik untuk urusan pribadi.
2. Minta **Admin Sistem** memberikan peran **Supervisor** (bila belum) dan **membuatkan kunci WireGuard** untuk perangkat Anda.
3. Pasang **WireGuard** (A.2) dan **autentikator** (A.1).
4. Masuk. Karena akun Anda sekarang punya bawahan, aplikasi **mewajibkan 2FA**: Anda diarahkan ke **Pengaturan** >
   **Keamanan** untuk mengaktifkannya. Selesaikan, lalu lanjut.
5. Untuk pekerjaan supervisor, nyalakan VPN lalu buka `[ALAMAT VPN]`.

Di pintu publik Anda hanya melihat halaman karyawan biasa, lengkap dengan pemberitahuan bahwa pekerjaan lain
memerlukan VPN. Itu normal.

### 2.2 Menyetujui atau menolak pengajuan bawahan

1. Nyalakan VPN, buka `[ALAMAT VPN]`, masuk.
2. Menu **Pengajuan** menampilkan angka di sampingnya bila ada yang menunggu Anda. Pilih filter **Menunggu Anda**.
3. Klik baris pengajuan untuk melihat jenis, tanggal, jumlah hari, catatan, lampiran, dan alur persetujuan.
4. Klik **Setujui**, atau klik **Tolak**, isi **Alasan penolakan** (wajib), lalu **Tolak**.
5. Karyawan menerima pemberitahuan keputusan.

**Di ponsel:** pengajuan yang menunggu Anda disematkan di atas sebagai kartu. **Setujui** cukup satu ketukan. **Tolak**
membuka jendela alasan.

**Yang perlu diketahui**
- Anda hanya memutuskan pengajuan yang **menunggu Anda**, yaitu yang menyebut nama Anda sebagai penyetuju.
- Tombol **Ekspor** mengunduh daftar sesuai filter yang aktif.
- Pengajuan **sakit** diputuskan **HR**, bukan Anda.
- Anda tidak dapat mengajukan atas nama bawahan. Itu hak HR dan Manajemen.

### 2.3 Melihat tim Anda

**Direktori** menampilkan Anda dan seluruh bawahan Anda (bukan atasan atau rekan sejajar). Buka sebuah profil untuk
ringkasan, absensi, dan pengajuannya. Tab **Tim** menampilkan bagan bawahan langsung. Dokumen, rekening, gaji, dan data
kesehatan **tidak** terlihat oleh Supervisor.

### 2.4 Absensi tim (bila modul absensi aktif)

Anda diberi tahu **pagi berikutnya** bila ada masalah absensi pada bawahan (mis. tidak ada scan, scan saat cuti). Menu
**Kalender**, **Rekap absensi**, dan **Masalah absensi** muncul bila Anda berhak. Menandai masalah selesai atau
dimaafkan dilakukan HR.

### 2.5 Meminta karyawan baru (lowongan)

1. Buka **Rekrutmen** > **Ajukan Lowongan**. Tombol hanya muncul untuk supervisor atas departemen yang dipimpinnya.
2. **Langkah 1, Permintaan:** posisi, departemen, cabang (opsional), jumlah kuota, tipe kontrak (Kontrak dan Magang
   meminta lama dalam bulan), **atasan langsung**, target mulai kerja (hari ini atau lebih), dan alasan permintaan.
3. **Langkah 2, Isi lowongan dan checklist kandidat:** Tentang Posisi, Tanggung Jawab Utama (minimal satu subjudul dengan
   satu poin), Benefit & Fasilitas (boleh kosong), serta checklist kualifikasi dan skills (kandidat menjawab ya atau tidak
   per baris).
4. **Langkah 3, Review:** periksa, lalu **Kirim**. Draft bisa disimpan kapan saja dan hanya terlihat oleh Anda.
5. Setelah terkirim, lowongan menunggu **Manajemen**, lalu **HR**. Anda tidak memutuskan lowongan Anda sendiri. Bila
   ditolak, lowongan kembali menjadi draft dengan alasannya; perbaiki dan kirim lagi. Selama belum ada yang memutuskan,
   Anda masih dapat mengubahnya.

### 2.6 Menjadi atasan langsung dalam rekrutmen

- Anda dapat ikut **Interview** sebagai peserta dan mengisi **evaluasi** untuk kandidat posisi Anda.
- Setelah interview, **Anda memilih** kandidat yang Anda inginkan (tombol **Pilih** pada kandidat yang sudah
  diinterview). Pilihan Anda menjadi usulan penawaran yang diputuskan Manajemen. HR yang menolak (**Tolak**) atau memulihkan
  (**Pulihkan**) kandidat.

### 2.7 Offboarding bawahan

Anda melihat kasus offboarding bawahan Anda **setelah dikirim** HR (menu **Offboarding**). Anda tidak menyetujui atau
menandatanganinya. Bila Anda memimpin sebuah departemen yang punya item checklist (IT, Fasilitas, dan sejenisnya), Anda
mendapat daftar item yang menjadi tugas Anda: pilih yang berlaku, klik **Ajukan kunci** (HR yang mengunci), lalu tandai selesai (Bagian
4.8). Sebelum kasus ditutup, HR memindahkan bawahan orang yang keluar ke atasan baru.

---

## Bagian 3. Manajemen

Manajemen (direksi dan satu tingkat di bawahnya) melihat **seluruh karyawan perusahaannya** dan memutuskan hal-hal
besar. Peran ini tidak mengelola data atau dokumen: dokumen, rekening, gaji, dan data kesehatan **tidak** terlihat.

### 3.1 Persiapan satu kali

1. Akun dibuat dengan salah satu cara: (a) Anda **Daftar** seperti karyawan (Bagian 1.1) lalu diaktifkan HR, atau (b)
   **Admin Sistem membuatkan akun** (Bagian 5.4) dengan email dan kata sandi sementara. Setelah itu Admin Sistem
   memberikan peran **Manajemen**.
2. Admin Sistem membuatkan **kunci WireGuard**. Pasang **WireGuard** dan **autentikator** (A.2 dan A.1).
3. Masuk lewat `[ALAMAT VPN]` dengan VPN menyala. Akun yang dibuat Admin Sistem masuk lewat tautan **Masuk dengan email (akun
   administrator)**, memakai kata sandi sementara, lalu langsung diminta membuat kata sandi baru.
4. Aktifkan **2FA** (wajib), lalu lanjut.

### 3.2 Yang dapat Anda lakukan

| Tugas | Di mana | Catatan |
|---|---|---|
| Menyetujui lowongan baru | **Rekrutmen** | Anda mengisi **Ekspektasi Gaji** (rentang minimum dan maksimum) |
| Menyetujui penawaran kerja | **Rekrutmen** > lowongan > tab **Penawaran** | Anda menetapkan gaji awal |
| Menyetujui offboarding | **Offboarding** | Selalu Manajemen yang pertama menyetujui |
| Menyetujui pengajuan | **Pengajuan** | Hanya bila Anda atasan langsung orang itu |
| Mengajukan atas nama bawahan | **Pengajuan** > **Ajukan** | Hanya untuk bawahan Anda; boleh tanggal lampau |
| Ikut interview dan evaluasi | **Rekrutmen** > kandidat | Sebagai peserta yang dipilih HR |
| Melihat seluruh karyawan | **Direktori** | Seluruh perusahaan, tanpa dokumen dan data sensitif |

Semua yang menunggu Anda muncul sebagai angka di samping menu dan di kartu **Menunggu keputusan** di Beranda.

### 3.3 Menyetujui lowongan

1. Buka **Rekrutmen**, pilih lowongan berstatus **Diajukan**.
2. Periksa permintaan, isi lowongan, dan checklist kandidat.
3. Isi **Ekspektasi Gaji** (min-maks, dalam rupiah). Ini wajib untuk menyetujui dan hanya terlihat oleh Manajemen dan HR.
4. Klik **Setujui**, atau **Tolak** dengan alasan (wajib). Bila disetujui, giliran **HR** menyetujui.
5. Diskusi di lowongan (tombol komentar) memberi catatan ke pengaju dan HR.

### 3.4 Menyetujui penawaran kerja

1. Buka **Rekrutmen** > lowongan > tab **Penawaran**.
2. Tiap kandidat yang dipilih atasan langsung tampil sebagai usulan, lengkap dengan "Direkomendasikan oleh n pewawancara".
3. Tetapkan **gaji awal**, lalu **Setujui**. Atau **Tolak** dengan alasan: kandidat kembali ke daftar Interview.
4. HR lalu menyusun surat penawaran (Bagian 4.7).

### 3.5 Menyetujui offboarding

1. Buka **Offboarding**, pilih kasus yang menunggu Anda.
2. Periksa alasan, jenis keluar, dan hari kerja terakhir. Surat pengunduran diri wajib terlampir untuk jenis
   pengunduran diri.
3. Klik **Setujui**, atau **Tolak** dengan alasan (kasus kembali menjadi draft, alasannya tercatat di diskusi).
4. Setelah itu Anda tidak menandatangani lagi. Pekerjaan berlanjut di pemilik item checklist dan HR.

### 3.6 Mengajukan atas nama bawahan

**Pengajuan** > **Ajukan**, lalu pilih bawahan di **Karyawan**. Anda boleh memilih tanggal lampau (misalnya untuk menutup
data yang terlewat). Pengajuan yang Anda ajukan mengikuti alur persetujuan yang sama dengan jenisnya.

---

## Bagian 4. Admin HR

Admin HR memegang data karyawan, dokumen, pengajuan, perubahan data, rekrutmen, offboarding, dan absensi. Admin HR
**tidak** membuat akun, mengatur peran, atau membuat perusahaan dan cabang: itu pekerjaan Admin Sistem.

### 4.1 Persiapan satu kali

1. **Akun HR pertama** untuk setiap perusahaan dibuat Admin Sistem (Bagian 5.4). Anda menerima **email** dan **kata
   sandi sementara**.
2. Admin Sistem membuatkan **kunci WireGuard**. Pasang **WireGuard** dan **autentikator** (A.2 dan A.1).
3. Nyalakan VPN, buka `[ALAMAT VPN]`, klik **Masuk dengan email (akun administrator)**, isi email dan kata sandi
   sementara. Aplikasi langsung meminta **kata sandi baru**.
4. Aktifkan **2FA** (wajib).
5. Pilih perusahaan yang Anda kerjakan di **pemilih perusahaan** (bila akun Anda mencakup lebih dari satu).

Rekan HR berikutnya bisa **Daftar** seperti karyawan (lalu Admin Sistem memberikan peran **Admin HR**), atau dibuatkan akun.

### 4.2 Menambahkan karyawan yang sudah ada, dan membuat mereka bisa masuk

Ini alur terpenting saat go-live. Urutan lengkap ada di Bagian 6. Inti pekerjaan HR:

**Langkah 0. Pastikan organisasi sudah siap.**
Cabang, Departemen, dan Posisi yang akan dipakai harus sudah ada **sebelum** karyawan dimasukkan, sesuai ejaan nama. Cabang
dibuat Admin Sistem; Departemen dan Posisi bisa dibuat HR di **Panel admin** (menu **Departemen** dan **Posisi**). Impor
**tidak** membuat ketiganya otomatis.

**Langkah 1. Siapkan data dalam Excel.**
1. Buka **Direktori** > **Impor**.
2. Klik **Unduh templat**. Berkas berisi semua kolom dan sheet **Petunjuk** berisi pilihan isiannya.
3. Isi satu baris per karyawan. Kolom dikenali dari baris judul, urutannya bebas.

| Kolom | Wajib? | Keterangan |
|---|---|---|
| Nomor Induk Karyawan | **Wajib** | Unik. Inilah **ID karyawan** yang dipakai saat masuk |
| Nama Karyawan | **Wajib** | |
| No KTP | **Wajib** | **16 digit**. Inilah **NIK** yang dipakai saat mendaftar. Unik di seluruh grup |
| Nama Perusahaan | Dianjurkan | Harus sama dengan perusahaan yang sedang dipilih |
| Lokasi kerja (cabang) | Dianjurkan | Harus sudah ada. Kosong = satu-satunya cabang perusahaan; ditolak bila cabangnya lebih dari satu |
| Divisi (departemen), Posisi | Dianjurkan | Harus sudah ada dan sama ejaannya |
| Jabatan | Bebas | Teks bebas |
| Supervisor | **Sangat dianjurkan** | Nomor induk atau nama lengkap atasan langsung, dari karyawan yang sudah ada atau yang ada di berkas yang sama |
| Status Karyawan | Dianjurkan | Tetap / Kontrak / Percobaan / Magang |
| Zona | Opsional | Hijau / Merah / Hitam (kosong = Hijau) |
| Tanggal | | Format `2026-12-31` atau `31/12/2026` (hari dulu) |

Tanpa **Supervisor**, pengajuan karyawan itu jatuh ke HR untuk diputuskan, jadi isi kolom ini dengan benar. Kolom sensitif
(rekening, NPWP, KK, nama ibu, PTKP, BPJS, asuransi) ikut terbaca hanya bila Anda berhak mengelolanya. **Gaji dan data
kesehatan tidak diimpor.**

**Langkah 2. Pilih perusahaan lalu periksa dulu.**
1. Pilih perusahaan di **pemilih perusahaan** (impor menolak bila belum ada perusahaan terpilih).
2. Unggah berkas (.xlsx, .xls, atau .csv, maksimal 2 MB) dan **centang "Hanya periksa, jangan simpan"**.
3. Klik **Impor**. Baca **Hasil pemeriksaan**: jumlah baris dibaca, akan ditambahkan, duplikat dilewati, dan **ditolak**,
   beserta daftar kesalahan per baris.
4. Perbaiki berkasnya sampai tidak ada baris ditolak, ulangi pemeriksaan.
5. Bila berkas terlalu besar atau melewati batas baris per impor, bagi menjadi beberapa berkas.

**Langkah 3. Impor sungguhan.**
Unggah berkas yang sama **tanpa** mencentang "Hanya periksa". Baris yang nomor indunknya sudah ada **dilewati**, tidak
ditimpa, jadi aman bila Anda mengulang. Untuk mengoreksi karyawan yang sudah masuk, buka profilnya dan klik **Ubah data**.

**Langkah 3b. Alternatif: menambah satu per satu.**
**Direktori** > **Tambah karyawan**. Isi bagian **Kepegawaian** (ID karyawan, Nama lengkap, Cabang, Departemen, Posisi,
Jabatan, **Atasan langsung**, Status, Zona, Tanggal bergabung), **Data pribadi**, **Kontak** (isi **NIK** 16 digit:
"dipakai karyawan untuk mendaftar"), alamat, dan bila berhak, keluarga, bank dan pajak, kesehatan, serta penggajian.
Klik simpan.

**Langkah 4. Periksa sebelum mengundang.**
Buka beberapa profil. Pastikan **ID karyawan** dan **NIK** benar (karyawan akan mengetiknya persis), dan **Atasan langsung**
terisi. NIK dan ID adalah "kunci" pendaftaran: salah satu huruf saja membuat pendaftaran gagal.

**Langkah 5. Minta karyawan mendaftar.**
Kirim pesan pengumuman (contoh di Lampiran) berisi `[ALAMAT PUBLIK]`, cara **Daftar**, serta pengingat bahwa **NIK dan ID
karyawan** harus sama dengan data yang Anda masukkan. Sampaikan ID karyawan masing-masing lewat jalur yang aman (mis.
pribadi atau slip), bukan di grup terbuka.

**Langkah 6. Mengaktifkan akun yang mendaftar.**
Setiap pendaftaran menaikkan pemberitahuan **Pendaftaran akun menunggu aktivasi** kepada HR perusahaan itu (lonceng dan
layar ponsel).
1. Klik pemberitahuan, atau buka profil karyawan di **Direktori**. Di bagian atas profil muncul kotak kuning:
   "... mendaftarkan akun dengan NIK dan ID karyawan ini, dan belum bisa masuk".
2. **Pastikan benar orangnya.** NIK dan ID bukan rahasia, jadi orang lain bisa saja mencoba mendaftar dengan data itu.
   Cocokkan dengan karyawannya (mis. tanyakan langsung bahwa memang dia yang baru mendaftar).
3. Klik **Aktifkan akun** bila benar. Karyawan bisa masuk saat itu juga.
4. Klik **Tolak** bila bukan orangnya. Pendaftaran dihapus dan profil bisa **didaftarkan ulang** oleh orang yang tepat.
5. Aktivasi dan penolakan tercatat di catatan aktivitas.

**Langkah 7. Atasan, Manajemen, dan HR lain.**
Setelah akun mereka aktif, minta **Admin Sistem** memberikan peran yang sesuai dan membuatkan kunci WireGuard (Bagian 5.5 dan
5.6). Mereka wajib memasang autentikator dan WireGuard sebelum bekerja sebagai penyetuju.

**Langkah 8. Pantau.**
Di **Direktori**, cek siapa yang belum mendaftar dan ingatkan. Kasus yang sering muncul ada di Bagian 7.

### 4.3 Mengelola data karyawan

- **Ubah data:** buka profil > **Ubah data**. Terkunci bila karyawan sudah keluar (gunakan **Aktifkan kembali**).
- **Dokumen:** tab **Dokumen** di profil: unggah dokumen, tambahkan versi baru, dan unduh. Kartu **Dokumen segera berakhir**
  di Beranda mengingatkan masa berlaku yang mendekati batas.
- **Supervisor dengan tim lintas perusahaan:** bila anggota tim Anda bekerja di perusahaan lain, **pemilih perusahaan**
  menampilkan perusahaan Anda dan perusahaan tim Anda; **Semua perusahaan** muncul bila ada lebih dari satu. Anda hanya melihat
  anggota tim Anda di sana (profil tanpa data sensitif, daftar tim, dan pengajuan mereka), tanpa dokumen, absensi, gaji, atau
  hak mengubah. Berlaku hanya untuk peran **Supervisor** lewat pintu privat; karyawan biasa yang memiliki bawahan tetap di
  perusahaannya sendiri.
- **Formulir data induk:** setiap karyawan baru (lewat **Tambah karyawan** atau dari rekrutmen, bukan impor massal) otomatis
  mendapat dokumen **Formulir data induk karyawan** berformat PDF di tab **Dokumen**, terisi data yang sudah ada. Karyawan
  mencetak, melengkapi, menandatangani, lalu mengunggah salinannya lewat **Unggah salinan bertanda tangan** (HR juga bisa
  mengunggahnya). Selama belum ada salinan, dokumen berlaku 7 hari lalu tampil kedaluwarsa. Bagian gaji sengaja dikosongkan.
  Alamat perusahaan dan metode pajak diisi di panel admin pada data Perusahaan; NITKU, NPWP 15, dan nomor BPJS per Cabang
  diisi di data Cabang.
- **Karyawan nonaktif:** secara bawaan Direktori hanya menampilkan karyawan yang masih bekerja dan akunnya tidak
  **Dinonaktifkan** (akun **Menunggu aktivasi** dan **Belum terdaftar** tetap tampil). Klik **Termasuk nonaktif** untuk melihat
  semuanya; yang masih aktif tampil lebih dulu. Memilih status **Nonaktif** langsung menampilkan karyawan yang sudah keluar.
  Karyawan yang sudah keluar atau akunnya **Dinonaktifkan** juga tidak bisa dipilih sebagai **atasan langsung** (di formulir
  karyawan, impor, permintaan rekrutmen, dan saat memindahkan bawahan di offboarding) maupun kepala departemen. Atasan yang
  sudah terpasang tidak berubah sampai HR menggantinya.
- **Ekspor:** **Direktori** > **Ekspor** untuk daftar karyawan.
- **Aktifkan kembali:** di profil karyawan yang sudah keluar (kembali bekerja, atau kasus offboarding keliru ditutup).
  Akun masuk aktif lagi; pemetaan PIN mesin absensi **tidak** kembali, petakan ulang.
- **Foto:** foto hanya tampil di pintu VPN; pintu publik menampilkan inisial.

### 4.4 Memutuskan permintaan perubahan data

1. Buka **Perubahan data** (angka di samping menu = jumlah yang menunggu).
2. Buka permintaan: perbandingan data lama dan baru ditampilkan.
3. Klik **Setujui**, atau **Tolak** dengan alasan. Karyawan melihat alasannya.
4. Isian yang sudah Anda ubah sendiri setelah permintaan masuk **tidak diterapkan** dan diberi tanda konflik.

### 4.5 Pengajuan

- **Lihat semua:** menu **Pengajuan** menampilkan semua pengajuan perusahaan; filter status, jenis, cabang, departemen,
  dan bulan. **Ekspor** mengunduh sesuai filter.
- **Menyetujui:** HR menyetujui hanya (a) pengajuan **Sakit**, dan (b) pengajuan karyawan yang **tidak punya atasan
  langsung**. Pengajuan lain diputuskan atasan langsung; HR hanya melihat dan diberi tahu.
- **Mengajukan atas nama:** **Ajukan** > pilih karyawan. HR boleh memakai tanggal lampau, dan memperbaiki pengajuan karyawan yang
  tidak punya akun.
- **Sakit:** karyawan ditandai **Alpa** lebih dulu, lalu mengajukan sakit untuk hari lampau dengan lampiran. Saat Anda
  menyetujui, Alpa berubah menjadi Sakit.
- **Jenis pengajuan dan aturan "Boleh mundur (hari)":** diatur di **Panel admin** > **Jenis pengajuan**. Jenis yang dihitung
  sebagai **Sakit** wajib punya "Boleh mundur (hari)".

### 4.6 Absensi (bila modul absensi diaktifkan)

Menu **Absensi** hanya ada bila modul diaktifkan di server. Bila tidak, hanya **Kalender** (hari libur dan cuti) yang
tersedia.

- **Kalender:** hari libur dan kegiatan perusahaan; HR menambah dan menghapus kejadian kalender.
- **Data absensi:** tempat memasukkan data.
  - **Cabang pusat (mesin pemindai):** **Unggah Laporan Absensi** dari mesin. Tombol **Contoh berkas** mengunduh contoh
    bentuk berkas yang dibaca.
  - **Cabang tanpa mesin:** **Unggah rekapitulasi cabang**. Pilih cabang dan unggah "Rekapitulasi absen karyawan"
    (**Contoh berkas** mengunduh contoh bentuknya, bisa diteruskan ke PIC cabang).
    Hasilnya berupa **draft**: nama dicocokkan dengan karyawan cabang itu; yang belum cocok dihubungkan manual atau dilewati;
    selisih Zona atau potongan ditandai merah (angka aplikasi yang dipakai). **Tidak ada yang dihitung sebelum Anda klik
    Konfirmasi rekapitulasi.** Mengonfirmasi berkas koreksi untuk cabang dan tanggal yang sama menggantikan yang lama.
  - **Tambah pemetaan:** menghubungkan PIN di mesin dengan karyawan.
- **Rekap absensi** dan **Masalah absensi:** tinjau dan selesaikan pengecualian (mis. scan saat cuti). Tersedia aksi
  **maafkan** massal dan **buka lagi**. Di Rekap absensi, cari nama di baris atas; tombol **Filter** memuat rentang
  tanggal, cabang, departemen dan tim (sama seperti di Kalender). **Excel**, **CSV** dan **Cetak / PDF** mengunduh atau
  mencetak hasil yang sedang tampil.
- **Zona** (Hijau, Merah, Hitam) di profil karyawan menentukan tarif potongan. Semua orang Hijau sampai HR mengubahnya.

### 4.7 Rekrutmen: dari lowongan sampai karyawan baru

Semua dilakukan lewat VPN di menu **Rekrutmen**. Urutannya:

1. **Diajukan:** supervisor atau HR mengajukan lowongan (Bagian 2.5). HR dapat mengajukan untuk departemen mana pun.
   Persetujuan: **Manajemen dulu**, baru **HR**. Pengaju tidak memutuskan lowongannya sendiri (HR lain yang menyetujui bila
   HR yang mengajukan).
2. **Diposting:** HR memasang lowongan **secara manual** di platform (Glints, JobStreet, dan sebagainya). Aplikasi menyediakan
   tombol salin judul, deskripsi, dan checklist. Catat "diposting di <platform>" dengan tautan dan tanggalnya.
3. **Screening:** masukkan hanya kandidat yang **di-shortlist**. Isian singkat: nama, telepon, email, sumber (platform,
   Walk-in, atau Referral), CV (opsional), catatan. Aplikasi memperingatkan duplikat telepon atau email. Gunakan **simpan dan
   tambah berikutnya** untuk memasukkan banyak orang.
4. **Interview:**
   - Klik undangan pada kandidat. Undangan dikirim lewat email, atau lewat **Google Calendar dengan tautan Meet** bila
     perusahaan sudah menghubungkan Google (Bagian 5.9).
   - Kandidat mendapat **tautan pribadi** ke formulir yang dibuka tanpa akun. Ia menjawab checklist yang diajukan
     (ya atau tidak per baris). Tautan sekali pakai dan kedaluwarsa.
   - Atur **peserta** per kandidat (HR, Manajemen, atasan langsung terkunci). Peserta ikut dan mengisi **evaluasi**.
   - **Hapus jadwal** membatalkan jadwal (kandidat tetap shortlist).
   - Menolak kandidat: **Tolak** (alasan), bisa **Pulihkan**. Email penolakan dikirim **pagi berikutnya** setelah HR
     meninjau drafnya.
5. **Penawaran:**
   - **Atasan langsung** memilih kandidat (**Pilih**). HR klik **Lanjut ke Penawaran**.
   - **Manajemen** menyetujui dan menetapkan gaji (Bagian 3.4).
   - HR mengisi **syarat**: tanggal mulai (di masa depan), gaji, masa percobaan (maksimal 3 bulan). Satu set syarat dapat
     dipakai beberapa kandidat.
   - **Surat penawaran** dibuat dari template; HR meninjau drafnya lalu **mengirim**. Surat terkirim sebagai **PDF**;
     kandidat menandatangani, memindai, dan mengembalikannya. Unggah **surat bertanda tangan** ke penawaran.
   - Kandidat menjawab: **Tandai Diterima** (jumlah terisi bertambah; ditolak bila kuota sudah penuh) atau **Menolak**
     dengan alasan (slot tetap terbuka).
6. **Terisi:** bila kuota penuh, klik **Tandai Selesai**. Ikuti checklist penutupan (ambil lowongan dari platform) dan,
   bila perlu, email "posisi terisi" ke kandidat lain.
7. **Menjadikan karyawan:** pada kandidat yang diterima, klik **Jadikan karyawan**. Formulir sudah terisi dari kandidat,
   penawaran, dan lowongan. Anda hanya melengkapi **ID karyawan**, **NIK 16 digit** (unik di grup), dan **Cabang**,
   lalu **Simpan karyawan**. Setelah itu orang tersebut **mendaftar sendiri** lewat Bagian 1.1 dan **Anda mengaktifkannya**
   seperti di 4.2 langkah 6. Email sambutan karyawan baru dikirim bila diaktifkan oleh HR.

Data pelamar disimpan sesuai kebijakan retensi di **Panel admin** > **Penyimpanan data pelamar**.

### 4.8 Offboarding (karyawan keluar)

Hanya **HR yang memulai** kasus. Urutannya:

1. **Offboarding** > buat kasus: pilih karyawan, jenis keluar, hari kerja terakhir, alasan, dan lampirkan surat pengunduran diri
   (wajib untuk pengunduran diri). Kirim.
2. **Manajemen menyetujui** (Bagian 3.5). Bila ditolak, kasus kembali menjadi draft.
3. **Checklist:** setelah disetujui, tiap **pemilik item** (departemen atau orang yang ditunjuk, atau HR bila tidak ada)
   menerima daftarnya: pilih item yang berlaku, tambah item khusus bila perlu, lalu klik **Ajukan kunci**. HR meninjau dan
   menekan **Kunci Checklist** (atau **Kembalikan** untuk diperbaiki); HR diberi tahu lewat notifikasi. Selama menunggu dan
   setelah terkunci, pemilik tidak bisa menambah atau menghapus item; untuk menambah, klik **Minta buka kunci** dan HR
   memilih **Buka kunci** atau **Tolak**. Setelah terkunci, pemilik menandai item satu per satu. HR dapat **Alihkan pemilik** untuk satu kasus, menandai departemen **Tidak terkait**, dan **Perbarui dari
   daftar induk** bila ada item baru. Daftar induk diatur di **Panel admin** > **Item offboarding**.
4. HR klik **Setujui checklist** (setelah semua pemilik selesai), lalu **Lanjut ke sign-off**.
5. **Sign-off:** isi **exit interview** (atau lewati dengan alasan), pindahkan **bawahan** orang itu ke atasan baru, lalu **Tutup
   kasus**. Kasus hanya bisa ditutup **setelah hari kerja terakhir** terlewati.
6. **Akun dinonaktifkan** otomatis sehari setelah hari kerja terakhir (tengah malam WIB). Item "Akses VPN dicabut" berarti
   **minta Admin Sistem menghapus kunci WireGuard** orang itu bila ia punya.
7. **Kembalikan ke checklist** (Send back) dengan catatan, **Batalkan** kasus dengan alasan (mis. pengunduran diri ditarik),
   dan ubah **Hari terakhir** bila perlu, masing-masing masih bisa dilakukan sebelum akun dinonaktifkan.
8. **Berita acara** tersedia sebagai halaman cetak (**Cetak / PDF**).

Setiap kasus punya satu utas **Komentar & feedback** untuk seluruh pihak.

### 4.9 Karyawan lupa kata sandi atau kehilangan ponsel

HR tidak mengatur ulang sendiri. Alurnya:
1. Karyawan menghubungi HR.
2. Pastikan orangnya benar.
3. Minta **Admin Sistem** menjalankan **Atur ulang sandi** (menghasilkan kata sandi sementara yang tampil sekali) atau
   **Atur ulang 2FA** (Bagian 5.7).
4. Sampaikan kata sandi sementara ke karyawan lewat jalur pribadi. Ia wajib menggantinya saat masuk.

### 4.10 Hal lain di Panel admin untuk HR

Menu **Panel admin** (VPN) memuat, antara lain: **Departemen**, **Posisi**, **Jenis pengajuan**, **Jam kerja**, **Template
rekrutmen**, **Template undangan**, **Template surat penawaran**, **Email hasil seleksi**, **Email sambutan karyawan baru**,
dan **Penyimpanan data pelamar**. Sebagian hanya terbuka untuk HR tingkat grup.

---

## Bagian 5. Admin Sistem

Admin Sistem memegang **akun, peran, perusahaan, dan organisasi**, serta teknis (VPN, cadangan, log). Admin Sistem
**tidak** membuka data dossier karyawan (dokumen, rekening). Seluruh pekerjaannya ada di **Panel admin**
(`[ALAMAT VPN]/admin`, dari menu **Panel admin**), kecuali WireGuard (5.6).

### 5.1 Masuk pertama kali

1. Server sudah disiapkan sesuai README (langkah 1 sampai 7). Akun awal dibuat dengan `INITIAL_ADMIN_EMAIL` dan
   `INITIAL_ADMIN_PASSWORD`.
2. Nyalakan VPN (A.2), buka `[ALAMAT VPN]`, klik **Masuk dengan email (akun administrator)**.
3. Isi email dan kata sandi awal. Aplikasi meminta **kata sandi baru**, lalu mewajibkan **2FA** (A.1).
4. Setelah itu hapus `INITIAL_ADMIN_PASSWORD` dari berkas konfigurasi server (README, langkah 4).

### 5.2 Menyiapkan organisasi (urutan)

1. **Perusahaan** > Buat: kode, nama, warna merek, logo (mode terang, dan opsional mode gelap), NPWP, dan cabang awal
   (setiap karyawan harus punya cabang, jadi satu cabang minimal dibuat di sini).
2. **Cabang:** tambah cabang lain sebelum mengimpor karyawan. Tiap cabang punya **Sumber absensi**: *Pemindai* atau
   *Rekapitulasi*. Hanya Admin Sistem yang menambah, mengganti nama, atau menghapus cabang.
3. **Departemen** dan **Posisi:** Admin Sistem atau HR dapat membuatnya. Buat semuanya sebelum impor karyawan.
4. **Jenis pengajuan:** atur jenis (Izin, Sakit, cuti), maksimal hari, "Boleh mundur (hari)", ikon.
5. **Jam kerja** dan **Peran dan izin** bila perlu disesuaikan.

### 5.3 Membuat akun HR Admin pertama setiap perusahaan

1. **Pengguna** > **Buat**.
2. Isi **Nama**, **Email**, biarkan **Akun aktif** menyala, isi **Kata sandi sementara** (minimal 12 karakter), pilih
   **Perusahaan** (akun terikat ke satu perusahaan hanya melihat data perusahaan itu; kosong = tingkat grup), dan **Peran**:
   **Admin HR**.
3. Simpan. Berikan email dan kata sandi sementara kepada orangnya **lewat jalur pribadi**. Saat masuk pertama kali ia
   wajib membuat kata sandi baru, lalu mengaktifkan 2FA.
4. Buat kunci WireGuard untuknya (5.6).

Kolom **Sandi sementara** di tabel Pengguna menampilkan tanda peringatan selama kata sandi sementara belum diganti.

### 5.4 Akun Manajemen

Sama seperti 5.3 dengan **Peran: Manajemen**, atau biarkan orangnya **Daftar** lalu aktifkan (Bagian 4.2) dan beri
peran (5.5). Bahan akun Manajemen (nama dan email) berasal dari data klien.

### 5.5 Memberikan peran kepada akun yang sudah mendaftar

Karyawan yang mendaftar sendiri otomatis berperan **Karyawan**. Untuk Supervisor, Manajemen, atau Admin HR:

1. **Pengguna**, cari namanya, klik **Edit**.
2. Di **Peran**, pilih satu atau lebih peran, lalu simpan. Perubahan peran tercatat di catatan aktivitas.
3. Anda **tidak dapat mengubah peran akun Anda sendiri**. Minta Admin Sistem lain.

Akun tingkat perusahaan hanya dapat mengelola akun perusahaannya.

### 5.6 Onboarding WireGuard: membuat kunci untuk seseorang

**Satu kali sebelum klien pertama:** panel WireGuard (wg-easy) sudah diatur sesuai README langkah 7: **Host** berisi alamat
IP publik server atau nama DNS tanpa proxy, **DNS dikosongkan**, dan **aturan firewall** di *Admin > Hooks* sudah terpasang.

**Untuk setiap orang (satu klien per perangkat):**

1. Dari komputer Anda, buka terowongan SSH ke server:
   `ssh -L 51821:127.0.0.1:51821 <pengguna>@<server>`
2. Buka `http://localhost:51821` di peramban, masuk dengan `WG_ADMIN_USER` dan `WG_ADMIN_PASSWORD`.
3. Klik tombol tambah klien (**New** atau **+**; nama tombol bisa berbeda menurut versi wg-easy), beri **nama** yang
   jelas, mis. `Budi Santoso - HP`, lalu simpan.
4. Pada klien itu ada ikon **kode QR** (untuk ponsel) dan **unduh** (berkas `.conf` untuk laptop).
5. Serahkan kuncinya **langsung ke orangnya** (tunjukkan QR di layar saat bertemu, atau kirim berkas lewat jalur pribadi).
   **Jangan** kirim ke grup. Setelah dipindai atau diimpor, hapus berkas dari tempat Anda mengirimnya.
6. Pandu orangnya lewat A.2. Uji: VPN menyala lalu `[ALAMAT VPN]` terbuka.
7. Bila orang itu keluar (item offboarding **Akses VPN dicabut**) atau perangkat hilang: **hapus kliennya** di panel yang sama.

Catatan: seluruh pengguna VPN terlihat di aplikasi dengan alamat yang sama. Catatan aktivitas tetap menyimpan **siapa**
pelakunya.

### 5.7 Mengatur ulang akses seseorang

Di **Pengguna**, baris orang itu memiliki aksi (tidak tersedia untuk akun Anda sendiri):

- **Atur ulang sandi:** membuat kata sandi sementara yang **tampil sekali**: salin sekarang, tidak bisa dilihat lagi. Semua
  sesinya dicabut, dan ia wajib mengganti saat masuk. Sampaikan lewat jalur pribadi.
- **Atur ulang 2FA:** untuk yang kehilangan ponsel. 2FA dimatikan dan sesinya dicabut; yang wajib 2FA harus mengaktifkannya
  lagi saat masuk berikutnya.
- **Cabut sesi:** keluarkan dari semua perangkat tanpa mengubah kata sandi (mis. perangkat hilang).
- **Akun aktif (di Edit):** matikan untuk menonaktifkan, tanpa menghapus. Akun tidak pernah dihapus, demi riwayat.

### 5.8 Cadangan data dan pemantauan

- **Cadangan data:** pengaturan "Simpan di server (hari)", status cadangan malam (02:30), dan salinan di luar server.
  Pantau ruang disk (README, "Backups").
- **Catatan aktivitas:** jejak masuk, perubahan peran, aktivasi akun, dan banyak lagi.
- **Log email:** semua email dari aplikasi, status, dan kirim ulang.
- Pembaruan server dan citra PHP: ikuti README.

### 5.9 Email pengirim dan Google Meet per perusahaan

Pengaturan ini ada di **Perusahaan** > perusahaan yang dipilih. Langkah lengkap, termasuk **Kata sandi aplikasi** untuk
akun Gmail, ada di README bagian **Company email and Google Meet invitations**.

- **Atur email pengirim:** server SMTP, port, enkripsi, nama pengguna, kata sandi, alamat pengirim. Gunakan **Kirim email
  uji** sebelum menyalakan **Aktif**.
- **Alamat pengirim undangan interview (Google)** dan **Hubungkan Google**: akun Google yang membuat undangan Meet.

---

## Bagian 6. Onboarding karyawan yang sudah ada: urutan lengkap

Untuk go-live dengan karyawan yang sudah bekerja. Kolom **Siapa** menunjukkan pelaku.

| # | Siapa | Apa | Hasil |
|---|---|---|---|
| 1 | Admin Sistem | Buat **Perusahaan**, **Cabang**, **Departemen**, **Posisi**, **Jenis pengajuan** (5.2) | Organisasi siap |
| 2 | Admin Sistem | Buat **akun HR Admin** tiap perusahaan (5.3) dan **kunci WireGuard** untuknya (5.6) | HR bisa masuk |
| 3 | Admin HR | Pasang WireGuard dan autentikator, masuk, ganti kata sandi, aktifkan 2FA (4.1) | Akun HR aman |
| 4 | Admin HR | **Impor** data karyawan: periksa dulu, lalu impor (4.2 langkah 1 sampai 3). Pastikan kolom **NIK**, **ID karyawan**, dan **Supervisor** benar | Profil karyawan ada, belum ada akun |
| 5 | Admin HR | Periksa sampel profil (NIK, ID, atasan) (langkah 4) | Data siap diklaim |
| 6 | Admin HR | **Umumkan pendaftaran** ke karyawan (Lampiran A) | Karyawan tahu cara mendaftar |
| 7 | Karyawan | **Daftar** dengan NIK dan ID karyawan (1.1) | Akun berstatus menunggu aktivasi |
| 8 | Admin HR | **Aktifkan akun** satu per satu setelah memastikan orangnya (langkah 6) | Karyawan bisa masuk |
| 9 | Karyawan | **Masuk** (1.2), pasang ke layar utama (A.3), aktifkan notifikasi, dan 2FA bila mau | Siap mengajukan |
| 10 | Admin Sistem | Beri peran **Supervisor**, **Manajemen**, dan **Admin HR** ke akun yang sesuai (5.5) | Penyetuju punya hak |
| 11 | Admin Sistem | Buat **kunci WireGuard** untuk Supervisor, Manajemen, dan HR lain (5.6) | Mereka bisa memakai VPN |
| 12 | Supervisor, Manajemen, HR lain | Pasang WireGuard dan autentikator, **aktifkan 2FA wajib** (A.1 dan A.2), masuk lewat `[ALAMAT VPN]` | Penyetuju siap bekerja |
| 13 | Admin HR | Uji satu pengajuan dari awal sampai disetujui bersama satu supervisor | Alur terbukti jalan |

**Urutan yang disarankan agar tidak macet:** selesaikan baris 1 sampai 5 **sebelum** mengumumkan pendaftaran. Aktifkan
supervisor dan Manajemen lebih dulu (baris 7 sampai 12 untuk mereka) supaya pengajuan karyawan sudah ada penyetujunya saat
karyawan mulai memakai aplikasi.

**Bila satu orang bergabung belakangan:** ulangi baris 4 (**Tambah karyawan**, atau impor untuk satu baris), 6, 7, 8, dan 9.
Untuk karyawan baru hasil rekrutmen, gunakan **Jadikan karyawan** (4.7) sebagai pengganti baris 4.

**Bila ada yang keluar:** HR menjalankan offboarding (4.8). Akun nonaktif otomatis, dan kunci WireGuard dihapus Admin Sistem.

---

## Bagian 7. Masalah yang sering terjadi

| Gejala | Kemungkinan sebab | Yang dilakukan |
|---|---|---|
| "Data tidak cocok atau akun sudah terdaftar. Hubungi HR." saat Daftar | NIK atau ID karyawan tidak sama dengan data HR; akun sudah pernah didaftarkan; profil sudah keluar | Karyawan menghubungi HR. HR memeriksa NIK (16 digit) dan ID di profil, lalu menyampaikan yang benar. Jangan mencoba berulang |
| "Akun Anda menunggu aktivasi HR." saat Masuk | HR belum menekan **Aktifkan akun** | HR membuka profil karyawan dan mengaktifkan (4.2 langkah 6) |
| Salah kata sandi berulang, lalu ditahan | Batas percobaan masuk per menit | Tunggu satu menit, lalu coba dengan teliti |
| Lupa kata sandi | Tidak ada atur ulang lewat email | Hubungi HR, HR meminta Admin Sistem **Atur ulang sandi** (4.9, 5.7) |
| Kode 2FA selalu ditolak | Jam ponsel tidak akurat; salah akun di autentikator; kode kedaluwarsa | Atur jam otomatis; gunakan kode terbaru untuk akun HR Dashboard; atau pakai kode pemulihan |
| Ponsel hilang, 2FA tidak bisa dipakai | | Pakai kode pemulihan, atau Admin Sistem **Atur ulang 2FA** lalu pasang ulang |
| Menu Rekrutmen, Direktori, atau persetujuan tidak muncul | Masuk lewat `[ALAMAT PUBLIK]`: pintu publik hanya halaman karyawan untuk semua orang | Nyalakan VPN dan buka `[ALAMAT VPN]` |
| Muncul pesan "Akun Anda wajib memakai autentikasi dua langkah" | Akun Anda tergolong wajib 2FA | Selesaikan A.1, lalu lanjut |
| `[ALAMAT VPN]` tidak terbuka padahal VPN "Active" | Kunci atau alamat server salah; **Latest handshake** kosong | Hubungi Admin Sistem untuk memeriksa Host dan kuncinya, atau minta kunci baru |
| `[ALAMAT VPN]` tidak terbuka dan VPN mati | VPN belum dinyalakan | Nyalakan WireGuard |
| Pengajuan saya tidak bisa diubah | Hanya pengajuan yang **ditolak** yang bisa diubah dan diajukan ulang | Hubungi atasan atau HR untuk menolaknya, lalu ajukan ulang |
| Pengajuan sakit tidak bisa dikirim | Lampiran wajib untuk Sakit; hari harus sudah ditandai Alpa dan masih dalam "boleh mundur" | Lampirkan surat; bila tanggal terlalu lampau, HR yang mengajukan atas nama |
| Pengajuan saya langsung ke HR, bukan atasan | Atasan langsung belum diisi di profil | HR mengisi **Atasan langsung** di **Ubah data** |
| Impor: "ditolak" untuk beberapa baris | Cabang, Departemen, atau Posisi tidak ada atau salah ketik; nama perusahaan tidak sama; NIK bukan 16 digit | Baca daftar kesalahan, perbaiki berkas, ulangi pemeriksaan |
| Impor menolak semuanya | Belum memilih perusahaan di pemilih perusahaan | Pilih perusahaan lalu ulangi |
| Notifikasi di ponsel tidak muncul | Belum diaktifkan di perangkat itu; di iPhone aplikasi belum dipasang ke layar utama; izin diblokir | A.3: pasang ke layar utama, aktifkan di **Pengaturan > Notifikasi**, izinkan di pengaturan situs bila diblokir |
| Lonceng masih menunjukkan angka padahal sudah dibaca di perangkat lain | Angka belum menyegarkan | Muat ulang halaman |

---

## Lampiran. Pesan siap pakai

### A. Pengumuman pendaftaran (Admin HR, ke karyawan)

> Halo, mulai [TANGGAL] kita memakai aplikasi HR Dashboard untuk pengajuan cuti, izin, dan sakit, serta data diri Anda.
> Cara mendaftar (sekali saja, tanpa VPN):
> 1. Buka [ALAMAT PUBLIK], ketuk **Daftar**.
> 2. Isi **NIK** (16 digit) dan **ID karyawan** Anda: [ID KARYAWAN ANDA DIKIRIM PRIBADI].
> 3. Buat kata sandi (minimal 12 karakter: huruf besar dan kecil, angka, simbol).
> 4. Ketuk **Daftar**, lalu tunggu HR mengaktifkan akun Anda. Kami kabari bila sudah aktif.
>
> Bila muncul "Data tidak cocok", hubungi HR, jangan coba berulang. Setelah aktif, masuk dengan NIK, ID karyawan, dan kata
> sandi Anda. Disarankan memasang aplikasi ke layar utama ponsel dan mengaktifkan notifikasi.

### B. Aktivasi berhasil (Admin HR, ke karyawan)

> Akun HR Dashboard Anda sudah aktif. Silakan masuk di [ALAMAT PUBLIK] dengan NIK, ID karyawan, dan kata sandi yang Anda buat.
> Disarankan mengaktifkan autentikasi dua langkah di **Pengaturan > Keamanan**.

### C. Kunci VPN (Admin Sistem, ke supervisor, Manajemen, atau HR)

> Berikut kunci WireGuard Anda untuk [PERANGKAT]. Jangan dibagikan.
> 1. Pasang aplikasi **WireGuard** dan **Google Authenticator** (atau Microsoft Authenticator).
> 2. Di WireGuard: **+** > **Pindai kode QR** (atau impor berkas .conf di laptop), lalu nyalakan.
> 3. Buka [ALAMAT VPN], masuk, lalu aktifkan 2FA di **Pengaturan > Keamanan**. Simpan kode pemulihan di tempat aman.
> 4. Matikan VPN setelah selesai bekerja.
