import base64
import numpy as np
from pydantic import BaseModel
from fastapi import APIRouter, HTTPException
from models.recognition import get_face_embedding

router = APIRouter()

class VerifyRequest(BaseModel):
    reference_image_base64: str
    live_image_base64: str

@router.post("/verify")
async def verify(request: VerifyRequest):
    try:
        ref_data = request.reference_image_base64
        live_data = request.live_image_base64

        if ',' in ref_data:
            ref_data = ref_data.split(",")[1]
        if ',' in live_data:
            live_data = live_data.split(",")[1]

        try:
            ref_bytes = base64.b64decode(ref_data)
            live_bytes = base64.b64decode(live_data)
        except Exception:
            raise HTTPException(status_code=400, detail="Invalid base64 encoding")

        ref_embedding = get_face_embedding(ref_bytes)
        if ref_embedding is None:
            raise HTTPException(status_code=400, detail="No face detected in reference image")

        live_embedding = get_face_embedding(live_bytes)
        if live_embedding is None:
            raise HTTPException(status_code=400, detail="No face detected in live image")

        ref_vec = np.array(ref_embedding, dtype=np.float32)
        live_vec = np.array(live_embedding, dtype=np.float32)
        
        similarity = float(np.dot(ref_vec, live_vec))
        is_match = similarity >= 0.5
        
        return {
            "status": "success",
            "similarity_score": similarity,
            "is_match": is_match
        }

    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
