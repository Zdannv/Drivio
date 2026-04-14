import uvicorn
import numpy as np
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from api.verify import router as verify_router
from models.recognition import model_app

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- PROSES MODEL WARM-UP ---
@app.on_event("startup")
async def startup_event():
    print("🕒 Memulai proses Warm-Up Model AI...")
    try:
        # Membuat gambar dummy hitam kosong berukuran 224x224 RGB
        dummy_image = np.zeros((224, 224, 3), dtype=np.uint8)
        
        # Memaksa model melakukan 1x deteksi untuk mengalokasikan memori
        model_app.get(dummy_image)
        
        print("✅ Warm-Up selesai! Service Face Recognition siap menerima request tanpa delay.")
    except Exception as e:
        print(f"⚠️ Peringatan saat Warm-Up: {e}")

app.include_router(verify_router, prefix="/api")

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)