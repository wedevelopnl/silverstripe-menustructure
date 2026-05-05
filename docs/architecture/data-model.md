# Data model

The module is two `DataObject`s and an enum. This page documents how they fit together, the cross-cutting behaviours that aren't obvious from the field lists, and the extension surface you can hook into.

## Models

### `Menu` (`Menustructure_Menu`)

The top-level container. Holds `Title`, `Slug`, and a `has_many` `Items` relation to `MenuItem`. `Slug` is auto-filled from `Title` via `URLSegmentFilter` on `onBeforeWrite` **only when empty** — once persisted, it never changes automatically.

Implements `TemplateGlobalProvider` to expose `$MenustructureMenu` and `$ViewableMenustructureMenu` template helpers (see [Template integration](../usage/templates.md)).

`Menu::forTemplate()` returns `string` (cast from the `DBHTMLText` produced by `renderWith`) to satisfy SilverStripe 6's `ModelData::forTemplate(): string` signature.

### `MenuItem` (`Menustructure_MenuItem`)

A single navigable item. Self-referential through `ParentItem` (`has_one MenuItem`) and `Items` (`has_many MenuItem`), so menus are arbitrarily deeply nested. Sort order within a parent is the `Sort` int, reordered through `Symbiote\GridFieldExtensions\GridFieldOrderableRows`.

CMS field exposure is gated on `LinkType` via `display-logic` (`UncleCheese\DisplayLogic\Forms\Wrapper`):

- `page` → `LinkedPage` (`SiteTree` tree dropdown), optionally `QueryString` and `AnchorText`
- `url` → `Url` (`Varchar(255)`)
- `file` → `File` (`Asset` `has_one`)
- `no-link` / `breakpoint` → label-only, no link fields shown

`getCMSFields()` looks up fields with `dataFieldByName()` and **null-guards every result.** `dataFieldByName()` returns `?FormField` in SS6 — chained calls without a guard will trip both PHPStan max and runtime when a field is removed by an extension.

### `LinkType` enum

```php
namespace WeDevelop\Menustructure\Model;

enum LinkType: string
{
    case Page = 'page';
    case Url = 'url';
    case File = 'file';
    case NoLink = 'no-link';
    case Breakpoint = 'breakpoint';
}
```

Stored as a MySQL `Enum('page,url,file,no-link,breakpoint', 'no-link')` column on `MenuItem`. Adding a new case requires a `dev/build` to run the schema `ALTER TABLE`.

The set of valid types is closed at the PHP level. Extending the `LinkType` enum is not a SilverStripe extension hook — downstream modules that need an additional type must contribute it through a code change to this module (or replace the column with a freer type via a fork). This is a deliberate trade-off against the previous `updateLinkTypes` hook, which made the runtime set of types unverifiable for static analysis. See the [CHANGELOG](../../CHANGELOG.md) for the migration path.

## Cascade behaviour

### `LastEdited` propagates upward

`MenuItem::onAfterWrite()` and `MenuItem::onBeforeDelete()` both bump the `LastEdited` timestamp on:

1. The owning `Menu` (`$this->Menu()`)
2. The parent `MenuItem` (`$this->ParentItem()`), if any

This is intentional. Templates that key partial-cache blocks on `$Menu.LastEdited` (or HTTP cache headers derived from it) need to invalidate when **any descendant** changes — without the upward propagation, editing a deeply nested item would leave a stale cached menu.

`tests/Model/MenuItemTest.php::stampLastEditedBackward()` pins this. Don't refactor the write paths to skip the cascade unless you also redesign the cache-invalidation contract for downstream consumers.

### `cascade_deletes` for the hierarchy

Both models declare `$cascade_deletes`:

```php
// Menu
private static array $cascade_deletes = ['Items'];

// MenuItem
private static array $cascade_deletes = ['Items'];
```

Deleting a `Menu` removes every `MenuItem` it owns. Deleting a `MenuItem` removes its sub-tree (recursive through the self-referential `Items`). The `File` `has_one` is **not** in `$owns` — neither model is `Versioned`, so a publish cascade would never run. Files attached to deleted items remain in assets.

## Permissions

`canCreate` / `canView` / `canEdit` / `canDelete` on both models check `CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin` and fall through to the parent `DataObject` defaults if the permission isn't granted. There's no per-menu ACL.

The permission methods are typed `mixed $member = null` (and `mixed $context = []` on `canCreate`). The parent `DataObject` declares them untyped, and PHP's LSP forbids narrowing to `?Member`. `mixed` satisfies `tomasvotruba/type-coverage` at 100% without breaking the parent contract — same approach used in `wedevelopnl/silverstripe-grid`. Don't tighten these unless the parent stub changes upstream.

`Menu::canDelete()` additionally returns `false` when `IsProtected()` is true (regardless of the member's permissions). See [Configuration → Protected menus](../usage/configuration.md#protected-menus).

## Extension hooks

The module exposes one `extend()` call on `MenuItem`:

| Hook | Signature | Purpose |
|------|-----------|---------|
| `updateLink` | `function updateLink(string &$link)` | Rewrite the generated link before it's returned by `getLink()` |

Register an extension on `MenuItem` to inject behaviour:

```yaml
WeDevelop\Menustructure\Model\MenuItem:
  extensions:
    - App\Extensions\TrackingMenuItemExtension
```

```php
namespace App\Extensions;

use SilverStripe\Core\Extension;

class TrackingMenuItemExtension extends Extension
{
    public function updateLink(string &$link): void
    {
        if ($link !== '') {
            $link .= (str_contains($link, '?') ? '&' : '?') . 'utm_source=menu';
        }
    }
}
```

The hook fires after `QueryString` / `AnchorText` are appended, so the extension sees the fully-composed link.

> The previous `updateLinkTypes` hook (and its companion `link_types` config array) was removed when `LinkType` became a backed enum. Custom link types are no longer pluggable at runtime — see [LinkType enum](#linktype-enum) above for the rationale.

## CMS surface

`MenusAdmin` (`src/Admin/MenusAdmin.php`) is a thin `ModelAdmin` exposing only `Menu`. Editing `MenuItem` happens through the `GridField` rendered on the menu's edit form (and recursively on each item's own edit form, for nested items). There's no separate `MenuItem` admin route.

The admin uses the `font-icon-menu` CMS icon and the URL segment `menus`.

## See also

- [Template integration](../usage/templates.md) — how the model surfaces in `.ss` templates
- [Configuration](../usage/configuration.md) — YAML toggles for `protected_menus`, `enable_query_string`, `enable_page_anchor`
