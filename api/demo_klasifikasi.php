<?php
// Koneksi ke database
include "conn.php";

if (!$conn) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}

// Ambil data training
$queryDataTraining = mysqli_query($conn, '
    SELECT nama, jurusan, pai, bi, mtk, sej, bing, senbud, ok, fis, jw 
    FROM data_training 
    JOIN jurusan ON data_training.id_jurusan = jurusan.id_jurusan
');

// Ambil data testing
$queryDataTesting = mysqli_query($conn, '
    SELECT nama, jurusan, pai, bi, mtk, sej, bing, senbud, ok, fis, jw 
    FROM data_testing 
    JOIN jurusan ON data_testing.id_jurusan = jurusan.id_jurusan
');

// Konversi hasil query menjadi array
$dataTraining = [];
while ($row = mysqli_fetch_assoc($queryDataTraining)) {
    $dataTraining[] = $row;
}

$dataTesting = [];
while ($row = mysqli_fetch_assoc($queryDataTesting)) {
    $dataTesting[] = $row;
}

// Fungsi untuk menghitung jarak Euclidean
function calculateEuclideanDistance($data1, $data2, $attributes)
{
    $sum = 0;
    foreach ($attributes as $attribute) {
        $sum += pow($data1[$attribute] - $data2[$attribute], 2);
    }
    return sqrt($sum);
}

// Atribut yang digunakan untuk perhitungan
$attributes = ['pai', 'bi', 'mtk', 'sej', 'bing', 'senbud', 'ok', 'fis', 'jw'];

// Jumlah tetangga terdekat (K)
$k = 23;

// Menyimpan hasil prediksi dan klasifikasi
$result = [];

// Menyimpan hitung TP, FP, TN, FN per kelas
$TP = $FP = $TN = $FN = [];
$classes = [];

// Perulangan untuk setiap data testing
foreach ($dataTesting as $test) {
    $distances = [];

    // Hitung jarak ke setiap data training
    foreach ($dataTraining as $train) {
        $distance = calculateEuclideanDistance($test, $train, $attributes);
        $distances[] = [
            'jurusan' => $train['jurusan'],
            'distance' => $distance
        ];
    }

    // Urutkan jarak dari terkecil ke terbesar
    usort($distances, function ($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    // Ambil K tetangga terdekat
    $nearestNeighbors = array_slice($distances, 0, $k);

    // Hitung kelas mayoritas
    $classCount = [];
    foreach ($nearestNeighbors as $neighbor) {
        $class = $neighbor['jurusan'];
        if (!isset($classCount[$class])) {
            $classCount[$class] = 0;
        }
        $classCount[$class]++;
    }

    // Ambil kelas mayoritas
    $majorityClass = array_keys($classCount, max($classCount))[0];

    // Tentukan hasil pencarian (TP, FP, FN, TN)
    $resultItem = [
        'nama' => $test['nama'],
        'jurusan_aktual' => $test['jurusan'],
        'jurusan_prediksi' => $majorityClass
    ];

    // Tambahkan hasil pencarian ke array hasil
    $result[] = $resultItem;

    // Menyimpan TP, FP, FN, TN untuk setiap kelas
    $actualClass = $test['jurusan'];
    if (!isset($TP[$actualClass])) {
        $TP[$actualClass] = 0;
        $FP[$actualClass] = 0;
        $TN[$actualClass] = 0;
        $FN[$actualClass] = 0;
    }

    // Hitung TP, FP, FN, TN untuk setiap kelas
    foreach ($classes as $class) {
        if ($class == $actualClass) {
            // TP jika kelas aktual sama dengan kelas prediksi
            if ($actualClass == $majorityClass) {
                $TP[$class]++;
            } else {
                // FN jika kelas aktual salah diprediksi
                $FN[$class]++;
            }
        } else {
            // FP jika kelas prediksi salah
            if ($majorityClass == $class) {
                $FP[$class]++;
            } else {
                // TN jika kelas prediksi benar (bukan kelas ini)
                $TN[$class]++;
            }
        }
    }

    // Update jumlah kelas untuk digunakan dalam perhitungan
    if (!in_array($actualClass, $classes)) {
        $classes[] = $actualClass;
    }
}

// Menghitung Accuracy
$total_data = count($dataTesting);
$TP_total = array_sum($TP); // Jumlahkan semua TP untuk setiap kelas
$accuracy = $TP_total / $total_data;

// Menghitung Precision, Recall, dan F1 Score untuk setiap kelas
$precision = [];
$recall = [];
$f1_score = [];
foreach ($classes as $class) {
    $TP_class = $TP[$class]; // TP untuk kelas ini
    $FP_class = $FP[$class]; // FP untuk kelas ini
    $FN_class = $FN[$class]; // FN untuk kelas ini

    // Precision
    $precision[$class] = $TP_class / ($TP_class + $FP_class);

    // Recall
    $recall[$class] = $TP_class / ($TP_class + $FN_class);

    // F1 Score
    $f1_score[$class] = 2 * ($precision[$class] * $recall[$class]) / ($precision[$class] + $recall[$class]);
}

// Menghitung Macro Average
$precision_macro = array_sum($precision) / count($precision);
$recall_macro = array_sum($recall) / count($recall);
$f1_score_macro = array_sum($f1_score) / count($f1_score);

// Menghitung Weighted Average (dengan bobot jumlah data di tiap kelas)
$precision_weighted = 0;
$recall_weighted = 0;
$f1_score_weighted = 0;
foreach ($classes as $class) {
    $class_size = count(array_filter($dataTesting, function ($item) use ($class) {
        return $item['jurusan'] == $class;
    })); // Jumlah data di kelas ini
    $precision_weighted += $precision[$class] * $class_size;
    $recall_weighted += $recall[$class] * $class_size;
    $f1_score_weighted += $f1_score[$class] * $class_size;
}
$precision_weighted /= $total_data;
$recall_weighted /= $total_data;
$f1_score_weighted /= $total_data;

// Menampilkan hasil dalam format JSON dengan TP, FP, TN, FN
echo json_encode([
    'result' => $result,
    'total_data' => $total_data,
    'TP' => $TP,
    'FP' => $FP,
    'TN' => $TN,
    'FN' => $FN,
    'accuracy' => $accuracy,
    'precision_macro' => $precision_macro,
    'recall_macro' => $recall_macro,
    'f1_score_macro' => $f1_score_macro,
    'precision_weighted' => $precision_weighted,
    'recall_weighted' => $recall_weighted,
    'f1_score_weighted' => $f1_score_weighted
]);

// Tutup koneksi database
mysqli_close($conn);
