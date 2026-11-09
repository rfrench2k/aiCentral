"""
voice_prints.py - local voice prints for AI Central (provider local_voice; ai_runLocalModel in includes/ai_functions.php
runs it). One vector per stretch of a recording, from the ECAPA speaker model (speechbrain) on this server's graphics
card; free, the audio never leaves the server. Apps compare the vectors (cosine) to tell voices apart and match people.

  python voice_prints.py <16 kHz mono wave file> <out json> --model <model code> --models-dir <folder> --options <json file>

options: {"segments": [[start_sec, end_sec], ...], "max_seconds": 10}   (each print uses at most max_seconds of its stretch)
out:     {"vectors": [[...192 floats, length 1...] or null (too short), ...]}  one per segment, in order
Prints one JSON line: {"seconds": <audio length>, "count": <vectors made>}.
"""
import argparse
import json
import os
import wave

import numpy as np
import torch

os.add_dll_directory(os.path.join(os.path.dirname(torch.__file__), "lib"))   # CUDA/cuDNN

MODELS = {"ecapa-voxceleb": "speechbrain/spkrec-ecapa-voxceleb"}   # model code -> its speechbrain source

p = argparse.ArgumentParser()
p.add_argument("audio")
p.add_argument("out")
p.add_argument("--model", required=True)
p.add_argument("--models-dir", required=True)
p.add_argument("--options", required=True)
p.add_argument("--progress", default="")
a = p.parse_args()
opt = json.load(open(a.options, encoding="utf-8"))

from speechbrain.inference.speaker import EncoderClassifier
enc = EncoderClassifier.from_hparams(source=MODELS[a.model], savedir=a.models_dir, run_opts={"device": "cuda"})
with wave.open(a.audio, "rb") as w:
    rate = w.getframerate()
    audio = np.frombuffer(w.readframes(w.getnframes()), dtype=np.int16).astype(np.float32) / 32768.0
segs = opt.get("segments") or []
most = float(opt.get("max_seconds") or 10)
out, made = [], 0
for n, (s, e) in enumerate(segs):
    clip = audio[int(s * rate):int(min(e, s + most) * rate)]
    if len(clip) < rate * 0.3:
        out.append(None)
        continue
    with torch.no_grad():
        v = enc.encode_batch(torch.from_numpy(clip).unsqueeze(0).to("cuda")).squeeze().cpu().numpy()
    out.append([round(float(x), 6) for x in v / (np.linalg.norm(v) or 1)])
    made += 1
    if a.progress and n % 20 == 0:
        open(a.progress, "w").write(str(round(n * 100 / max(1, len(segs)))))
json.dump({"vectors": out}, open(a.out, "w", encoding="utf-8"))
print(json.dumps({"seconds": round(len(audio) / rate, 1), "count": made}))
