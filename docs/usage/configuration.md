# Configuration

All configuration is done through SilverStripe's standard YAML config layer (typically `app/_config/menustructure.yml`).

## Protected menus

Mark menus whose `Slug` editors should not be able to delete or rename. Use this for menus your templates depend on by slug — losing the slug breaks the rendered output.

```yaml
WeDevelop\Menustructure\Model\Menu:
  protected_menus:
    - 'main-menu'
    - 'footer-col1'
    - 'footer-col2'
    - 'footer-col3'
```

When a menu's `Slug` matches an entry:

- `Menu::canDelete()` returns `false` — the delete action disappears from the CMS edit form.
- The `Slug` field is forced read-only — editors can rename the menu (`Title`) but not its public handle.
- `Menu::IsProtected()` exposes the same check for use in templates or extensions.

The match is exact and case-sensitive. Slugs are auto-generated from `Title` via `URLSegmentFilter` on first save — verify the generated slug matches the protected list before relying on it. A slug, once persisted, is never overwritten by subsequent renames.

## Optional `MenuItem` fields

Two fields on `MenuItem` ship hidden by default. Enable per project:

```yaml
WeDevelop\Menustructure\Model\MenuItem:
  enable_query_string: true
  enable_page_anchor: true
```

| Config key | Field exposed | Effect on `$Link` for `LinkType = page` |
|------------|----------------|------------------------------------------|
| `enable_query_string` | `QueryString` (`Varchar`) | Appends `?<value>` (e.g. `?utm_source=newsletter`) |
| `enable_page_anchor` | `AnchorText` (`Varchar`) | Appends `#<value>` |

Both fields:

- Show only for `page` link types (controlled by `display-logic`).
- Are stored unconditionally on every `MenuItem` row regardless of the flag — the YAML toggle only controls CMS exposure and link rendering. Disabling the flag after data is saved hides the field but does not strip the stored value.
- Are appended in this order if both apply: `pageLink?QueryString#AnchorText`.

`QueryString` is rendered raw (no validation, no URL-encoding). The CMS field description hints at the `foo=bar&john=doe` shape but the value is whatever the editor types — sanitise downstream if you echo it into HTML attributes outside the bundled template.

## Permissions

Both `Menu` and `MenuItem` delegate `canCreate` / `canView` / `canEdit` / `canDelete` to a single permission code: `CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin`. Granting a CMS role access to the **Menus** section automatically grants edit rights on every menu and menu item — there is no per-menu ACL.

`canDelete` on `Menu` additionally consults `IsProtected()` (see above) and refuses regardless of permission.

## See also

- [Template integration](templates.md) — how protected slugs feed into the rendering helpers
- [Data model](../architecture/data-model.md#extension-hooks) — extending link generation when the built-in fields aren't enough
