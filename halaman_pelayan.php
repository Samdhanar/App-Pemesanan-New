<?php
session_start();
include "koneksi/connect_db.php";
// Kalau belum login atau bukan pelayan → kembali ke index.php
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'pelayan') {
    header("Location: index.php");
    exit;
}

// Jika ada aksi selesai
if (isset($_GET['selesai']) && isset($_GET['jam'])) {
    $meja = intval($_GET['selesai']);
    $jamMenit = $_GET['jam']; // format HH:ii

    $db->query("
    INSERT INTO rekap_penjualan (meja, product_id, jumlah, total_harga, tanggal, status)
    SELECT meja, product_id, SUM(jumlah) AS jumlah, SUM(total_harga) AS total_harga, NOW(), 'BELUM'
    FROM pesanan 
    WHERE meja = '$meja'
        AND TIME(tanggal) BETWEEN STR_TO_DATE('$jamMenit:00','%H:%i:%s') 
        AND STR_TO_DATE('$jamMenit:59','%H:%i:%s')
    GROUP BY meja, product_id
");


    $db->query("
        DELETE FROM pesanan 
        WHERE meja = '$meja' AND DATE_FORMAT(tanggal, '%H:%i') = '$jamMenit'
    ");

    header("Location: halaman_pelayan.php");
    exit;
}

// ============= MODE TABLE UNTUK AJAX =============
if (isset($_GET['mode']) && $_GET['mode'] === 'table') {
    $query = "
        SELECT 
            x.meja,
            DATE_FORMAT(x.waktu, '%H:%i') AS jam_menit,
            GROUP_CONCAT(CONCAT(m.nama, ' (', x.jumlah, ')') SEPARATOR ', ') AS daftar_pesanan,
            SUM(x.total_harga) AS total_harga
        FROM (
            SELECT 
                p.meja,
                p.product_id,
                SUM(p.jumlah) AS jumlah,
                SUM(p.total_harga) AS total_harga,
                MIN(p.tanggal) AS waktu
            FROM pesanan p
            GROUP BY p.meja, p.product_id, DATE_FORMAT(p.tanggal, '%H:%i')
        ) x
        JOIN menu m ON m.id = x.product_id
        GROUP BY x.meja, jam_menit
        ORDER BY x.meja ASC, jam_menit ASC
    ";
    $result = $db->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td class='text-center'>{$row['meja']}</td>
                    <td class='text-center'>{$row['jam_menit']}</td>
                    <td>{$row['daftar_pesanan']}</td>
                    <td>Rp " . number_format($row['total_harga'], 0, ',', '.') . "</td>
                    <td class='text-center'>
                        <a href='?selesai={$row['meja']}&jam=" . urlencode($row['jam_menit']) . "' 
                           class='btn btn-success btn-sm'
                           onclick=\"return confirm('Pesanan meja {$row['meja']} jam {$row['jam_menit']} sudah selesai?')\">
                           Selesai
                        </a>
                    </td>
                </tr>";
        }
    } else {
        echo "<tr><td colspan='5' class='text-center'>Belum ada pesanan</td></tr>";
    }
    exit; // penting agar tidak render full HTML
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dhanar Project</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/png" href="assets/image/kedai_sor_sawo.jpg">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
        }

        .page-wrapper {
            border: 2px solid #0d6efd;
            border-radius: 10px;
            padding: 20px;
            background: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .page-title {
            background-color: #0d6efd;
            color: white;
            padding: 12px;
            font-weight: bold;
            text-align: left;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px -20px;
        }
    </style>
</head>

<body>
    <!-- HEADER NAVBAR -->
    <?php include 'head_kasir_pelayan.php'; ?>
    <!-- END HEADER -->

    <main>
        <div class="container mt-5">
            <div class="page-wrapper position-relative">
                <h2 class="page-title"> Pelayan</h2>

                <!-- Input Pencarian -->
                <div class="mb-3">
                    <input type="number" id="cariPesanan" class="form-control" placeholder="Cari Meja" onkeyup="applyFilter()">
                </div>

                <!-- Tabel Pesanan -->
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-primary text-center">
                        <tr>
                            <th>Meja</th>
                            <th>Jam</th>
                            <th>Daftar Pesanan</th>
                            <th>Total Harga</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tabelPesanan">
                        <?php
                        $query = "
                            SELECT 
                                x.meja,
                                DATE_FORMAT(x.waktu, '%H:%i') AS jam_menit,
                                GROUP_CONCAT(CONCAT(m.nama, ' (', x.jumlah, ')') SEPARATOR ', ') AS daftar_pesanan,
                                SUM(x.total_harga) AS total_harga
                            FROM (
                                SELECT 
                                    p.meja,
                                    p.product_id,
                                    SUM(p.jumlah) AS jumlah,
                                    SUM(p.total_harga) AS total_harga,
                                    MIN(p.tanggal) AS waktu
                                FROM pesanan p
                                GROUP BY p.meja, p.product_id, DATE_FORMAT(p.tanggal, '%H:%i')
                            ) x
                            JOIN menu m ON m.id = x.product_id
                            GROUP BY x.meja, jam_menit
                            ORDER BY x.meja ASC, jam_menit ASC
                        ";
                        $result = $db->query($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                    <td class='text-center'>{$row['meja']}</td>
                                    <td class='text-center'>{$row['jam_menit']}</td>
                                    <td>{$row['daftar_pesanan']}</td>
                                    <td>Rp " . number_format($row['total_harga'], 0, ',', '.') . "</td>
                                    <td class='text-center'>
                                        <a href='?selesai={$row['meja']}&jam=" . urlencode($row['jam_menit']) . "' 
                                        class='btn btn-success btn-sm'
                                        onclick=\"return confirm('Pesanan meja {$row['meja']} jam {$row['jam_menit']} sudah selesai?')\">
                                        Selesai
                                        </a>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center'>Belum ada pesanan</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
    </main>

    <!-- Footer -->
    <hr>
    <footer class="bg-light text-center py-4">
        <p class="mb-0">© 2025 Mas Dhanar || Kedai Sor Sawo</p>
    </footer>

    <!-- Script AJAX reload + Live Search -->
    <script>
        // Fungsi reload tabel
        function reloadTabel() {
            fetch("halaman_pelayan.php?mode=table")
                .then(res => res.text())
                .then(html => {
                    document.getElementById("tabelPesanan").innerHTML = html;
                    applyFilter(); // tetap filter setelah reload
                });
        }
        setInterval(reloadTabel, 1000); // reload tiap 1 detik

        // ========== FILTER PENCARIAN BERDASARKAN NOMOR MEJA ==========
        function applyFilter() {
            let input = document.getElementById("cariPesanan").value.toLowerCase(); // Ambil teks input dan ubah jadi huruf kecil
            let rows = document.querySelectorAll("#tabelPesanan tr"); // Ambil semua baris tabel
            let found = false; // Penanda apakah ada data yang cocok

            // Hapus pesan lama jika ada
            let notFoundMsg = document.getElementById("notFoundMsg");
            if (notFoundMsg) notFoundMsg.remove();

            // Jika input kosong → tampilkan semua data dan hentikan fungsi
            if (input.trim() === "") {
                rows.forEach(row => row.style.display = "");
                return;
            }

            // Loop setiap baris untuk mencocokkan data meja
            rows.forEach(row => {
                let mejaCell = row.querySelector("td:first-child"); // Ambil kolom pertama (nomor meja)
                if (mejaCell) {
                    let mejaText = mejaCell.innerText.toLowerCase();
                    if (mejaText.includes(input)) {
                        row.style.display = ""; // Tampilkan baris jika cocok
                        found = true; // Tandai ada hasil
                    } else {
                        row.style.display = "none"; // Sembunyikan baris jika tidak cocok
                    }
                }
            });

            // Jika tidak ada hasil yang cocok, tampilkan pesan
            if (!found) {
                let msg = document.createElement("tr"); // Buat elemen baris baru
                msg.id = "notFoundMsg"; // Beri ID agar mudah dihapus nanti
                msg.innerHTML = `
            <td colspan="100%" class="text-center text-danger fw-bold py-3">
                Meja tidak ditemukan
            </td>
        `;
                document.getElementById("tabelPesanan").appendChild(msg); // Tambahkan pesan ke tabel
            }
        }
    </script>
</body>

</html>