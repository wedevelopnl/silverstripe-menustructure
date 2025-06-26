<?php

namespace WeDevelop\Menustructure\Model;

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
use SilverStripe\Security\Member;
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
 * @method MenuItem ParentItem()
 * @method HasManyList Items()
 * @method SiteTree LinkedPage()
 */
class MenuItem extends DataObject
{
    private const LINK_TYPE_PAGE = 'page';

    private const LINK_TYPE_URL = 'url';

    private const LINK_TYPE_FILE = 'file';
    
    private const LINK_TYPE_NO_LINK = 'no-link';

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

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function ($fields) {
            $fields->removeByName([
                'Sort',
                'ParentItemID',
                'MenuID',
            ]);

            $fields->replaceField('LinkType', DropdownField::create('LinkType', $this->fieldLabel('LinkType'), $this->getLinkTypes()));
            $fields->replaceField('LinkedPageID', $linkedPageWrapper = Wrapper::create(TreeDropdownField::create('LinkedPageID', $this->fieldLabel('LinkedPage'), SiteTree::class)));

            $linkedPageWrapper->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_PAGE);
            $fields->dataFieldByName('File')->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_FILE);
            $fields->dataFieldByName('Url')->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_URL);
            $fields->dataFieldByName('OpenInNewWindow')->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_PAGE)->orIf('LinkType')->isEqualTo(self::LINK_TYPE_URL)->orIf('LinkType')->isEqualTo(self::LINK_TYPE_FILE);

            if (self::config()->enable_query_string) {
                /** @var TextField $queryStringField */
                $queryStringField = $fields->dataFieldByName('QueryString');
                $queryStringField->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_PAGE);
                $queryStringField->setDescription('Example: <code>foo=bar&john=doe</code>');
                $fields->addFieldToTab('Root.Main', $queryStringField);
            } else {
                $fields->removeByName('QueryString');
            }

            if (self::config()->enable_page_anchor) {
                $fields->dataFieldByName('AnchorText')->displayIf('LinkType')->isEqualTo(self::LINK_TYPE_PAGE);
                $fields->addFieldToTab('Root.Main', $fields->dataFieldByName('AnchorText'));
            } else {
                $fields->removeByName('AnchorText');
            }

            $fields->addFieldToTab('Root.Main', $fields->dataFieldByName('OpenInNewWindow'));

            $fields->removeByName('Items');
            if ($this->exists()) {
                $gridConfig = new GridFieldConfig_RelationEditor();
                $gridConfig->addComponent(GridFieldOrderableRows::create());
                $fields->addFieldToTab('Root.Main', GridField::create('Items', 'Items', $this->Items(), $gridConfig));
            }
        });

        return parent::getCMSFields();
    }

    private function getLinkTypes(): array
    {
        $linkTypes = self::$link_types;

        $this->extend('updateLinkTypes', $linkTypes);

        return $linkTypes;
    }

    public function getLink(): string
    {
        $link = match ($this->LinkType) {
            'url' => $this->Url,
            'page' => $this->LinkedPage()->Link(),
            'file' => $link = $this->File()->Link(),
            default => ''
        };

        if ($this->LinkType === self::LINK_TYPE_PAGE && self::config()->enable_query_string && $this->QueryString) {
            $link = sprintf('%s?%s', $link, $this->QueryString);
        }

        if ($this->LinkType === self::LINK_TYPE_PAGE && self::config()->enable_page_anchor && $this->AnchorText) {
            $link = sprintf('%s#%s', $link, $this->AnchorText);
        }

        $this->extend('updateLink', $link);

        return $link ?? '';
    }

    public function LinkingMode(): string
    {
        if ($this->LinkType === self::LINK_TYPE_PAGE) {
            return Controller::curr()->ID === $this->LinkedPageID ? 'current' : 'link';
        }

        return 'link';
    }

    /**
     * @param null|int|Member $member
     */
    public function canCreate($member = null, $context = []): bool
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_' . MenusAdmin::class)) {
            return true;
        }

        return parent::canCreate($member, $context);
    }

    /**
     * @param null|int|Member $member
     */
    public function canView($member = null): bool
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_' . MenusAdmin::class)) {
            return true;
        }

        return parent::canView($member);
    }

    /**
     * @param null|int|Member $member
     */
    public function canEdit($member = null): bool
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_' . MenusAdmin::class)) {
            return true;
        }

        return parent::canEdit($member);
    }

    /**
     * @param null|int|Member $member
     */
    public function canDelete($member = null): bool
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_' . MenusAdmin::class)) {
            return true;
        }

        return parent::canDelete($member);
    }

    public function onBeforeDelete(): void
    {
        parent::onBeforeDelete();

        /** @var Menu $menu */
        $menu = $this->Menu();

        /** @var MenuItem $parentItem */
        $parentItem = $this->ParentItem();

        $now = new \DateTime();

        if ($menu && $menu->exists()) {
            $menu->LastEdited = $now->format('Y-m-d H:i:s');
            $menu->write();
        }

        if ($parentItem && $parentItem->exists()) {
            $parentItem->LastEdited = $now->format('Y-m-d H:i:s');
            $parentItem->write();
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        /** @var Menu $menu */
        $menu = $this->Menu();

        /** @var MenuItem $parentItem */
        $parentItem = $this->ParentItem();

        if ($menu && $menu->exists()) {
            $menu->LastEdited = $this->LastEdited;
            $menu->write();
        }

        if ($parentItem && $parentItem->exists()) {
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
