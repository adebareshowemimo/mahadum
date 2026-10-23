from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse, unquote

root = Path(__file__).resolve().parent.parent

class Preview(SimpleHTTPRequestHandler):
    def translate_path(self, url):
        path = unquote(urlparse(url).path).lstrip('/')
        if path in ('', 'languages'):
            return str(root / 'tmp/languages-preview.html')
        for folder in ('web/public', 'web/dist'):
            base = (root / folder).resolve()
            candidate = (base / path).resolve()
            if candidate.is_relative_to(base) and candidate.is_file():
                return str(candidate)
        return str(root / 'tmp/no-preview-file')

ThreadingHTTPServer(('127.0.0.1', 8767), Preview).serve_forever()
