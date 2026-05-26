# Mock Data Seeder - Toko Bangunan

## 📦 Deskripsi
Seeder ini membuat mock data lengkap untuk aplikasi Drivio yang disesuaikan dengan use case **Toko Bangunan** di Surabaya.

## 🎯 Data yang Dibuat

### 1. **Users**
- **1 Admin**: Admin Toko Bangunan
- **6 Drivers**: 
  - Budi Santoso
  - Agus Wijaya
  - Eko Prasetyo
  - Dedi Kurniawan
  - Hendra Gunawan
  - Rizki Firmansyah

### 2. **Attendance Records**
- 4 drivers sudah check-in (online)
- 1 driver sudah check-out (offline)
- 1 driver belum check-in (offline)

### 3. **Deliveries** (~17 deliveries)
- **3 completed** (hari ini)
- **5 completed** (kemarin)
- **4 completed** (2 hari lalu)
- **2 on_way** (sedang dalam perjalanan)
- **3 pending** (menunggu pengiriman)

### 4. **Items/Barang**
Bahan bangunan yang realistis:
- Semen, bata merah, besi beton
- Pintu, jendela, genteng
- Cat, paku, triplek
- Pipa PVC, keramik
- Dan lainnya

### 5. **Lokasi Delivery**
10 lokasi berbeda di area Surabaya dan sekitarnya:
- Perumahan Griya Kebraon Indah
- Jl. Rungkut Asri Utara
- Komplek Ruko Dharmahusada Mas
- Perumahan Citra Harmoni (Sidoarjo)
- Dan lokasi lainnya

### 6. **Tracking Logs**
- 10-15 tracking points untuk setiap delivery yang on_way/completed
- Simulasi perjalanan dari toko ke lokasi tujuan
- Interval 2-5 menit antar tracking point

### 7. **Proof of Delivery**
- Attendance record dengan type 'proof_of_delivery'
- Face similarity score 85-99%
- Untuk semua completed deliveries

## 🚀 Cara Menggunakan

### Step 1: Jalankan Migration (jika belum)
```bash
./vendor/bin/sail artisan migrate
```

### Step 2: Reset Database & Jalankan Seeder
```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

**ATAU** jika ingin menjalankan seeder saja tanpa reset:
```bash
./vendor/bin/sail artisan db:seed --class=BuildingMaterialStoreSeeder
```

## 🔐 Login Credentials

### Admin
- **Email**: `admin@tokobangunan.com`
- **Password**: `password`

### Drivers
- **Email**: `budi@tokobangunan.com` (atau driver lainnya)
- **Password**: `password`

Semua driver menggunakan format: `[nama]@tokobangunan.com`

## 📊 Fitur yang Bisa Ditest

### Admin Dashboard
✅ Melihat analytics (deliveries today/week/month)
✅ Top performers hari ini
✅ Driver status overview (available/busy/offline)
✅ Live map tracking dengan idle detection
✅ Delivery completion rate

### Deliveries Page
✅ Tab Active Deliveries (pending + on_way)
✅ Tab Completed Deliveries
✅ Detail delivery dengan map route
✅ Proof of delivery photo
✅ Items/barang yang dikirim

### Attendance Log
✅ Check-in/check-out records
✅ Status badges (check in/check out)
✅ Filter by date range
✅ Export functionality

### Driver Dashboard
✅ Active deliveries list
✅ GPS tracking (jika check-in)
✅ Delivery status updates

## 🗺️ Lokasi Toko (Starting Point)
**Toko Bangunan Jaya**
- Alamat: Jl. Raya Darmo No. 123, Surabaya
- Koordinat: -7.2504, 112.7688

## 💡 Tips Testing

1. **Test Idle Detection**: 
   - Beberapa driver memiliki tracking logs yang menunjukkan minimal movement
   - Cek di Admin Dashboard map untuk melihat marker merah (idle)

2. **Test Delivery Flow**:
   - Login sebagai admin
   - Create new delivery dengan items bahan bangunan
   - Assign ke driver yang online
   - Login sebagai driver untuk update status

3. **Test Analytics**:
   - Data tersebar di hari ini, kemarin, dan 2 hari lalu
   - Coba switch period selector (Today/This Week/This Month)

4. **Test Map Tracking**:
   - Delivery yang completed memiliki tracking route lengkap
   - Buka detail delivery untuk melihat journey map

## 🎨 Customization

Jika ingin mengubah data, edit file:
```
database/seeders/BuildingMaterialStoreSeeder.php
```

Anda bisa mengubah:
- Nama drivers
- Lokasi delivery
- Jenis bahan bangunan
- Jumlah deliveries per status
- Koordinat toko

## ⚠️ Catatan Penting

1. **Migration `items` column**: Pastikan sudah menjalankan migration untuk menambahkan kolom `items` ke tabel deliveries
2. **Timezone**: Data menggunakan timezone server (biasanya UTC), sesuaikan jika perlu
3. **Face Recognition**: Seeder tidak membuat foto asli, hanya record dengan similarity score
4. **GPS Coordinates**: Koordinat adalah koordinat real di Surabaya, cocok untuk testing map

## 🔄 Reset Data

Jika ingin reset dan mulai dari awal:
```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

**WARNING**: Ini akan menghapus SEMUA data di database!

## 📞 Support

Jika ada masalah dengan seeder, cek:
1. Apakah semua migration sudah dijalankan?
2. Apakah ada error di console saat seeding?
3. Apakah database connection sudah benar?

---

**Happy Testing! 🚀**
