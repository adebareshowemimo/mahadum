from pathlib import Path
from PIL import Image
import json
root=Path(r'C:/xampp/htdocs/mahamu360/web/public/images/languages')
root.mkdir(parents=True,exist_ok=True)
files={"yoruba":"exec-e083beb2-d248-4a73-aa59-754c42f3f5e9.png","igbo":"exec-6c8c4a1a-2177-4f7a-8590-aebd21ad8335.png","hausa":"exec-a5dfe28b-a7ff-47a3-8298-0d295f75a24d.png","english":"exec-f50f7855-1be5-41ca-8e0e-bf3e50f3e59b.png"}
for key,name in files.items():
 im=Image.open(Path(r'C:/Users/adeba/.codex/generated_images/01a15499-8c1c-7403-8178-d2e6bbc813fc/')/name)
 im.resize((960,640),Image.Resampling.LANCZOS).save(root/(key+'.webp'),'WEBP',quality=86)
 print(key,(root/(key+'.webp')).stat().st_size)
