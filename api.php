<?php
include 'koneksi.php';
header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// --- 1. ESP32 MENGIRIM DATA SENSOR & LOG (POST) ---
if ($action == 'update_sensor' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $jarak = $_POST['jarak'];
    $status = $_POST['status']; // TERISI / KOSONG
    $mode = $_POST['mode'];     // AUTO / MANUAL / MONITORING

    // Simpan ke log history
    $stmt = $conn->prepare("INSERT INTO logs (jarak, status, mode) VALUES (?, ?, ?)");
    $stmt->bind_param("dss", $jarak, $status, $mode);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Log saved"]);
    } else {
        echo json_encode(["message" => "Error"]);
    }
}

// --- 2. ESP32 CEK PERINTAH (GET) ---
else if ($action == 'check_command') {
    $result = $conn->query("SELECT command FROM controls WHERE id=1");
    $row = $result->fetch_assoc();
    echo json_encode($row); // Output: {"command":"FEED"} atau {"command":"IDLE"}
}

// --- 3. ESP32 SELESAI FEEDING (RESET COMMAND) ---
else if ($action == 'clear_command') {
    $conn->query("UPDATE controls SET command='IDLE' WHERE id=1");
    echo json_encode(["message" => "Command cleared"]);
}

// --- 4. WEB BROWSER MINTA DATA TERAKHIR (GET) ---
else if ($action == 'get_web_data') {
    // Ambil log terakhir
    $result = $conn->query("SELECT * FROM logs ORDER BY id DESC LIMIT 1");
    $data = $result->fetch_assoc();
    echo json_encode($data);
}

// --- 5. WEB BROWSER KIRIM PERINTAH FEED (POST) ---
else if ($action == 'trigger_feed') {
    $conn->query("UPDATE controls SET command='FEED' WHERE id=1");
    echo json_encode(["message" => "Feed command sent"]);
}
?>