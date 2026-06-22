# SilverStripe Menustructure

A SilverStripe CMS module for editor-managed multi-menu navigation — define multiple named menus in the CMS, nest them arbitrarily deep, and render them in templates by slug.

## Requirements

- PHP ^8.3
- silverstripe/framework ^6.0, silverstripe/cms ^6.0, silverstripe/admin ^3.0
- unclecheese/display-logic ^4.0, symbiote/silverstripe-gridfieldextensions ^5.0

## Installation

```bash
composer require wedevelopnl/silverstripe-menustructure
```

Then run `dev/build?flush=1` to pick up the new database schema.

## Usage

Create a menu in the CMS under **Menus**, give it a slug, and render it in any template:

```silverstripe
$MenustructureMenu('main-menu')
```

That uses the bundled `Menu.ss` template. To render with your own markup:

```silverstripe
$ViewableMenustructureMenu('main-menu', 'Includes/PrimaryNavigation')
```

See [Template integration](docs/usage/templates.md) for the full rendering contract — link types, active-link styling, nesting, and the bundled markup.

## Documentation

### Usage guides

- [Template integration](docs/usage/templates.md) — `$MenustructureMenu` / `$ViewableMenustructureMenu` helpers, custom templates, link types, active-link styling
- [Configuration](docs/usage/configuration.md) — `protected_menus`, `enable_query_string`, `enable_page_anchor`, permission codes

### Architecture

- [Data model](docs/architecture/data-model.md) — `Menu` / `MenuItem` / `LinkType` enum, cascade behaviour, extension hooks

### Contributing

- [Contributing guide](docs/contributing.md) — dev environment, test/coverage/QA commands, pull-request conventions

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

See [LICENSE](LICENSE).

## Maintainers

[WeDevelop](https://www.wedevelop.nl/) — <development@wedevelop.nl>
