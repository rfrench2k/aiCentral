"""
whisper_transcribe.py - local speech to text for AI Central (provider local_whisper; ai_makeTranscriptionRequest and
ai_runLocalModel in includes/ai_functions.php run it). Uses faster-whisper on this server's graphics card; free, the
audio never leaves the server. The Python that has faster-whisper and the folder the models are kept in are set in AI
Central's config (includes/config.php); the model is the feature's model row (its model_code, e.g. large-v3).

  python whisper_transcribe.py <audio file> <out text file> --model large-v3 --models-dir <folder> [--language en]
  python whisper_transcribe.py <audio file> <out json> --model large-v3 --models-dir <folder> --options <json file> [--progress <file>]

Reads any recording faster-whisper reads (.m4a, .webm, .mp3, .wav, .mp4 ...). Plain: writes the words to the out file,
one line per stretch of speech. With --options (ai_runLocalModel; {"language": "en", "beam_size": 5, "seconds": <length,
for progress>}): writes JSON {"segments": [{"start", "end", "text", "no_speech_prob", "avg_logprob", "words":
[[start, end, word], ...]}]}, every word with its own times. Prints one JSON line: {"seconds": <audio length>, "language": <heard>}.
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
p.add_argument("--options", default="")
p.add_argument("--progress", default="")
a = p.parse_args()
opt = json.load(open(a.options, encoding="utf-8")) if a.options else {}

model = WhisperModel(a.model, device="cuda", compute_type="float16", download_root=a.models_dir)
# not conditioned on the previous text: a long recording otherwise can get stuck repeating one word ("uh, uh, ...")
if not a.options:
    segments, info = model.transcribe(a.audio, language=a.language or None, vad_filter=True, condition_on_previous_text=False)
    with open(a.out, "w", encoding="utf-8") as f:
        for s in segments:
            f.write(s.text.strip() + "\n")
else:
    segments, info = model.transcribe(a.audio, language=opt.get("language") or None, vad_filter=True, beam_size=int(opt.get("beam_size", 5)),
                                      condition_on_previous_text=False, word_timestamps=True)
    total = float(opt.get("seconds") or 0)
    out = []
    for s in segments:
        out.append({"start": round(s.start, 3), "end": round(s.end, 3), "text": s.text, "no_speech_prob": round(s.no_speech_prob, 4),
                    "avg_logprob": round(s.avg_logprob, 4), "words": [[round(w.start, 3), round(w.end, 3), w.word] for w in (s.words or [])]})
        if a.progress and total:
            open(a.progress, "w").write(str(min(99, round(s.end * 100 / total))))
    json.dump({"segments": out}, open(a.out, "w", encoding="utf-8"))
print(json.dumps({"seconds": round(info.duration, 1), "language": info.language}))
