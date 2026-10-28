(() => {
  'use strict';
  const configured = window.Joomla?.getOptions('com_newsletterbridge.editorPresentation', {})?.editors;
  const contexts = window.Joomla?.getOptions('plg_editorsxtd_sourcesbutton.preview', {})?.editors;
  if (!configured || !contexts) return;
  // Initial options are the contract. No Sources imports, runtime overrides,
  // raw-editor hooks, content manipulation or private integration APIs are needed.
  const owned = new Map();
  const declarations = new Map(Object.keys(configured).map(id => [id, contexts[id]]));
  let observer;
  function refresh() {
    for (const [id, destination] of Object.entries(configured)) {
      if (destination !== 'email') continue;
      const field = document.getElementById(id);
      let entry = owned.get(id);
      if (!entry && field?.closest('[data-nb-editor]')) {
        entry = { root: field.closest('[data-nb-editor]'), value: declarations.get(id) };
        owned.set(id, entry);
      }
      if (!entry) continue;
      const present = entry.root.isConnected && field && entry.root.contains(field);
      if (!present) {
        if (contexts[id] === entry.value) delete contexts[id];
        owned.delete(id);
        continue;
      }
      if (!contexts[id]) contexts[id] = entry.value;
      let confirmed = false;
      try { confirmed = window.SuperSoftSourcesPreview?.getPresentation?.(id) === 'email'; }
      catch { /* Old/missing/incompatible Sources must not prevent editing. */ }
      const notice = entry.root.querySelector('[data-nb-email-preview-status]');
      if (notice) notice.hidden = confirmed;
    }
  }
  function mount() {
    refresh();
    observer?.disconnect();
    observer = new MutationObserver(refresh);
    observer.observe(document.body, { childList: true, subtree: true });
  }
  function suspend() {
    observer?.disconnect();
    for (const [id, entry] of owned) if (contexts[id] === entry.value) delete contexts[id];
    owned.clear();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
  else mount();
  window.addEventListener('load', refresh, { once: true });
  window.addEventListener('pagehide', suspend);
  window.addEventListener('pageshow', mount);
})();
