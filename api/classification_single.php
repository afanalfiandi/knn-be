<?php
include 'conn.php';

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// $nama = $_GET['nama'];
// $pai = $_GET['pai'];
// $bi = $_GET['bi'];
// $mtk = $_GET['mtk'];
// $sej = $_GET['sej'];
// $bing = $_GET['bing'];
// $senbud = $_GET['senbud'];
// $ok = $_GET['ok'];
// $fis = $_GET['fis'];
// $jw = $_GET['jw'];
// $jurusan = $_GET['jurusan'];

$nama = "Rundo";
$pai = 71;
$bi = 72;
$mtk = 86;
$sej = 90;
$bing = 28;
$senbud = 67;
$ok = 87;
$fis = 84;
$jw = 65;
$jurusan = 1;

$checkData = mysqli_query($conn, "SELECT * FROM data_testing WHERE nama = '$nama'");
$resultCheck = mysqli_fetch_array($checkData);

if ($resultCheck === null) {
    $insertDB = mysqli_query($conn, "INSERT INTO data_testing (id_jurusan, nama, pai, bi, mtk, sej, bing, senbud, ok, fis, jw) 
    VALUES ('$jurusan', '$nama', '$pai', '$bi', '$mtk', '$sej', '$bing', '$senbud', '$ok', '$fis', '$jw') 
    ");

    if ($insertDB) {
        getCalculationResult($nama, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $jurusan);
    }
} else {
    getCalculationResult($nama, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $jurusan);
}


function getCalculationResult($nama, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $jurusan)
{
    global $conn;
    // Query untuk mengambil data training
    $query_training = "SELECT id_data_training, nama, jurusan.id_jurusan as id_jurusan_pilihan, jurusan.jurusan as jurusan_pilihan, pai, bi, mtk, sej, bing, senbud, ok, fis, jw
                   FROM data_training
                   JOIN jurusan ON data_training.id_jurusan = jurusan.id_jurusan";
    $result_training = $conn->query($query_training);

    // Query untuk mengambil data testing
    $query_testing = "SELECT id_data_testing, nama, jurusan.id_jurusan as id_jurusan_pilihan, jurusan.jurusan as jurusan_pilihan, pai, bi, mtk, sej, bing, senbud, ok, fis, jw
                  FROM data_testing
                  JOIN jurusan ON data_testing.id_jurusan = jurusan.id_jurusan";
    $result_testing = $conn->query($query_testing);

    // Query untuk mengambil daftar kelas
    $queryKelas = "SELECT * FROM jurusan";
    $resultKelas = $conn->query($queryKelas);

    // Ambil daftar kelas dalam array
    $kelas = [];
    while ($row = $resultKelas->fetch_assoc()) {
        $kelas[$row['id_jurusan']] = $row['jurusan'];
    }

    // Ambil data training dalam array
    $training_data = [];
    while ($row = $result_training->fetch_assoc()) {
        $training_data[] = $row;
    }

    // Ambil data testing dalam array
    $testing_data = [];
    while ($row = $result_testing->fetch_assoc()) {
        $testing_data[] = $row;
    }

    // Hitung nilai K (akar kuadrat dari jumlah data training)
    $num_training_data = count($training_data);
    $k = (int) sqrt($num_training_data); // Konversi ke integer

    // Fungsi untuk menghitung jarak Euclidean antara dua data
    function euclideanDistance($data1, $data2)
    {
        $sum = 0;
        $attributes = ['pai', 'bi', 'mtk', 'sej', 'bing', 'senbud', 'ok', 'fis', 'jw'];
        foreach ($attributes as $attribute) {
            $sum += pow($data1[$attribute] - $data2[$attribute], 2);
        }
        return sqrt($sum);
    }

    // Array untuk menyimpan perhitungan TP, FP, TN, FN per kelas
    $confusion_matrix = [];
    foreach ($kelas as $id_kelas => $nama_kelas) {
        $confusion_matrix[$id_kelas] = [
            'TP' => 0,
            'FP' => 0,
            'TN' => 0,
            'FN' => 0
        ];
    }

    // Array untuk menyimpan hasil klasifikasi
    $classification_results = [];
    $index = 1;

    // Klasifikasi KNN untuk setiap data testing
    foreach ($testing_data as $test) {
        $distances = [];
        foreach ($training_data as $train) {
            $distance = euclideanDistance($test, $train);
            $distances[] = ['distance' => $distance, 'id_jurusan_pilihan' => $train['id_jurusan_pilihan']];
        }

        // Urutkan berdasarkan jarak terdekat
        usort($distances, function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        // Ambil K tetangga terdekat
        $nearest_neighbors = array_slice($distances, 0, $k);

        // Hitung suara terbanyak untuk menentukan klasifikasi
        $votes = [];
        foreach ($nearest_neighbors as $neighbor) {
            $votes[$neighbor['id_jurusan_pilihan']] = ($votes[$neighbor['id_jurusan_pilihan']] ?? 0) + 1;
        }

        // Tentukan jurusan berdasarkan suara terbanyak
        arsort($votes);
        $predicted_jurusan_id = key($votes);
        $predicted_jurusan_name = $kelas[$predicted_jurusan_id];

        // Perhitungan TP, FP, TN, FN untuk setiap kelas
        $actual_label = $test['id_jurusan_pilihan'];
        $predicted_label = $predicted_jurusan_id;

        foreach ($kelas as $id_kelas => $nama_kelas) {
            if ($actual_label == $predicted_label && $actual_label == $id_kelas) {
                $confusion_matrix[$id_kelas]['TP']++;
            } elseif ($actual_label != $predicted_label && $actual_label == $id_kelas) {
                $confusion_matrix[$id_kelas]['FN']++;
            } elseif ($actual_label != $predicted_label && $predicted_label == $id_kelas) {
                $confusion_matrix[$id_kelas]['FP']++;
            } elseif ($actual_label != $predicted_label && $actual_label != $id_kelas && $predicted_label != $id_kelas) {
                $confusion_matrix[$id_kelas]['TN']++;
            }
        }

        // Simpan hasil klasifikasi dalam array hasil
        $classification_results = [
            'nomor' => $index++,
            'nama' => $test['nama'],
            'id_jurusan' => $test['id_jurusan_pilihan'],
            'jurusan' => $test['jurusan_pilihan'],
            'predicted_jurusan_id' => $predicted_jurusan_id,
            'predicted_jurusan' => $predicted_jurusan_name,
            'confusion_matrix' => $confusion_matrix[$predicted_jurusan_id] // Tambahkan hasil perhitungan untuk kelas yang diprediksi
        ];
    }

    // Fungsi untuk menghitung metrik dari TP, FP, TN, FN
    function calculateMetrics($TP, $FP, $TN, $FN)
    {
        $accuracy = ($TP + $TN) / ($TP + $FP + $FN + $TN);
        $precision = $TP / ($TP + $FP);
        $recall = $TP / ($TP + $FN);
        $f1_score = 2 * (($precision * $recall) / ($precision + $recall));

        return [
            'accuracy' => $accuracy,
            'precision' => $precision,
            'recall' => $recall,
            'f1_score' => $f1_score
        ];
    }

    // Menghitung metrik untuk setiap kelas
    $metrics_per_class = [];
    foreach ($kelas as $id_kelas => $nama_kelas) {
        $metrics_per_class[$id_kelas] = calculateMetrics(
            $confusion_matrix[$id_kelas]['TP'],
            $confusion_matrix[$id_kelas]['FP'],
            $confusion_matrix[$id_kelas]['TN'],
            $confusion_matrix[$id_kelas]['FN']
        );
    }

    // Menghitung metrik agregat
    $total_tp = 0;
    $total_fp = 0;
    $total_tn = 0;
    $total_fn = 0;

    foreach ($confusion_matrix as $cm) {
        $total_tp += $cm['TP'];
        $total_fp += $cm['FP'];
        $total_tn += $cm['TN'];
        $total_fn += $cm['FN'];
    }

    // Hitung metrik agregat
    $aggregate_metrics = calculateMetrics(
        $total_tp,
        $total_fp,
        $total_tn,
        $total_fn
    );

    // Tambahkan total TP, FP, TN, FN ke aggregate_metrics
    $aggregate_metrics['TP'] = $total_tp;
    $aggregate_metrics['FP'] = $total_fp;
    $aggregate_metrics['TN'] = $total_tn;
    $aggregate_metrics['FN'] = $total_fn;

    // Format hasil sebagai JSON
    header('Content-Type: application/json');

    $akurasi = $aggregate_metrics['accuracy'];
    mysqli_query($conn, "INSERT INTO klasifikasi (id_jurusan, nama, pai, bi, mtk, sej, bing, senbud, ok, fis, jw, hasil, akurasi) 
            VALUES ('$jurusan', '$nama', '$pai', '$bi', '$mtk', '$sej', '$bing', '$senbud', '$ok', '$fis', '$jw', '$predicted_jurusan_id', '$akurasi')");
    echo json_encode([
        'classification_results' => $classification_results,
        'confusion_matrix' => $confusion_matrix,
        'metrics_per_class' => $metrics_per_class,
        'aggregate_metrics' => $aggregate_metrics
    ], JSON_PRETTY_PRINT);
}

function checkExist($nama)
{
    global $conn;

    $checkData = mysqli_query($conn, "SELECT * FROM data_testing WHERE nama = '$nama'");
    $resultCheck = mysqli_fetch_array($checkData);

    return $resultCheck === null ? false : true;
}
$conn->close();
