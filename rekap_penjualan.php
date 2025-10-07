<?php
session_start();
include "koneksi/connect_db.php";
date_default_timezone_set('Asia/Jakarta');

// Kalau belum login atau bukan admin → kembali ke index.php
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// === Ambil tipe rekap ===
$tipe = $_GET['tipe'] ?? 'bulanan';
$bulan_aktif = $_GET['bulan'] ?? date('Y-m');
$tanggal_aktif = $_GET['tanggal'] ?? date('Y-m-d');

// === Buat filter SQL ===
$where = "";
if ($tipe == "bulanan") {
    $where = "DATE_FORMAT(r.tanggal, '%Y-%m') = '$bulan_aktif'";
} else {
    $where = "DATE(r.tanggal) = '$tanggal_aktif'";
}

// === Hitung total pendapatan ===
$q_total = "
    SELECT SUM(r.total_harga) AS pendapatan
    FROM rekap_penjualan r
    WHERE $where AND r.status = 'BELUM'
";
$res_total = $db->query($q_total);
$total_pendapatan = 0;
if ($res_total && $row = $res_total->fetch_assoc()) {
    $total_pendapatan = $row['pendapatan'] ?? 0;
}

// === Query data utama, dikelompokkan per meja + jam+menit ===
$query = "
    SELECT 
        r.meja,
        DATE_FORMAT(r.tanggal, '%H:%i') AS jam_menit,
        GROUP_CONCAT(CONCAT(m.nama, ' (', r.jumlah, ')') SEPARATOR ', ') AS menu_list,
        SUM(r.jumlah) AS total_jumlah,
        SUM(r.total_harga) AS total_harga
    FROM rekap_penjualan r
    JOIN menu m ON r.product_id = m.id
    WHERE $where AND r.status = 'BELUM'
    GROUP BY r.meja, jam_menit
    ORDER BY MAX(r.tanggal) DESC
";


$result = $db->query($query);

?>
<!DOCTYPE html>
<html>

<head>
    <title>Dhanar Project</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/png" href="assets/image/logo_cafe.png">
    <style>
        body {
            min-height: 100vh;
            /* set tinggi minimum layar penuh */
            display: flex;
            flex-direction: column;
            /* susun vertikal */
        }

        main {
            flex: 1;
            /* biar isi konten dorong footer ke bawah */
        }
    </style>
</head>

<body>
    <?php include "header.php"; ?>
    <main>
        <div class="container mt-4">

            <h3 class="mb-4 text-center">Rekap Pesanan Belum Lunas</h3>

            <!-- Filter Form -->
            <form method="get" class="row g-2 mb-3">
                <div class="col-auto">
                    <select name="tipe" class="form-select" onchange="this.form.submit()">
                        <option value="bulanan" <?= $tipe == 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
                        <option value="harian" <?= $tipe == 'harian' ? 'selected' : '' ?>>Harian</option>
                    </select>
                </div>
                <div class="col-auto">
                    <?php if ($tipe == 'bulanan'): ?>
                        <input type="month" name="bulan" value="<?= $bulan_aktif ?>" class="form-control" onchange="this.form.submit()">
                    <?php else: ?>
                        <input type="date" name="tanggal" value="<?= $tanggal_aktif ?>" class="form-control" onchange="this.form.submit()">
                    <?php endif; ?>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <!-- Total Pendapatan -->
                <h5 class="mb-0">
                    Total Pendapatan:
                    <span id="total-pendapatan" class="text-dark">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></span>
                </h5>
            </div>

            <!-- Tabel Data -->
            <div id="tabel-data" class="table-responsive mt-4">
                <table class="table table-bordered table-striped">
                    <thead class="table-primary text-center">
                        <tr>
                            <th>Meja</th>
                            <th>Jam</th>
                            <th>Menu</th>
                            <th>Jumlah</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center"><?= $row['meja'] ?></td>
                                    <td class="text-center"><?= $row['jam_menit'] ?></td>
                                    <td><?= $row['menu_list'] ?></td>
                                    <td class="text-center"><?= $row['total_jumlah'] ?></td>
                                    <td class="text-end"><?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Tidak ada data</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
    </main>
    </div>
    <hr class="mt-5">
    <footer class="text-center py-4 mt-4">
        <p class="mb-0">© 2025 masdhanar | Elkusa Cafe</p>
    </footer>

    <script>
        // reload div tabel-data setiap 5 detik
        setInterval(function() {
            fetch(window.location.href) // ambil ulang halaman sekarang
                .then(response => response.text())
                .then(html => {
                    // ambil isi tabel-data dari halaman baru
                    let parser = new DOMParser();
                    let doc = parser.parseFromString(html, "text/html");
                    let newTable = doc.querySelector("#tabel-data");
                    document.querySelector("#tabel-data").innerHTML = newTable.innerHTML;
                })
                .catch(err => console.error("Gagal reload tabel:", err));
        }, 1000); // 1000ms = 1 detik

        // reload total pendapatan setiap 1 detik
        setInterval(function() {
            fetch(window.location.href) // ambil ulang halaman sekarang
                .then(response => response.text())
                .then(html => {
                    let parser = new DOMParser();
                    let doc = parser.parseFromString(html, "text/html");
                    let newTotal = doc.querySelector("#total-pendapatan");
                    if (newTotal) {
                        document.querySelector("#total-pendapatan").innerHTML = newTotal.innerHTML;
                    }
                })
                .catch(err => console.error("Gagal reload total pendapatan:", err));
        }, 1000); // 1000ms = 1 detik
    </script>


</body>

</html>