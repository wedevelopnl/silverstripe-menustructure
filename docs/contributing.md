# Contributing

Everything you need to run the module locally, make changes, and run the QA suite.

## Prerequisites

- Docker Desktop (or compatible)
- Composer (only needed if you plan to edit `composer.json` outside the container)

The dev container ships PHP 8.3, FrankenPHP, MySQL 8, and the full QA toolchain (`phpstan`, `cambis/silverstan`, `phpstan/phpstan-deprecation-rules`, `tomasvotruba/type-coverage`, `wernerkrauss/silverstripe-rector`). You don't need any of those installed locally.

## Quick start

```bash
make up
```

That's it. `make up` provisions a SilverStripe 6 dev environment around the module, runs `composer install` inside the container, and prints the testbed URL once the app is healthy. First boot takes ~1–2 minutes — wait for the URL to print before browsing.

## Local environment

- **CMS:** the URL printed by `make up` (typically `https://localhost:80xx` — exact port lives in `.docker/.env` as `WEB_PORT`)
- **Admin:** `<that URL>/admin` — login `admin` / `admin`
- **Database:** MySQL 8 exposed on `127.0.0.1:<DB_PORT>` (also in `.docker/.env`)

## Day-to-day

| Command | Description |
|---------|-------------|
| `make up` | Start (or resume) services |
| `make down` | Stop services (keeps DB volume) |
| `make destroy` | Stop services and drop volumes (full reset — wipes DB) |
| `make build` | Rebuild Docker images without starting |
| `make sh` | Open a shell inside the `app` container |
| `make flush` | Clear SilverStripe cache |
| `make dev-build` | Run `dev/build flush=1` inside the container |

`make` targets pass through to `docker compose -f .docker/compose.yml exec app …`. The `ensure-up` dependency on each tool target brings the stack up if it's not already running.

## Testing

| Command | Description |
|---------|-------------|
| `make test` | Run the PHPUnit suite (`tests/Model/*`) |
| `make coverage` | PHPUnit with text + HTML + Clover coverage reports (written to `coverage/`) |

Tests are SilverStripe `SapphireTest` instances wired up through the dev-app's PHPUnit config (`.docker/app/phpunit.xml.dist`). Fixtures live in `tests/fixtures/*.yml`.

## Quality

| Command | Description |
|---------|-------------|
| `make analyse` | PHPStan at level `max` + Silverstan + deprecation rules + 100 % type coverage |
| `make rector-dry` | Preview Rector refactorings (SS5→SS6 + PHP 8.3 + standard presets) |
| `make rector` | Apply Rector refactorings |

There is **no** `php-cs-fixer` step. Code style is enforced by Rector via `Netwerkstatt\SilverstripeRector\Set\SilverstripeSetList::CODE_STYLE` — run `make rector` to apply.

CI runs the same commands on every push and PR (`.github/workflows/ci.yml`):

- **static-analysis** — `make analyse` + `make rector-dry`
- **phpunit** — matrix across PHP 8.3 / 8.4 / 8.5

## Things to know before editing

- **Branching.** The SS6 line lives on the `6` branch, matching SilverStripe's own convention. PRs targeting SS6 work merge into `6`, not `main`. Composer infers the dev version from the branch name (so `dev-6` resolves naturally — there is no `branch-alias` in `composer.json`).
- **`composer.lock` is gitignored**, as is `vendor/`. The container's `entrypoint.sh` runs `composer install` on startup; changes to `composer.json` need `make build` (or `make destroy && make up`) to rebuild the image cleanly.
- **Distribution.** `.gitattributes` marks `/.docker`, `/.github`, `/Makefile`, `/docs`, `/tests`, etc. as `export-ignore` so they don't ship in Packagist tarballs. New dev-only files at the root need a matching `export-ignore` entry.
- **Releases** are tagged on GitHub. `CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) — update the `[Unreleased]` section as you go and move it under the version heading at release time.

## Pull requests

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

When contributing code:

- Run `make analyse` and `make test` locally before pushing — CI runs the same commands.
- PHPStan runs at level `max` with 100 % type coverage and SS deprecation rules. New deprecations surface as errors before runtime warnings.
- Public API changes need an entry in `CHANGELOG.md` under the matching section (`Added` / `Changed` / `Fixed` / `Removed`). Breaking changes go under **Changed** with a `**BREAKING:**` prefix on the bullet.

## Reporting bugs

Bugs are tracked in the [issues section](https://github.com/wedevelopnl/silverstripe-menustructure/issues) of this repository. Before submitting, check for an existing issue.

If your report is new:

- Describe the steps to reproduce and the expected outcome. A failing test is the most effective form.
- Describe the environment: SilverStripe version, PHP version, browser (for CMS issues), other installed modules.

## Security

Report security issues directly to the maintainers (<development@wedevelop.nl>). Do not file security issues in the public bug tracker.
