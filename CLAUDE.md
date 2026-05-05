# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A SilverStripe CMS module (`wedevelopnl/silverstripe-menustructure`) that lets editors define multiple named, nested menus in the CMS and render them in templates by slug. PHP 8.1+, SilverStripe CMS `^5`, PSR-4 namespace `WeDevelop\Menustructure\` rooted at `src/`.

There is no application around this module — the repo *is* the package. There is no `silverstripe/recipe-cms` or kitchen-sink installation here; the Docker image only installs Composer dependencies for tooling (php-cs-fixer). You cannot boot a running CMS from this repo alone.

## Common commands

All `make` targets shell into the `php` Docker service when run from the host (the `${docker}` prefix in the Makefile detects this); inside the container they run directly.

| Command | What it does |
| --- | --- |
| `make build` | Build the Docker image and start detached |
| `make up` / `make down` | Start / stop the dev container |
| `make sh` | Open a shell inside the `php` container |
| `make test` | `php-cs-fixer fix --diff --dry-run` (style check only — there is no PHPUnit suite) |
| `make fix-cs` | Apply php-cs-fixer fixes |
| `make help` | List all targets |

PHP-CS-Fixer rules (see `.php-cs-fixer.php`): `@PHP81Migration`, `@PSR12`, short array syntax, strict comparison, strict param, `array_push` rule, no unused imports. `declare_strict_types` is intentionally **off** (tracked TODO in the config — re-enabling is paired with adding PHPStan).

## Architecture

Three concerns matter here, and they're all small files — read `src/Model/Menu.php` and `src/Model/MenuItem.php` end-to-end before changing anything; the cross-cutting behaviour isn't obvious from the field lists.

### Models and the rendering entry point

- **`Menu`** (`Menustructure_Menu` table) — `Title` + `Slug`. Auto-fills `Slug` from `Title` via `URLSegmentFilter` on `onBeforeWrite` *only when empty*; once set, the slug is the stable public handle and is rendered read-only when the menu is "protected" (see below).
- **`MenuItem`** (`Menustructure_MenuItem` table) — self-referential via `ParentItem` (`has_one MenuItem`) and `Items` (`has_many MenuItem`), so menus are arbitrarily nested. Sorted by `Sort` and reordered through `Symbiote\GridFieldExtensions\GridFieldOrderableRows`.
- **`MenusAdmin`** is a thin `ModelAdmin` exposing only `Menu`. All permission checks on both models key off `CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin` — there is no separate per-model permission code.

`Menu` implements `TemplateGlobalProvider`, exposing two template helpers:

- `$MenustructureMenu('slug')` → returns the `Menu` (renders via its default template — `templates/WeDevelop/Menustructure/Model/Menu.ss`).
- `$ViewableMenustructureMenu('slug', 'Path/To/Template')` → renders the matching menu with a custom template.

Custom templates iterate `$Items` and check `$LinkType != "no-link"` before emitting `<a href="$Link">` (see the bundled `Menu.ss`).

### LinkType state machine on `MenuItem`

`LinkType` is a string enum with four values defined as private constants: `page`, `url`, `file`, `no-link`. The CMS field exposure of every other field is gated by display-logic on `LinkType` (using `UncleCheese\DisplayLogic\Forms\Wrapper`):

- `page` → shows `LinkedPage` (`SiteTree` tree dropdown), optionally `QueryString` and `AnchorText`.
- `url` → shows `Url`.
- `file` → shows `File` (assets `has_one`, also in `$owns` so it's published with the item).
- `no-link` → renders as `<span>` in the default template; `getLink()` returns `''`.

`getLink()` is a `match` on `LinkType` that **also** appends `?QueryString` and `#AnchorText` for `page` links — but only when those features are enabled via config (`enable_query_string`, `enable_page_anchor`, both default `false`). The `updateLinkTypes` and `updateLink` extension hooks let downstream modules add new link types or rewrite generated links — preserve those when refactoring.

### Cascading-write side effect (the non-obvious one)

`MenuItem::onAfterWrite()` and `onBeforeDelete()` *propagate `LastEdited` upward* to the parent `MenuItem` and to the owning `Menu`. This is intentional: it lets downstream caching (HTTP cache, partial caches keyed on `Menu.LastEdited`) invalidate the whole menu when any descendant item changes. If you refactor write paths, do not break this propagation — there is no test catching it.

`Menu::onBeforeDelete()` deletes all `Items` directly (rather than relying on `cascade_deletes`); the equivalent `cascade_deletes` config is not set.

### Protected menus

Set in YAML to prevent deletion of menus whose `Slug` matches:

```yaml
WeDevelop\Menustructure\Model\Menu:
  protected_menus:
    - 'main-menu'
    - 'footer'
```

`Menu::IsProtected()` flips `canDelete()` to `false` and forces the `Slug` field read-only in CMS edit. Note: `docs/configuration.md` still references the legacy `TheWebmen\Menustructure\Model\Menu` namespace and `templates/TheWebmen/...` path — the actual namespace is `WeDevelop\Menustructure` and the template lives at `templates/WeDevelop/Menustructure/Model/Menu.ss`. Treat the docs as out-of-date until updated.

## Things to know before editing

- **No automated tests exist.** The current branch (`feature/ss6-compatibility-and-test-integration`) is set up to introduce a test integration; do not assume `make test` runs PHPUnit. Verify behaviour manually in a host SilverStripe project or add the test harness as part of the change.
- **Branch alias** in `composer.json` maps `dev-main` → `4.x-dev`. The next major (matching SS6 compatibility) will likely be `5.x` — coordinate the alias bump with the release.
- **`composer.lock` is gitignored**, as is `vendor/`. The Dockerfile bakes `composer install` into the image build, but the entrypoint also runs it on container start — local code changes to `composer.json` require `make build` (rebuild) rather than just `make up`.
- **CHANGELOG**: releases are tagged on GitHub; do not maintain `CHANGELOG.md` manually (it points at the GitHub releases page).
