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

## 1.3.1 — 2026-10-22

- 18 standalone URL regression assertions (subdirectory, root-relative paths, dot segments, query/fragment, external URLs, CID/data/mailto, srcset, backgrounds, idempotence).
- 60 Joomla 6.2.0 integration assertions passed; six messages captured only in memory. Test, preview, upstream batch and resumed legacy snapshot include absolute URLs. Isolated synthetic tables dropped in finally.
- 3,677 unit, 46 archive, 15 dependency and 611 package assertions passed.
- No real mail sent. Inbox rendering, public image availability on the live domain and delivery authentication remain unverified.

## 1.3.2 — 2026-10-23

- Optional Sources integration tested with the installed Sources 4.0.3 on Joomla 6.2.0. No Sources/SmartBrowser/Simple Newsletter/template code changed.
- 102 actual Joomla integration assertions passed; nine messages captured only in memory. Synthetic article rows and isolated newsletter tables removed in finally; temporary native email override removed.
- Checks cover public-only article selection, draft/private/future/expired exclusions, identity restoration after success/errors, all five email layouts, native template override, operation with SmartBrowser disabled in the request-local component registry, merged ordering/limit/deduplication, preview/test, stable send snapshots after article changes, resume, rejection of legacy raw-token jobs, invalid tokens, rendering/token depth limits, active HTML removal and no partial queue on rendering failure.
- Browser inspected at desktop and 390px viewport. Public local sample images loaded; responsive columns stack without horizontal overflow. This is browser verification of generated HTML, not Outlook/Gmail inbox rendering.
- 8 Sources optional-dependency assertions, 18 URL assertions, 3,677 security/unit assertions, 46 archive assertions, 15 dependency assertions and 636 package assertions passed.
- No real email sent. Live deliverability, inbox rendering and Joomla 5.4 remain unverified. Layouts intentionally omit decorative font icons and site-specific SmartBrowser resource fields.

## 1.4.0 — 2026-10-23

- Removed bridge-owned Sources parsing/resolution/email layouts. Added version 1 `onNewsletterPrepareEmail` protocol, explicitly importing only the optional `newsletter` plugin group.
- 49 standalone contract assertions passed: no-provider fast path, reserved marker guard, standard placeholders/CSS, all purposes, public identity, immutable metadata, output/counter bounds, malformed results, provider errors and identity restoration.
- 84 actual Joomla 6.2.0 integration assertions passed, with a real temporary plugin loaded through Joomla's plugin loader. Nine messages captured in memory; zero real mail. Preview/test/start invoke providers, process/resume do not. Snapshot stays unchanged after provider data changes. Failures create no mail/new job. Synthetic provider files/registration and isolated tables removed in finally.
- 5 installer cleanup assertions passed. Actual 1.3.2 -> 1.4.0 upgrade removed only the obsolete component SourcesEmail.php and email/sources.php; repeated update succeeded. No template overrides or upstream extension files modified.
- 3,677 security/unit, 18 URL, 46 archive, 15 dependency and 644 package assertions passed. PHP syntax and git diff whitespace checks passed.
- Full API contract is packaged at docs/EMAIL_OUTPUT_PROTOCOL.md; Sources handoff prompt is docs/SOURCES_EMAIL_PROMPT.md.
- Sources 4.0.3 does not yet provide the new newsletter plugin. Existing Sources source tokens intentionally fail closed until its provider is implemented. No claim of Sources integration or inbox-rendering validation for 1.4.0. Already rendered snapshots remain usable; ordinary newsletters are unchanged.

## 1.5.0 — 2026-10-26

- Added per-editor Sources presentation via the documented Sources 4.2.0 initial script-options contract, using the actual Joomla Form field ID before input rendering. No Sources renderer/parser/layout/CSS transformations were added to the host.
- Host lifecycle observation removes/restores only its own field declaration on detach/reattach and pagehide/pageshow. Sources owns raw-editor reinit/destroy subscriptions and cancellation; the host creates no runtime presentation overrides. Missing API leaves editing available with an explicit unconfirmed-email notice.
- Added a native administrator workspace through shared site models/controllers/templates and thin administrator MVC adapters. Every backend management entry additionally requires core.manage, while resource/job scope, CSRF, checkout and dual-send grants remain required. Public subscription views are not exposed as backend management views.
- 9 standalone editor-declaration assertions passed, including multiple IDs, preservation of other options and CLI/non-HTML handling.
- 10 real TinyMCE/JCE browser-engine fixtures passed with read-only Sources 4.2 modules, frontend/backend styles and desktop/mobile viewports. All five email layouts, token-only GetContent, simultaneous site/email editors, raw-editor destruction/reinit, host removal and missing Sources were checked. Preview AJAX was mocked in these isolated UI fixtures.
- After Sources 4.2.0 was installed, actual browser checks passed on both the local frontend and backend newsletter editor: five layout requests each confirmed presentation=email, purpose=preview, renderer=EmailRenderer, styles=[] and token-only saved content. No newsletter save, job, snapshot or subscriber write occurred in these browser checks. Temporary users/groups mapping/sessions cleaned up. Guided-tour auto-start was disabled only for the temporary test account.
- 112 real Joomla 6.2.0 host integration assertions passed, with twelve messages captured in memory and zero real mail: existing frontend pipeline plus native backend dispatch/render, real field metadata, validation, access denials and unchanged mock transport.
- Sources' own isolated real Joomla integration runner was executed read-only against installed 4.2.0 through a temporary candidate symlink. 49 endpoint/provider/public-policy checks passed, including five AJAX renders matching final email output, identity restoration, snapshot stability and no redispatch in batches. Three messages captured in memory; existing provider preserved and synthetic tables removed.
- Existing suites passed: 3,677 security/unit, 49 protocol, 18 URL, 46 archive, 15 dependency and 5 installer-cleanup assertions. All 58 component PHP files passed syntax checks. Package checks verify portable paths and both protocol documents.
- No Sources or Catichisi template files were changed. No commit/push/release performed. These checks do not certify Outlook/Gmail inbox rendering. Sources live AJAX still requires its own content-authoring permissions; declaring email context does not grant them.

## 2026-10-28 ? Bridge 1.5.1 provider counter alignment

- Removed host cumulative item/token rejection. Accurate counters of 120 items / 16 tokens pass preview, test and new-send preparation; legacy limit metadata remains immutable and documented as hints only. Sources owns its 4.3.0 Contents depth/header contract.
- 65 standalone protocol assertions passed, including malformed/negative counters, public-policy mutation, oversized output, unresolved syntax and identity restoration. 3677 security/unit assertions passed.
- Installed 1.5.1 on local Joomla 6.2.0. 112 integration assertions passed with the real Joomla provider loader, frontend/backend boundaries, fixed snapshots and batch processing. The fixture reports 120 items / 16 containers; 12 emails were captured in memory, zero real sends. Temporary fixture data/plugin cleaned up by the runner.
- This verifies the host contract, not actual Sources 4.3.0 nested rendering or mail-client appearance. Those remain provider/inbox verification work. Joomla 5.4 was not tested.
- Package validation: 690 assertions passed. Deferred legacy dismantling is recorded in README; no current functionality removed, no Sources/template edits, no commit/push/release.
