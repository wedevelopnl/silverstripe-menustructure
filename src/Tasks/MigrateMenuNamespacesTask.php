<?php

namespace WeDevelop\Menustructure\Tasks;

use SilverStripe\Dev\BuildTask;
use WeDevelop\Menustructure\Model\Menu;
use WeDevelop\Menustructure\Model\MenuItem;

class MigrateMenuNamespacesTask extends BuildTask
{
    protected $title = 'Migrate menu namespaces';
    private static string $segment = 'migrate-menu-namespaces';
    protected string $description = 'Migrate menu (items) namespaces from old to new namespace';

    public function run($request)
    {
        $menus = Menu::get();
        $menuItems = MenuItem::get();

        foreach ($menus as $menu) {
            $menu->ClassName = str_replace('TheWebmen', 'WeDevelop', $menu->ClassName);
            $menu->write();
        }

        foreach ($menuItems as $menuItem) {
            $menuItem->ClassName = str_replace('TheWebmen', 'WeDevelop', $menuItem->ClassName);
            $menuItem->write();
        }
    }
}
