import pathlib, tempfile, subprocess, os
source = pathlib.Path('/mnt/c/xampp/htdocs/mahamu360/deploy/deploy.sh').read_text()
start = source.index('for code_dir in app bootstrap')
block = source[start:source.index('\necho "==> Building the SPA"', start)]
with tempfile.TemporaryDirectory(prefix='mahadum-code-permissions-') as directory:
    root = pathlib.Path(directory)
    for name in ['app','bootstrap','config','database','routes','resources','vendor','bootstrap/cache','storage']:
        path = root/name; path.mkdir(parents=True,exist_ok=True); os.chmod(path,0o700)
        file = path/'fixture.php'; file.write_text('test'); os.chmod(file,0o600)
    secret = root/'.env'; secret.write_text('test'); os.chmod(secret,0o600)
    subprocess.run(['bash','-c','set -euo pipefail\n'+block],cwd=root,check=True)
    for name in ['app','bootstrap','config','database','routes','resources','vendor']:
        assert (root/name).stat().st_mode & 0o777 == 0o755
        assert (root/name/'fixture.php').stat().st_mode & 0o777 == 0o644
    for name in ['bootstrap/cache','storage']:
        assert (root/name).stat().st_mode & 0o777 == 0o700
        assert (root/name/'fixture.php').stat().st_mode & 0o777 == 0o600
    assert secret.stat().st_mode & 0o777 == 0o600
print('PASS: application permissions repaired; environment, caches and storage permissions preserved.')