from pathlib import Path
from PIL import Image
import json

root = Path(r'C:/xampp/htdocs/mahamu360')
files = json.loads(r'''{"yoruba":"C:\\Users\\adeba\\.codex\\generated_images\\01a15499-8c1c-7403-8178-d2e6bbc813fc\\exec-11d51c05-99c9-4d78-8943-23ba502b58ca.png","igbo":"C:\\Users\\adeba\\.codex\\generated_images\\01a15499-8c1c-7403-8178-d2e6bbc813fc\\exec-d4839eb6-2e7c-450f-bc91-52b731a11bfd.png","hausa":"C:\\Users\\adeba\\.codex\\generated_images\\01a15499-8c1c-7403-8178-d2e6bbc813fc\\exec-a87ef05c-7744-44dc-9740-e1e369d5077d.png","english":"C:\\Users\\adeba\\.codex\\generated_images\\01a15499-8c1c-7403-8178-d2e6bbc813fc\\exec-1fdfb3fa-8bad-4989-9eb9-a93f708eb06a.png"}''')
for key, source in files.items():
    image = Image.open(source)
    output = root / 'web/public/images/languages' / (key + '-natural.webp')
    image.resize((960, 640), Image.Resampling.LANCZOS).save(output, 'WEBP', quality=86)
    print(key, output.stat().st_size)
    build_output = root / 'web/dist/images/languages' / output.name
    if build_output.parent.is_dir():
        build_output.write_bytes(output.read_bytes())
