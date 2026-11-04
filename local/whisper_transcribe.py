"""
whisper_transcribe.py - local speech to text for AI Central (provider local_whisper; ai_makeTranscriptionRequest in
includes/ai_functions.php runs it). Uses faster-whisper on this server's graphics card; free, the audio never leaves
the server. The Python that has faster-whisper and the folder the models are kept in are set in AI Central's config
(includes/config.php); the model is the feature's model row (its model_code, e.g. large-v3).

  python whisper_transcribe.py <audio file> <out text file> --model large-v3 --models-dir <folder> [--language en]

Reads any recording faster-whisper reads (.m4a, .webm, .mp3, .wav, .mp4 ...). Writes the words to the out file, one
line per stretch of speech, and prints one JSON line: {"seconds": <audio length>, "language": <language heard>}.
"""
import argparse
import json
import os

import torch

os.add_dll_directory(os.path.join(os.path.dirname(torch.__file__), "lib"))   # CUDA/cuDNN for faster-whisper
from faster_whisper import WhisperModel

p = argparse.ArgumentParser()
p.add_argument("audio")
p.add_argument("out")
p.add_argument("--model", required=True)
p.add_argument("--models-dir", required=True)
p.add_argument("--language", default="")   # empty: detected
a = p.parse_args()

model = WhisperModel(a.model, device="cuda", compute_type="float16", download_root=a.models_dir)
# not conditioned on the previous text: a long recording otherwise can get stuck repeating one word ("uh, uh, ...")
segments, info = model.transcribe(a.audio, language=a.language or None, vad_filter=True, condition_on_previous_text=False)
with open(a.out, "w", encoding="utf-8") as f:
    for s in segments:
        f.write(s.text.strip() + "\n")
print(json.dumps({"seconds": round(info.duration, 1), "language": info.language}))
