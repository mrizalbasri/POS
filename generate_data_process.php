<?php
/**
 * Generate Data Backend Process (AJAX Handler)
 * Handles: cleanup, products, customers, transactions, update_stats
 */

require_once __DIR__ . '/config/app.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$step = $_POST['step'] ?? '';
$db = getDB();

try {
    switch ($step) {

        // ===================== STEP 0: CLEANUP =====================
        case 'cleanup':
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $db->exec("TRUNCATE TABLE transaction_details");
            $db->exec("TRUNCATE TABLE transactions");
            $db->exec("TRUNCATE TABLE products");
            $db->exec("TRUNCATE TABLE customers");
            $db->exec("TRUNCATE TABLE categories");
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");

            // Re-insert categories
            $cats = ['Makanan','Minuman','Elektronik','Pakaian','Kesehatan','Alat Tulis','Peralatan Rumah','Snack','Obat-obatan','Perlengkapan Bayi'];
            $stmt = $db->prepare("INSERT INTO categories (nama_kategori) VALUES (?)");
            foreach ($cats as $c) $stmt->execute([$c]);

            echo json_encode(['success' => true, 'message' => 'Data lama berhasil dihapus, kategori dibuat ulang']);
            break;

        // ===================== STEP 1: PRODUCTS (1000) =====================
        case 'products':
            $realProducts = [
                // Makanan (cat 1)
                ['Indomie Goreng',1,3500],['Indomie Kuah Soto',1,3500],['Indomie Hype Abis Ayam Geprek',1,4000],['Mie Sedaap Goreng',1,3500],['Pop Mie Rasa Ayam',1,5000],
                ['Sarimi Isi 2 Rasa Ayam',1,3000],['Supermi Goreng',1,3000],['Bakmi Mewah Ayam Panggang',1,7000],['Roti Tawar Sari Roti',1,15000],['Roti Sobek Cokelat',1,17000],
                ['Khong Guan Biskuit Kaleng',1,45000],['Roma Kelapa',1,10000],['Monde Butter Cookies',1,55000],['Good Time Cookies',1,12000],['Oreo Vanilla',1,8000],
                ['Tango Wafer Cokelat',1,10000],['Richeese Nabati',1,9000],['Beng-Beng',1,3000],['SilverQueen Chunky Bar',1,12000],['Cadbury Dairy Milk',1,15000],
                ['Chitato Rasa Sapi Panggang',1,11000],['Lays Classic',1,11000],['Pringles Original',1,32000],['Qtela Tempe',1,9000],['Taro Net',1,5000],
                ['Kecap Manis ABC 275ml',1,12000],['Kecap Bango 275ml',1,14000],['Saos Sambal ABC',1,10000],['Sasa Tepung Bumbu',1,6000],['Royco Ayam 100g',1,7000],
                ['Masako Sapi 100g',1,7000],['Bimoli Minyak Goreng 1L',1,18000],['Sania Minyak Goreng 2L',1,34000],['Filma Margarin 200g',1,8000],['Blue Band 200g',1,10000],
                ['Gula Pasir Gulaku 1kg',1,16000],['Tepung Terigu Segitiga Biru 1kg',1,12000],['Beras Cap Jago 5kg',1,65000],['Telur Ayam 1kg',1,28000],['Susu Kental Manis Frisian Flag',1,10000],
                ['Indomilk UHT Cokelat 1L',1,17000],['Ultra Milk Full Cream 1L',1,18000],['Yakult',1,10000],['Cimory Yogurt Drink',1,8000],['Keju Kraft Cheddar 165g',1,18000],
                ['Sarden ABC 155g',1,12000],['Kornet Pronas 198g',1,22000],['Nasi Goreng Indofood Bumbu',1,6000],['Sambal Bu Rudy',1,28000],['Krupuk Udang Finna',1,15000],
                // Minuman (cat 2)
                ['Aqua 600ml',2,4000],['Aqua 1500ml',2,7000],['Le Minerale 600ml',2,4000],['Teh Botol Sosro 450ml',2,5000],['Teh Pucuk Harum 350ml',2,4000],
                ['Frestea Jasmine 500ml',2,5000],['Coca-Cola 390ml',2,7000],['Fanta Strawberry 390ml',2,7000],['Sprite 390ml',2,7000],['Pepsi Blue 390ml',2,6000],
                ['Pocari Sweat 500ml',2,8000],['Mizone Lychee Lemon',2,6000],['Floridina Orange',2,5000],['Minute Maid Pulpy Orange',2,7000],['Buavita Guava 250ml',2,7000],
                ['Kopi Good Day Cappuccino',2,3000],['Kopi Kapal Api Special',2,2000],['Nescafe Classic',2,3000],['Torabika Cappuccino',2,3000],['ABC Kopi Susu',2,2000],
                ['Energen Cokelat',2,3000],['Hilo Teen Cokelat',2,5000],['Milo 3in1',2,4000],['Chocolatos Drink',2,3000],['Nutrisari Jeruk',2,2000],
                ['Bear Brand 189ml',2,9000],['Green Tea Ichi Ocha 500ml',2,5000],['Teh Kotak Jasmine 300ml',2,4000],['Fruit Tea Blackcurrant',2,4000],['Ale-Ale Rasa Jeruk',2,2000],
                // Elektronik (cat 3)
                ['Charger USB Type-C',3,25000],['Kabel Data Lightning',3,30000],['Earphone Bluetooth TWS',3,85000],['Powerbank 10000mAh',3,95000],['Mouse Wireless Logitech',3,75000],
                ['Keyboard USB Logitech',3,85000],['Flashdisk 32GB Sandisk',3,65000],['MicroSD 64GB Samsung',3,95000],['Headphone On-Ear Sony',3,150000],['Speaker Bluetooth JBL Mini',3,120000],
                ['Lampu LED Philips 12W',3,25000],['Stop Kontak 4 Lubang',3,35000],['Kabel Roll 10m',3,55000],['Senter LED Rechargeable',3,45000],['Baterai ABC Alkaline AA 4pcs',3,18000],
                // Pakaian (cat 4)
                ['Kaos Polos Hitam',4,35000],['Kaos Polos Putih',4,35000],['Kaos Polo Pria',4,65000],['Kemeja Flanel Pria',4,89000],['Celana Jeans Pria',4,120000],
                ['Celana Chino Pria',4,95000],['Jaket Hoodie Unisex',4,85000],['Sweater Rajut Wanita',4,75000],['Rok Midi Wanita',4,70000],['Dress Casual Wanita',4,95000],
                ['Kaos Kaki Pria 3pcs',4,20000],['Boxer Pria 3pcs',4,45000],['Bra Wanita',4,55000],['Celana Dalam Wanita 3pcs',4,35000],['Topi Baseball',4,30000],
                // Kesehatan (cat 5)
                ['Masker Medis 3Ply 50pcs',5,35000],['Hand Sanitizer Lifebuoy 100ml',5,12000],['Dettol Antiseptik 100ml',5,18000],['Betadine 30ml',5,15000],['Minyak Kayu Putih Cap Lang 60ml',5,22000],
                ['Minyak Angin Freshcare',5,15000],['Hansaplast Plester 10pcs',5,8000],['Tolak Angin Cair',5,5000],['Panadol Extra 10tab',5,12000],['Bodrex 4tab',5,5000],
                ['Promag 6tab',5,8000],['Diapet 4tab',5,6000],['Vitamin C 1000mg Ester-C',5,35000],['Blackmores Vit D3',5,95000],['Sabun Dettol 100g',5,8000],
                // Alat Tulis (cat 6)
                ['Pulpen Pilot G-2',6,12000],['Pulpen Snowman V5',6,4000],['Pensil Faber Castell 2B',6,3000],['Penghapus Staedtler',6,4000],['Penggaris Besi 30cm',6,6000],
                ['Buku Tulis Sidu 58lbr',6,5000],['Buku Gambar A3',6,10000],['Spidol Snowman Whiteboard',6,8000],['Stabilo Boss Highlighter',6,12000],['Tip-Ex Correction Tape',6,7000],
                ['Stapler Kenko HD-10',6,18000],['Gunting Joyko',6,10000],['Lem Kertas UHU Stick',6,8000],['Double Tape 1inch',6,6000],['Map Plastik Folio',6,3000],
                // Peralatan Rumah (cat 7)
                ['Sapu Ijuk',7,18000],['Pel Putar Spin Mop',7,65000],['Ember Plastik 20L',7,25000],['Baskom Plastik Besar',7,15000],['Gayung Plastik',7,5000],
                ['Tempat Sampah Injak',7,35000],['Gantungan Baju Kawat 10pcs',7,12000],['Jemuran Lipat',7,85000],['Rak Sepatu 5 Tingkat',7,55000],['Keset Kaki',7,15000],
                ['Piring Melamin 6pcs',7,30000],['Gelas Kaca 6pcs',7,25000],['Sendok Makan Stainless 6pcs',7,18000],['Wajan Teflon 24cm',7,65000],['Panci Aluminium 22cm',7,45000],
                // Snack (cat 8)
                ['Pocky Cokelat',8,10000],['Yan Yan',8,8000],['Hello Panda Cokelat',8,10000],['Ciki Cheetos',8,5000],['JetZ Keju',8,5000],
                ['Sukro Kacang',8,8000],['Garuda Kacang Atom',8,12000],['Dua Kelinci Kacang',8,15000],['Tic Tac Snek',8,5000],['Chiki Balls',8,5000],
                ['Permen Kopiko',8,2000],['Permen Relaxa',8,2000],['Permen Alpenliebe',8,2000],['Yupi Gummy Bear',8,7000],['Mentos Roll',8,5000],
                // Obat-obatan (cat 9)
                ['Antangin JRG',9,5000],['Konidin',9,3000],['Decolgen',9,5000],['Neo Rheumacyl',9,12000],['Entrostop',9,6000],
                ['Dulcolax 5mg',9,15000],['Mylanta Cair 150ml',9,25000],['Combantrin',9,18000],['Imboost Force',9,35000],['Stimuno Cair',9,30000],
                ['Salonpas Koyo 5pcs',9,12000],['Counterpain 30g',9,25000],['Thrombophob Gel',9,45000],['Visine Tetes Mata',9,22000],['Insto Tetes Mata',9,15000],
                // Perlengkapan Bayi (cat 10)
                ['Pampers Premium Care S 48',10,95000],['MamyPoko Pants M 34',10,72000],['Sweety Silver Pants L 28',10,65000],['Baby Happy Pants XL 22',10,55000],['Mitu Baby Wipes 50s',10,15000],
                ['Johnson Baby Powder 200g',10,18000],['Johnson Baby Oil 125ml',10,20000],['Johnson Baby Shampoo 200ml',10,22000],['Cussons Baby Soap 75g',10,6000],['Pigeon Botol Susu 240ml',10,45000],
                ['Pigeon Dot Silikon S',10,25000],['Empeng Bayi Pigeon',10,30000],['SGM Eksplor 1+ 400g',10,42000],['Dancow 1+ 400g',10,48000],['Bebelac 3 400g',10,52000],
            ];

            $stmt = $db->prepare("INSERT INTO products (kode_produk, nama_produk, category_id, harga_beli, harga_jual, stok, satuan, status) VALUES (?,?,?,?,?,?,?,?)");
            $count = 0;

            // Insert real products
            foreach ($realProducts as $p) {
                $count++;
                $kode = 'PRD-' . str_pad($count, 4, '0', STR_PAD_LEFT);
                $hargaJual = $p[2];
                $hargaBeli = (int)round($hargaJual * 0.7 / 1000) * 1000;
                $stmt->execute([$kode, $p[0], $p[1], $hargaBeli, $hargaJual, 10000, 'pcs', 'aktif']);
            }

            // Fill remaining to 1000
            $genericCats = range(1, 10);
            $satuans = ['pcs','box','botol','pack','set','lusin','roll','lembar'];
            while ($count < 1000) {
                $count++;
                $kode = 'PRD-' . str_pad($count, 4, '0', STR_PAD_LEFT);
                $nama = 'Produk ' . str_pad($count, 4, '0', STR_PAD_LEFT);
                $catId = $genericCats[array_rand($genericCats)];
                $hargaJual = rand(1, 100) * 1000;
                $hargaBeli = (int)round($hargaJual * 0.7 / 1000) * 1000;
                $sat = $satuans[array_rand($satuans)];
                $stmt->execute([$kode, $nama, $catId, $hargaBeli, $hargaJual, 10000, $sat, 'aktif']);
            }

            echo json_encode(['success' => true, 'message' => "$count produk berhasil dibuat", 'count' => $count]);
            break;

        // ===================== STEP 2: CUSTOMERS (300) =====================
        case 'customers':
            $namaDepan = ['Adi','Agus','Ahmad','Andi','Angga','Anita','Arief','Ayu','Bagus','Bambang','Bayu','Budi','Cahya','Citra','Dani','Dedi','Desi','Dewi','Dian','Dina',
                'Eka','Endang','Erwin','Evi','Fajar','Fani','Farhan','Feri','Fitri','Galih','Gilang','Gita','Hadi','Hani','Hendra','Ika','Imam','Indah','Irfan','Ita',
                'Jaka','Joni','Juli','Kartika','Kiki','Laras','Lina','Lukman','Maya','Mega','Mira','Nanda','Nani','Nia','Nina','Nita','Nova','Nurul','Oscar','Putra',
                'Putri','Rahma','Rani','Ratna','Reza','Rian','Rika','Rina','Rini','Rizal','Rosa','Rudi','Santi','Sari','Siska','Siti','Sri','Surya','Taufik','Tina',
                'Tri','Umi','Usman','Vera','Vina','Wati','Wawan','Widya','Wulan','Yani','Yoga','Yudi','Yuli','Yuni','Zahra','Zaki'];
            $namaBelakang = ['Pratama','Santoso','Wijaya','Kusuma','Hidayat','Saputra','Nugraha','Permana','Lestari','Rahayu','Utami','Sari','Dewi','Purnama','Setiawan',
                'Suryadi','Firmansyah','Wahyudi','Hartono','Susanto','Wibowo','Handoko','Kurniawan','Sulistyo','Maulana','Adriansyah','Ramadhan','Putra','Anggraini','Fitriani',
                'Safitri','Wulandari','Maharani','Damayanti','Septiani','Indrawati','Kusumawardani','Prihatin','Rahmawati','Agustina'];
            $kota = ['Jakarta','Bandung','Surabaya','Yogyakarta','Semarang','Medan','Makassar','Palembang','Denpasar','Malang','Bogor','Depok','Tangerang','Bekasi','Balikpapan','Solo','Manado','Padang','Banjarmasin','Pontianak'];

            $stmt = $db->prepare("INSERT INTO customers (kode_pelanggan, nama_pelanggan, jenis_kelamin, no_telepon, email, alamat, kota) VALUES (?,?,?,?,?,?,?)");
            $count = 0;

            for ($i = 1; $i <= 300; $i++) {
                $count++;
                $kode = 'CST-' . str_pad($i, 4, '0', STR_PAD_LEFT);
                $depan = $namaDepan[array_rand($namaDepan)];
                $belakang = $namaBelakang[array_rand($namaBelakang)];
                $nama = "$depan $belakang";
                $jk = rand(0,1) ? 'L' : 'P';
                $telp = '08' . rand(10,99) . rand(1000000, 9999999);
                $email = strtolower(str_replace(' ','.',$nama)) . rand(1,99) . '@email.com';
                $alamat = 'Jl. ' . $namaBelakang[array_rand($namaBelakang)] . ' No. ' . rand(1,200);
                $kt = $kota[array_rand($kota)];
                $stmt->execute([$kode, $nama, $jk, $telp, $email, $alamat, $kt]);
            }

            echo json_encode(['success' => true, 'message' => "$count pelanggan berhasil dibuat", 'count' => $count]);
            break;

        // ===================== STEP 3: TRANSACTIONS (BATCH) =====================
        case 'transactions':
            $offset = (int)($_POST['offset'] ?? 0);
            $limit  = (int)($_POST['limit'] ?? 10);

            // Get customers in batch
            $stmt = $db->prepare("SELECT id FROM customers ORDER BY id LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Get all product IDs + prices
            $prodRows = $db->query("SELECT id, harga_jual FROM products WHERE status='aktif'")->fetchAll();
            $prodIds = array_column($prodRows, 'id');
            $prodPrices = array_column($prodRows, 'harga_jual', 'id');

            // Get admin user
            $adminId = $db->query("SELECT id FROM users LIMIT 1")->fetchColumn();

            $methods = ['tunai','tunai','tunai','kartu_debit','kartu_kredit','e-wallet','e-wallet','transfer'];
            $totalTrx = 0;
            $totalDet = 0;

            $stmtTrx = $db->prepare("INSERT INTO transactions (no_invoice, tanggal_transaksi, customer_id, user_id, total_item, subtotal, diskon, pajak, total_bayar, jumlah_dibayar, kembalian, metode_pembayaran, status) VALUES (?,?,?,?,?,?,0,0,?,?,?,?,?)");
            $stmtDet = $db->prepare("INSERT INTO transaction_details (transaction_id, product_id, harga_satuan, jumlah, diskon_item, subtotal) VALUES (?,?,?,?,0,?)");

            $db->beginTransaction();

            foreach ($customers as $custId) {
                $freq = rand(10, 60);

                for ($t = 0; $t < $freq; $t++) {
                    // Random date in 2025
                    $month = rand(1, 12);
                    $day = rand(1, 28);
                    $hour = rand(7, 21);
                    $min = rand(0, 59);
                    $sec = rand(0, 59);
                    $tanggal = sprintf('2025-%02d-%02d %02d:%02d:%02d', $month, $day, $hour, $min, $sec);

                    $invoice = 'INV-' . date('Ymd', strtotime($tanggal)) . '-' . str_pad($custId, 4, '0', STR_PAD_LEFT) . '-' . str_pad($t + 1, 3, '0', STR_PAD_LEFT);

                    // Random items 1-10
                    $itemCount = rand(1, 10);
                    $usedProds = array_rand(array_flip($prodIds), min($itemCount, count($prodIds)));
                    if (!is_array($usedProds)) $usedProds = [$usedProds];

                    $totalBayar = 0;
                    $totalItem = 0;
                    $detailRows = [];

                    foreach ($usedProds as $pid) {
                        $qty = rand(1, 10);
                        $harga = $prodPrices[$pid];
                        $sub = $harga * $qty;
                        $totalBayar += $sub;
                        $totalItem += $qty;
                        $detailRows[] = [$pid, $harga, $qty, $sub];
                    }

                    $metode = $methods[array_rand($methods)];
                    $dibayar = $metode === 'tunai' ? (int)(ceil($totalBayar / 10000) * 10000) : $totalBayar;
                    $kembalian = $dibayar - $totalBayar;

                    $stmtTrx->execute([$invoice, $tanggal, $custId, $adminId, $totalItem, $totalBayar, $totalBayar, $dibayar, $kembalian, $metode, 'selesai']);
                    $trxId = $db->lastInsertId();
                    $totalTrx++;

                    foreach ($detailRows as $dr) {
                        $stmtDet->execute([$trxId, $dr[0], $dr[1], $dr[2], $dr[3]]);
                        $totalDet++;
                    }
                }
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => "Batch selesai",
                'transactions' => $totalTrx,
                'details' => $totalDet
            ]);
            break;

        // ===================== STEP 4: UPDATE STATS =====================
        case 'update_stats':
            $db->exec("
                UPDATE customers c SET
                    total_transaksi = (SELECT COUNT(*) FROM transactions t WHERE t.customer_id = c.id AND t.status = 'selesai'),
                    total_belanja = (SELECT COALESCE(SUM(t.total_bayar), 0) FROM transactions t WHERE t.customer_id = c.id AND t.status = 'selesai')
            ");
            echo json_encode(['success' => true, 'message' => 'Statistik pelanggan berhasil diperbarui']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Step tidak dikenal: ' . $step]);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
