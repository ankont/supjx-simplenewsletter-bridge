# Email output protocol v1 β€” Newsletter Bridge 1.4.0+

## Ownership and execution

Email providers are optional Joomla plugins in group **`newsletter`**. Each extension ships and maintains its own provider (for example `plg_newsletter_sourcesemail`). Providers own token parsing, public resource resolution, validation, recursion, HTML sanitisation and email layouts. The bridge knows no Sources or SmartBrowser classes, article schema, layouts or renderers.

The host imports only group `newsletter` and synchronously dispatches **`onNewsletterPrepareEmail`** once per preview, explicit test email or new send. This is not `onContentPrepare`: ordinary pages and status/process/resume/cancel requests do not dispatch it. No providers means no plugin boot, event or identity switch. Plugin metadata lookup still uses Joomla's normal plugin cache.

Providers run in normal Joomla ordering. There is **one pass**, with no automatic redispatch or retry. A provider must not recursively dispatch the host event. It handles its own nested syntax within its own bounded selection and recursion policy. If it produces another provider's syntax, that provider must run later; unresolved syntax fails closed at the end. Avoid cross-provider token generation and cycles.

Joomla remains the only shared dependency; providers must not reference bridge PHP classes or require its installation to boot. Use `SubscriberInterface` and `EventInterface`. If the bridge is absent, the provider is simply never called by it. Use Public access for the provider plugin: public content preparation must not depend on an author's plugin access level.

## Event and document

The event is a `Joomla\Event\Event`, with these named arguments:

- `context`: exact string **`com_newsletterbridge.newsletter`**.
- `document`: mutable **`Joomla\Registry\Registry`** shared by all providers.

Document data on entry:

```php
[
    'version' => 1,
    'purpose' => 'preview', // or 'test', 'send'
    'publicOnly' => true,
    'rootUrl' => 'https://example.test/subdirectory/', // Joomla Uri::root()
    'limits' => [
        'maxBytes' => 2000000,
        'maxItems' => 60, // legacy provider hint; not a host cap
        'maxTokens' => 8, // legacy provider hint; not a host cap
        'maxDepth' => 3, // legacy provider hint; not a host cap
    ],
    'html' => '<p>Author content, including extension tokens and {name}...</p>',
    'usage' => ['items' => 0, 'tokens' => 0],
    'errors' => [],
    'unresolved' => [],
]
```

Only **`html`, `usage.items`, `usage.tokens`, `errors`, `unresolved`** may change. Do not replace/reset another provider's counters or diagnostics. Use `$document->get('limits.maxBytes')` for nested reads and `$document->set('usage.items', $newTotal)` for writes. `Registry::toArray()` converts nested Registry objects into arrays. Integers must remain integers; HTML must be a UTF-8 string.

The version, purpose, public policy, root URL and limits are immutable. Mutating them fails preparation. `purpose` is informational: produce equivalent content for all three modes. Do not embed test-only content, random values, timestamps unrelated to selected resources, subscriber-specific values or send side effects.

## Minimal provider handler

In a standard namespaced Joomla plugin with its own manifest and DI service provider:

```php
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

final class SourcesEmail extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onNewsletterPrepareEmail' => 'prepareEmail'];
    }

    public function prepareEmail(EventInterface $event): void
    {
        if ($event->getArgument('context') !== 'com_newsletterbridge.newsletter') {
            return;
        }
        $document = $event->getArgument('document');
        if (!$document instanceof Registry || $document->get('version') !== 1) {
            return;
        }
        $html = $document->get('html');
        if (!is_string($html) || strpos($html, '{sources') === false) {
            return; // No resource lookup, layout work or assets for unrelated content.
        }
        // Sources-owned API must validate configuration and public access,
        // enforce byte bounds and provider-owned work limits, render safe email HTML,
        // update cumulative usage and preserve unrelated markup/placeholders.
        // $result = ...;
        // $document->set('html', $result);
    }
}
```

The handler above is a skeleton, not a complete or installed Sources provider.

## Access, safety and budgets

The bridge checks newsletter/job ACL first, then temporarily loads an anonymous `Joomla\CMS\User\User` into the application for provider import and dispatch. It restores the original identity in `finally`, even on errors. This is an access boundary, **not a PHP sandbox**. Installed PHP providers are trusted code and must enforce public-only access themselves; do not obtain an elevated user from the session, global caches or another API.

Resolve only publicly accessible and published resources, including category/ancestor access and publication windows and the site's language policy. Never expose private fields, subscriber data or tokens. Any provider resource cache must include the effective public access policy and relevant configuration.

From Bridge 1.5.1, the host validates `usage.items` and `usage.tokens` as nonnegative integers without cumulative caps. Keep accurate cumulative counters, including nested selections; never clamp or reset them. The immutable `maxItems`/`maxTokens`/`maxDepth` fields remain only as legacy hints for older providers, which may still apply their own limits. Sources 4.3.0 ignores these hints and owns its shared `contents_levels` setting and depth/cycle header fallback. The bridge adds no second recursion policy.

The host still enforces the 2 MB UTF-8 input/output bound. Providers must respect `limits.maxBytes` and bound their own queries and recursion, avoiding unbounded result sets and N+1 lookups. The host cannot preempt arbitrary provider PHP or expensive SQL.

Return HTML suitable for email, with escaped values, safe links/images and no scripts, iframes, forms, embedded code execution or dependence on the site's CSS, JavaScript, icon fonts or interactive UI. Native Joomla layout overrides belong to the provider. Do not indiscriminately dispatch normal content events. Render only explicitly supported constructs; document omitted custom fields and unsupported resources.

## Placeholders, URLs and unresolved syntax

Leave `{name}`, `{email}`, `{unsubscribe_link}`, `{confirm_link}`, `{sitename}`, `{siteurl}`, `{date}` intact. Do not replace or derive their values. Preserve author markup outside the provider's own tokens. The bridge normalises relative URLs after providers, then replaces sample placeholders for preview/test. For sending it stores provider output plus absolute URLs as the job snapshot; the upstream sender replaces subscriber placeholders later.

Extension markers use the reserved form `{namespace:...}` (for example `{sources:{...}}`). Unrecognised `{word}` markers are also reserved, except for the seven newsletter placeholders above. The host performs a final generic guard for remaining brace markers in text, attributes and comments, excluding style/script blocks so CSS braces are not interpreted as tokens. The guard is **not** a provider parser, validator or sanitizer. Providers must explicitly report invalid, unsupported, encoded or misplaced forms of their own syntax, including any syntax not covered by this guard.

On a recognised invalid or unsupported selection, either append a stable non-sensitive diagnostic code to `errors`/`unresolved`, preserving previous entries, or throw. The host maps errors to localised generic messages and never displays raw provider exception details. `errors` nonempty or an exception stops preparation; `unresolved` nonempty or a remaining reserved marker stops preparation. No test mail or new job is created after failure. Lack of public matches may return an empty section if that is the extension's documented behaviour.

From Bridge 1.5.0, final preparation also runs in the administrator application after the extra `core.manage` boundary. The public-only identity and protocol document are unchanged. Sources-block live editor presentation uses the separate Sources 4.2.0 per-editor context contract; see [editor presentation](EDITOR_PRESENTATION.md). It does not dispatch this event or create jobs.

## Snapshots and migration

The bridge prepares before creating a job/queue. Every batch uses the stored HTML without provider calls; edits to an article/provider configuration do not change that job. An older job containing unresolved extension markers is rejected with HTTP 409, even when a provider is now available. Cancel it and review already delivered recipients before explicitly starting a new send. Already rendered 1.3.2 jobs remain usable.

Bridge 1.4.0 removes its former Sources-specific renderer and fallback layout. Template overrides are left untouched, but providers must own/document their new override paths. The standalone Sources 4.0.3 content plugin does **not** implement this new protocol. Sources emails require its new `newsletter` provider; installing the bridge alone is not enough. Existing newsletter source bodies and saved presets are not rewritten. Simple Newsletter's administrator send path is unchanged.

## Verification

- Host contract: `php tests/email-preparation.php`.
- Ordinary bridge/security regressions: `php tests/unit.php`.
- Real Joomla loader/dispatch/snapshot tests: copy `tests/joomla-integration.php`, `tests/email-provider-joomla-cases.php` and `tests/fixtures/newsletterbridgefixture.php` into one temporary directory, then run the integration runner with `/var/www/html --email-providers`.
- The fixture provider is test-only, transient and removed in `finally`. It performs no real mail, and is not shipped in the bridge ZIP.
- Each extension must add its own parser, ACL, rendering, budget and native override tests. Host fixture success does not certify Sources, SmartBrowser or Outlook/Gmail rendering.
