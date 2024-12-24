<?php
// Koneksi ke database
$mysqli = new mysqli("localhost", "root", "", "knn_temp");

// Cek koneksi
if ($mysqli->connect_error) {
    die("Koneksi gagal: " . $mysqli->connect_error);
}

// Query untuk mengambil data master
$query = "SELECT * FROM data_master
          JOIN jurusan ON data_master.id_jurusan = jurusan.id_jurusan";
$result = $mysqli->query($query);

// Array untuk menyimpan data training dan testing
$data_training = [];
$data_testing = [];

// Array untuk menyimpan data berdasarkan jurusan (opsional jika diperlukan)
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[$row['id_jurusan']][] = $row;
}

// Proporsi data training dan testing (misalnya 80% untuk training, 20% untuk testing)
$training_ratio = 0.8;
$testing_ratio = 1 - $training_ratio; // 20% untuk testing

// Membagi data untuk training dan testing
foreach ($data as $jurusan => $students) {
    $total_students = count($students);
    $num_training = round($total_students * $training_ratio);
    $num_testing = $total_students - $num_training; // Sisanya menjadi data testing

    // Shuffle data untuk randomisasi
    shuffle($students);

    // Ambil data untuk training
    $training_students = array_slice($students, 0, $num_training);
    // Ambil data untuk testing
    $testing_students = array_slice($students, $num_training, $num_testing);

    // Format data untuk output JSON
    foreach ($training_students as $student) {
        $data_training[] = [
            "id_data" => $student['id_data'],
            "id_jurusan" => $student['id_jurusan'],
            "nama" => $student['nama'],
            "pai" => $student['pai'],
            "bi" => $student['bi'],
            "mtk" => $student['mtk'],
            "sej" => $student['sej'],
            "bing" => $student['bing'],
            "senbud" => $student['senbud'],
            "ok" => $student['ok'],
            "fis" => $student['fis'],
            "jw" => $student['jw'],
            "jurusan" => $student['id_jurusan']
        ];
    }

    foreach ($testing_students as $student) {
        $data_testing[] = [
            "id_data" => $student['id_data'],
            "id_jurusan" => $student['id_jurusan'],
            "nama" => $student['nama'],
            "pai" => $student['pai'],
            "bi" => $student['bi'],
            "mtk" => $student['mtk'],
            "sej" => $student['sej'],
            "bing" => $student['bing'],
            "senbud" => $student['senbud'],
            "ok" => $student['ok'],
            "fis" => $student['fis'],
            "jw" => $student['jw'],
            "jurusan" => $student['id_jurusan']
        ];
    }
}

$deleteRowTraining = mysqli_query($mysqli, "DELETE FROM data_training");
$deleteRowTesting = mysqli_query($mysqli, "DELETE FROM data_testing");

if ($deleteRowTraining && $deleteRowTesting) {

    foreach ($data_training as $key => $val) {
        $sql = mysqli_query($mysqli, "INSERT INTO data_training (id_jurusan, nama, pai, bi, mtk, sej, bing, senbud, ok, fis, jw) 
    values(
    '" . $val['id_jurusan'] . "', 
    '" . $val['nama'] . "', 
    '" . $val['pai'] . "',
    '" . $val['bi'] . "',
    '" . $val['mtk'] . "',
    '" . $val['sej'] . "',
    '" . $val['bing'] . "',
    '" . $val['senbud'] . "',
    '" . $val['ok'] . "',
    '" . $val['fis'] . "',
    '" . $val['jw'] . "'
    )");
    }

    foreach ($data_testing as $key => $val) {
        $sql = mysqli_query(
            $mysqli,
            "INSERT INTO data_testing (id_jurusan, nama, pai, bi, mtk, sej, bing, senbud, ok, fis, jw) 
        values(
    '" . $val['id_jurusan'] . "', 
    '" . $val['nama'] . "', 
    '" . $val['pai'] . "',
    '" . $val['bi'] . "',
    '" . $val['mtk'] . "',
    '" . $val['sej'] . "',
    '" . $val['bing'] . "',
    '" . $val['senbud'] . "',
    '" . $val['ok'] . "',
    '" . $val['fis'] . "',
    '" . $val['jw'] . "'
    )"
        );
    }
}
// echo json_encode($data_training);

// Tutup koneksi database
$mysqli->close();
