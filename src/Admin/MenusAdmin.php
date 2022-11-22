<?php

namespace WeDevelop\Menustructure\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use WeDevelop\Menustructure\Model\Menu;

class MenusAdmin extends ModelAdmin
{
    private static string $menu_title = 'Menus';

    private static string $url_segment = 'menus';

    private static string $menu_icon_class = 'font-icon-menu';

    private static array $managed_models = [
        Menu::class,
    ];

    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);

        /** @var GridField $gridField */
        $gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));

        if (class_exists('SilverStripe\Subsites\Model\Subsite')) {
            $list = $gridField->getList()->filter(['SubsiteID' => \SilverStripe\Subsites\State\SubsiteState::singleton()->getSubsiteId()]);
            $gridField->setList($list);
        }

        return $form;
    }

    public function subsiteCMSShowInMenu(): bool
    {
        return true;
    }
}
