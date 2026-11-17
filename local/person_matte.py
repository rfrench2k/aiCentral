"""
person_matte.py - local person cut-out for AI Central (provider local_matte; ai_runLocalModel in includes/ai_functions.php
runs it). Cuts the person out of a talking video with Robust Video Matting (a TorchScript model) on this server's graphics
card; free, nothing leaves the server. The model keeps its state from frame to frame, so the edge is steady. The answer is
a grey video the same length as the input, one frame for each frame of the picture: white = the person, black = the
background, soft in between (nothing is cut hard). A caller puts that in front of any background (ffmpeg alphamerge).

  python person_matte.py <video> <out json> --model <model code, the file name without .torchscript>
                         --models-dir <folder> --options <json file> [--progress <file>]

options: {"out": <the grey video to write, .mp4>, "ffmpeg": <ffmpeg program>, "fps": 30, "width": 1080, "height": 1920,
          "downsample_ratio": 0.25, "seconds": <video length, for progress>}
         The picture is scaled to cover width x height and cropped to it first (the same fill a 9:16 edit uses), so the
         grey video lines up with it. downsample_ratio: the model works on a picture this part of the size (0.25 for 1080 x 1920).
out:     {"matte": <the grey video>, "frames": <count>, "fps": <fps>, "width": <w>, "height": <h>}
Prints one JSON line: {"seconds": <video length>, "count": <frames>}.
"""
import argparse
import json
import subprocess
import sys
from pathlib import Path

import numpy as np
import torch

p = argparse.ArgumentParser()
p.add_argument("media")
p.add_argument("out")
p.add_argument("--model", required=True)
p.add_argument("--models-dir", required=True)
p.add_argument("--options", required=True)
p.add_argument("--progress", default="")
a = p.parse_args()
opt = json.load(open(a.options, encoding="utf-8"))
ffmpeg = opt.get("ffmpeg") or "ffmpeg"
fps = int(opt.get("fps", 30))
W, H = int(opt.get("width", 1080)), int(opt.get("height", 1920))
ratio = float(opt.get("downsample_ratio", 0.25))
length = float(opt.get("seconds") or 0)
target = opt.get("out")
if not target:
    print("options.out (the grey video to write) is missing")
    sys.exit(1)
dev = "cuda" if torch.cuda.is_available() else "cpu"
model = torch.jit.load(str(Path(a.models_dir) / (a.model + ".torchscript")), map_location=dev).eval()

dec = subprocess.Popen([ffmpeg, "-nostdin", "-v", "error", "-i", a.media, "-an", "-vf", f"fps={fps},scale={W}:{H}:force_original_aspect_ratio=increase,crop={W}:{H}",
                        "-f", "rawvideo", "-pix_fmt", "rgb24", "-"], stdout=subprocess.PIPE, stdin=subprocess.DEVNULL)
enc = subprocess.Popen([ffmpeg, "-nostdin", "-v", "error", "-y", "-f", "rawvideo", "-pix_fmt", "gray", "-s", f"{W}x{H}", "-r", str(fps), "-i", "-",
                        "-c:v", "libx264", "-crf", "12", "-preset", "veryfast", "-pix_fmt", "gray", target], stdin=subprocess.PIPE)
size = W * H * 3
rec = [None] * 4                                                    # the model's state, carried from frame to frame
n = 0
with torch.no_grad():
    while True:
        raw = dec.stdout.read(size)
        if len(raw) < size:
            break
        src = torch.from_numpy(np.frombuffer(raw, np.uint8).reshape(H, W, 3).copy()).to(dev).permute(2, 0, 1).float().div(255).unsqueeze(0)
        _, pha, *rec = model(src, *rec, ratio)
        enc.stdin.write((pha[0, 0].clamp(0, 1) * 255).round().byte().cpu().numpy().tobytes())
        n += 1
        if a.progress and length and n % 30 == 0:
            Path(a.progress).write_text(str(min(99, int(n / fps * 100 / length))))
enc.stdin.close()
dec.stdout.close()
enc.wait()
dec.wait()
if enc.returncode or n == 0 or not Path(target).is_file():
    print("the cut-out could not be made")
    sys.exit(1)
json.dump({"matte": target, "frames": n, "fps": fps, "width": W, "height": H}, open(a.out, "w"))
print(json.dumps({"seconds": round(n / fps, 2), "count": n}))
