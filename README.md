# SilverStripe Menustructure module

## Protect menus from deletion
```yaml
TheWebmen\Menustructure\Model\Menu:
  protected_menus:
    - 'main-menu'
    - 'footer-col1'
    - 'footer-col2'
    - 'footer-col3'
```

## Usage
Render a menu in any template using:
```
$MenustructureMenu('menu-slug')
```
Render a menu using a custom template using:
```
$MenustructureMenu('menu-slug', 'Menus/MainMenu')
```

## Todo
* Improve docs
