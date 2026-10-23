import difflib
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parent.parent

def stage(path, desired):
    original = subprocess.check_output(['git', 'show', 'HEAD:' + path], cwd=root).decode()
    patch = ''.join(difflib.unified_diff(original.splitlines(True), desired.splitlines(True), fromfile='a/' + path, tofile='b/' + path))
    subprocess.run(['git', 'apply', '--cached', '--whitespace=error', '-'], input=patch.encode(), cwd=root, check=True)

path = 'web/src/pages/RegisterPage.tsx'
original = subprocess.check_output(['git', 'show', 'HEAD:' + path], cwd=root).decode()
old = "  const [languageVote, setLanguageVote] = useState('')\n  const [otherLanguage, setOtherLanguage] = useState('')"
new = """  const [languageVote, setLanguageVote] = useState(() => {
    const vote = searchParams.get('vote') ?? ''
    return VOTE_OPTIONS.includes(vote) ? vote : ''
  })
  const [otherLanguage, setOtherLanguage] = useState(() => (searchParams.get('other_language') ?? '').trim().slice(0, 60))"""
assert old in original
stage(path, original.replace(old, new, 1))

path = 'web/src/pages/RegisterPage.test.tsx'
original = subprocess.check_output(['git', 'show', 'HEAD:' + path], cwd=root).decode()
working = (root / path).read_text(encoding='utf-8')
start = working.index("  it('keeps a suggested language when continuing from the vote page'")
end = working.index("  it('preselects Hausa", start)
addition = working[start:end]
marker = "describe('RegisterPage age gate', () => {\n"
assert marker in original
stage(path, original.replace(marker, marker + addition, 1))
print('Staged voting signup changes without unrelated registration edits.')
