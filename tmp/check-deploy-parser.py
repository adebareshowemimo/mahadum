import pathlib, subprocess, re
source = pathlib.Path('deploy/deploy.sh').read_text()
code = re.search(r"done < <\(php -r '([^']+)'\)", source).group(1)
code = code.replace('resources/spa/index.html','web/dist/index.html')
result = subprocess.run(['C:/xampp/php/php.exe','-r',code],capture_output=True,text=True,check=True)
paths = result.stdout.splitlines()
assert len(paths) >= 4 and all(p.startswith('/assets/') for p in paths), result
assert any(p.endswith('.css') for p in paths) and any(p.endswith('.js') for p in paths)
print('PASS: exact deployment parser found', len(paths), 'scripts/styles in the real Vite build.')