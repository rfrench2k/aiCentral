"""
face_scan.py - local face finding for AI Central (provider local_face; ai_runLocalModel in includes/ai_functions.php
runs it). Finds the faces in a video every few seconds (or in one picture) with insightface on this server's graphics
card; free, nothing leaves the server. Each face: where it is, how sure, and its face vector (apps compare vectors,
cosine, to group faces and match people).

  python face_scan.py <video, picture, or .json list of pictures> <out json> --model <model code, e.g. buffalo_l>
                      --models-dir <folder> --options <json file> [--progress <file>]

options: {"every_seconds": 2.0, "min_score": 0.65, "min_pixels": 36, "det_size": 640, "seconds": <video length, for progress>,
          "max_side": <pictures larger than this are shrunk first, e.g. 1600>}
out:     {"faces": [[time_sec, x, y, w, h, score, [512 floats]], ...]}   x, y, w, h as parts of the picture (0-1)
         for a .json list of picture files: {"pictures": [[faces as above, time 0], or null when it could not be read, ...]}
Prints one JSON line: {"seconds": <video length>, "count": <faces>}.
"""
import argparse
import json
import os

import cv2
import numpy as np
import onnxruntime

onnxruntime.preload_dlls()        # its CUDA libraries (the nvidia-* packages), so faces run on the graphics card
from insightface.app import FaceAnalysis

p = argparse.ArgumentParser()
p.add_argument("media")
p.add_argument("out")
p.add_argument("--model", required=True)
p.add_argument("--models-dir", required=True)
p.add_argument("--options", required=True)
p.add_argument("--progress", default="")
a = p.parse_args()
opt = json.load(open(a.options, encoding="utf-8"))
every, min_score, min_px = float(opt.get("every_seconds", 2.0)), float(opt.get("min_score", 0.65)), float(opt.get("min_pixels", 36))
size = int(opt.get("det_size", 640))

fa = FaceAnalysis(name=a.model, root=a.models_dir, providers=["CUDAExecutionProvider", "CPUExecutionProvider"],
                  allowed_modules=["detection", "recognition"])
fa.prepare(ctx_id=0, det_size=(size, size))


def faces_in(img, t):
    h, w = img.shape[:2]
    out = []
    for f in fa.get(img):
        x1, y1, x2, y2 = (float(v) for v in f.bbox)
        if f.det_score < min_score or min(x2 - x1, y2 - y1) < min_px:
            continue
        out.append([round(t, 3), max(0, x1) / w, max(0, y1) / h, (x2 - x1) / w, (y2 - y1) / h, round(float(f.det_score), 4),
                    [round(float(x), 6) for x in f.normed_embedding.astype(np.float32)]])
    return out


def picture(path):
    img = cv2.imread(path) if os.path.exists(path) else None
    most = int(opt.get("max_side") or 0)
    if img is not None and most and max(img.shape[:2]) > most:
        h, w = img.shape[:2]
        k = most / max(h, w)
        img = cv2.resize(img, (int(w * k), int(h * k)))
    return img


found = []
if a.media.lower().endswith(".json"):          # many pictures, one model load
    files = json.load(open(a.media, encoding="utf-8"))
    pics = []
    for n, path in enumerate(files):
        img = picture(path)
        pics.append(None if img is None else faces_in(img, 0.0))
        if a.progress and n % 50 == 0:
            open(a.progress, "w").write(str(round(n * 100 / max(1, len(files)))))
    json.dump({"pictures": pics}, open(a.out, "w", encoding="utf-8"))
    print(json.dumps({"seconds": 0, "count": sum(len(p) for p in pics if p)}))
    raise SystemExit(0)
img = picture(a.media) if a.media.lower().endswith((".jpg", ".jpeg", ".png", ".webp", ".bmp")) else None
if img is not None:
    found, length = faces_in(img, 0.0), 0.0
else:
    cap = cv2.VideoCapture(a.media)
    fps = cap.get(cv2.CAP_PROP_FPS) or 29.97
    total = float(opt.get("seconds") or (cap.get(cv2.CAP_PROP_FRAME_COUNT) / fps if fps else 0))
    step = max(1, int(round(fps * every)))
    frame_no = 0
    while cap.grab():
        if frame_no % step == 0:
            ok, frame = cap.retrieve()
            if ok:
                found += faces_in(frame, frame_no / fps)
            if a.progress and total and frame_no % (step * 30) == 0:
                open(a.progress, "w").write(str(min(99, round(frame_no / fps * 100 / total))))
        frame_no += 1
    cap.release()
    length = frame_no / fps
json.dump({"faces": found}, open(a.out, "w", encoding="utf-8"))
print(json.dumps({"seconds": round(length, 1), "count": len(found)}))
