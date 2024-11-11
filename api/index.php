<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

include 'conn.php';
$act = $_GET['act'];

switch ($act) {
    case 'login':
        login();
        break;
    case 'getJurusan':
        getJurusan();
        break;
    case 'onUpload':
        onUpload();
        break;
    case 'classification':
        classification();
        break;
    case 'getRiwayat':
        getRiwayat();
        break;
    default:
        login();
}

function onUpload()
{
    global $conn;
    $inputFileName = file('master.csv');

    foreach ($inputFileName as $r) {
        $data = explode(";", $r);
        $nama[] = $data[0];
        $jurusan[] = $data[1];
        $pai[] = $data[2];
        $bi[] = $data[3];
        $mtk[] = $data[4];
        $sej[] = $data[5];
        $bing[] = $data[6];
        $senbud[] = $data[7];
        $ok[] = $data[8];
        $fis[] = $data[9];
        $jw[] = $data[10];
    }

    for ($i = 0; $i < count($inputFileName); $i++) {
        $sql = mysqli_query($conn, "INSERT INTO data_training(id_jurusan, nama, pai, bi, mtk, sej, bing, senbud, ok, fis, jw) 
          VALUES(
          '$jurusan[$i]', 
          '$nama[$i]', 
          '$pai[$i]', 
          '$bi[$i]', 
          '$mtk[$i]', 
          '$sej[$i]', 
          '$bing[$i]', 
          '$senbud[$i]', 
          '$ok[$i]', 
          '$fis[$i]', 
          '$jw[$i]'
          )
          ");
    }
}

function login()
{
    global $conn;

    $username = $_GET['username'];
    $password = md5($_GET['password']);

    $sql = mysqli_query($conn, "SELECT * FROM user 
    JOIN role ON user.id_role = role.id_role
    WHERE username = '$username' && password = '$password'");

    $row = mysqli_fetch_array($sql);

    $data = [];
    if ($row > 0) {
        $data[] = [
            'nama' => $row['nama'],
            'role' => $row['role'],
            'username' => $row['username']
        ];
    }

    echo json_encode($data);
}

function getJurusan()
{
    global $conn;

    $sql = mysqli_query($conn, "SELECT * FROM jurusan");

    $data = [];

    foreach ($sql as $key => $val) {
        $data[] = [
            'id_jurusan' => $val['id_jurusan'],
            'jurusan' => $val['jurusan'],
        ];
    }

    $res = $data;
    echo json_encode($res);
    return ($res);
}

function getTotalData()
{
    global $conn;
    $getTotal = mysqli_query($conn, "SELECT COUNT(*) as total_data FROM data_training");
    $row = mysqli_fetch_array($getTotal);
    $total = $row['total_data'];

    // get K params
    $k = (int) sqrt($total);
    return $k;
}

function euclideanDistance($data1, $data2)
{
    $distance = 0;
    foreach ($data1 as $key => $value) {
        if ($key !== 'id_jurusan' && $key !== 'nama') {
            $distance += pow($value - $data2[$key], 2);
        }
    }
    return sqrt($distance);
}

function getJurusanByID($id)
{
    global $conn;

    $sql = mysqli_query($conn, "SELECT * FROM jurusan WHERE id_jurusan = $id");
    $rActualJurusan = mysqli_fetch_array($sql);

    $jurusan = $rActualJurusan['jurusan'];

    return $jurusan;
}

function getJurusanByName($jurusan)
{
    global $conn;

    $sql = mysqli_query($conn, "SELECT * FROM jurusan WHERE jurusan = '$jurusan'");
    $rActualJurusan = mysqli_fetch_array($sql);

    $jurusan = $rActualJurusan['id_jurusan'];

    return $jurusan;
}

function classification()
{
    global $conn;

    $k = getTotalData();

    // Data uji
    $nama = $_GET['nama'];
    $jurusan_pilihan = $_GET['jurusan'];
    $pai = $_GET['pai'];
    $bi = $_GET['bi'];
    $mtk = $_GET['mtk'];
    $sej = $_GET['sej'];
    $bing = $_GET['bing'];
    $senbud = $_GET['senbud'];
    $ok = $_GET['ok'];
    $fis = $_GET['fis'];
    $jw = $_GET['jw'];

    $distances = [];

    $jurusan = getJurusanByID($jurusan_pilihan);

    // get data training
    $getTraining = mysqli_query($conn, "SELECT nama, data_training.id_jurusan, jurusan, pai, bi, mtk, sej, bing, senbud, ok, fis, jw FROM data_training JOIN jurusan ON data_training.id_jurusan = jurusan.id_jurusan");

    foreach ($getTraining as $value) {
        // calculate euclidean distance
        $distance = sqrt(
            pow($pai - $value['pai'], 2) +
                pow($bi - $value['bi'], 2) +
                pow($mtk - $value['mtk'], 2) +
                pow($sej - $value['sej'], 2) +
                pow($bing - $value['bing'], 2) +
                pow($senbud - $value['senbud'], 2) +
                pow($ok - $value['ok'], 2) +
                pow($fis - $value['fis'], 2) +
                pow($jw - $value['jw'], 2)
        );

        $distances[] = ['distance' => $distance, 'jurusan' => $value['jurusan']];
    }

    // sort ascending
    usort($distances, function ($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    // get nearest
    $nearestNeighbor = array_slice($distances, 0, $k);

    $jurusan_counts = [];

    // get total majority class
    foreach ($nearestNeighbor as $neighbor) {
        $jurusan = $neighbor['jurusan'];
        if (!isset($jurusan_counts[$jurusan])) {
            $jurusan_counts[$jurusan] = 0;
        }
        $jurusan_counts[$jurusan]++;
    }

    // define majority class and total
    arsort($jurusan_counts);
    $majority_class = array_key_first($jurusan_counts);
    $majority_count = $jurusan_counts[$majority_class];

    $jurusan_rekomendasi = getJurusanByName($majority_class);


    $highest_accuracy = 0;
    $highest_accuracy_key = null;

    $result_conf_matrix = confussionMatrix();

    foreach ($result_conf_matrix['metrics'] as $key => $metric) {
        if ($metric['Accuracy'] > $highest_accuracy) {
            $highest_accuracy = $metric['Accuracy'];
            $highest_accuracy_key = $key;
        }
    }

    $result_conf_matrix['accuracy'] = $highest_accuracy;
    $result_conf_matrix['key'] = $highest_accuracy_key;

    $hasil_jurusan_rekomendasi = getJurusanByID($highest_accuracy_key);
    // result
    $result = [
        'status' => 'success',
        'nilai_k' => $k,
        'jurusan_pilihan' => $jurusan,
        'jurusan_rekomendasi' => $majority_class,
        'jumlah_kelas_mayoritas' => $majority_count,
        'akurasi_tertinggi' => $highest_accuracy,
        'hasil_jurusan_rekomendasi' => $hasil_jurusan_rekomendasi,
        'confussion_matrix' => confussionMatrix()
    ];


    // dummy 
    $akurasi = 80;

    $status = saveData($nama, $jurusan_pilihan, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $jurusan_rekomendasi, $akurasi);
    echo $status ? json_encode($result) : json_encode(['status' => 'failed']);
}

function saveData($nama, $jurusan_pilihan, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $hasil, $akurasi)
{
    global $conn;

    $sql = mysqli_query($conn, "INSERT INTO klasifikasi ( nama, id_jurusan, pai, bi, mtk, sej, bing, senbud, ok, fis, bj, hasil, akurasi)
    VALUES ('$nama', '$jurusan_pilihan', '$pai', '$bi', '$mtk', '$sej', '$bing', '$senbud', '$ok', '$fis', '$jw', '$hasil', '$akurasi')");

    return $sql ? true : false;
}

function getRiwayat()
{
    global $conn;

    $sql = mysqli_query($conn, "SELECT id_klasifikasi, nama, pai, klasifikasi.id_jurusan as id_jurusan_aktual, klasifikasi.hasil as id_jurusan_prediksi, bi, mtk, sej, bing, senbud, ok, fis, bj, hasil, akurasi, a.jurusan as jurusan_pilihan, b.jurusan as jurusan_rekomendasi FROM klasifikasi
                                JOIN jurusan as a ON klasifikasi.id_jurusan = a.id_jurusan
                                JOIN jurusan as b ON klasifikasi.id_jurusan = b.id_jurusan");

    $data = [];
    foreach ($sql as $key => $val) {
        $data[] = [
            'nama' => $val['nama'],
            'pai' => $val['pai'],
            'bi' => $val['bi'],
            'mtk' => $val['mtk'],
            'sej' => $val['sej'],
            'bing' => $val['bing'],
            'senbud' => $val['senbud'],
            'ok' => $val['ok'],
            'fis' => $val['fis'],
            'bj' => $val['bj'],
            'hasil' => $val['hasil'],
            'akurasi' => $val['akurasi'],
            'jurusan_pilihan' => $val['jurusan_pilihan'],
            'jurusan_rekomendasi' => $val['jurusan_rekomendasi']
        ];
    }

    echo json_encode($data);
    return ($data);
}

function confussionMatrix()
{
    global $conn;
    // get all data testing
    $getData = mysqli_query($conn, "SELECT id_klasifikasi, nama, pai, klasifikasi.id_jurusan as id_jurusan_aktual, klasifikasi.hasil as id_jurusan_prediksi, bi, mtk, sej, bing, senbud, ok, fis, bj, hasil, akurasi, a.jurusan as jurusan_pilihan, b.jurusan as jurusan_rekomendasi FROM klasifikasi
                                    JOIN jurusan as a ON klasifikasi.id_jurusan = a.id_jurusan
                                    JOIN jurusan as b ON klasifikasi.id_jurusan = b.id_jurusan");

    // get all data jurusan
    $getJurusan = mysqli_query($conn, "SELECT * FROM jurusan");

    $data = []; // untuk data testing
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
        // 'confusion_matrix' => $confusion_matrix,
        'metrics' => $metrics
    ];

    // hasil
    return $result;
}
