# Silverstripe Menustructure configuration
## Usage
Render a menu in any template using:
```
$MenustructureMenu('menu-slug')
```

## Protect menus from deletion
You can add some custom configuration file (eg. `app/_config/menu.yml)` to your project to protect menus.\
See the example below;

```yaml
TheWebmen\Menustructure\Model\Menu:
  protected_menus:
    - 'main-menu'
    - 'footer-col1'
    - 'footer-col2'
    - 'footer-col3'
```

## Customizing
### Templates
It is possible to render the menus in custom templates.
De default template is found in this module under `templates/TheWebmen/Menustructure/Model/Menu.ss`.

You can use this code as example for custom menus.

### Render the custom menu

Render a menu using a custom template using:
```
$MenustructureMenu('menu-slug', 'Menus/MainMenu')
```

Here the first argument is the slug of the menu and the second argument is the template you want to use.
