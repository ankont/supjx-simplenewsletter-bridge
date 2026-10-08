(() => {
  'use strict';
  const opts = window.Joomla?.getOptions('com_newsletterbridge');
  if (!opts) return;
  const labels = window.Joomla.getOptions('com_newsletterbridge.labels', {});
  async function request(task, fields) {
    const data = new URLSearchParams({ task: 'send.' + task, ...fields });
    data.set(opts.token, '1');
    const response = await fetch(opts.endpoint, { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: data });
    let payload;
    try { payload = await response.json(); }
    catch { throw new Error(labels.network_error || 'Request failed'); }
    if (!response.ok || payload.success !== true) throw new Error(payload.message || labels.network_error);
    return payload;
  }
  const editorRoot = document.querySelector('[data-nb-editor]');
  if (editorRoot) {
    const form = editorRoot.querySelector('form');
    const textarea = form.querySelector('[name="jform[body]"]');
    const editor = () => window.JoomlaEditor?.get(textarea?.id) || window.Joomla.editors?.instances[textarea?.id];
    form.addEventListener('submit', (event) => {
      if (event.submitter?.value === 'newsletter.cancel') return;
      if (editor()) textarea.value = editor().getValue();
      if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); }
    });
    editorRoot.querySelector('[data-nb-preset]').addEventListener('click', async (event) => {
      const preset = editorRoot.querySelector('#nb-preset').value;
      if (!preset || !window.confirm(labels.preset_confirm)) return;
      event.target.disabled = true;
      try {
        const { data } = await request('preset', { id: editorRoot.dataset.id, preset });
        if (editor()) editor().setValue(data.body);
        else if (textarea) textarea.value = data.body;
        form.querySelector('[name="jform[subject]"]').value = data.subject;
        editorRoot.querySelector('[data-nb-message]').textContent = '';
      } catch (error) { editorRoot.querySelector('[data-nb-message]').textContent = error.message; }
      finally { event.target.disabled = false; }
    });
  }
  const root = document.querySelector('[data-nb-send]');
  if (!root) return;
  let busy = false, stop = false;
  const pause = root.querySelector('[data-nb-pause]');
  const message = (text) => { root.querySelector('[data-nb-message]').textContent = text || ''; };
  function controls(value) {
    busy = value;
    root.querySelectorAll('button:not([data-nb-pause])').forEach(button => { button.disabled = value; });
    pause.hidden = !value;
  }
  function render(job) {
    const progress = root.querySelector('[data-nb-progress]');
    progress.max = Math.max(1, job.total); progress.value = job.sent + job.failed;
    root.querySelector('[data-nb-counts]').textContent =
      labels.total + ': ' + job.total + ' · ' + labels.sent + ': ' + job.sent + ' · ' + labels.failed + ': ' + job.failed;
    let row = root.querySelector('[data-job="' + job.jobId + '"]');
    if (!row) {
      row = document.createElement('tr'); row.dataset.job = job.jobId;
      for (let i = 0; i < 7; i++) row.append(document.createElement('td'));
      row.cells[0].textContent = job.jobId;
      row.cells[1].textContent = '—';
      ['state', 'total', 'sent', 'failed'].forEach((key, i) => { row.cells[i + 2].dataset.col = key; });
      const fragment = root.querySelector('[data-nb-active-actions]').content.cloneNode(true);
      fragment.querySelector('[data-nb-resume]').dataset.nbResume = job.jobId;
      fragment.querySelector('[data-nb-cancel]').dataset.nbCancel = job.jobId;
      row.cells[6].append(fragment);
      root.querySelector('[data-nb-jobs]').prepend(row);
    }
    for (const key of ['state', 'total', 'sent', 'failed']) {
      row.querySelector('[data-col="' + key + '"]').textContent = key === 'state' ? labels['state' + job.state] : job[key];
    }
    if (job.done) row.cells[6].replaceChildren();
    else row.querySelectorAll('button').forEach(button => { button.disabled = busy; });
  }
  async function drain(job) {
    render(job);
    while (!stop && !job.done) {
      const result = await request('process', { job_id: job.jobId });
      job = result.data; render(job);
      // Each request finishes before the next; errors stop without automatic retries.
      await new Promise(resolve => window.setTimeout(resolve, 300));
    }
    message(job.done ? labels.done : labels.paused);
  }
  async function perform(work) {
    if (busy) return;
    stop = false; controls(true); message(labels.sending);
    try { await work(); }
    catch (error) { message(error.message); }
    finally { controls(false); }
  }
  pause.addEventListener('click', () => { stop = true; message(labels.paused); });
  root.querySelector('[data-nb-test]').addEventListener('submit', event => {
    event.preventDefault();
    const email = root.querySelector('#nb-email').value;
    perform(async () => {
      const result = await request('test', { id: root.dataset.id, email }); message(result.message || labels.test_sent);
    });
  });
  root.querySelector('[data-nb-start]')?.addEventListener('submit', event => {
    event.preventDefault();
    if (!event.target.checkValidity()) return;
    perform(async () => {
      const { data } = await request('start', { id: root.dataset.id, confirmed: '1' });
      event.target.reset(); await drain(data);
    });
  });
  root.addEventListener('click', event => {
    const resume = event.target.closest('[data-nb-resume]');
    const cancel = event.target.closest('[data-nb-cancel]');
    if (resume) perform(async () => {
      const { data } = await request('resume', { job_id: resume.dataset.nbResume }); await drain(data);
    });
    if (cancel && window.confirm(labels.cancel_confirm)) perform(async () => {
      const { data } = await request('cancel', { job_id: cancel.dataset.nbCancel }); render(data); message(labels.done);
    });
  });
})();
