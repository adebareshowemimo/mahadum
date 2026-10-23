import pathlib, subprocess, tempfile
source = pathlib.Path('/mnt/c/xampp/htdocs/mahamu360/deploy/deploy.sh').read_text()
health = source[source.index('# A healthy API'):source.index('\ntrap - ERR', source.index('# A healthy API'))]
with tempfile.TemporaryDirectory(prefix='mahadum-all-assets-') as directory:
    root = pathlib.Path(directory)
    (root/'public/assets').mkdir(parents=True)
    for filename in ['app.js','app.css','FamiliesPage.js']:
        (root/'public/assets'/filename).write_text('test')
    for forbidden in [False, True]:
        (root/'calls').write_text('')
        prelude = '''set -euo pipefail
trap 'echo rollback; exit 17' ERR
HEALTH_URL=https://example.test/up
php() { printf '/assets/app.js\\n/assets/app.css\\n'; }
curl() { printf '%s\\n' "${@: -1}" >> calls; ''' + ('[[ "${@: -1}" != *FamiliesPage.js ]];' if forbidden else 'return 0;') + ' }\n'
        result = subprocess.run(['bash','-c',prelude + health],cwd=root,capture_output=True,text=True)
        if forbidden:
            assert result.returncode == 17 and 'rollback' in result.stdout, result
        else:
            assert result.returncode == 0, result
            assert set((root/'calls').read_text().splitlines()) == {'https://example.test/assets/app.js','https://example.test/assets/app.css','https://example.test/assets/FamiliesPage.js'}
print('PASS: all lazy page assets checked; forbidden Families chunk triggers rollback.')