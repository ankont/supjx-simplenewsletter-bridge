# Simple Newsletter Frontend Bridge

**Manage Joomla newsletters from the frontend, without giving authors access to the administrator.**

Simple Newsletter Frontend Bridge (`com_newsletterbridge`) is an independent Joomla component that adds a permission-controlled authoring and sending workspace to **Simple Newsletter**.

Current version: **1.5.0**. English and Greek interface translations are included.

## What is Simple Newsletter?

**Simple Newsletter** is a separate Joomla extension by **SaintArt Designs**. Its package contains the `com_simplenewsletter` component and a subscription module. It provides subscriber registration and confirmation, newsletter templates, test messages, batch sending and delivery history. It also has a frontend subscription view; subscription is not limited to the module.

The bridge uses Simple Newsletter's newsletter records, subscribers and send jobs, reusing its Joomla models and helpers where appropriate. It adds frontend management, resource-level permissions, article-based presets and an optional public archive.

**The bridge does not replace Simple Newsletter and cannot run without it.** It does not modify Simple Newsletter's source files. Simple Newsletter is not bundled in the bridge installation ZIP. The separate [reference package](reference/pkg_simplenewsletter-1.3.0.zip) is the compatibility baseline.

## Features

- Paginated newsletter list with search and ownership-based access.
- Create and edit with a Joomla editor and available editor buttons.
- One preset selector combining built-in designs with reusable Joomla articles.
- Preview saved content and send sample-only test messages to a specified address.
- Start, pause, resume and cancel batch sends, with progress and delivery totals.
- Public subscription page and an author-approved archive of previous issues.
- Optional email-output providers for extensions that insert special content tokens.
- Native Joomla template overrides, without requiring a particular site template or frontend framework.

Subscriber administration remains in Simple Newsletter. Frontend authors are not given subscriber lists, recipient queues, confirmation/unsubscribe tokens or SMTP credentials.

## Requirements and compatibility

| Dependency | Requirement |
| --- | --- |
| Joomla | 5.4.x or 6.x. The current integration suite has run on 6.2.0; Joomla 5.4 has not been functionally verified. |
| PHP | A PHP version and extensions supported by the installed Joomla release. |
| Database | MySQL/MariaDB, InnoDB for the required tables, and `GET_LOCK` / `RELEASE_LOCK` support. |
| Simple Newsletter | Installed and enabled. Version 1.3.0 is the reference implementation. |
| Email transport | Joomla's configured mail transport and a valid sender configuration. |

Different or unknown Simple Newsletter versions, and reference-file hash differences, produce an administrator warning rather than automatic rejection. Missing required APIs, schema, storage support or an enabled dependency still prevent operation. A warning does not certify an untested upstream release or its administrator interface.

## Installation and setup

1. Install and enable Simple Newsletter. Configure Joomla mail settings and the sender settings in Simple Newsletter.
2. Install [com_newsletterbridge-1.5.1.zip](dist/com_newsletterbridge-1.5.1.zip) through **System β†’ Install β†’ Extensions**.
3. Open **System β†’ Global Configuration** and select **Newsletter Bridge**. Configure the editor, optional preset category and permissions. The Components menu also provides a backend workspace for users with component management permission.
4. Create a menu item of type **Simple Newsletter Frontend Bridge β†’ Newsletter management**. Choose an appropriate access level, such as Registered or a dedicated author view level.
5. Grant the component permissions below. Menu access alone does not grant permission to manage newsletters.
6. Sign in through the frontend and open the new menu item. Save changes before previewing or sending.

New newsletters start unpublished. Normal delivery requires publication and explicit confirmation of sending to all confirmed subscribers. Preview and test messages are available for unpublished newsletters within the user's permissions.

## Permissions

Management requires a signed-in user and `newsletterbridge.access` on `com_newsletterbridge`. Checks run on the server for each action and resource.

| Permission | Allows |
| --- | --- |
| `core.manage` | Enter the bridge backend, in addition to the normal newsletter permissions. |
| `newsletterbridge.access` | Enter frontend management; does not grant access to other authors' content. |
| `core.create` | Create newsletters; the server assigns ownership. |
| `core.edit` | Read and edit all newsletters. |
| `core.edit.own` | Read and edit newsletters owned by the current user. |
| `core.edit.state` | Change publication state, together with permission to edit that newsletter. |
| `newsletterbridge.send` | Test messages and send-job operations within the user's resource scope. |
| `simplenewsletter.send` on **`com_simplenewsletter`** | Additional mandatory permission for sending and access to jobs. |
| `newsletterbridge.archive` | Review, publish and withdraw archive editions, together with newsletter edit permission. |

A typical author needs access, create and edit-own. Publishers also need edit-state. Senders need access to the relevant newsletters **and both send permissions**. Administrator configuration permissions are not required for frontend authors.

Reading follows edit scope; there is no separate read-all permission. A creator without edit/edit-own cannot reopen saved newsletters. Upstream records with no owner require edit-all access.

Status, process, resume and cancel check both newsletter access and job ownership. An edit-own sender can manage only their own jobs for their own newsletters. An edit-all sender can manage other creators' jobs.

## Editor and reusable presets

The configured editor defaults to TinyMCE. Other editors must support frontend use and Joomla's editor API. Joomla text filters and editor HTML rules still apply. Editor buttons are enabled, excluding pagebreak and readmore; available buttons depend on installed plugins and permissions.

Choose a dedicated Joomla article category in the bridge settings to enable custom presets. The selector combines accessible articles with the six built-in Simple Newsletter presets:

- A new name creates a reusable preset article.
- The same name as a built-in preset replaces it in the selector without changing upstream files.
- The same name as an existing article preset updates that article, if permitted.

Matching ignores case and redundant whitespace and recognises English/Greek built-in names. Preset articles retain Joomla access, ownership and checkout controls. Creating or replacing one also requires the corresponding `com_content` category/article permissions. Preset subjects are stored separately from article text.

Sources 4.2.0 or later supports explicit email presentation in the newsletter editor, including TinyMCE/JCE where Sources supports them. The bridge declares the actual field ID before editor initialisation; article editors on the same page keep their own context. Missing or older APIs leave editing available and show an unconfirmed-preview notice. Sources preview still requires its own content-authoring permissions; the bridge does not grant or bypass them. See [editor presentation](docs/EDITOR_PRESENTATION.md).

An editor button that inserts extension tokens does not automatically make them render in email. See [Email-output extensions](#email-output-extensions).

## Preview and delivery

Preview uses saved content, generic sample values and a sandboxed frontend document. A test message goes only to the entered address, with sample name/email values and no subscriber token. It does not look up the recipient's subscriber record.

Relative image/link URLs are converted to absolute site URLs. Images must remain publicly reachable. Email clients can block remote images or render HTML differently from browsers.

Normal sending creates a content snapshot and recipient queue through Simple Newsletter. The browser processes batches sequentially while the send page is active. Pause or leaving the page stops further batches after the current request. Resume continues pending recipients. Cancel does not recall delivered messages, and failed recipients are not automatically retried.

Delivery limitations:

- There is no background scheduler or exactly-once guarantee. A crash after submission but before recording queue state can cause duplicates on resume.
- Do not process the same job simultaneously through the bridge and Simple Newsletter's administrator; the upstream interface does not share the bridge's concurrency lock.
- The reference sender snapshots confirmed recipients at job creation and does not recheck subscription changes between batches.
- A recorded send means submission to the mail transport, not guaranteed inbox delivery. SMTP, SPF, DKIM and DMARC configuration belong to the site's mail infrastructure.

## Public subscription and archive

Create a second menu item of type **Newsletter subscription and archive**, with Public access, to combine subscription and previous issues on one page. Subscription uses Simple Newsletter's confirmation workflow and may send a confirmation email.

Sent newsletters are **not published automatically**. An authorised author can create a public edition from a completed send job, edit it, review it and explicitly approve publication. Editions start private and use the job snapshot, not later newsletter edits. Private saving withdraws an already published edition.

The archive does not read subscriber records or recipient queues. Its sanitiser replaces known personal placeholders/email addresses and removes unsafe HTML, personalised links, tracking constructs and remote images. This can change the original appearance.

**Authors must still review free text and images for personal information.** Sanitisation is not complete anonymisation. Withdrawal cannot recall copies already obtained by visitors.

## Email-output extensions

From 1.4.0, optional Joomla plugins in the **`newsletter`** group handle extension-specific content through **`onNewsletterPrepareEmail`**. Each extension owns its parser, public-content resolution and email layouts. The bridge validates output, normalises URLs and stores the send snapshot.

Providers run only for preview, test and new-send preparation, never per recipient or resumed batch. Ordinary site pages do not trigger this mechanism. Without providers, there is no provider event dispatch or identity switch.

See the [email-output protocol](docs/EMAIL_OUTPUT_PROTOCOL.md) for implementation, access policy, budgets and error handling.

**Upgrading from 1.3.2:** the bridge's direct Sources renderer was removed in 1.4.0. Sources now requires a compatible email-output provider from that extension; the reference Sources 4.0.3 content plugin alone does not implement the protocol. Unresolved tokens stop preparation instead of being emailed verbatim. Rendered job snapshots remain usable. Older unresolved jobs require cancellation and a review of delivered recipients before a new send.

## Template overrides

Frontend views use standard Joomla override locations. For example:

```text
components/com_newsletterbridge/tmpl/newsletters/default.php
    β†’ templates/YOUR_TEMPLATE/html/com_newsletterbridge/newsletters/default.php
```

Available views: `newsletters`, `newsletter`, `preview`, `send`, `archive`, `issue`, `publications`, `publication`. Preserve JavaScript data attributes and form controls when overriding interactive views. ACL checks are independent of the markup. Email-output providers own and document their own layout override paths.

## Data and uninstall behaviour

The bridge uses Simple Newsletter records and maintains its own tables: `#__newsletterbridge_archive` for public editions and `#__newsletterbridge_presets` for preset metadata.

Uninstalling removes those two tables. It does not remove Simple Newsletter newsletters, subscribers or jobs, or the Joomla articles/categories used as presets. Back up bridge-owned data before uninstalling if it must be retained.

## Development and testing

| Directory | Contents |
| --- | --- |
| `source/` | Installable component source and manifest. |
| `scripts/` | ZIP build script. |
| `tests/` | Standalone regressions and opt-in Joomla integration runners. |
| `docs/` | Email-output protocol and integration guidance. |
| `reference/` | Separate upstream compatibility reference package. |
| `dist/` | Latest bridge installation ZIP. |

With PHP CLI and the required XML/Phar support:

```sh
php tests/unit.php
php tests/archive-unit.php
php tests/dependency-unit.php
php tests/email-urls.php
php tests/email-preparation.php
php tests/install-cleanup.php
php scripts/build.php
php tests/package.php
```

The build creates a ZIP with a root manifest and portable `/` paths. It keeps only the latest bridge ZIP in `dist/`; earlier releases are documented below.

Integration runners require a disposable Joomla installation with the bridge, Simple Newsletter and MySQL/MariaDB, plus permission to create/remove fixtures. They use mock mail transport and cleanup blocks. Setup for the temporary email-provider fixture is documented in the [protocol verification instructions](docs/EMAIL_OUTPUT_PROTOCOL.md#verification).

Standalone tests and browser previews do not certify every Joomla release or mail client. Validate installation, ACL, editors, checkout, provider output and batch recovery in a test installation before production use. Use a mock transport or isolated mail catcher for development.

## Deferred legacy cleanup

After the successor component and data migration have been completed and verified, narrow this bridge to frontend access to Simple Newsletter's supported functionality. Remove the additional backend workspace and capabilities that cannot behave consistently through upstream administration. This is deferred work, not a removal in this release. Preserve the complete implementation in version control and transfer useful integrations to the successor first. Review stored extension tokens and pending jobs before disabling providers; do not leave content that can be mailed unresolved.

## Version history

### 1.5.1

- Accept accurate provider counters above 60 articles / 8 containers, aligning with Sources 4.3.0 Contents header fallback. Provider recursion policy remains provider-owned.
- Retain public-only preparation, immutable policy, nonnegative integer counters, the 2 MB bound and render-once send snapshots.


### 1.5.0

- Added a backend workspace using the shared newsletter models, forms and actions, with an additional `core.manage` check.
- Declare Sources 4.2 email presentation per newsletter editor before initialisation; preserve tokens, isolate instances and clean up host-owned declarations on removal.
- Report unavailable/unconfirmed live email preview without blocking editing. Final preparation remains protocol v1 with anonymous/public-only provider dispatch in both clients.

### 1.4.0

- Introduced the common versioned email-output protocol and optional `newsletter` provider plugins.
- Removed the bridge-specific Sources renderer; each supplying extension now owns its email output.
- Added provider result validation and unresolved-token protection while retaining fixed send snapshots.
- Upgrade cleanup preserves template overrides and newsletter content.

### 1.3.2

- Added the first direct Sources integration for version 2 selections, with five email layouts, public-only article resolution and fixed job snapshots.
- Superseded by the provider mechanism in 1.4.0.

### 1.3.1

- Enabled Joomla editor buttons, excluding pagebreak/readmore.
- Converted relative image, link and supported background/srcset URLs to absolute URLs in previews, test messages and send snapshots.

### 1.3.0

- Made upstream version/hash differences advisory while retaining required API, schema and storage checks.
- Improved preset selector styling, compact management controls and placeholder guidance.
- Hid the Components menu entry and translated configuration titles.

### 1.2.1

- Standardised Greek terminology for newsletters, layouts and presets.

### 1.2.0

- Added reusable article-based presets in a configurable category.
- Added same-name overrides and separate subject metadata, with article ACL and checkout protection.

### 1.1.0

- Added the combined subscription/archive menu item.
- Added private-by-default public editions, explicit review/publication and a separate archive permission.

### 1.0.0

- Initial frontend management, editor, built-in presets, preview/test and batch sending.
- Added resource ACL, dual send permissions, CSRF protection and checkout/checkin integration.

## License

GNU General Public License version 2 or later, as declared in the component manifest. Simple Newsletter is a separate project with its own copyright and licensing notices.
