const express = require('express');
const mysql = require('mysql2');
const bodyParser = require('body-parser');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(bodyParser.json());

// Konfigurasi Database
const db = mysql.createConnection({
    host: 'localhost',
    user: 'root',      // Default XAMPP user
    password: '',      // Default XAMPP password kosong
    database: 'uap_petfeeder'
});

// --- API UNTUK ESP32 ---
app.post('/api/iot/update', (req, res) => {
    // Terima data jarak & status switch dari ESP32
    const { distance, manual_switch } = req.body; 

    // Simpan data sensor ke DB untuk grafik
    if(distance !== undefined) {
        db.query('INSERT INTO sensor_logs (distance) VALUES (?)', [distance]);
    }

    // Cek apakah Servo harus buka atau tutup
    db.query('SELECT * FROM device_state WHERE id = 1', (err, result) => {
        if (err) return res.status(500).send(err);
        
        let state = result[0];
        let servoCmd = 0; // Default: Tutup

        // Cek Waktu Sekarang (Realtime)
        const now = new Date();
        const currentTime = now.getHours().toString().padStart(2, '0') + ":" + now.getMinutes().toString().padStart(2, '0');
        const isScheduleTime = (state.schedule_time === currentTime && now.getSeconds() < 15); // Durasi aktif 15 detik

        // LOGIKA UTAMA: Buka jika (Tombol Web ditekan) ATAU (Switch Fisik ON) ATAU (Jadwal Cocok)
        if (state.servo_status == 1 || manual_switch == 1 || isScheduleTime) {
            servoCmd = 1; 
        }

        // Kirim perintah balik ke ESP32
        res.json({ servo: servoCmd });
    });
});

// --- API UNTUK WEBSITE ---
// 1. Ambil Data untuk Dashboard
app.get('/api/web/data', (req, res) => {
    db.query('SELECT * FROM sensor_logs ORDER BY id DESC LIMIT 10', (err, logs) => {
        db.query('SELECT * FROM device_state WHERE id = 1', (err2, state) => {
            res.json({ logs: logs.reverse(), state: state[0] });
        });
    });
});

// 2. Kontrol Manual dari Web
app.post('/api/web/control', (req, res) => {
    const { action, time } = req.body;
    
    if (action === 'FEED_NOW') {
        // Set servo buka di DB
        db.query('UPDATE device_state SET servo_status = 1 WHERE id = 1');
        
        // Otomatis tutup kembali setelah 5 detik (simulasi pakan tumpah)
        setTimeout(() => {
            db.query('UPDATE device_state SET servo_status = 0 WHERE id = 1');
        }, 5000);
        res.send("Feeding...");
    } 
    else if (action === 'SET_SCHEDULE') {
        db.query('UPDATE device_state SET schedule_time = ? WHERE id = 1', [time]);
        res.send("Schedule Updated");
    }
});

// Jalankan Server di Port 3000
// PENTING: Ganti '0.0.0.0' agar bisa diakses dari HP/ESP32 dalam satu WiFi
app.listen(3000, '0.0.0.0', () => {
    console.log('Server berjalan di port 3000');
});