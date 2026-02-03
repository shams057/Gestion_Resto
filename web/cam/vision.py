import cv2
from ultralytics import YOLO
import torch
import time
import os
import signal
import sys
import glob
import subprocess

# Clear old output files
for f in os.listdir('.'):
    if f.startswith('output_frame_') or f.startswith('detection_'):
        os.remove(f)

print("⚡ GPU YOLOv8 PERSON DETECTION")
print("Chargement du modèle...")

# 🔥 LOAD MODEL + FORCE GPU
model = YOLO('yolov8m.pt')

# CRITICAL GPU LINES:
device = 'cuda' if torch.cuda.is_available() else 'cpu'
print(f"🔍 CUDA Available: {torch.cuda.is_available()}")

# Move model to GPU
if torch.cuda.is_available():
    model.to('cuda')
    torch.backends.cudnn.benchmark = True
    print("✅ GPU ACTIVATED - yolov8m on CUDA!")
else:
    print("⚠️  No GPU detected - using CPU")

print(f"🖥️  Final device: {model.device}")
cap = cv2.VideoCapture(1)
cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)

# Parameters
PATIENCE_FRAMES = 10 
absence_counter = 0
presence_status = False
frame_count = 0
start_time = time.time()

print("\n🚀 LIVE STREAMING ACTIVÉ!")
print("📺 Terminal 1: ffplay -f image2 -framerate 30 -i /tmp/live_stream.jpg")
print("🎬 Ctrl+C = Vidéo MP4 + Arrêt propre")

def signal_handler(sig, frame):
    print("\n\n🎬 Création vidéo finale...")
    
    # Convert images to MP4
    images = sorted(glob.glob('output_frame_*.jpg'), 
                   key=lambda x: int(''.join(filter(str.isdigit, x.split('_')[2]))))
    
    if len(images) >= 2:
        first_frame = cv2.imread(images[0])
        height, width, _ = first_frame.shape
        
        fourcc = cv2.VideoWriter_fourcc(*'mp4v')
        video_filename = f'detection_video_{int(start_time)}.mp4'
        out = cv2.VideoWriter(video_filename, fourcc, 20.0, (width, height))
        
        for img_path in images:
            frame = cv2.imread(img_path)
            out.write(frame)
        out.release()
        print(f"✅ VIDÉO SAUVÉE: {video_filename}")
    
    cap.release()
    print(f"\n📊 RÉSUMÉ:")
    print(f"   Frames: {frame_count}")
    print(f"   Durée: {time.time() - start_time:.1f}s")
    print(f"   FPS moyen: {frame_count/(time.time()-start_time):.1f}")
    sys.exit(0)

signal.signal(signal.SIGINT, signal_handler)

try:
    while True:
        ret, frame = cap.read()
        if not ret:
            print("❌ Caméra déconnectée")
            break

        frame_count += 1
        
        # === DÉTECTION YOLO GPU ===
        # FORCE GPU in prediction
        results = model(frame, classes=[0], conf=0.4, iou=0.5, verbose=False, device=device)
        detection_in_frame = len(results[0].boxes) > 0

        # === LOGIQUE PATIENCE 10 FRAMES ===
        if detection_in_frame:
            absence_counter = 0
            presence_status = True
            
            # Boîtes vertes + cercles
            for box in results[0].boxes:
                x1, y1, x2, y2 = map(int, box.xyxy[0])
                cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), 3)
                cv2.circle(frame, (x1, y1), 5, (0, 255, 0), -1)
                cv2.circle(frame, (x2, y2), 5, (0, 255, 0), -1)

        else:
            if presence_status:
                absence_counter += 1
                if absence_counter > PATIENCE_FRAMES:
                    presence_status = False
                    absence_counter = 0
                else:
                    cv2.putText(frame, "Cible perdue momentanement...", (20, 100), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 165, 255), 2)

        # === BANNière STATUT ===
        if presence_status:
            cv2.rectangle(frame, (0, 0), (frame.shape[1], 80), (0, 200, 0), -1)
            cv2.putText(frame, "👤 PERSONNE CONFIRMEE", (50, 55), 
                       cv2.FONT_HERSHEY_TRIPLEX, 1.5, (255, 255, 255), 3)
        else:
            cv2.rectangle(frame, (0, 0), (frame.shape[1], 80), (50, 50, 50), -1)
            cv2.putText(frame, "🚫 ZONE VIDE", (50, 55), 
                       cv2.FONT_HERSHEY_TRIPLEX, 1.5, (200, 200, 200), 2)

        # GPU VRAM overlay
        if torch.cuda.is_available():
            vram_used = torch.cuda.memory_allocated() / 1e9
            cv2.putText(frame, f"GPU:{vram_used:.1f}GB", (frame.shape[1]-200, 30), 
                       cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 255), 2)

        # === LIVE STREAM + SAUVEGARDE ===
        cv2.imwrite('/tmp/live_stream.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 90])
        filename = f'output_frame_{frame_count:06d}.jpg'
        cv2.imwrite(filename, frame, [cv2.IMWRITE_JPEG_QUALITY, 90])
        
        # Console live
        if frame_count % 10 == 0:
            status = "👤 PERSONNE" if presence_status else "🚫 VIDE"
            detections = len(results[0].boxes)
            fps = frame_count / (time.time() - start_time)
            print(f"📸 {frame_count:04d} | {status} | {detections} pers. | FPS: {fps:.1f}")

        time.sleep(0.03)  # 33 FPS

except KeyboardInterrupt:
    pass

finally:
    signal_handler(None, None)
