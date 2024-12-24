<?php
include 'conn.php';
// get all data testing
$getData = mysqli_query($conn, "SELECT id_klasifikasi, nama, pai, klasifikasi.id_jurusan as id_jurusan_aktual, klasifikasi.hasil as id_jurusan_prediksi, bi, mtk, sej, bing, senbud, ok, fis, bj, hasil, akurasi, a.jurusan as jurusan_pilihan, b.jurusan as jurusan_rekomendasi FROM klasifikasi
JOIN jurusan as a ON klasifikasi.id_jurusan = a.id_jurusan
JOIN jurusan as b ON klasifikasi.id_jurusan = b.id_jurusan");

// get all data jurusan
$getJurusan = mysqli_query($conn, "SELECT * FROM jurusan");

$data = []; `// untuk data testing
$all_classes = []; // untuk data jurusan, define as class
$confusion_matrix = []; // conf matrix as array

foreach ($getData as $key => $val) {
    $data[] = [
        "id_klasifikasi" => $val['id_klasifikasi'],
        "id_jurusan" => $val['id_jurusan_aktual'],
        "hasil" => $val['id_jurusan_prediksi']
    ];
}

foreach ($getJurusan as $k => $v) {
    $all_classes[] = $v['id_jurusan'];
}

foreach ($all_classes as $class) {
    foreach ($all_classes as $predicted_class) {
        $confusion_matrix[$class][$predicted_class] = 0;
    }
}

// isi confussion matrix berdasarkan data testing 
foreach ($data as $row) {
    $actual_class = $row["id_jurusan"];
    $predicted_class = $row["hasil"];
    $confusion_matrix[$actual_class][$predicted_class]++;
}

// hitung metrix
$metrics = []; // simpan data hasil perhitungan metrix

// hitung metrix pada setiap class (jurusan)
foreach ($all_classes as $class) {
    $TP = $confusion_matrix[$class][$class]; // define TP secara langsung dimana id_jurusan dari data testing = id_jurusan dari data jurusan
    $FP = 0; // define as 0
    $FN = 0; // define as 0
    $TN = 0; // define as 0

    // hitung FP, FN, TN
    foreach ($all_classes as $other_class) { // hitung dari data jurusan
        if ($other_class != $class) { // jika id_jurusan dari data jurusan asli != id_jurusan hasil iterasi
            $FP += $confusion_matrix[$other_class][$class]; // jika class prediksi = class aktual => FP
            $FN += $confusion_matrix[$class][$other_class]; // jika class aktual = class aktual => FN
            foreach ($all_classes as $another_class) { // mencari TN
                if ($other_class != $class && $another_class != $class) {
                    $TN += $confusion_matrix[$other_class][$another_class]; // jika bukan keduanya
                }
            }
        }
    }

    $precision = $TP + $FP > 0 ? $TP / ($TP + $FP) : 0; // TP + FP / (TP + FP)
    $recall = $TP + $FN > 0 ? $TP / ($TP + $FN) : 0; // TP + FN / (TP + FN)
    $f1_score = $precision + $recall > 0 ? 2 * ($precision * $recall) / ($precision + $recall) : 0; // 2 * (precision * recall) / (preision + recall) 
    $accuracy = ($TP + $TN) / array_sum(array_map('array_sum', $confusion_matrix)); // TP + TN / jumlah data

    $metrics[$class] = [
        'TP' => $TP,
        'FP' => $FP,
        'FN' => $FN,
        'TN' => $TN,
        'Precision' => round($precision * 100),
        'Recall' => round($recall * 100),
        'F1 Score' => round($f1_score * 100),
        'Accuracy' => round($accuracy * 100),
    ];
}

// define as json
$result = [
    'confusion_matrix' => $confusion_matrix,
    'metrics' => $metrics
];

// hasil
echo json_encode($result, JSON_PRETTY_PRINT);
