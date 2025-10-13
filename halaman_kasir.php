<?php
include "koneksi/connect_db.php";
date_default_timezone_set('Asia/Jakarta');
session_start();

// Kalau belum login atau bukan kasir → kembali ke index.php
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'kasir') {
    header("Location: index.php");
    exit;
}

// ================== PROSES PEMBAYARAN ==================
if (isset($_POST['aksi']) && $_POST['aksi'] == "bayar") {
    $meja = $_POST['meja'];
    $jam_menit = $_POST['jam_menit'];
    $bayar = intval($_POST['bayar']);
    $diskon = intval($_POST['diskon']);

    $q = $db->query("
        SELECT SUM(total_harga) as total_harga, MAX(tanggal) as tanggal
        FROM rekap_penjualan 
        WHERE meja='$meja' AND DATE_FORMAT(tanggal, '%H:%i')='$jam_menit' AND status='BELUM'
    ");
    $d = $q->fetch_assoc();
    $total = $d['total_harga'];

    $total_setelah_diskon = $total - ($total * $diskon / 100);

    if ($bayar < $total_setelah_diskon) {
        echo "<script>alert('Uang bayar kurang dari total belanja!'); window.location='halaman_kasir.php';</script>";
        exit;
    }

    $db->query("UPDATE rekap_penjualan 
            SET status='LUNAS'
            WHERE meja='$meja' AND DATE_FORMAT(tanggal, '%H:%i')='$jam_menit' AND status='BELUM'");

    header("Location: struk.php?meja=$meja&jam_menit=$jam_menit&bayar=$bayar&diskon=$diskon");
    exit;
}

// ================== MODE TABEL SAJA (AJAX) ==================
if (isset($_GET['mode']) && $_GET['mode'] === 'table') {
    $query = "
        SELECT 
            r.meja,
            DATE_FORMAT(r.tanggal, '%H:%i') AS jam_menit,
            GROUP_CONCAT(CONCAT(m.nama, ' (', r.jumlah, ')') SEPARATOR ', ') AS daftar_pesanan,
            SUM(r.total_harga) AS total_harga
        FROM rekap_penjualan r
        JOIN menu m ON r.product_id = m.id
        WHERE r.status = 'BELUM'
        GROUP BY r.meja, jam_menit
        ORDER BY MAX(r.tanggal) DESC
    ";
    $result = $db->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td class='text-center'>{$row['meja']}</td>
                    <td class='text-center'>{$row['jam_menit']}</td>
                    <td>{$row['daftar_pesanan']}</td>
                    <td class='text-start'>Rp " . number_format($row['total_harga'], 0, ',', '.') . "</td>
                    <td class='text-center'>
                        <button class='btn btn-success btn-sm'
                            onclick=\"pembayaran('{$row['meja']}', '{$row['jam_menit']}', {$row['total_harga']})\">
                            Pembayaran
                        </button>
                    </td>
                </tr>";
        }
    } else {
        echo "<tr><td colspan='5' class='text-center'>Belum ada pesanan</td></tr>";
    }
    exit; // hentikan supaya tidak render full HTML
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
    <script>
        // Pembayaran
        function pembayaran(meja, jam_menit, total) {
            let diskon = prompt("Masukkan diskon (%) jika ada:", "0");
            if (diskon === null || isNaN(diskon) || diskon < 0) diskon = 0;

            let total_setelah_diskon = total - (total * diskon / 100);

            alert("Total belanja: Rp" + total.toLocaleString() +
                "\nDiskon: " + diskon + "%" +
                "\nTotal setelah diskon: Rp" + total_setelah_diskon.toLocaleString());

            let bayar = prompt("Masukkan jumlah uang bayar (Total: Rp" + total_setelah_diskon.toLocaleString() + "):", "");
            if (bayar && !isNaN(bayar)) {
                if (parseInt(bayar) < total_setelah_diskon) {
                    alert("Uang bayar kurang dari total belanja!");
                    return;
                }
                let form = document.getElementById("formBayar");
                form.meja.value = meja;
                form.jam_menit.value = jam_menit;
                form.bayar.value = bayar;
                form.diskon.value = diskon;
                form.submit();
            }
        }

        // ================ PENCARIAN NOMOR MEJA ================
        function cariData() {
            let input = document.getElementById("searchInput").value.toLowerCase(); // "searchInput" didapat dari id="searchInput" pada HTML input
            let rows = document.querySelectorAll("#kasirBody tr"); // "kasirBody" didapat dari id="kasirBody" pada HTML table body
            let found = false; // penanda apakah ada data yang cocok

            rows.forEach(row => { //Loop Setiap baris tabel
                let mejaCell = row.querySelector("td:first-child"); // Ambil kolom pertama (Meja)
                if (mejaCell) { // Pastikan Kolom Meja Ada
                    let mejaText = mejaCell.innerText.toLowerCase(); //Lowercase untuk pencocokan tidak case-sensitive (huruf besar kecil bisa)
                    if (mejaText.includes(input)) {
                        row.style.display = ""; // Tampilkan Baris
                        found = true; // ada data yang cocok
                    } else {
                        row.style.display = "none"; // Sembunyikan Baris
                    }
                }
            });

            // cek apakah sudah ada elemen pesan
            let notFoundMsg = document.getElementById("notFoundMsg");

            // kalau tidak ada hasil pencarian
            if (!found) {
                if (!notFoundMsg) { //Jika Pesan belum ada buat elemen baru
                    let msg = document.createElement("tr"); // Buat Baris Baru di tabel
                    msg.id = "notFoundMsg"; // Buat ID supaya bisa dihapus nanti
                    msg.innerHTML = `<td colspan="100%" class="text-center text-danger fw-bold py-3">Meja tidak ada</td>`;
                    document.getElementById("kasirBody").appendChild(msg);
                }
            } else {
                // kalau ada hasil, hapus pesan jika sebelumnya muncul
                if (notFoundMsg) notFoundMsg.remove();
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("searchInput").addEventListener("keyup", cariData); // saat user mengetik di input pencarian jalankan fungsi cariData
            loadTabelKasir(); // muat ulang tabel kasir
        });

        // 🔄 Reload tabel otomatis
        function loadTabelKasir() {
            fetch("halaman_kasir.php?mode=table")
                .then(response => response.text())
                .then(data => {
                    document.getElementById("kasirBody").innerHTML = data;
                    cariData(); // tetap filter jika user sedang mencari
                });
        }

        setInterval(loadTabelKasir, 1000); // reload tiap 1 detik
    </script>
</head>

<body>
    <?php include "head_kasir_pelayan.php"; ?>
    <main>
        <div class="bg-light">
            <form method="POST" id="formBayar" style="display:none;">
                <input type="hidden" name="aksi" value="bayar">
                <input type="hidden" name="meja">
                <input type="hidden" name="jam_menit">
                <input type="hidden" name="bayar">
                <input type="hidden" name="diskon">
            </form>

            <div class="container mt-5">
                <div class="page-wrapper position-relative">
                    <h2 class="page-title"> Kasir</h2>
                    <div class="card-body">

                        <!-- 🔎 Input Pencarian -->
                        <div class="mb-3">
                            <input type="number" id="searchInput" class="form-control" placeholder="Cari Meja">
                        </div>

                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-primary text-center">
                                <tr>
                                    <th class="text-center">Meja</th>
                                    <th class="text-center">Jam</th>
                                    <th class="text-center">Daftar Pesanan</th>
                                    <th class="text-center">Total Harga</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="kasirBody">
                                <?php
                                $query = "
                                    SELECT 
                                        r.meja,
                                        DATE_FORMAT(r.tanggal, '%H:%i') AS jam_menit,
                                        GROUP_CONCAT(CONCAT(m.nama, ' (', r.jumlah, ')') SEPARATOR ', ') AS daftar_pesanan,
                                        SUM(r.total_harga) AS total_harga
                                    FROM rekap_penjualan r
                                    JOIN menu m ON r.product_id = m.id
                                    WHERE r.status = 'BELUM'
                                    GROUP BY r.meja, jam_menit
                                    ORDER BY MAX(r.tanggal) DESC
                            ";

                                $result = $db->query($query);
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>
                                                <td class='text-center'>{$row['meja']}</td>
                                                <td class='text-center'>{$row['jam_menit']}</td>
                                                <td>{$row['daftar_pesanan']}</td>
                                                <td class='text-start'>Rp " . number_format($row['total_harga'], 0, ',', '.') . "</td>
                                                <td class='text-center'>
                                                    <button class='btn btn-success btn-sm'
                                                        onclick=\"pembayaran('{$row['meja']}', '{$row['jam_menit']}', {$row['total_harga']})\">
                                                        Pembayaran
                                                    </button>
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
                </div>
            </div>
    </main>
    <hr>
    <footer class="bg-light text-center py-4">
        <p class="mb-0">© 2025 Mas Dhanar || Kedai Sor Sawo</p>
    </footer>
</body>

</html>