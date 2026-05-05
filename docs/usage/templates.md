# Template integration

This guide covers how to render menus in your templates, what the bundled markup looks like, and how to swap it for your own.

## Rendering a menu

Two `TemplateGlobalProvider` helpers are exposed by the `Menu` model. Both look up a menu by its `Slug`.

| Helper | Returns | Use when |
|--------|---------|----------|
| `$MenustructureMenu('slug')` | `Menu` (cast to `string` via the bundled template) | You want the module's default markup |
| `$ViewableMenustructureMenu('slug', 'Path/To/Template')` | `?DBHTMLText` (`null` if the slug doesn't resolve) | You want to render with a project-supplied template |

```silverstripe
{# Default markup #}
$MenustructureMenu('main-menu')

{# Custom template — resolved against the standard SilverStripe template paths #}
$ViewableMenustructureMenu('main-menu', 'Includes/PrimaryNavigation')
```

The slug is the stable public handle for a menu — set once in the CMS, it doesn't change when the editor renames the menu. See [Configuration](configuration.md#protected-menus) for protecting slugs of menus your templates rely on.

## The bundled template

`templates/WeDevelop/Menustructure/Model/Menu.ss` is what `$MenustructureMenu('…')` renders. It's intentionally minimal — copy it as a starting point for your own template:

```silverstripe
<nav>
    <ul>
        <% loop $Items %>
            <li>
                <% if $Link && $LinkType != "no-link" %>
                    <a href="$Link"<% if $OpenInNewWindow %> target="_blank"<% end_if %>>$Title</a>
                <% else %>
                    <span>$Title</span>
                <% end_if %>
            </li>
        <% end_loop %>
    </ul>
</nav>
```

Two things to note:

1. **`$Items` is the top-level collection** — `Menu->Items()` returns only items whose `ParentItemID = 0`. If you need nested rendering, recurse into each item's `$Items` (which is the `MenuItem->Items()` `has_many`). Sort order is `Sort ASC` for both relations.
2. **The `$LinkType != "no-link"` guard is deliberate.** `$Link` is empty for both `no-link` and `breakpoint` items, so a single `$Link` check would render both as a `<span>`. Keep the explicit check if you want to distinguish them — see [Link types](#link-types) below.

## Link types in templates

`MenuItem::LinkType` is a backed enum (`WeDevelop\Menustructure\Model\LinkType`) with five cases. In templates the value is the string scalar (`'page'`, `'url'`, `'file'`, `'no-link'`, `'breakpoint'`).

| Value | `$Link` | Typical markup |
|-------|---------|----------------|
| `page` | Page link, optionally with `?QueryString` and `#AnchorText` (see [Configuration](configuration.md#optional-menuitem-fields)) | `<a href="$Link">` |
| `url` | The `Url` field verbatim | `<a href="$Link">` |
| `file` | Asset link from the `File` `has_one` | `<a href="$Link">` |
| `no-link` | Empty string | `<span>$Title</span>` — labelled non-link |
| `breakpoint` | Empty string | Structural marker (e.g. divider, mega-menu column break) |

`no-link` and `breakpoint` both yield an empty `$Link` but have different intent. `no-link` is for items that should appear as visible labels with no navigation; `breakpoint` is for structural items the template should detect and act on (typically without rendering a label at all). Switch on `$LinkType` directly in your template if you need to distinguish them:

```silverstripe
<% loop $Items %>
    <% if $LinkType = "breakpoint" %>
        <li class="menu__divider" aria-hidden="true"></li>
    <% else_if $LinkType = "no-link" %>
        <li><span class="menu__label">$Title</span></li>
    <% else %>
        <li><a href="$Link"<% if $OpenInNewWindow %> target="_blank"<% end_if %>>$Title</a></li>
    <% end_if %>
<% end_loop %>
```

## Active-link styling

`MenuItem::LinkingMode()` returns `'current'` when the item is a `page` link pointing at the current `Controller::curr()->ID`, and `'link'` otherwise. Use it to apply an active class:

```silverstripe
<a href="$Link" class="menu__link menu__link--$LinkingMode">$Title</a>
```

`LinkingMode` does not walk ancestor pages — only the exact match returns `'current'`. If you need "section" highlighting (parent of current page), do that in your own getter on `MenuItem` and call it from the template.

## Nesting depth

`MenuItem::getLevel()` returns the zero-indexed nesting depth (top-level items return `0`). Useful for limiting recursion or applying depth-specific classes:

```silverstripe
<li class="menu__item menu__item--depth-$Level">…</li>
```

## See also

- [Configuration](configuration.md) — `protected_menus`, `enable_query_string`, `enable_page_anchor`
- [Data model](../architecture/data-model.md) — `Menu` / `MenuItem` / `LinkType` and the cascading `LastEdited` behaviour you can key cache invalidation on
