# Silverstripe Menustructure configuration

## Usage

Render a menu in any template using:

```
$MenustructureMenu('menu-slug')
```

This returns the matching `Menu` and renders it through the bundled
`templates/WeDevelop/Menustructure/Model/Menu.ss` template.

## Render with a custom template

Use the `ViewableMenustructureMenu` helper to render with your own template:

```
$ViewableMenustructureMenu('menu-slug', 'Path/To/CustomMenu')
```

The first argument is the menu's slug; the second is the dot/slash-separated
template path (resolved against your project + module template paths).

You can use the bundled `Menu.ss` as a starting point. Iterate `$Items` and
check `$LinkType != "no-link"` before emitting `<a href="$Link">`.

## Protect menus from deletion

Add a configuration file (eg. `app/_config/menus.yml`) to your project to mark
menus that editors should not be able to delete:

```yaml
WeDevelop\Menustructure\Model\Menu:
  protected_menus:
    - 'main-menu'
    - 'footer-col1'
    - 'footer-col2'
    - 'footer-col3'
```

When a menu's `Slug` matches an entry in `protected_menus`:

- `canDelete()` returns `false` (the delete action disappears in the CMS).
- The `Slug` field becomes read-only in the menu's edit form.

## Optional `MenuItem` features

Both default to `false`. Enable per project:

```yaml
WeDevelop\Menustructure\Model\MenuItem:
  enable_query_string: true
  enable_page_anchor: true
```

- `enable_query_string` — exposes a `QueryString` field for `page`-type items
  and appends `?<value>` to the rendered link.
- `enable_page_anchor` — exposes an `AnchorText` field for `page`-type items
  and appends `#<value>` to the rendered link.
