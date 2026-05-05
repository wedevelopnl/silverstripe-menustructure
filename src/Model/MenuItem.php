<?php

declare(strict_types=1);

namespace WeDevelop\Menustructure\Model;

use DateTime;
use Override;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Permission;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UncleCheese\DisplayLogic\Forms\Wrapper;
use WeDevelop\Menustructure\Admin\MenusAdmin;

/**
 * @property string $LinkType
 * @property string $QueryString
 * @property string $AnchorText
 * @property string $Url
 * @property int $LinkedPageID
 * @method File File()
 * @method Menu Menu()
 * @method MenuItem ParentItem()
 * @method HasManyList<MenuItem> Items()
 * @method SiteTree LinkedPage()
 */
class MenuItem extends DataObject
{
    private const string LINK_TYPE_PAGE = 'page';

    private const string LINK_TYPE_URL = 'url';

    private const string LINK_TYPE_FILE = 'file';

    private const string LINK_TYPE_NO_LINK = 'no-link';

    /** @config */
    private static string $table_name = 'Menustructure_MenuItem';

    /** @config */
    private static array $db = [
        'Title' => 'Varchar',
        'LinkType' => 'Varchar',
        'Url' => 'Varchar(255)',
        'OpenInNewWindow' => 'Boolean',
        'Sort' => 'Int',
        'AnchorText' => 'Varchar',
        'QueryString' => 'Varchar',
    ];

    /** @config */
    private static array $has_one = [
        'File' => File::class,
        'Menu' => Menu::class,
        'ParentItem' => MenuItem::class,
        'LinkedPage' => SiteTree::class,
    ];

    /** @config */
    private static array $has_many = [
        'Items' => MenuItem::class,
    ];

    private static array $owns = [
        'File',
    ];

    /** @config */
    private static array $summary_fields = [
        'Title',
        'LinkType',
        'OpenInNewWindow',
    ];

    private static array $link_types = [
        self::LINK_TYPE_PAGE => 'Page',
        self::LINK_TYPE_URL => 'URL',
        self::LINK_TYPE_FILE => 'File',
        self::LINK_TYPE_NO_LINK => 'Not linked',
    ];

    /** @config */
    private static string $default_sort = 'Sort';

    /** @config */
    private static bool $enable_page_anchor = false;

    /** @config */
    private static bool $enable_query_string = false;

    #[Override]
    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields): void {
            $fields->removeByName([
                'Sort',
                'ParentItemID',
                'MenuID',
            ]);

            $fields->replaceField('LinkType', DropdownField::create('LinkType', $this->fieldLabel('LinkType'), $this->getLinkTypes()));
            $fields->replaceField('LinkedPageID', $linkedPageWrapper = Wrapper::create(TreeDropdownField::create('LinkedPageID', $this->fieldLabel('LinkedPage'), SiteTree::class)));

            $linkedPageWrapper->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_PAGE);

            $fileField = $fields->dataFieldByName('File');
            $fileField?->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_FILE);

            $urlField = $fields->dataFieldByName('Url');
            $urlField?->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_URL);

            $openInNewWindow = $fields->dataFieldByName('OpenInNewWindow');
            $openInNewWindow?->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_PAGE)
                ->orIf('LinkType')->isEqualTo(self::LINK_TYPE_URL)
                ->orIf('LinkType')->isEqualTo(self::LINK_TYPE_FILE);

            if (self::config()->get('enable_query_string')) {
                /** @var TextField|null $queryStringField */
                $queryStringField = $fields->dataFieldByName('QueryString');
                if ($queryStringField !== null) {
                    $queryStringField->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_PAGE);
                    $queryStringField->setDescription('Example: <code>foo=bar&john=doe</code>');
                    $fields->addFieldToTab('Root.Main', $queryStringField);
                }
            } else {
                $fields->removeByName('QueryString');
            }

            if (self::config()->get('enable_page_anchor')) {
                $anchorField = $fields->dataFieldByName('AnchorText');
                if ($anchorField !== null) {
                    $anchorField->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_PAGE);
                    $fields->addFieldToTab('Root.Main', $anchorField);
                }
            } else {
                $fields->removeByName('AnchorText');
            }

            if ($openInNewWindow !== null) {
                $fields->addFieldToTab('Root.Main', $openInNewWindow);
            }

            $fields->removeByName('Items');
            if ($this->exists()) {
                $gridConfig = GridFieldConfig_RelationEditor::create();
                $gridConfig->addComponent(GridFieldOrderableRows::create());
                $fields->addFieldToTab('Root.Main', GridField::create('Items', 'Items', $this->Items(), $gridConfig));
            }
        });

        return parent::getCMSFields();
    }

    /**
     * @return array<string, string>
     */
    private function getLinkTypes(): array
    {
        /** @var array<string, string> $linkTypes */
        $linkTypes = self::config()->get('link_types');

        $this->extend('updateLinkTypes', $linkTypes);

        return $linkTypes;
    }

    public function getLink(): string
    {
        $link = match ($this->LinkType) {
            self::LINK_TYPE_URL => $this->Url,
            self::LINK_TYPE_PAGE => $this->LinkedPage()->exists() ? (string)$this->LinkedPage()->Link() : '',
            self::LINK_TYPE_FILE => $this->File()->exists() ? (string)$this->File()->Link() : '',
            default => '',
        };

        if ($this->LinkType === self::LINK_TYPE_PAGE && self::config()->get('enable_query_string') && $this->QueryString) {
            $link = sprintf('%s?%s', $link, $this->QueryString);
        }

        if ($this->LinkType === self::LINK_TYPE_PAGE && self::config()->get('enable_page_anchor') && $this->AnchorText) {
            $link = sprintf('%s#%s', $link, $this->AnchorText);
        }

        $this->extend('updateLink', $link);

        return $link;
    }

    public function LinkingMode(): string
    {
        if ($this->LinkType !== self::LINK_TYPE_PAGE) {
            return 'link';
        }

        $controller = Controller::curr();

        return $controller !== null && $controller->ID === $this->LinkedPageID ? 'current' : 'link';
    }

    /**
     * @param mixed[] $context
     */
    #[Override]
    public function canCreate(mixed $member = null, mixed $context = []): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_' . MenusAdmin::class)) {
            return true;
        }

        return parent::canCreate($member, $context);
    }

    #[Override]
    public function canView(mixed $member = null): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_' . MenusAdmin::class)) {
            return true;
        }

        return parent::canView($member);
    }

    #[Override]
    public function canEdit(mixed $member = null): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_' . MenusAdmin::class)) {
            return true;
        }

        return parent::canEdit($member);
    }

    #[Override]
    public function canDelete(mixed $member = null): bool
    {
        if (Permission::checkMember($member ?? 0, 'CMS_ACCESS_' . MenusAdmin::class)) {
            return true;
        }

        return parent::canDelete($member);
    }

    #[Override]
    public function onBeforeDelete(): void
    {
        parent::onBeforeDelete();

        $menu = $this->Menu();
        $parentItem = $this->ParentItem();

        $now = new DateTime();

        if ($menu->exists()) {
            $menu->LastEdited = $now->format('Y-m-d H:i:s');
            $menu->write();
        }

        if ($parentItem->exists()) {
            $parentItem->LastEdited = $now->format('Y-m-d H:i:s');
            $parentItem->write();
        }
    }

    #[Override]
    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

        $menu = $this->Menu();
        $parentItem = $this->ParentItem();

        if ($menu->exists()) {
            $menu->LastEdited = $this->LastEdited;
            $menu->write();
        }

        if ($parentItem->exists()) {
            $parentItem->LastEdited = $this->LastEdited;
            $parentItem->write();
        }
    }


    public function getLevel(): int
    {
        $menuItem = $this;
        $level = 0;

        while ($menuItem->ParentItem()->exists()) {
            $menuItem = $menuItem->ParentItem();
            $level++;
        }

        return $level;
    }
}
