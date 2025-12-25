<?php
include 'koneksi.php'; // Pastikan file koneksi.php sudah benar
header('Content-Type: application/json');

// Ambil parameter action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// =================================================================================
// BAGIAN 1: KOMUNIKASI DENGAN ESP32 (ALAT)
// =================================================================================

// 1. ESP32 MENGIRIM DATA SENSOR (POST)
// Arduino: updateSensorDanLED()
if ($action == 'update_sensor' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $jarak = isset($_POST['jarak']) ? $_POST['jarak'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : 'UNKNOWN';
    $mode = isset($_POST['mode']) ? $_POST['mode'] : 'MONITORING';

    // Simpan ke database (table logs)
    $stmt = $conn->prepare("INSERT INTO logs (jarak, status, mode) VALUES (?, ?, ?)");
    $stmt->bind_param("dss", $jarak, $status, $mode);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Log saved"]);
    } else {
        echo json_encode(["message" => "Error saving log"]);
    }
}

// 2. ESP32 CEK PERINTAH & STATUS MODE (GET) -> PENTING UNTUK FAST RESPONSE
// Arduino: cekPerintahDanMode() (Looping 2 detik)
else if ($action == 'check_command') {
    // Ambil Command Manual (Tombol Feed di Web)
    $resCmd = $conn->query("SELECT command FROM controls WHERE id=1");
    $rowCmd = $resCmd->fetch_assoc();
    
    // Ambil Status Mode Auto Refill (Switch di Web)
    $resMode = $conn->query("SELECT mode_auto FROM pengaturan WHERE id=1");
    $rowMode = $resMode->fetch_assoc();

    // Gabungkan jadi satu JSON agar ESP32 langsung tahu keduanya
    echo json_encode([
        "command" => $rowCmd['command'],       // "FEED" atau "IDLE"
        "mode_auto" => (int)$rowMode['mode_auto'] // 1 (ON) atau 0 (OFF)
    ]);
}

// 3. ESP32 RESET COMMAND SETELAH FEEDING (GET)
// Arduino: laksanakanFeeding()
else if ($action == 'clear_command') {
    $conn->query("UPDATE controls SET command='IDLE' WHERE id=1");
    echo json_encode(["message" => "Command cleared"]);
}

// 4. ESP32 SINKRONISASI JADWAL (GET)
// Arduino: sinkronisasiDataWeb() (Looping 1 menit)
else if ($action == 'get_settings') {
    // Ambil Mode
    $resMode = $conn->query("SELECT mode_auto FROM pengaturan WHERE id=1");
    $rowMode = $resMode->fetch_assoc();

    // Ambil Daftar Jadwal
    $jadwalArr = [];
    $resJadwal = $conn->query("SELECT * FROM jadwal_pakan ORDER BY jam ASC, menit ASC");
    while ($row = $resJadwal->fetch_assoc()) {
        $jadwalArr[] = [
            "id" => $row['id'],
            "jam" => (int)$row['jam'],
            "menit" => (int)$row['menit']
        ];
    }

    // Kirim JSON lengkap
    echo json_encode([
        "mode_auto" => (int)$rowMode['mode_auto'],
        "jadwal" => $jadwalArr
    ]);
}

// =================================================================================
// BAGIAN 2: KOMUNIKASI DENGAN WEBSITE (FRONTEND)
// =================================================================================

// 5. WEB UPDATE MODE AUTO REFILL (POST)
// JS: updateAutoMode()
else if ($action == 'update_mode' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $mode = $_POST['mode']; // 1 atau 0
    $stmt = $conn->prepare("UPDATE pengaturan SET mode_auto=? WHERE id=1");
    $stmt->bind_param("i", $mode);
    $stmt->execute();
    echo json_encode(["message" => "Mode updated successfully"]);
}

// 6. WEB TAMBAH JADWAL BARU (POST)
// JS: addSchedule()
else if ($action == 'add_schedule' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $jam = $_POST['jam'];
    $menit = $_POST['menit'];
    
    $stmt = $conn->prepare("INSERT INTO jadwal_pakan (jam, menit) VALUES (?, ?)");
    $stmt->bind_param("ii", $jam, $menit);
    
    if($stmt->execute()) echo json_encode(["message" => "Schedule added"]);
    else echo json_encode(["message" => "Error adding schedule"]);
}

// 7. WEB HAPUS JADWAL (POST)
// JS: deleteSchedule()
else if ($action == 'delete_schedule' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM jadwal_pakan WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["message" => "Schedule deleted"]);
}

// 8. WEB AMBIL DATA MONITORING TERAKHIR (GET)
// JS: updateSensorData()
else if ($action == 'get_web_data') {
    $result = $conn->query("SELECT * FROM logs ORDER BY id DESC LIMIT 1");
    if ($result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        echo json_encode(["jarak" => 0, "status" => "NODATA", "mode" => "-"]);
    }
}

// 9. WEB KIRIM PERINTAH FEEDING MANUAL (POST)
// JS: manualFeed()
else if ($action == 'trigger_feed') {
    $conn->query("UPDATE controls SET command='FEED' WHERE id=1");
    echo json_encode(["message" => "Feed command sent to ESP32"]);
}

else {
    echo json_encode(["message" => "Invalid Action"]);
}
?>
