<?php

namespace WeDevelop\Menustructure\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\View\TemplateGlobalProvider;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * @property string $Title
 * @property string $Slug
 * @property int $SubsiteID
 * @method MenuItem|HasManyList Items()
 */
class Menu extends DataObject implements TemplateGlobalProvider
{
    /** @config */
    private static string $table_name = 'Menustructure_Menu';

    /** @config */
    private static array $db = [
        'Title' => 'Varchar',
        'Slug' => 'Varchar',
        'SubsiteID' => 'Int',
    ];

    /** @config */
    private static array $has_many = [
        'Items' => MenuItem::class,
    ];

    /** @config */
    private static array $summary_fields = [
        'Title',
        'Slug',
    ];

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function ($fields) {
            if ($this->IsProtected()) {
                $fields->dataFieldByName('Slug')->setReadonly(true);
            }

            $fields->removeByName([
                'SubsiteID',
                'Items',
            ]);

            if (class_exists('SilverStripe\Subsites\Model\Subsite')) {
                $fields->push(new HiddenField('SubsiteID', 'SubsiteID', \SilverStripe\Subsites\State\SubsiteState::singleton()->getSubsiteId()));
            }

            if ($this->exists()) {
                $gridConfig = new GridFieldConfig_RecordEditor();
                $gridConfig->addComponent(GridFieldOrderableRows::create());
                $fields->addFieldToTab('Root.Main', GridField::create('Items', 'Items', $this->Items(), $gridConfig));
            }
        });

        return parent::getCMSFields();
    }

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        if (!$this->Slug) {
            $this->Slug = URLSegmentFilter::create()->filter($this->Title);
        }
    }

    public function onBeforeDelete(): void
    {
        parent::onBeforeDelete();

        foreach ($this->Items() as $item) {
            $item->delete();
        }
    }

    public function IsProtected(): bool
    {
        $protectedMenus = $this->Config()->get('protected_menus');

        if ($protectedMenus && $this->Slug && in_array($this->Slug, $protectedMenus, true)) {
            return true;
        }

        return false;
    }

    public function canCreate(int|Member $member = null, $context = []): bool
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canCreate($member);
    }

    public function canView(int|Member $member = null): bool
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canView($member);
    }

    public function canEdit(int|Member $member = null): bool
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canEdit($member);
    }

    public function canDelete(int|Member $member = null): bool
    {
        if ($this->IsProtected()) {
            return false;
        }

        if (Permission::checkMember($member, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canDelete($member);
    }

    public function forTemplate(): DBHTMLText
    {
        return $this->renderWith(self::class);
    }

    public static function MenustructureMenu(string $slug, string $template = null): DataObject|DBHTMLText|null
    {
        if (class_exists('SilverStripe\Subsites\Model\Subsite')) {
            $menu = Menu::get()->filter([
                'Slug' => $slug,
                'SubsiteID' => \SilverStripe\Subsites\State\SubsiteState::singleton()->getSubsiteId(),
            ])->first();
        } else {
            $menu = Menu::get()->filter([
                'Slug' => $slug,
            ])->first();
        }

        if (!is_null($template) && $menu) {
            return $menu->renderWith($template);
        }

        return $menu;
    }

    public static function get_template_global_variables(): array
    {
        return [
            'MenustructureMenu',
        ];
    }
}
