# Verification — 2026-10-08

Component: com_newsletterbridge 1.0.0. Actual Joomla target: 6.1.3, local Docker catichisi-local-web-1, http://localhost:8089/. Simple Newsletter: supplied 1.3.0; compatibility hashes matched the installed extension.

Passed:

- Standalone unit/security regression suite: 3,671 assertions.
- Package structure, XML, language and portable ZIP paths: 368 assertions.
- Real site-application MVC/models/MySQL tests: 34 assertions, empty isolated tables, six messages captured in memory through a mandatory mock mailer. Includes actual GET_LOCK contention, foreign checkout/checkin/save rejection, sample-only test message, job snapshots, batching/resume/cancel, terminal-state protection, summary counter recovery and restoration of retention settings.
- Actual Joomla controller dispatch: 19 cases, each in a separate process with a mock mailer installed before dispatch. Includes missing CSRF, disallowed tasks, forbidden author send, and GET rejection. No case reached the mailer.
- Browser: actual author and sender accounts with narrowly scoped Joomla ACL. Native TinyMCE + presets; draft creation; checkout/edit/save; sandboxed CSP preview; published sender form; pagination 20+4; native template override marker; HTTP 403 for anonymous users and for foreign resource/send-screen access.
- Joomla installer: initial installation and subsequent upgrades of the final code.

Cleanup: temporary users, groups, ACL grants, newsletters and template override were removed and original component ACL restored. Random-prefix integration tables were dropped in finally. The installed bridge and Registered menu item (219) remain for review.

No real email was sent. Automatic approval review rejected browser POST tests for sending because the HTTP session did not use a mock transport. Those endpoint checks were performed in the mock-protected Joomla harness. SMTP delivery, inbox rendering and Joomla 5.4 remain unverified.
