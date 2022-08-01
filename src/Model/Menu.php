<?php

namespace TheWebmen\Menustructure\Model;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\View\TemplateGlobalProvider;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class Menu extends DataObject implements TemplateGlobalProvider
{
    private static $table_name = 'Menustructure_Menu';

    private static $db = [
        'Title' => 'Varchar',
        'Slug' => 'Varchar',
        'SubsiteID' => 'Int'
    ];

    private static $has_many = [
        'Items' => MenuItem::class
    ];

    private static $summary_fields = [
        'Title',
        'Slug'
    ];

    /**
     * @return \SilverStripe\Forms\FieldList
     */
    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function ($fields) {
            if ($this->IsProtected()) {
                $fields->dataFieldByName('Slug')->setReadonly(true);
            }

            $fields->removeByName('SubsiteID');
            if (class_exists('SilverStripe\Subsites\Model\Subsite')) {
                $fields->push(new HiddenField('SubsiteID', 'SubsiteID', \SilverStripe\Subsites\State\SubsiteState::singleton()->getSubsiteId()));
            }

            $fields->removeByName('Items');
            if ($this->exists()) {
                $gridConfig = new GridFieldConfig_RecordEditor();
                $gridConfig->addComponent(GridFieldOrderableRows::create());
                $fields->addFieldToTab('Root.Main', GridField::create('Items', 'Items', $this->Items(), $gridConfig));
            }
        });

        return parent::getCMSFields();
    }

    /**
     * On before write generate the slug if needed
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->Slug) {
            $this->Slug = URLSegmentFilter::create()->filter($this->Title);
        }
    }

    /**
     * Recursive delete
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        foreach ($this->Items() as $item) {
            $item->delete();
        }
    }

    /**
     * @return bool
     */
    public function IsProtected()
    {
        $protectedMenus = $this->Config()->get('protected_menus');
        if ($protectedMenus && $this->Slug && in_array($this->Slug, $protectedMenus)) {
            return true;
        }
        return false;
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool
     */
    public function canCreate($member = null, $context = array())
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_TheWebmen\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canCreate($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canView($member = null)
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_TheWebmen\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canView($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canEdit($member = null)
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_TheWebmen\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canEdit($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canDelete($member = null)
    {
        if ($this->IsProtected()) {
            return false;
        }

        if (Permission::checkMember($member, 'CMS_ACCESS_TheWebmen\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canDelete($member);
    }

    /**
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function forTemplate()
    {
        return $this->renderWith(self::class);
    }

    /**
     * @param $slug
     * @param $template
     * @return DataObject
     */
    public static function MenustructureMenu($slug, $template = false)
    {
        if (class_exists('SilverStripe\Subsites\Model\Subsite')) {
            $menu = Menu::get()->filter([
                'Slug' => $slug,
                'SubsiteID' => \SilverStripe\Subsites\State\SubsiteState::singleton()->getSubsiteId()
            ])->first();
        } else {
            $menu = Menu::get()->filter([
                'Slug' => $slug
            ])->first();
        }
        if ($template && $menu) {
            return $menu->renderWith($template);
        }
        return $menu;
    }

    /**
     * @return array
     */
    public static function get_template_global_variables()
    {
        return array(
            'MenustructureMenu'
        );
    }
}
