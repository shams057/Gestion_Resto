import cv2
from ultralytics import YOLO
import torch
from fastapi import FastAPI
from fastapi.responses import StreamingResponse, JSONResponse
from fastapi.middleware.cors import CORSMiddleware
import threading
import time
import copy

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- CONFIGURATION MODEL ---
print("Chargement du modèle...")
try:
    model = YOLO('yolov8m.pt')
    device = 'cuda' if torch.cuda.is_available() else 'cpu'
    if device == 'cuda':
        model.to('cuda')
    print(f"Modèle chargé sur {device}")
except Exception as e:
    print(f"Erreur chargement modèle: {e}")
    # On continue même si le modèle plante (pour le debug)

# --- GLOBAL STATUS ---
global_status = {
    "personnes": 0,
    "presence": False,
    "message": "Initialisation...",
    "camera_index": 0  # <--- Added so Dashboard knows which Table
}

# --- CLASS SINGLETON CAMERA ---
class VideoCamera(object):
    def __init__(self):
        self.lock = threading.Lock()
        self.cam_index = 0
        self.cap = None
        self.latest_frame = None  # Stocke la dernière image traitée (bytes)
        self.is_running = False
        
        # Statuts de détection
        self.absence_counter = 0 
        self.presence_status = False
        self.patience_frames = 50

        # Lance le thread de lecture en arrière-plan
        self.thread = threading.Thread(target=self.update, args=())
        self.thread.daemon = True
        self.thread.start()

    def set_source(self, index):
        """Change la source proprement sans bloquer le serveur web"""
        with self.lock:
            print(f"[Camera] Changement de source vers {index}...")
            self.cam_index = index
            if self.cap is not None:
                self.cap.release()
                self.cap = None
            # La méthode update() détectera que cap est None et tentera de rouvrir
    
    def get_frame(self):
        """Retourne la dernière frame disponible"""
        if self.latest_frame is not None:
            return self.latest_frame
        else:
            # Image d'attente si pas encore de flux
            return self._get_blank_frame("CHARGEMENT...")

    def _get_blank_frame(self, text):
        blank = torch.zeros((480, 640, 3), dtype=torch.uint8).numpy()
        cv2.putText(blank, text, (50, 240), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
        _, buffer = cv2.imencode('.jpg', blank)
        return buffer.tobytes()

    def update(self):
        """Boucle infinie qui lit la caméra (tourne en background)"""
        while True:
            # 1. Ouverture sécurisée de la caméra si nécessaire
            if self.cap is None or not self.cap.isOpened():
                with self.lock:
                    print(f"[Camera] Tentative ouverture index {self.cam_index}...")
                    self.cap = cv2.VideoCapture(self.cam_index)
                    if not self.cap.isOpened():
                        print(f"[Camera] Echec index {self.cam_index}. Pause 2s.")
                        self.latest_frame = self._get_blank_frame("CAMERA INTROUVABLE")
                        time.sleep(2)
                        continue
                    else:
                        print(f"[Camera] Succès ouverture index {self.cam_index}")

            # 2. Lecture de l'image
            ret, frame = self.cap.read()
            if not ret:
                print("[Camera] Erreur lecture frame. Tentative reconnexion...")
                self.cap.release()
                self.cap = None
                continue

            # --- CALCUL DU FACTEUR D'ÉCHELLE (RESPONSIVE) ---
            h, w = frame.shape[:2]
            # On utilise 600px comme hauteur de référence.
            # Si l'image est plus grande, sf sera > 1.0 (ex: 1.8 pour du 1080p)
            sf = max(0.5, h / 600.0) 

            # Variables de style ajustées
            thick_sm = max(1, int(1 * sf))  # Épaisseur petite
            thick_lg = max(2, int(2 * sf))  # Épaisseur grande
            font_sm = 0.5 * sf              # Taille police petite
            font_md = 0.8 * sf              # Taille police moyenne
            font_lg = 1.2 * sf              # Taille police grande
            
            # Marges ajustées
            margin_x = int(20 * sf)
            margin_y = int(55 * sf)
            banner_h = int(80 * sf)

            # 3. Traitement IA (YOLO)
            try:
                results = model(frame, classes=[0], conf=0.5, verbose=False, device=device)
                
                num_persons = 0
                detection_actuelle = False
                
                if len(results) > 0 and results[0].boxes is not None:
                    num_persons = len(results[0].boxes)
                    detection_actuelle = num_persons > 0

                    for box in results[0].boxes:
                        x1, y1, x2, y2 = map(int, box.xyxy[0])
                        # Dessin Responsive
                        cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), thick_lg)
                        
                        label = f"Personne {float(box.conf[0]):.2f}"
                        # Ajustement de la position Y pour que ça reste collé à la boîte
                        text_y = max(y1 - int(10 * sf), int(20 * sf)) 
                        
                        cv2.putText(frame, label, (x1, text_y), 
                                    cv2.FONT_HERSHEY_SIMPLEX, font_sm, (0, 255, 0), thick_lg)

                # Logique Métier (Présence/Absence)
                if detection_actuelle:
                    self.absence_counter = 0
                    self.presence_status = True
                else:
                    if self.presence_status:
                        self.absence_counter += 1
                        if self.absence_counter > self.patience_frames:
                            self.presence_status = False
                            self.absence_counter = 0
                        else:
                            # Message Responsive
                            cv2.putText(frame, "Cible perdue...", (margin_x, int(150 * sf)), 
                                        cv2.FONT_HERSHEY_SIMPLEX, font_md, (0, 165, 255), thick_lg)

                # Bannière Responsive
                if self.presence_status:
                    color = (0, 200, 0)
                    txt = f"PERSONNES: {num_persons}" if detection_actuelle else "RECHERCHE..."
                else:
                    color = (50, 50, 50)
                    txt = "ZONE VIDE"

                cv2.rectangle(frame, (0, 0), (w, banner_h), color, -1)
                cv2.putText(frame, txt, (margin_x, margin_y), cv2.FONT_HERSHEY_SIMPLEX, font_lg, (255, 255, 255), thick_lg)

                # Update Global Status (pour l'API JSON)
                global_status["personnes"] = num_persons
                global_status["presence"] = self.presence_status
                global_status["message"] = txt
                global_status["camera_index"] = self.cam_index # <--- Critical for Dashboard

            except Exception as e:
                print(f"Erreur IA: {e}")

            # 4. Encodage JPEG pour le stream
            _, buffer = cv2.imencode('.jpg', frame)
            self.latest_frame = buffer.tobytes()
            
            # Petite pause pour limiter le CPU (optionnel, ~30 FPS)
            time.sleep(0.01)

# Instanciation globale de la caméra
video_camera = VideoCamera()

def gen_frames():
    """Générateur pour le StreamingResponse. Ne touche JAMAIS au hardware."""
    while True:
        frame = video_camera.get_frame()
        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + frame + b'\r\n')
        # Limite le débit d'envoi au client web pour ne pas saturer le réseau
        time.sleep(0.04) 

@app.get("/video_feed")
def video_feed():
    return StreamingResponse(gen_frames(), media_type="multipart/x-mixed-replace; boundary=frame")

@app.get("/api/data")
def get_data():
    return JSONResponse(global_status)

@app.get("/api/set_camera/{index}")
def set_camera(index: int):
    # On demande simplement au thread de changer de source
    video_camera.set_source(index)
    return JSONResponse({"status": "success", "message": f"Switched to camera {index}"})

if __name__ == "__main__":
    import uvicorn
    # run single process
    uvicorn.run(app, host="0.0.0.0", port=3000)