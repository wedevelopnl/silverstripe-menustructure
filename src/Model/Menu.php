<?php

namespace WeDevelop\Menustructure\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripe\View\ViewableData;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * @property string $Title
 * @property string $Slug
 * @method HasManyList<MenuItem> Items()
 */
class Menu extends DataObject implements TemplateGlobalProvider
{
    /** @config */
    private static string $table_name = 'Menustructure_Menu';

    /** @config */
    private static array $db = [
        'Title' => 'Varchar',
        'Slug' => 'Varchar',
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
                'Items',
            ]);

            if ($this->exists()) {
                $gridConfig = GridFieldConfig_RelationEditor::create();
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
        $protectedMenus = static::config()->get('protected_menus');

        if (is_array($protectedMenus) && $this->Slug && in_array($this->Slug, $protectedMenus, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param Member|null $member
     * @param array<string, mixed> $context
     */
    public function canCreate($member = null, $context = []): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canCreate($member, $context);
    }

    /**
     * @param Member|null $member
     */
    public function canView($member = null): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canView($member);
    }

    /**
     * @param Member|null $member
     */
    public function canEdit($member = null): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canEdit($member);
    }

    /**
     * @param Member|null $member
     */
    public function canDelete($member = null): bool
    {
        if ($this->IsProtected()) {
            return false;
        }

        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canDelete($member);
    }

    public function forTemplate(): DBHTMLText
    {
        return $this->renderWith(self::class);
    }

    public static function ViewableMenustructureMenu(string $slug, string $template): ?ViewableData
    {
        $menu = self::MenustructureMenu($slug);

        if ($menu instanceof Menu) {
            return $menu->renderWith($template);
        }

        return $menu;
    }

    public static function MenustructureMenu(string $slug): ?Menu
    {
        return Menu::get()->filter([
            'Slug' => $slug,
        ])->first();
    }

    /**
     * @return array<int, string>
     */
    public static function get_template_global_variables(): array
    {
        return [
            'MenustructureMenu',
            'ViewableMenustructureMenu',
        ];
    }
}
