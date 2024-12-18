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

    $file = $_FILES['file'];

    $targetDir = "../uploads/";

    $fileName = pathinfo($file["name"], PATHINFO_FILENAME);
    $fileExtension = pathinfo($file["name"], PATHINFO_EXTENSION);

    $newFileName = $fileName . "_" . time() . "." . $fileExtension;
    $targetFile = $targetDir . $newFileName;


    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        $inputFileName = file($targetFile);
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

        $result_testing = [];

        for ($i = 0; $i < count($inputFileName); $i++) {
            if ($i === 0) {
                continue; // Lewati baris pertama (A1)
            }
            $rowData = [
                'nama' => $nama[$i],
                'id_jurusan_pilihan' => match ($jurusan[$i]) {
                    'Teknik Audio Video' => 1,
                    'Teknik Bisnis dan Sepeda Motor' => 2,
                    'Teknik Elektronika Industri' => 3,
                    'Teknik Instalasi Tenaga Listrik' => 4,
                    'Teknik Komputer dan Jaringan' => 5,
                    default => 0,
                },
                'jurusan_pilihan' => $jurusan[$i],
                'pai' => $pai[$i],
                'bi' => $bi[$i],
                'mtk' => $mtk[$i],
                'sej' => $sej[$i],
                'bing' => $bing[$i],
                'senbud' => $senbud[$i],
                'ok' => $ok[$i],
                'fis' => $fis[$i],
                'jw' => $jw[$i],
            ];

            $result_testing[] = $rowData;
        }

        $query_training = "SELECT id_data_training, nama, jurusan.id_jurusan as id_jurusan_pilihan, jurusan.jurusan as jurusan_pilihan, pai, bi, mtk, sej, bing, senbud, ok, fis, jw
               FROM data_training
               JOIN jurusan ON data_training.id_jurusan = jurusan.id_jurusan";
        $result_training = $conn->query($query_training);

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
        foreach ($result_testing as $test) {
            $distances = [];
            foreach ($training_data as $train) {
                $distance = euclideanDistance($test, $train);
                $distances[] = ['distance' => $distance, 'id_jurusan_pilihan' => $train['id_jurusan_pilihan']];
            }

            // Urutkan berdasarkan jarak terdekat
            usort(
                $distances,
                function ($a, $b) {
                    return $a['distance'] <=> $b['distance'];
                }
            );

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
                if (
                    $actual_label == $predicted_label && $actual_label == $id_kelas
                ) {
                    $confusion_matrix[$id_kelas]['TP']++;
                } elseif ($actual_label != $predicted_label && $actual_label == $id_kelas) {
                    $confusion_matrix[$id_kelas]['FN']++;
                } elseif ($actual_label != $predicted_label && $predicted_label == $id_kelas) {
                    $confusion_matrix[$id_kelas]['FP']++;
                } elseif ($actual_label != $predicted_label && $actual_label != $id_kelas && $predicted_label != $id_kelas) {
                    $confusion_matrix[$id_kelas]['TN']++;
                }
            }

            // Calculate metrics for this individual result
            $metrics = calculateMetrics(
                $confusion_matrix[$predicted_jurusan_id]['TP'],
                $confusion_matrix[$predicted_jurusan_id]['FP'],
                $confusion_matrix[$predicted_jurusan_id]['TN'],
                $confusion_matrix[$predicted_jurusan_id]['FN']
            );

            // Save the classification result including metrics
            $classification_results[] = [
                'nomor' => $index++,
                'nama' => $test['nama'],
                'id_jurusan' => $test['id_jurusan_pilihan'],
                'jurusan' => $test['jurusan_pilihan'],
                'predicted_jurusan_id' => $predicted_jurusan_id,
                'predicted_jurusan' => $predicted_jurusan_name,
                'confusion_matrix' => $confusion_matrix[$predicted_jurusan_id],
                'accuracy' => $metrics['accuracy'],
                'precision' => $metrics['precision'],
                'recall' => $metrics['recall'],
                'f1_score' => $metrics['f1_score']
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


        foreach ($classification_results as $key => $val) {
            mysqli_query(
                $conn,
                "INSERT INTO klasifikasi (id_jurusan, nama, pai, bi, mtk, sej, bing, senbud, ok, fis, jw, hasil, akurasi) 
                 VALUES (
                 '" . $val['id_jurusan'] . "', 
                 '" . preg_replace('/[^a-zA-Z0-9\s]/', '', $val['nama']) . "', 
                 '" . $result_testing[$key]['pai'] . "',
                 '" . $result_testing[$key]['bi'] . "', 
                 '" . $result_testing[$key]['mtk'] . "', 
                 '" . $result_testing[$key]['sej'] . "', 
                 '" . $result_testing[$key]['bing'] . "', 
                 '" . $result_testing[$key]['senbud'] . "', 
                 '" . $result_testing[$key]['ok'] . "', 
                 '" . $result_testing[$key]['fis'] . "', 
                 '" . $result_testing[$key]['jw'] . "', 
                 '" . $val['predicted_jurusan_id'] . "', 
                 '" . $val['accuracy'] . "')"
            );
        }
        echo json_encode([
            'classification_results' => $classification_results,
            'confusion_matrix' => $confusion_matrix,
            'metrics_per_class' => $metrics_per_class,
            'aggregate_metrics' => $aggregate_metrics
        ], JSON_PRETTY_PRINT);
    }
}

// Fungsi untuk menghitung metrik dari TP, FP, TN, FN
function calculateMetrics($TP, $FP, $TN, $FN)
{
    // Cek untuk pembagian dengan nol
    $accuracy = ($TP + $TN) / max(($TP + $FP + $FN + $TN), 1); // Pastikan denominator tidak nol
    $precision = ($TP + $FP) > 0 ? $TP / ($TP + $FP) : 0;  // Jika (TP + FP) == 0, set precision ke 0
    $recall = ($TP + $FN) > 0 ? $TP / ($TP + $FN) : 0;  // Jika (TP + FN) == 0, set recall ke 0
    $f1_score = ($precision + $recall) > 0 ? 2 * (($precision * $recall) / ($precision + $recall)) : 0;  // Jika precision + recall == 0, set F1 score ke 0

    return [
        'accuracy' => $accuracy,
        'precision' => $precision,
        'recall' => $recall,
        'f1_score' => $f1_score
    ];
}

function stratifiedSampling($dataset, $testSize = 0.2)
{
    // Step 1: Kelompokkan dataset berdasarkan kelas aktual (Komp)
    $classes = [];
    foreach ($dataset as $data) {
        $kelas = $data['jurusan']; // Menggunakan label aktual
        $classes[$kelas][] = $data;
    }

    // Step 2: Tentukan ukuran testing set berdasarkan testSize
    $trainData = [];
    $testData = [];

    foreach ($classes as $class => $classData) {
        $classSize = count($classData);
        $testSizeForClass = ceil($classSize * $testSize);  // Jumlah data testing per kelas
        $trainSizeForClass = $classSize - $testSizeForClass;

        // Shuffle data untuk mendapatkan sampel acak
        shuffle($classData);

        // Pisahkan data training dan testing untuk kelas ini
        $testClassData = array_slice($classData, 0, $testSizeForClass);
        $trainClassData = array_slice($classData, $testSizeForClass);

        // Tambahkan ke data training dan testing
        $trainData = array_merge($trainData, $trainClassData);
        $testData = array_merge($testData, $testClassData);
    }

    return ['train' => $trainData, 'test' => $testData];
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
            'username' => $row['username'],
            'id_role' => $row['id_role'],
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

function checkExist($nama, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $jurusan)
{
    global $conn;

    $checkData = mysqli_query($conn, "SELECT * FROM data_testing WHERE nama = '$nama' && pai = '$pai' && bi = '$bi' && mtk = '$mtk' && sej = '$sej' && bing = '$bing' && senbud = '$senbud' && ok = '$ok' && fis = '$fis' && jw = '$jw' && id_jurusan = '$jurusan'");
    $resultCheck = mysqli_fetch_array($checkData);

    return $resultCheck === null ? false : true;
}

function classification()
{
    global $conn;

    $k = getTotalData();

    // Data uji
    $nama = $_GET['nama'];
    $pai = $_GET['pai'];
    $bi = $_GET['bi'];
    $mtk = $_GET['mtk'];
    $sej = $_GET['sej'];
    $bing = $_GET['bing'];
    $senbud = $_GET['senbud'];
    $ok = $_GET['ok'];
    $fis = $_GET['fis'];
    $jw = $_GET['jw'];
    $jurusan = $_GET['jurusan'];

    if (!checkExist($nama, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $jurusan)) {
        $insertDB = mysqli_query($conn, "INSERT INTO data_testing (id_jurusan, nama, pai, bi, mtk, sej, bing, senbud, ok, fis, jw) 
    VALUES ('$jurusan', '$nama', '$pai', '$bi', '$mtk', '$sej', '$bing', '$senbud', '$ok', '$fis', '$jw') 
    ");

        if ($insertDB) {
            getCalculationResult($nama, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $jurusan);
        }
    } else {
        getCalculationResult($nama, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $jurusan);
    }
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

function saveData($nama, $jurusan_pilihan, $pai, $bi, $mtk, $sej, $bing, $senbud, $ok, $fis, $jw, $highest_accuracy_key, $highest_accuracy)
{
    global $conn;

    $sql = mysqli_query($conn, "INSERT INTO klasifikasi ( nama, id_jurusan, pai, bi, mtk, sej, bing, senbud, ok, fis, jw, hasil, akurasi)
    VALUES ('$nama', '$jurusan_pilihan', '$pai', '$bi', '$mtk', '$sej', '$bing', '$senbud', '$ok', '$fis', '$jw', '$highest_accuracy_key', '$highest_accuracy')");

    return $sql ? true : false;
}

function getRiwayat()
{
    global $conn;

    $sql = mysqli_query($conn, "SELECT id_klasifikasi, nama, pai, klasifikasi.id_jurusan as id_jurusan_aktual, klasifikasi.hasil as id_jurusan_prediksi, bi, mtk, sej, bing, senbud, ok, fis, jw, hasil, akurasi, a.jurusan as jurusan_pilihan, b.jurusan as jurusan_rekomendasi FROM klasifikasi
                                JOIN jurusan as a ON klasifikasi.id_jurusan = a.id_jurusan
                                JOIN jurusan as b ON klasifikasi.id_jurusan = b.id_jurusan
                                ORDER BY id_klasifikasi DESC
                                ");

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
            'jw' => $val['jw'],
            'hasil' => $val['hasil'],
            'akurasi' => $val['akurasi'],
            'jurusan_pilihan' => $val['jurusan_pilihan'],
            'jurusan_rekomendasi' => $val['jurusan_rekomendasi']
        ];
    }

    echo json_encode($data);
    return ($data);
}
