# Newsletter editor presentation — Bridge 1.5.0

## Contract and dependencies

Sources **4.2.0 or later**, including its editor button and enabled newsletter
email provider, is required for live Sources **email** presentation. This uses
Sources' documented **Per-Editor Presentation Context** API, not the final
email-output event as a preview transport. The final email provider protocol
remains version 1.

The bridge reads the actual `body` field ID from the Joomla Form. Before the
editor's input is rendered, it declares:

```php
$document->addScriptOptions('plg_editorsxtd_sourcesbutton.preview', [
    'editors' => [$actualFieldId => ['presentation' => 'email']],
]);
```

Existing options and other editor IDs are preserved. No component name, route,
content or selector is used by Sources to infer the destination. The shared
newsletter form configures this identically in site and administrator clients.
The bridge does not set a context for unrelated article editors or public
archive-edition editors.

Sources consumes this initial configuration for TinyMCE/JCE instances, their
builders and nested previews. The bridge only reads the documented
`window.SuperSoftSourcesPreview.getPresentation(id)` to confirm the declaration;
it does not create runtime presentation overrides or import Sources modules.

## Lifecycle and graceful degradation

Destroying/recreating the raw editor over the same textarea retains its initial
email destination. Sources owns its raw-editor subscriptions, request cancellation
and preview cleanup. The host does not add editor-specific event handlers.

The host's small observer removes only its own initial declaration when its
newsletter field/root is detached, restores it on reattachment, and disconnects
on pagehide. Pageshow restores observation, including back/forward-cache cases.
Other editor IDs and subsequently replaced declarations are not removed.

When Sources or its new getter API is missing, incompatible or reports another
destination, editing continues. An explicit notice remains visible stating that
live email presentation is unconfirmed and directing users to final newsletter
Preview. The host does not silently label a site preview as accurate email output.
There are no global editor filters, HTML transformations, token parsers or layout
definitions in this integration.

The declaration is destination metadata, not permission. Sources' preview
endpoint still requires its own site/administrator content-authoring rights and
CSRF checks. Bridge-only author permissions do not automatically grant Sources
preview permission. No identities, persistent ACL or subscriber data are changed
by declaring editor context.

## Backend workspace

The bridge now supplies its own administrator list, editor, preview/send and
archive-management views. Access additionally requires `core.manage` on
`com_newsletterbridge`. The same resource/owner/job ACL, CSRF, validation,
checkout, safe returns and dual send permissions apply. Models, controllers and
templates are reused through administrator MVC adapters rather than copying the
Simple Newsletter administrator or its editor.

The normal Components entry now opens this workspace. Component options remain
available through Global Configuration. No Simple Newsletter or template source
is modified. Administrator presentation overrides follow the administrator
template's native Joomla view override paths.

Final preparation uses the same public-only/anonymous protocol-v1 provider
dispatch in either application, with exact identity restoration. Live preview
does not call send/start/process, create jobs or modify snapshots. GetContent
must still contain Sources tokens only, not its preview iframes or rendered HTML.

## Verification

- `php tests/editor-context.php`: actual-ID configuration, multiple declarations,
  preservation of existing options and non-HTML/CLI handling.
- `node tests/editor-browser.mjs <sources-repository> <joomla-asset-root>`:
  opt-in real TinyMCE/JCE engines with read-only Sources 4.2 modules and generated
  Sources email fixtures. Requires Playwright already available in the Sources
  development checkout and Chrome. Covers five layouts, site/admin styles,
  desktop/mobile, concurrent site/email instances, destroy/reinit, removal and
  missing Sources. AJAX is mocked; it does not test delivery.
- `tests/joomla-integration.php ... --email-providers --administrator`, with
  `admin-joomla-cases.php` alongside the normal provider fixtures: native backend
  MVC, extra manage checks, shared forms/validation, emitted field IDs and mock
  mail transport. The CLI runner uses the administrator template root for this
  mode; it is not a browser session.
- `node tests/editor-local.mjs`: opt-in **disposable local site only**. Uses
  `editor-web-fixture.php` to create a temporary Super User for actual site/admin
  editor requests and removes that user/session mapping in finally. Reads an
  existing public article for previews; never saves a newsletter or sends mail.
  Configure `JOOMLA_CONTAINER` and `JOOMLA_URL` for that environment. Sources 4.2
  and its provider must already be installed/enabled. Do not run in production.

Browser previews are Sources-block previews, not Outlook/Gmail emulation or a
complete rendering of the newsletter's outer template.
