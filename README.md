# silverstripe-menustructure
This module makes it possible to use multiple menus on 1 SilverStripe site, instead of using the "default" menu.
The menus in this module are also more customizable then the "default" silverstripe menu.

## Requirements
* See `composer.json` requirements

## Breaking changes (6.x)

* `MenuItem::LinkType` is now backed by the `WeDevelop\Menustructure\Model\LinkType` PHP enum and stored as a MySQL `Enum` column. Existing rows are preserved (`page`/`url`/`file`/`no-link` map onto the matching cases); a `dev/build` issues an `ALTER TABLE` to swap `Varchar` for the constrained enum type.
* The `updateLinkTypes` extension hook on `MenuItem` and the `link_types` configuration array have been removed. Custom link types should be added by extending the `LinkType` enum upstream — downstream modules can no longer inject new types at runtime.
* A new `breakpoint` link type has been added for templates that need a non-clickable structural marker (separate from `no-link`, which renders as a labelled span).

## Installation
```
composer require wedevelopnl/silverstripe-menustructure
```

## License
See [License](LICENSE)

## Maintainers
* [WeDevelop](https://www.wedevelop.nl/) <development@wedevelop.nl>

## Development and contribution
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.
See read our [contributing](CONTRIBUTING.md) document for more information.

### Getting started
We advise to use [Docker](https://docker.com)/[Docker compose](https://docs.docker.com/compose/) for development.\
We also included a [Makefile](https://www.gnu.org/software/make/) to simplify some commands

Our development container contains some built-in tools like `PHPCSFixer` and `yarn`.

#### Getting development container up
`make build` to build the Docker container and then run detached.\
If you want to only get the container up, you can simply type `make up`.

You can SSH into the container using `make sh`.

#### Front-end
Webpack and yarn are used to compile front-end assets.

If you use the Docker environment, you can just run `make yarn-watch` to watch for changes or run `make yarn-build` to build assets (minified and production ready!)

#### All make commands
You can run `make help` to get a list with all available `make` commands.
