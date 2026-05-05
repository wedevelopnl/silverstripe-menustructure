# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Releases prior to `6.0.0-rc.1` were tracked exclusively as GitHub releases. See
[github.com/wedevelopnl/silverstripe-menustructure/releases](https://github.com/wedevelopnl/silverstripe-menustructure/releases)
for the historical record.

## [6.0.0-rc.1] - Unreleased

First release on the SilverStripe 6 line. Targets PHP 8.3+ and SilverStripe `^6`. The previous SilverStripe 5 line continues on its own branch and is not affected.

### Added

- **`breakpoint` link type on `MenuItem`** — a structural marker case alongside the existing `page` / `url` / `file` / `no-link` types. Templates can detect it via `$LinkType = "breakpoint"` and render dividers, mega-menu column breaks, or other non-clickable structural items separately from the labelled `no-link` case
- **PHPUnit test suite** — first tests for the module, covering both `DataObject`s' behaviour: slug auto-fill, `protected_menus` config gating, permission delegation, `getLink()` across all `LinkType` branches (with `QueryString` / `AnchorText` config flips), `LinkingMode`, `getLevel`, and `LastEdited` cascade on write/delete
- **GitHub Actions CI workflow** — static-analysis job (`make analyse` + `make rector-dry`) and a PHPUnit matrix across PHP 8.3 / 8.4 / 8.5 run on every push and PR to the `6` branch
- **FrankenPHP-based dev environment** — `make up` provisions FrankenPHP 8.3 + MySQL 8 + Caddy with deterministic ports, replacing the legacy single-container `php-cs-fixer`-only setup. Ships `make` targets for `test`, `coverage`, `analyse`, `rector`, `rector-dry`, `flush`, `dev-build`, and `sh`
- **Rector configuration** — `make rector` / `make rector-dry` target SS5→SS6 + PHP 8.3 + standard presets, applying both refactors and code style. Replaces the previous `php-cs-fixer` step entirely
- **Dependabot configuration** for Composer, the `.docker/` Dockerfile, and GitHub Actions versions
- **Restructured documentation** — `docs/usage/templates.md`, `docs/usage/configuration.md`, `docs/architecture/data-model.md`, `docs/contributing.md` replace the single `docs/configuration.md` file
- **`CHANGELOG.md` adopts Keep a Changelog format** — previous releases continue to be tracked on GitHub

### Changed

- **BREAKING: SilverStripe 6 / PHP 8.3 baseline.** Requires `silverstripe/framework ^6.0`, `silverstripe/cms ^6.0`, `silverstripe/admin ^3.0`, `unclecheese/display-logic ^4.0`, `symbiote/silverstripe-gridfieldextensions ^5.0`, and PHP `^8.3`. The previous `silverstripe/display-logic ^3` constraint was incorrect (the code imports `UncleCheese\…`) and has been corrected as part of the bump
- **BREAKING: `MenuItem::LinkType` is now a backed PHP enum.** The constant trio (`LINK_TYPE_PAGE` / `LINK_TYPE_URL` / `LINK_TYPE_FILE` / `LINK_TYPE_NO_LINK`) is replaced by `WeDevelop\Menustructure\Model\LinkType`. The database column changes from `Varchar` to MySQL `Enum('page,url,file,no-link,breakpoint', 'no-link')` — `dev/build` performs an in-place `ALTER TABLE` and existing rows are preserved (the four prior string values map onto the matching cases). Code that compared `$item->LinkType === MenuItem::LINK_TYPE_PAGE` should compare against `LinkType::Page->value` (or use `LinkType::tryFrom($item->LinkType)`)
- **BREAKING: `updateLinkTypes` extension hook removed.** Combined with the dropped `link_types` config array, the set of valid link types is now closed at the PHP level. Custom link types must be contributed through a code change to this module — they can no longer be injected by downstream extensions at runtime. This is a deliberate trade-off against runtime extensibility for static analysis: the previous mechanism produced an unverifiable runtime set of types
- **`MenuItem::getLink()` is now a `match` over `LinkType`.** Behaviour is unchanged for existing types; the function is exhaustive against the enum and no longer needs the trailing `?? ''` fallback
- **Hierarchy cleanup uses `$cascade_deletes`** instead of a manual `Menu::onBeforeDelete()` loop, and `MenuItem` now declares the same so deleting a sub-tree root no longer orphans descendants. `MenuItem::$owns = ['File']` removed — neither model is `Versioned`, so the publish cascade never ran and the config was dead
- **`Menu::forTemplate()` returns `string`** to match SilverStripe 6's `ModelData::forTemplate(): string` signature. `renderWith()` still produces a `DBHTMLText`, which the method casts
- **Permission methods (`canCreate` / `canView` / `canEdit` / `canDelete`) typed as `mixed $member = null`** (and `mixed $context = []` on `canCreate`). The parent `DataObject` declares them untyped; PHP's LSP forbids narrowing to `?Member`. `mixed` satisfies `tomasvotruba/type-coverage` at 100 % without violating the parent contract
- **All source files declare `strict_types=1` and use `#[Override]`** on overridden methods, consistently with the rest of the WeDevelop SS6 modules

### Removed

- **`updateLinkTypes` extension hook** (see Changed)
- **`link_types` config array** (see Changed)
- **`MenuItem::$owns`** — dead config (see Changed)
- **`php-cs-fixer` integration** — code style is now enforced via Rector's `SilverstripeSetList::CODE_STYLE`

### Developer Experience

- **PHPStan at level `max`** with `cambis/silverstan` ^2.1, `phpstan/phpstan-deprecation-rules`, and `tomasvotruba/type-coverage` at 100 %. Silverstan resolves SilverStripe's config conventions (private static `$db` / `$has_one` / `$has_many`, `Config_ForClass` access) which was the root cause of the bulk of the original analysis errors
- **Deprecation rules surface SS6 deprecations as static-analysis errors** rather than runtime warnings — relevant because this branch will not be released alongside an SS5 backport
- **`@covers` PHPDoc annotations replaced with `#[CoversClass]` attributes** in the test suite (PHPUnit 11 idiom)
- **`.gitattributes` `export-ignore` entries** for `/.docker`, `/.github`, `/Makefile`, `/docs`, `/tests`, etc., so dev-only files don't ship in Packagist tarballs

[6.0.0-rc.1]: https://github.com/wedevelopnl/silverstripe-menustructure/releases/tag/6.0.0-rc.1
