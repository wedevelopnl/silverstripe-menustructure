<?php

namespace WeDevelop\Menustructure\Admin;

use SilverStripe\Admin\ModelAdmin;
use WeDevelop\Menustructure\Model\Menu;

class MenusAdmin extends ModelAdmin
{
    private static string $menu_title = 'Menus';

    private static string $url_segment = 'menus';

    private static string $menu_icon_class = 'font-icon-menu';

    private static array $managed_models = [
        Menu::class,
    ];
}
