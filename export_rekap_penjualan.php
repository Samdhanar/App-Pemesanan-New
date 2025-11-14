<?php
include "koneksi/connect_db.php";
session_start();

// Cek login admin
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Ambil parameter filter dari URL
$tipe = $_GET['tipe'] ?? 'bulanan';       // 'harian' atau 'bulanan'
$bulan = $_GET['bulan'] ?? date('Y-m');    // untuk bulanan
$tanggal = $_GET['tanggal'] ?? date('Y-m-d'); // untuk harian

// Header Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=rekap_penjualan.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Filter WHERE sesuai tipe
$where = "r.status = 'LUNAS'";
if ($tipe === 'harian') {
    $where .= " AND DATE(r.tanggal) = '" . date('Y-m-d', strtotime($tanggal)) . "'";
} elseif ($tipe === 'bulanan') {
    $where .= " AND DATE_FORMAT(r.tanggal, '%Y-%m') = '" . date('Y-m', strtotime($bulan)) . "'";
}

// Query ambil data
$query = "
    SELECT 
        r.meja,
        DATE_FORMAT(r.tanggal, '%H:%i') AS jam,
        GROUP_CONCAT(CONCAT(m.nama, ' (', r.jumlah, ')') SEPARATOR ', ') AS menu_list,
        SUM(r.jumlah) AS total_jumlah,
        SUM(r.total_harga) AS total_harga
    FROM rekap_penjualan r
    JOIN menu m ON r.product_id = m.id
    WHERE $where
    GROUP BY r.meja, jam
    ORDER BY MAX(r.tanggal) DESC
";

$result = $db->query($query);
if (!$result) {
    die("Query Error: " . $db->error);
}

// Tampilkan tabel Excel
echo "<table border='1'>";
echo "<tr>
        <th>Meja</th>
        <th>Jam</th>
        <th>Menu</th>
        <th>Jumlah</th>
        <th>Total Harga</th>
      </tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$row['meja']}</td>
            <td>{$row['jam']}</td>
            <td>{$row['menu_list']}</td>
            <td>{$row['total_jumlah']}</td>
            <td>{$row['total_harga']}</td>
          </tr>";
}

echo "</table>";
exit;
?>
