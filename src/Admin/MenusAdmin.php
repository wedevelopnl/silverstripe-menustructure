<?php

namespace TheWebmen\Menustructure\Admin;

use SilverStripe\Admin\ModelAdmin;
use TheWebmen\Menustructure\Model\Menu;

class MenusAdmin extends ModelAdmin
{
    private static $managed_models = [
        Menu::class
    ];

    private static $url_segment = 'menus';

    private static $menu_title = 'Menus';

    private static $menu_icon_class = 'font-icon-menu';

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));
        if (class_exists('SilverStripe\Subsites\Model\Subsite')) {
            $list = $gridField->getList()->filter(array('SubsiteID' => \SilverStripe\Subsites\State\SubsiteState::singleton()->getSubsiteId()));
            $gridField->setList($list);
        }

        return $form;
    }

    public function subsiteCMSShowInMenu()
    {
        return true;
    }
}
