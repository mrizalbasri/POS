# Segmentasi Pelanggan - FastAPI Service

## Prasyarat
- Python 3.10+ (pastikan sudah terinstall dan masuk PATH)
- MySQL Server aktif dengan database `pos_db`
- Tabel segmentasi sudah dibuat (jalankan `database/segmentasi_pelanggan.sql`)

## Langkah Setup

### 1. Buka Terminal / Command Prompt

```bash
cd D:\Coding\POS\python
```

### 2. Buat Virtual Environment

```bash
python -m venv venv
```

### 3. Aktifkan Virtual Environment

```bash
venv\Scripts\activate
```

Jika berhasil, akan muncul `(venv)` di awal baris terminal.

### 4. Install Dependencies

```bash
pip install -r requirements.txt
```

Library yang akan terinstall:
| Library | Fungsi |
|---------|--------|
| fastapi | Web framework untuk API |
| uvicorn | ASGI server untuk menjalankan FastAPI |
| mysql-connector-python | Koneksi ke MySQL |
| pandas | Manipulasi data tabular |
| scikit-learn | K-Means clustering & metrik evaluasi |
| kneed | Deteksi elbow point otomatis |
| numpy | Komputasi numerik |

### 5. Jalankan Service

```bash
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

Jika berhasil, akan muncul:
```
INFO:     Uvicorn running on http://0.0.0.0:8000 (Press CTRL+C to quit)
INFO:     Started reloader process
```

### 6. Verifikasi

Buka browser: http://localhost:8000

Harus muncul:
```json
{"status": "ok", "message": "POS Segmentasi API is running"}
```

Dokumentasi API otomatis: http://localhost:8000/docs

## Endpoint API

| Method | URL | Fungsi |
|--------|-----|--------|
| GET | `/` | Health check |
| POST | `/api/segmentasi/proses/{id}` | Jalankan proses segmentasi |
| GET | `/api/segmentasi/status/{id}` | Cek status proses |

## Troubleshooting

### Error: `python` tidak dikenali
Gunakan `python3` atau pastikan Python sudah masuk PATH Windows.

### Error: MySQL connection refused
Pastikan MySQL Server aktif dan konfigurasi di `main.py` sesuai:
```python
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "pos_db",
}
```

### Error: ModuleNotFoundError
Pastikan virtual environment aktif (`(venv)` terlihat di terminal), lalu jalankan ulang:
```bash
pip install -r requirements.txt
```

### Menghentikan Service
Tekan `CTRL+C` di terminal.

### Menjalankan Ulang (setelah restart PC)
```bash
cd D:\Coding\POS\python
venv\Scripts\activate
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```
