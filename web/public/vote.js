(() => {
  const search = document.getElementById('language-search');
  const grid = document.getElementById('vote-grid');
  const dialog = document.getElementById('vote-dialog');
  if (!search || !grid || !dialog) return;

  const cards = [...grid.querySelectorAll('[data-language]')];
  const sort = document.getElementById('vote-sort');
  const clear = document.getElementById('vote-clear');
  const results = document.getElementById('vote-results');
  const other = document.getElementById('other-language');
  const confirm = document.getElementById('vote-confirm');
  const dialogMessage = document.getElementById('vote-dialog-message');
  const feedback = document.getElementById('vote-feedback');
  const feedbackText = document.getElementById('vote-feedback-text');
  const feedbackLink = document.getElementById('vote-feedback-link');
  const buttons = [...document.querySelectorAll('[data-vote]')];
  let selection = null;
  let busy = false;
  let recorded = false;

  function token() {
    try { return localStorage.getItem('mahadum.token'); } catch { return null; }
  }

  function filterAndSort() {
    const term = search.value.trim().toLocaleLowerCase();
    const ordered = [...cards].sort((a, b) => {
      const byName = a.dataset.language.localeCompare(b.dataset.language);
      return sort.value === 'votes' ? Number(b.dataset.count) - Number(a.dataset.count) || byName : byName;
    });
    let visible = 0;
    ordered.forEach(card => {
      card.hidden = !card.dataset.language.toLocaleLowerCase().includes(term);
      if (!card.hidden) visible++;
      grid.append(card);
    });
    clear.hidden = !search.value;
    document.getElementById('vote-empty').hidden = visible !== 0;
    results.textContent = term ? `${visible} ${visible === 1 ? 'language matches' : 'languages match'} your search` : `${cards.length} languages to choose from`;
  }

  function resetSearch() {
    search.value = '';
    filterAndSort();
    search.focus();
  }

  function announce(message, success = false, needsLogin = false) {
    feedbackText.textContent = message;
    feedback.hidden = false;
    feedback.classList.toggle('is-success', success);
    feedbackLink.hidden = !needsLogin;
    feedback.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }

  function markRecorded() {
    recorded = true;
    buttons.forEach(button => { button.disabled = true; });
  }

  function closeDialog() {
    dialog.close();
  }

  function choose(button) {
    if (busy || recorded) return;
    const language = button.dataset.vote;
    if (language === 'Other') {
      other.setCustomValidity(other.value.trim() ? '' : 'Enter the language you would like to suggest.');
      if (!other.reportValidity()) { other.focus(); return; }
    }
    selection = { language, otherLanguage: language === 'Other' ? other.value.trim() : '' };
    const name = language === 'Other' ? selection.otherLanguage : language;
    cards.forEach(card => card.classList.toggle('is-selected', card.dataset.language === language));
    buttons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    document.getElementById('vote-selected-name').textContent = name;
    const signedIn = Boolean(token());
    document.getElementById('vote-dialog-description').textContent = signedIn
      ? 'Each account gets one vote. Once recorded, your choice cannot be changed here.'
      : 'Create an account to save this choice, then verify your email to make it count. Each account gets one vote.';
    document.getElementById('vote-dialog-auth').hidden = signedIn;
    confirm.textContent = signedIn ? 'Confirm my vote →' : 'Continue to sign up →';
    confirm.disabled = false;
    dialogMessage.textContent = '';
    dialog.showModal();
  }

  search.addEventListener('input', filterAndSort);
  sort.addEventListener('change', filterAndSort);
  clear.addEventListener('click', resetSearch);
  document.getElementById('vote-reset').addEventListener('click', resetSearch);
  other.addEventListener('input', () => other.setCustomValidity(''));
  other.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      choose(buttons.find(button => button.dataset.vote === 'Other'));
    }
  });
  buttons.forEach(button => button.addEventListener('click', () => choose(button)));
  document.getElementById('vote-dialog-close').addEventListener('click', closeDialog);
  document.getElementById('vote-dialog-back').addEventListener('click', closeDialog);
  dialog.addEventListener('click', event => { if (event.target === dialog) {
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeDialog();
  } });

  confirm.addEventListener('click', async () => {
    if (!selection || busy || recorded) return;
    const accessToken = token();
    if (!accessToken) {
      const query = new URLSearchParams({ vote: selection.language });
      if (selection.otherLanguage) query.set('other_language', selection.otherLanguage);
      window.location.assign('/register?' + query.toString());
      return;
    }
    busy = true;
    confirm.disabled = true;
    confirm.textContent = 'Recording your vote…';
    dialogMessage.textContent = '';
    // Capture the confirmed choice so pending requests never follow a later selection.
    const submitted = { ...selection };
    try {
      const response = await fetch('/api/v1/language-votes', {
        method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: 'Bearer ' + accessToken },
        body: JSON.stringify({ language: submitted.language, other_language: submitted.otherLanguage || null }),
      });
      const data = await response.json().catch(() => ({}));
      if (response.ok) {
        markRecorded();
        const card = cards.find(item => item.dataset.language === submitted.language);
        if (card) {
          const count = Number(card.dataset.count) + 1;
          const target = Number(card.dataset.target);
          card.dataset.count = String(count);
          card.querySelector('.vote-count').textContent = `${count.toLocaleString()} of ${target.toLocaleString()} votes`;
          card.querySelector('progress').value = Math.min(target, count);
          card.querySelector('.vote-percent').textContent = `${Math.round(Math.min(100, count / Math.max(1, target) * 100) * 10) / 10}% of goal`;
          card.querySelector('[data-vote]').textContent = 'Your vote is counted ✓';
        }
        const total = document.getElementById('vote-total-count');
        total.textContent = (Number(total.textContent.replace(/[^0-9]/g, '')) + 1).toLocaleString();
        closeDialog();
        announce(submitted.language === 'Other' ? `Thank you! Your suggestion for ${submitted.otherLanguage} has been recorded for the team to review.` : `Thank you! Your vote for ${submitted.language} has been counted.`, true);
        filterAndSort();
        return;
      }
      const message = response.status === 403 ? 'Verify your email before voting. Sign in to your account to complete verification, then return here.'
        : response.status === 401 ? 'Your session has expired. Sign in, then return here to vote.'
        : response.status === 409 ? 'Your language vote has already been recorded. Each account gets one vote.'
        : response.status === 429 ? 'Too many attempts. Please wait a moment before trying again.'
        : data.message || data.error?.message || 'We could not record your vote. Please try again.';
      if ([401, 403, 409].includes(response.status)) {
        if (response.status === 409) markRecorded();
        closeDialog();
        announce(message, response.status === 409, [401, 403].includes(response.status));
      } else {
        dialogMessage.textContent = message;
        if (!dialog.open) announce(message);
      }
    } catch {
      dialogMessage.textContent = 'Unable to connect. Check your connection, then try again.';
      if (!dialog.open) announce(dialogMessage.textContent);
    } finally {
      busy = false;
      confirm.disabled = recorded;
      confirm.textContent = 'Confirm my vote →';
    }
  });
  filterAndSort();
})();
