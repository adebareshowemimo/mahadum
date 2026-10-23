from pathlib import Path
from PIL import Image
import json

root = Path(r'C:/xampp/htdocs/mahamu360/web/public/images/languages')
files = json.loads(r'''{"yoruba":"C:\\Users\\adeba\\.codex\\generated_images\\01a15499-8c1c-7403-8178-d2e6bbc813fc\\exec-d27ee07e-6fa7-4ca4-9eda-1f8738b31067.png","igbo":"C:\\Users\\adeba\\.codex\\generated_images\\01a15499-8c1c-7403-8178-d2e6bbc813fc\\exec-0278081e-d3ef-4693-8124-c5388baeff4a.png","hausa":"C:\\Users\\adeba\\.codex\\generated_images\\01a15499-8c1c-7403-8178-d2e6bbc813fc\\exec-5f928e63-6cee-43d4-9161-80f487c5b9e8.png","english":"C:\\Users\\adeba\\.codex\\generated_images\\01a15499-8c1c-7403-8178-d2e6bbc813fc\\exec-ed1462fe-9020-4806-8da9-d105cd75faa9.png"}''')
for key, source in files.items():
    image = Image.open(source)
    image.resize((960, 640), Image.Resampling.LANCZOS).save(root / (key + '-app.webp'), 'WEBP', quality=86)
    print(key, (root / (key + '-app.webp')).stat().st_size)
