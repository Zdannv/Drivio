import cv2
import numpy as np
from insightface.app import FaceAnalysis

def initialize_model():
    model = FaceAnalysis(name='buffalo_l', providers=['CPUExecutionProvider'])
    model.prepare(ctx_id=-1)

    return model

model_app = initialize_model()

def get_face_embedding(image_bytes):
    nparr = np.frombuffer(image_bytes, np.uint8)
    image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

    if image is None:
        return None
    
    faces = model_app.get(image)
    if not faces:
        return None
    
    return faces[0].normed_embedding.tolist()