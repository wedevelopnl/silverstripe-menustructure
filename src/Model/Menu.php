<?php

declare(strict_types=1);

namespace WeDevelop\Menustructure\Model;

use Override;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Permission;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\View\TemplateGlobalProvider;
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
    private static array $cascade_deletes = [
        'Items',
    ];

    /** @config */
    private static array $summary_fields = [
        'Title',
        'Slug',
    ];

    #[Override]
    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields): void {
            if ($this->IsProtected()) {
                $fields->dataFieldByName('Slug')?->setReadonly(true);
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

    #[Override]
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        if (!$this->Slug) {
            $this->Slug = URLSegmentFilter::create()->filter($this->Title);
        }
    }

    public function IsProtected(): bool
    {
        $protectedMenus = static::config()->get('protected_menus');
        return is_array($protectedMenus) && $this->Slug && in_array($this->Slug, $protectedMenus, true);
    }

    /**
     * @param mixed[] $context
     */
    #[Override]
    public function canCreate(mixed $member = null, mixed $context = []): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canCreate($member, $context);
    }

    #[Override]
    public function canView(mixed $member = null): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canView($member);
    }

    #[Override]
    public function canEdit(mixed $member = null): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canEdit($member);
    }

    #[Override]
    public function canDelete(mixed $member = null): bool
    {
        if ($this->IsProtected()) {
            return false;
        }

        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canDelete($member);
    }

    #[Override]
    public function forTemplate(): string
    {
        return (string)$this->renderWith(self::class);
    }

    public static function ViewableMenustructureMenu(string $slug, string $template): ?DBHTMLText
    {
        $menu = self::MenustructureMenu($slug);

        if (!$menu instanceof Menu) {
            return null;
        }

        return $menu->renderWith($template);
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
