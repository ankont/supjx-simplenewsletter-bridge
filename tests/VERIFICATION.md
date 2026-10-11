# Verification — 2026-10-08

Component: com_newsletterbridge 1.2.0. Actual target: Joomla 6.1.3, local Docker catichisi-local-web-1, http://localhost:8089/. Simple Newsletter: supplied 1.3.0; compatibility hashes matched the installed extension.

Passed:

- Standalone unit/security regression suite: 3,677 assertions.
- Public archive security suite: 46 assertions for HTML and URL allowlists, anonymisation, malformed markup, idempotence and separate archive ACL.
- Package structure, XML, language and portable ZIP paths: 579 assertions. PHP syntax checks: all 34 component PHP files. JavaScript syntax check passed.
- Real site-application MVC/models/MySQL tests: 56 assertions, empty isolated tables, six messages captured in memory through a mandatory mock mailer. Includes checkout, job batching, sender ACL, opt-in archive defaults, review requirement, foreign publication/withdrawal rejection, stale revision protection, public response allowlist, anonymous reading, withdrawal and upstream invalidation.
- Article presets: 42 real Joomla assertions in temporary categories, mandatory mock mailer, zero mail calls. Includes normalised and translated built-in overrides, native article asset/workflow, external article edits retaining subject, View Levels/ownership, category create and article edit-own checks, checkout protection, duplicate names, concurrent category lock, failed-update compensation and failed-first-save cleanup.
- Actual preset controller dispatch: 11 cases for CSRF, GET, task allowlist, field validation, disabled category, anonymous and foreign same-name overwrite denial.
- Browser presets: native TinyMCE; save override before saving the newsletter; one unchanged built-in option; reload/apply uses the saved subject/body; same-name update and independent new name in the same flat selector.
- Actual sender controller dispatch: 19 cases with a mock mailer installed before dispatch. No case reached the mailer.
- Actual archive controller dispatch: 12 cases for POST/CSRF, task allowlist, explicit review and foreign resource denial. No successful mutation or mail calls.
- Browser: native TinyMCE public-copy editing; private save and refreshed sandboxed preview; unchecked approval by default; private direct URL returns 404; explicit reviewed publication; anonymous read; no literal test email in public HTML; withdrawal immediately returns 404; archive pagination 20+2; native archive template override. Subscription form action, optional name, email, honeypot, CSRF and canonical local return verified without submitting.
- Previous browser management checks: author/sender ACL, native editor and presets, checkout/edit/save, sandboxed preview, pagination and ownership denials.
- Joomla installer: original 1.0.0 installation and upgrades to 1.1.0 and 1.2.0, including creation of the bridge-owned archive and preset metadata tables. Final same-version reinstallation also checked. No upstream source changes.

Cleanup: temporary users, groups, newsletters, synthetic jobs, public archive copies and template override removed; original component ACL restored. Random-prefix integration tables dropped in finally. Installed bridge, Registered management menu item (219) and Public subscription/archive menu item (224) remain. No real newsletter was published by these tests. Temporary article preset category/articles/history were removed and the original preset-category setting restored.

No real email was sent. SMTP delivery, confirmation-email delivery, inbox rendering and Joomla 5.4 remain unverified. In the earlier sending tests, automatic approval review rejected web POSTs because that session lacked a mock transport; those endpoints were tested through the mock-protected Joomla harness.

## 1.3.0 — 2026-10-10

- 15 standalone compatibility assertions: differing/unknown version and changed hashes are advisory; actual missing schema, API, InnoDB, enabled component and Joomla requirements still block.
- 6 real Joomla compatibility/admin assertions: different and empty cached version still boot the MVC factory and render an administrator warning. Metadata changes were held in a transaction and rolled back in finally.
- Existing unit tests: 3,677 assertions; archive tests: 46; actual Joomla integration: 56, with six messages captured in memory and zero real mail.
- Browser: Greek terminology, native accessible selector, theme font Play, styled field height and focus. No compatibility warning rendered in the frontend editor.
- 603 package assertions; installer succeeded as www-data. Temporary author/sender accounts and grants cleaned up.
