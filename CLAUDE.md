# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A SilverStripe CMS module (`wedevelopnl/silverstripe-menustructure`) that lets editors define multiple named, nested menus in the CMS and render them in templates by slug. PHP 8.3+, SilverStripe CMS `^6`, PSR-4 namespace `WeDevelop\Menustructure\` rooted at `src/`.

The repo *is* the package — there is no surrounding application. The dev environment under `.docker/` builds a throwaway SilverStripe app (recipe-cms `^6`) that mounts the module at `/module` and pulls it in via a path repository, modelled on `silverstripe-grid/.docker/`. The dev-app `composer.json` adds the QA chain: `cambis/silverstan`, `phpstan/phpstan-deprecation-rules`, `tomasvotruba/type-coverage`, `wernerkrauss/silverstripe-rector`.

Per the SilverStripe convention, the long-lived release branch is named after the major SilverStripe version it targets — this work merges into the `6` branch (not `main`). The previous SS5 line stays on its own line. Because this branch will not be released alongside an SS5 backport, `phpstan-deprecation-rules` is enabled at level max so any deprecated SS6 API surfaces immediately rather than via runtime warnings.

## Common commands

| Command | What it does |
| --- | --- |
| `make build` | Build the dev container without starting |
| `make up` | Start FrankenPHP + MySQL stack (auto-generates `.docker/.env`, builds if needed) |
| `make down` / `make destroy` | Stop services / stop and wipe volumes |
| `make sh` | Open a shell inside the `app` container |
| `make test` / `make coverage` | Run PHPUnit (`.docker/app/phpunit.xml.dist`) — `coverage` adds text + HTML + clover output |
| `make analyse` | Run PHPStan at level `max` (incl. deprecation rules + 100 % type coverage) |
| `make rector` / `make rector-dry` | Apply / preview Rector refactors (SS5→SS6 set + PHP 8.3 set + standard presets) |
| `make flush` / `make dev-build` | `sake flush` / `sake dev/build flush=1` |

`make` targets pass through to `docker compose -f .docker/compose.yml exec app …`. `ensure-up` is a dependency on tool targets that brings the stack up if it's not already running.

There is **no** `php-cs-fixer` step. Code style is enforced by Rector via `Netwerkstatt\SilverstripeRector\Set\SilverstripeSetList::CODE_STYLE` — run `make rector` to apply.

## Architecture

Three concerns matter here, and they're all small files — read `src/Model/Menu.php` and `src/Model/MenuItem.php` end-to-end before changing anything; the cross-cutting behaviour isn't obvious from the field lists.

### Models and the rendering entry point

- **`Menu`** (`Menustructure_Menu` table) — `Title` + `Slug`. Auto-fills `Slug` from `Title` via `URLSegmentFilter` on `onBeforeWrite` *only when empty*; once set, the slug is the stable public handle and is rendered read-only when the menu is "protected" (see below).
- **`MenuItem`** (`Menustructure_MenuItem` table) — self-referential via `ParentItem` (`has_one MenuItem`) and `Items` (`has_many MenuItem`), so menus are arbitrarily nested. Sorted by `Sort` and reordered through `Symbiote\GridFieldExtensions\GridFieldOrderableRows`.
- **`MenusAdmin`** is a thin `ModelAdmin` exposing only `Menu`. All permission checks on both models key off `CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin` — there is no separate per-model permission code.

`Menu` implements `TemplateGlobalProvider`, exposing two template helpers:

- `$MenustructureMenu('slug')` → returns the `Menu` (renders via its default template — `templates/WeDevelop/Menustructure/Model/Menu.ss`).
- `$ViewableMenustructureMenu('slug', 'Path/To/Template')` → renders the matching menu with a custom template; returns `?DBHTMLText`.

`Menu::forTemplate()` returns `string` to match SS6's `ModelData::forTemplate(): string` signature — `renderWith()` still produces a `DBHTMLText`, which we cast.

Custom templates iterate `$Items` and check `$LinkType != "no-link"` before emitting `<a href="$Link">` (see the bundled `Menu.ss`).

### LinkType state machine on `MenuItem`

`LinkType` is a real backed PHP enum (`src/Model/LinkType.php`) with five cases — `Page`, `Url`, `File`, `NoLink`, `Breakpoint` — backed by the strings `page` / `url` / `file` / `no-link` / `breakpoint`. The DB column is a MySQL `Enum('page,url,file,no-link,breakpoint', 'no-link')`. Compare with `LinkType::Page->value` (or `LinkType::tryFrom($item->LinkType)`), never with the legacy `MenuItem::LINK_TYPE_*` constants — they were removed in the SS6 bump. The CMS field exposure of every other field is gated by display-logic on `LinkType` (using `UncleCheese\DisplayLogic\Forms\Wrapper`):

- `page` → shows `LinkedPage` (`SiteTree` tree dropdown), optionally `QueryString` and `AnchorText`.
- `url` → shows `Url`.
- `file` → shows `File` (assets `has_one`, also in `$owns` so it's published with the item).
- `no-link` → renders as `<span>` in the default template; `getLink()` returns `''`.
- `breakpoint` → structural marker (dividers, mega-menu column breaks); `getLink()` returns `''`. Templates detect it via `$LinkType = "breakpoint"`.

`getCMSFields()` uses `dataFieldByName()` defensively — in SS6 it returns `?FormField`, so the code null-guards each lookup. Don't reintroduce chained `dataFieldByName()->displayIf()->…` without a null check; PHPStan max + deprecation rules will flag it.

`getLink()` is a `match` on `LinkType` that **also** appends `?QueryString` and `#AnchorText` for `page` links — but only when those features are enabled via config (`enable_query_string`, `enable_page_anchor`, both default `false`). The `updateLinkTypes` and `updateLink` extension hooks let downstream modules add new link types or rewrite generated links — preserve those when refactoring.

### Cascading-write side effect (the non-obvious one)

`MenuItem::onAfterWrite()` and `onBeforeDelete()` *propagate `LastEdited` upward* to the parent `MenuItem` and to the owning `Menu`. This is intentional: it lets downstream caching (HTTP cache, partial caches keyed on `Menu.LastEdited`) invalidate the whole menu when any descendant item changes. `tests/Model/MenuItemTest.php` covers this with `stampLastEditedBackward()` — keep those tests passing if you refactor write paths.

Hierarchy cleanup uses framework cascade: both `Menu` and `MenuItem` declare `$cascade_deletes = ['Items']`, so deleting a menu or a parent item drops the descendants automatically. There is no manual `onBeforeDelete()` cleanup — `MenuItem::onBeforeDelete()` only stamps `LastEdited` on the parent/menu (see above).

### Protected menus

Set in YAML to prevent deletion of menus whose `Slug` matches:

```yaml
WeDevelop\Menustructure\Model\Menu:
  protected_menus:
    - 'main-menu'
    - 'footer'
```

`Menu::IsProtected()` flips `canDelete()` to `false` and forces the `Slug` field read-only in CMS edit.

### Permission-method signatures

`canCreate / canView / canEdit / canDelete` are typed as `mixed $member = null` (and `mixed $context = []` on `canCreate`). The parent `DataObject` declares them untyped, and PHP's LSP forbids narrowing to `?Member`. `mixed` satisfies `tomasvotruba/type-coverage` at 100 % without breaking the parent contract — same approach used in `wedevelopnl/silverstripe-grid`. Don't "tighten" these to `?Member` unless the parent stub changes upstream.

## Things to know before editing

- **PHP 8.3+, SilverStripe `^6`.** `silverstripe/cms`, `silverstripe/framework`, `silverstripe/admin`, `unclecheese/display-logic ^4`, `symbiote/silverstripe-gridfieldextensions ^5`. The previous `silverstripe/display-logic ^3` constraint was actually wrong (the code imports `UncleCheese\…`); fixed during the SS6 bump.
- **Branching**: the SS6 line lives on the `6` branch, matching SilverStripe's own convention. There is no `branch-alias` in `composer.json` — Composer infers the dev version from the branch name (so `dev-6` resolves naturally).
- **`composer.lock` is gitignored**, as is `vendor/`. The `entrypoint.sh` runs `composer install` on container start; local code changes to `composer.json` need `make build` (or `make destroy && make up`) to rebuild the image cleanly.
- **Distribution**: `.gitattributes` marks `/.docker`, `/.github`, `/Makefile`, `/docs`, `/tests`, etc. as `export-ignore` so they don't ship in Packagist tarballs. When adding new dev-only files at the root, add a matching `export-ignore` entry.
- **CHANGELOG**: maintained in `CHANGELOG.md` using Keep a Changelog format (`6.0.0-rc.1` and later). Pre-SS6 releases remain tracked only as GitHub releases — link from the changelog header.
- **Docs layout**: user-facing docs live under `docs/` and split into `docs/usage/` (templates, configuration), `docs/architecture/` (data-model), and `docs/contributing.md`. Update the relevant file rather than centralising in the README.

## Rector

Config at `.docker/app/rector.php`. Sets enabled: `deadCode`, `codeQuality`, `typeDeclarations`, `instanceOf`, `earlyReturn`, `rectorPreset`, PHP 8.3, `SilverstripeSetList::CODE_STYLE`, `SilverstripeLevelSetList::UP_TO_SS_6_0`. Three rules are explicitly skipped (subjective style — matching the silverstripe-grid setup): `ChangeOrIfContinueToMultiContinueRector`, `FlipTypeControlToUseExclusiveTypeRector`, `PostIncDecToPreIncDecRector`.

The `wernerkrauss/silverstripe-rector` SS6 ruleset only renames a handful of classes (`ViewableData` → `ModelData` and friends). It does **not** move `DBHTMLText`, `HasManyList`, or other ORM types — those still live under `SilverStripe\ORM\…` in SS6. Don't manually rewrite imports based on a guess; verify against `vendor/silverstripe/framework/src/` first.

## CI

`.github/workflows/ci.yml` runs two jobs on `6` branch pushes/PRs: `static-analysis` (`make analyse` + `make rector-dry`) and a `phpunit` matrix across PHP 8.3/8.4/8.5. `.github/dependabot.yml` watches `composer`, the `.docker/` Dockerfile, and GitHub Actions versions.
