from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import mysql.connector
import pandas as pd
import numpy as np
from sklearn.cluster import KMeans
from sklearn.preprocessing import MinMaxScaler
from sklearn.metrics import silhouette_score, davies_bouldin_score, calinski_harabasz_score
from kneed import KneeLocator
import json
import os
import httpx
from datetime import datetime

app = FastAPI(title="POS Segmentasi API", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "pos_db",
    "charset": "utf8mb4",
}

GEMINI_API_KEY = os.environ.get("GEMINI_API_KEY", "")
GEMINI_API_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent"


def get_db():
    return mysql.connector.connect(**DB_CONFIG)


def generate_llm_insight(cluster_info: dict) -> str:
    """Call Gemini API to get business insight for a cluster."""
    if not GEMINI_API_KEY:
        return "API Key Gemini belum dikonfigurasi. Set environment variable GEMINI_API_KEY."

    prompt = f"""Kamu adalah seorang bisnis analis profesional di bidang retail/Point of Sale.
Berdasarkan hasil analisis segmentasi pelanggan menggunakan metode RFM (Recency, Frequency, Monetary) dan K-Means Clustering, berikut adalah informasi salah satu cluster:

## Informasi Cluster
- **Nomor Cluster:** {cluster_info['nomor_cluster']}
- **Kelas Segmentasi:** {cluster_info['nama_kelas']}
- **Jumlah Anggota:** {cluster_info['jumlah_anggota']} pelanggan

## Centroid (Pusat Cluster)
- **Recency:** {cluster_info['centroid_recency']:.1f} hari (rata-rata hari sejak transaksi terakhir)
- **Frequency:** {cluster_info['centroid_frequency']:.1f} kali (rata-rata jumlah transaksi)
- **Monetary:** Rp {cluster_info['centroid_monetary']:,.0f} (rata-rata total belanja)

## Konteks Kelas RFM
- **Kelas Recency:** {cluster_info['kelas_r_label']} (1=Aktif/baru belanja, 2=Cukup aktif, 3=Tidak aktif/lama tidak belanja)
- **Kelas Frequency:** {cluster_info['kelas_f_label']} (1=Jarang, 2=Cukup sering, 3=Sering)
- **Kelas Monetary:** {cluster_info['kelas_m_label']} (1=Rendah, 2=Sedang, 3=Tinggi)

## Statistik Anggota Cluster
- **Recency:** Min={cluster_info['r_min']} hari, Max={cluster_info['r_max']} hari, Std={cluster_info['r_std']:.1f}
- **Frequency:** Min={cluster_info['f_min']}, Max={cluster_info['f_max']}, Std={cluster_info['f_std']:.1f}
- **Monetary:** Min=Rp {cluster_info['m_min']:,.0f}, Max=Rp {cluster_info['m_max']:,.0f}, Std=Rp {cluster_info['m_std']:,.0f}

## Proporsi
- Cluster ini mencakup {cluster_info['pct_of_total']:.1f}% dari total pelanggan yang dianalisis.

---

Berikan analisis dan rekomendasi bisnis dalam format berikut (gunakan Bahasa Indonesia):

1. **Profil Pelanggan**: Jelaskan karakteristik pelanggan pada cluster ini secara naratif (2-3 kalimat).
2. **Interpretasi Bisnis**: Apa arti cluster ini bagi bisnis retail? Apakah mereka pelanggan loyal, pelanggan baru potensial, pelanggan berisiko churn, dll?
3. **Rekomendasi Strategi**: Berikan 3-5 rekomendasi strategi bisnis yang spesifik dan actionable untuk cluster ini (marketing, promosi, retention, dll).
4. **Prioritas**: Seberapa penting cluster ini untuk di-prioritaskan? (Tinggi/Sedang/Rendah) dan alasannya.
"""

    payload = {
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {
            "temperature": 0.7,
            "maxOutputTokens": 1024,
        },
    }

    try:
        with httpx.Client(timeout=60.0) as client:
            response = client.post(
                f"{GEMINI_API_URL}?key={GEMINI_API_KEY}",
                json=payload,
            )
            response.raise_for_status()
            data = response.json()
            return data["candidates"][0]["content"]["parts"][0]["text"]
    except Exception as e:
        return f"Gagal mendapatkan insight dari LLM: {str(e)}"


@app.get("/")
def root():
    return {"status": "ok", "message": "POS Segmentasi API is running"}


@app.post("/api/segmentasi/proses/{periode_id}")
def proses_segmentasi(periode_id: int):
    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    try:
        # 1. Read periode_segmentasi
        cursor.execute(
            "SELECT * FROM periode_segmentasi WHERE id = %s", (periode_id,)
        )
        periode = cursor.fetchone()
        if not periode:
            raise HTTPException(status_code=404, detail="Periode segmentasi tidak ditemukan")

        # 2. Update status = 'proses'
        cursor.execute(
            "UPDATE periode_segmentasi SET status = 'proses' WHERE id = %s",
            (periode_id,),
        )
        conn.commit()

        tanggal_proses = periode["tanggal_proses"]
        tgl_mulai = periode["tanggal_transaksi_mulai"]
        tgl_selesai = periode["tanggal_transaksi_selesai"]

        # 3. Compute RFM
        query_rfm = """
            SELECT
                t.customer_id,
                DATEDIFF(%s, MAX(t.tanggal_transaksi)) AS recency,
                COUNT(t.id) AS frequency,
                SUM(t.total_bayar) AS monetary
            FROM transactions t
            WHERE t.customer_id IS NOT NULL
              AND t.status = 'selesai'
              AND DATE(t.tanggal_transaksi) BETWEEN %s AND %s
            GROUP BY t.customer_id
            HAVING frequency > 0
        """
        cursor.execute(query_rfm, (tanggal_proses, tgl_mulai, tgl_selesai))
        rfm_data = cursor.fetchall()

        if len(rfm_data) < 3:
            cursor.execute(
                "UPDATE periode_segmentasi SET status = 'gagal', keterangan = 'Data pelanggan kurang dari 3, tidak cukup untuk clustering' WHERE id = %s",
                (periode_id,),
            )
            conn.commit()
            raise HTTPException(
                status_code=400,
                detail="Data pelanggan kurang dari 3, tidak cukup untuk clustering",
            )

        df = pd.DataFrame(rfm_data)
        df["monetary"] = df["monetary"].astype(float)
        df["recency"] = df["recency"].astype(int)
        df["frequency"] = df["frequency"].astype(int)

        # 4. Insert into periode_segmentasi_pelanggan
        cursor.execute(
            "DELETE FROM periode_segmentasi_pelanggan WHERE periode_segmentasi_id = %s",
            (periode_id,),
        )
        insert_pelanggan = """
            INSERT INTO periode_segmentasi_pelanggan
            (periode_segmentasi_id, customer_id, recency, frequency, monetary)
            VALUES (%s, %s, %s, %s, %s)
        """
        for _, row in df.iterrows():
            cursor.execute(
                insert_pelanggan,
                (periode_id, int(row["customer_id"]), int(row["recency"]), int(row["frequency"]), float(row["monetary"])),
            )
        conn.commit()

        # 5. Normalize RFM
        scaler = MinMaxScaler()
        rfm_features = df[["recency", "frequency", "monetary"]].values
        rfm_normalized = scaler.fit_transform(rfm_features)

        df["recency_normalized"] = rfm_normalized[:, 0]
        df["frequency_normalized"] = rfm_normalized[:, 1]
        df["monetary_normalized"] = rfm_normalized[:, 2]

        # Update normalized values
        update_norm = """
            UPDATE periode_segmentasi_pelanggan
            SET recency_normalized = %s, frequency_normalized = %s, monetary_normalized = %s
            WHERE periode_segmentasi_id = %s AND customer_id = %s
        """
        for _, row in df.iterrows():
            cursor.execute(
                update_norm,
                (
                    float(row["recency_normalized"]),
                    float(row["frequency_normalized"]),
                    float(row["monetary_normalized"]),
                    periode_id,
                    int(row["customer_id"]),
                ),
            )
        conn.commit()

        # 6. Elbow Method
        max_k = min(10, len(df) - 1)
        if max_k < 2:
            max_k = 2

        inertias = []
        k_range = range(2, max_k + 1)

        for k in k_range:
            km = KMeans(n_clusters=k, random_state=42, n_init=10)
            km.fit(rfm_normalized)
            inertias.append({"k": k, "inertia": float(km.inertia_)})

        # 7. Determine optimal K using kneed
        kneedle = KneeLocator(
            [item["k"] for item in inertias],
            [item["inertia"] for item in inertias],
            curve="convex",
            direction="decreasing",
        )
        optimal_k = kneedle.knee if kneedle.knee else 3

        # 8. Run final K-Means
        final_km = KMeans(n_clusters=optimal_k, random_state=42, n_init=10)
        labels = final_km.fit_predict(rfm_normalized)
        centroids_normalized = final_km.cluster_centers_

        # Inverse transform centroids to original scale
        centroids_original = scaler.inverse_transform(centroids_normalized)

        # 9. Compute metrics
        sil_score = float(silhouette_score(rfm_normalized, labels))
        db_index = float(davies_bouldin_score(rfm_normalized, labels))
        ch_index = float(calinski_harabasz_score(rfm_normalized, labels))
        final_inertia = float(final_km.inertia_)

        # 10. Compute tertile boundaries
        recency_values = df["recency"].values
        frequency_values = df["frequency"].values
        monetary_values = df["monetary"].values

        r_tertiles = np.percentile(recency_values, [33.33, 66.67])
        f_tertiles = np.percentile(frequency_values, [33.33, 66.67])
        m_tertiles = np.percentile(monetary_values, [33.33, 66.67])

        batas_kelas = {
            "recency_rendah_min": float(recency_values.min()),
            "recency_rendah_max": float(r_tertiles[0]),
            "recency_sedang_min": float(r_tertiles[0]),
            "recency_sedang_max": float(r_tertiles[1]),
            "recency_tinggi_min": float(r_tertiles[1]),
            "recency_tinggi_max": float(recency_values.max()),
            "frequency_rendah_min": float(frequency_values.min()),
            "frequency_rendah_max": float(f_tertiles[0]),
            "frequency_sedang_min": float(f_tertiles[0]),
            "frequency_sedang_max": float(f_tertiles[1]),
            "frequency_tinggi_min": float(f_tertiles[1]),
            "frequency_tinggi_max": float(frequency_values.max()),
            "monetary_rendah_min": float(monetary_values.min()),
            "monetary_rendah_max": float(m_tertiles[0]),
            "monetary_sedang_min": float(m_tertiles[0]),
            "monetary_sedang_max": float(m_tertiles[1]),
            "monetary_tinggi_min": float(m_tertiles[1]),
            "monetary_tinggi_max": float(monetary_values.max()),
        }

        # Save batas kelas
        cursor.execute(
            "DELETE FROM periode_segmentasi_batas_kelas WHERE periode_segmentasi_id = %s",
            (periode_id,),
        )
        insert_batas = """
            INSERT INTO periode_segmentasi_batas_kelas
            (periode_segmentasi_id, recency_rendah_min, recency_rendah_max,
             recency_sedang_min, recency_sedang_max, recency_tinggi_min, recency_tinggi_max,
             frequency_rendah_min, frequency_rendah_max, frequency_sedang_min, frequency_sedang_max,
             frequency_tinggi_min, frequency_tinggi_max, monetary_rendah_min, monetary_rendah_max,
             monetary_sedang_min, monetary_sedang_max, monetary_tinggi_min, monetary_tinggi_max)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(
            insert_batas,
            (
                periode_id,
                batas_kelas["recency_rendah_min"], batas_kelas["recency_rendah_max"],
                batas_kelas["recency_sedang_min"], batas_kelas["recency_sedang_max"],
                batas_kelas["recency_tinggi_min"], batas_kelas["recency_tinggi_max"],
                batas_kelas["frequency_rendah_min"], batas_kelas["frequency_rendah_max"],
                batas_kelas["frequency_sedang_min"], batas_kelas["frequency_sedang_max"],
                batas_kelas["frequency_tinggi_min"], batas_kelas["frequency_tinggi_max"],
                batas_kelas["monetary_rendah_min"], batas_kelas["monetary_rendah_max"],
                batas_kelas["monetary_sedang_min"], batas_kelas["monetary_sedang_max"],
                batas_kelas["monetary_tinggi_min"], batas_kelas["monetary_tinggi_max"],
            ),
        )
        conn.commit()

        # 11. Map centroids to kelas_segmentasi
        def get_kelas_level(value, tertiles):
            if value <= tertiles[0]:
                return 1
            elif value <= tertiles[1]:
                return 2
            else:
                return 3

        # Delete old clusters
        cursor.execute(
            "DELETE FROM cluster WHERE periode_segmentasi_id = %s", (periode_id,)
        )
        conn.commit()

        cluster_id_map = {}

        for i in range(optimal_k):
            centroid_r = float(centroids_original[i][0])
            centroid_f = float(centroids_original[i][1])
            centroid_m = float(centroids_original[i][2])

            kelas_r = get_kelas_level(centroid_r, r_tertiles)
            kelas_f = get_kelas_level(centroid_f, f_tertiles)
            kelas_m = get_kelas_level(centroid_m, m_tertiles)

            # Find matching kelas_segmentasi
            cursor.execute(
                "SELECT id FROM kelas_segmentasi WHERE kelas_recency = %s AND kelas_frequency = %s AND kelas_monetary = %s",
                (kelas_r, kelas_f, kelas_m),
            )
            kelas_row = cursor.fetchone()
            kelas_segmentasi_id = kelas_row["id"] if kelas_row else None

            jumlah_anggota = int(np.sum(labels == i))

            # 12. Insert cluster
            insert_cluster = """
                INSERT INTO cluster
                (periode_segmentasi_id, nomor_cluster, kelas_segmentasi_id,
                 centroid_recency, centroid_frequency, centroid_monetary, jumlah_anggota)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(
                insert_cluster,
                (periode_id, i, kelas_segmentasi_id, centroid_r, centroid_f, centroid_m, jumlah_anggota),
            )
            cluster_id_map[i] = cursor.lastrowid

        conn.commit()

        # 13. Update cluster_id in periode_segmentasi_pelanggan
        df["label"] = labels
        for _, row in df.iterrows():
            cluster_db_id = cluster_id_map[int(row["label"])]
            cursor.execute(
                "UPDATE periode_segmentasi_pelanggan SET cluster_id = %s WHERE periode_segmentasi_id = %s AND customer_id = %s",
                (cluster_db_id, periode_id, int(row["customer_id"])),
            )
        conn.commit()

        # 14. Generate LLM business insights per cluster
        kelas_labels = {1: "Rendah", 2: "Sedang", 3: "Tinggi"}
        total_pelanggan = len(df)

        for i in range(optimal_k):
            centroid_r = float(centroids_original[i][0])
            centroid_f = float(centroids_original[i][1])
            centroid_m = float(centroids_original[i][2])

            kelas_r = get_kelas_level(centroid_r, r_tertiles)
            kelas_f = get_kelas_level(centroid_f, f_tertiles)
            kelas_m = get_kelas_level(centroid_m, m_tertiles)

            # Get cluster members stats
            cluster_members = df[df["label"] == i]
            jumlah_anggota = len(cluster_members)

            # Find kelas name
            cursor.execute(
                "SELECT nama_kelas FROM kelas_segmentasi WHERE kelas_recency = %s AND kelas_frequency = %s AND kelas_monetary = %s",
                (kelas_r, kelas_f, kelas_m),
            )
            kelas_row = cursor.fetchone()
            nama_kelas = kelas_row["nama_kelas"] if kelas_row else f"R{kelas_r}-F{kelas_f}-M{kelas_m}"

            cluster_info = {
                "nomor_cluster": i,
                "nama_kelas": nama_kelas,
                "jumlah_anggota": jumlah_anggota,
                "centroid_recency": centroid_r,
                "centroid_frequency": centroid_f,
                "centroid_monetary": centroid_m,
                "kelas_r_label": f"{kelas_r} - {kelas_labels[kelas_r]}",
                "kelas_f_label": f"{kelas_f} - {kelas_labels[kelas_f]}",
                "kelas_m_label": f"{kelas_m} - {kelas_labels[kelas_m]}",
                "r_min": int(cluster_members["recency"].min()),
                "r_max": int(cluster_members["recency"].max()),
                "r_std": float(cluster_members["recency"].std()) if jumlah_anggota > 1 else 0.0,
                "f_min": int(cluster_members["frequency"].min()),
                "f_max": int(cluster_members["frequency"].max()),
                "f_std": float(cluster_members["frequency"].std()) if jumlah_anggota > 1 else 0.0,
                "m_min": float(cluster_members["monetary"].min()),
                "m_max": float(cluster_members["monetary"].max()),
                "m_std": float(cluster_members["monetary"].std()) if jumlah_anggota > 1 else 0.0,
                "pct_of_total": (jumlah_anggota / total_pelanggan) * 100,
            }

            insight = generate_llm_insight(cluster_info)

            # Update cluster with LLM insight
            cursor.execute(
                "UPDATE cluster SET llm_rekomendasi_bisnis = %s WHERE periode_segmentasi_id = %s AND nomor_cluster = %s",
                (insight, periode_id, i),
            )
            conn.commit()

        # 15. Update periode_segmentasi with results
        update_periode = """
            UPDATE periode_segmentasi SET
                jumlah_pelanggan = %s,
                jumlah_cluster = %s,
                inertia = %s,
                silhouette_score = %s,
                davies_bouldin_index = %s,
                calinski_harabasz_index = %s,
                elbow_data = %s,
                status = 'selesai',
                keterangan = 'Proses segmentasi berhasil'
            WHERE id = %s
        """
        cursor.execute(
            update_periode,
            (
                len(df),
                optimal_k,
                final_inertia,
                sil_score,
                db_index,
                ch_index,
                json.dumps(inertias),
                periode_id,
            ),
        )
        conn.commit()

        return {
            "success": True,
            "message": "Proses segmentasi berhasil",
            "data": {
                "periode_id": periode_id,
                "jumlah_pelanggan": len(df),
                "jumlah_cluster": optimal_k,
                "silhouette_score": round(sil_score, 4),
                "davies_bouldin_index": round(db_index, 4),
                "calinski_harabasz_index": round(ch_index, 4),
                "inertia": round(final_inertia, 4),
            },
        }

    except HTTPException:
        raise
    except Exception as e:
        conn.rollback()
        cursor.execute(
            "UPDATE periode_segmentasi SET status = 'gagal', keterangan = %s WHERE id = %s",
            (str(e), periode_id),
        )
        conn.commit()
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        cursor.close()
        conn.close()


@app.get("/api/segmentasi/status/{periode_id}")
def get_status(periode_id: int):
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    try:
        cursor.execute("SELECT id, nama_periode, status, keterangan, jumlah_pelanggan, jumlah_cluster, silhouette_score, davies_bouldin_index, calinski_harabasz_index, inertia FROM periode_segmentasi WHERE id = %s", (periode_id,))
        row = cursor.fetchone()
        if not row:
            raise HTTPException(status_code=404, detail="Tidak ditemukan")
        return {"success": True, "data": row}
    finally:
        cursor.close()
        conn.close()
