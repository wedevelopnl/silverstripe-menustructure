<?php

namespace WeDevelop\Menustructure\Tests\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use WeDevelop\Menustructure\Model\Menu;
use WeDevelop\Menustructure\Model\MenuItem;

#[CoversClass(MenuItem::class)]
class MenuItemTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/MenuItemTest.yml';

    private const ADMIN_PERMISSION = 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin';

    private const PAST_DATETIME = '2020-01-01 00:00:00';

    public function testGetLinkReturnsUrlForUrlType(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'topLevel');

        $this->assertSame('https://example.com', $item->getLink());
    }

    public function testGetLinkReturnsLinkedPageLinkForPageType(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'pageLink');
        $page = $this->objFromFixture(SiteTree::class, 'homepage');

        $this->assertSame($page->Link(), $item->getLink());
    }

    public function testGetLinkReturnsNullForNoLinkType(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'emptyLink');

        $this->assertNull($item->getLink());
    }

    public function testGetLinkReturnsNullForBreakpointType(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'breakpointLink');

        $this->assertNull($item->getLink());
    }

    public function testGetLinkReturnsFileLinkForFileType(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'fileLink');
        $file = $item->File();

        // Whether File::exists() is true depends on test-env asset state — fixture
        // rows alone don't physically write bytes. Mirror both branches of the
        // match so the test pins down the contract regardless of that state:
        // exists() → string Link(); missing → null.
        $expected = $file->exists() ? (string)$file->Link() : null;
        $this->assertSame($expected, $item->getLink());
    }

    public function testGetLinkAppendsQueryStringWhenEnabledForPageType(): void
    {
        Config::modify()->set(MenuItem::class, 'enable_query_string', true);
        $item = $this->objFromFixture(MenuItem::class, 'pageLink');
        $page = $this->objFromFixture(SiteTree::class, 'homepage');

        $this->assertSame($page->Link() . '?utm=home', $item->getLink());
    }

    public function testGetLinkOmitsQueryStringWhenConfigDisabled(): void
    {
        Config::modify()->set(MenuItem::class, 'enable_query_string', false);
        $item = $this->objFromFixture(MenuItem::class, 'pageLink');
        $page = $this->objFromFixture(SiteTree::class, 'homepage');

        $this->assertSame($page->Link(), $item->getLink());
    }

    public function testGetLinkOmitsQueryStringWhenLinkTypeIsNotPage(): void
    {
        Config::modify()->set(MenuItem::class, 'enable_query_string', true);

        // Force a QueryString onto a URL-type item to prove the gate is on LinkType.
        $item = $this->objFromFixture(MenuItem::class, 'topLevel');
        $item->QueryString = 'utm=foo';

        $this->assertSame('https://example.com', $item->getLink());
    }

    public function testGetLinkAppendsAnchorWhenEnabledForPageType(): void
    {
        Config::modify()->set(MenuItem::class, 'enable_page_anchor', true);
        $item = $this->objFromFixture(MenuItem::class, 'pageLink');
        $page = $this->objFromFixture(SiteTree::class, 'homepage');

        $this->assertSame($page->Link() . '#top', $item->getLink());
    }

    public function testGetLinkOmitsAnchorWhenConfigDisabled(): void
    {
        Config::modify()->set(MenuItem::class, 'enable_page_anchor', false);
        $item = $this->objFromFixture(MenuItem::class, 'pageLink');
        $page = $this->objFromFixture(SiteTree::class, 'homepage');

        $this->assertSame($page->Link(), $item->getLink());
    }

    public function testGetLinkAppendsBothQueryStringAndAnchorWhenEnabled(): void
    {
        Config::modify()->set(MenuItem::class, 'enable_query_string', true);
        Config::modify()->set(MenuItem::class, 'enable_page_anchor', true);
        $item = $this->objFromFixture(MenuItem::class, 'pageLink');
        $page = $this->objFromFixture(SiteTree::class, 'homepage');

        $this->assertSame($page->Link() . '?utm=home#top', $item->getLink());
    }

    public function testLinkingModeReturnsCurrentWhenCurrentControllerIDMatchesLinkedPage(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'pageLink');

        $this->withCurrentController($item->LinkedPageID, function () use ($item) {
            $this->assertSame('current', $item->LinkingMode());
        });
    }

    public function testLinkingModeReturnsLinkWhenCurrentControllerIDDoesNotMatchLinkedPage(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'pageLink');

        $this->withCurrentController($item->LinkedPageID + 999, function () use ($item) {
            $this->assertSame('link', $item->LinkingMode());
        });
    }

    public function testLinkingModeReturnsLinkForNonPageTypeWithoutTouchingCurrentController(): void
    {
        // The non-page branch must short-circuit before reading Controller::curr().
        // Asserting it works with no controller pushed pins down that contract —
        // a refactor that removed the early return would surface here.
        $item = $this->objFromFixture(MenuItem::class, 'topLevel');

        $this->assertSame('link', $item->LinkingMode());
    }

    public function testGetLevelIsZeroForTopLevelItem(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'topLevel');

        $this->assertSame(0, $item->getLevel());
    }

    public function testGetLevelCountsParentChainDepth(): void
    {
        $child = $this->objFromFixture(MenuItem::class, 'childLevel');
        $grandchild = $this->objFromFixture(MenuItem::class, 'grandchildLevel');

        $this->assertSame(1, $child->getLevel());
        $this->assertSame(2, $grandchild->getLevel());
    }

    public function testWritingItemBumpsOwningMenuLastEdited(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'topLevel');
        $menu = $this->objFromFixture(Menu::class, 'default');

        $this->stampLastEditedBackward(Menu::class, $menu->ID);
        $beforeMenu = Menu::get()->byID($menu->ID);
        $this->assertSame(self::PAST_DATETIME, $beforeMenu->LastEdited);

        $item->Title = 'Updated title';
        $item->write();

        $afterMenu = Menu::get()->byID($menu->ID);
        $this->assertNotSame(self::PAST_DATETIME, $afterMenu->LastEdited);
    }

    public function testWritingItemBumpsParentItemLastEdited(): void
    {
        $child = $this->objFromFixture(MenuItem::class, 'childLevel');
        $parent = $this->objFromFixture(MenuItem::class, 'topLevel');

        $this->stampLastEditedBackward(MenuItem::class, $parent->ID);
        $beforeParent = MenuItem::get()->byID($parent->ID);
        $this->assertSame(self::PAST_DATETIME, $beforeParent->LastEdited);

        $child->Title = 'Updated title';
        $child->write();

        $afterParent = MenuItem::get()->byID($parent->ID);
        $this->assertNotSame(self::PAST_DATETIME, $afterParent->LastEdited);
    }

    public function testDeletingItemBumpsOwningMenuLastEdited(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'emptyLink');
        $menu = $this->objFromFixture(Menu::class, 'default');

        $this->stampLastEditedBackward(Menu::class, $menu->ID);
        $beforeMenu = Menu::get()->byID($menu->ID);
        $this->assertSame(self::PAST_DATETIME, $beforeMenu->LastEdited);

        $item->delete();

        $afterMenu = Menu::get()->byID($menu->ID);
        $this->assertNotSame(self::PAST_DATETIME, $afterMenu->LastEdited);
    }

    public function testDeletingItemBumpsParentItemLastEdited(): void
    {
        $child = $this->objFromFixture(MenuItem::class, 'grandchildLevel');
        $parent = $this->objFromFixture(MenuItem::class, 'childLevel');

        $this->stampLastEditedBackward(MenuItem::class, $parent->ID);
        $beforeParent = MenuItem::get()->byID($parent->ID);
        $this->assertSame(self::PAST_DATETIME, $beforeParent->LastEdited);

        $child->delete();

        $afterParent = MenuItem::get()->byID($parent->ID);
        $this->assertNotSame(self::PAST_DATETIME, $afterParent->LastEdited);
    }

    public function testDeleteCascadesToDescendantItems(): void
    {
        // Fixture chain: topLevel → childLevel → grandchildLevel.
        // Deleting the middle node must take its descendants with it (down) but
        // leave the parent and unrelated siblings intact (no upward / sideways
        // cascade). Removing $cascade_deletes from MenuItem would orphan
        // grandchildLevel — that's the regression this test pins down.
        $parent = $this->objFromFixture(MenuItem::class, 'topLevel');
        $middle = $this->objFromFixture(MenuItem::class, 'childLevel');
        $leaf = $this->objFromFixture(MenuItem::class, 'grandchildLevel');
        $sibling = $this->objFromFixture(MenuItem::class, 'pageLink');

        $middle->delete();

        $this->assertNull(MenuItem::get()->byID($middle->ID), 'Deleted item itself is gone');
        $this->assertNull(MenuItem::get()->byID($leaf->ID), 'Descendant is cascade-deleted');
        $this->assertNotNull(MenuItem::get()->byID($parent->ID), 'Parent is not touched by downward cascade');
        $this->assertNotNull(MenuItem::get()->byID($sibling->ID), 'Unrelated siblings are not touched');
    }

    #[DataProvider('permissionMatrix')]
    public function testCanMethodHonoursAdminPermission(string $permission, string $method, bool $expected): void
    {
        $this->logInWithPermission($permission);

        $item = $this->objFromFixture(MenuItem::class, 'topLevel');

        $this->assertSame($expected, $item->$method());
    }

    public static function permissionMatrix(): array
    {
        return [
            'canCreate allowed with admin permission' => [self::ADMIN_PERMISSION, 'canCreate', true],
            'canCreate denied without admin permission' => ['SOME_OTHER_PERMISSION', 'canCreate', false],
            'canView allowed with admin permission' => [self::ADMIN_PERMISSION, 'canView', true],
            'canView falls through to parent without admin permission' => ['SOME_OTHER_PERMISSION', 'canView', false],
            'canEdit allowed with admin permission' => [self::ADMIN_PERMISSION, 'canEdit', true],
            'canEdit falls through to parent without admin permission' => ['SOME_OTHER_PERMISSION', 'canEdit', false],
            'canDelete allowed with admin permission' => [self::ADMIN_PERMISSION, 'canDelete', true],
            'canDelete falls through to parent without admin permission' => ['SOME_OTHER_PERMISSION', 'canDelete', false],
        ];
    }

    #[DataProvider('permissionMethods')]
    public function testCanMethodHonoursExplicitMemberOverSessionUser(string $method): void
    {
        // The can* methods take a $member argument and must judge that member,
        // not whoever happens to be logged in. Both directions are asserted:
        // an explicit privileged member grants access to an unprivileged
        // session, and an explicit unprivileged member is refused even while
        // an admin holds the session.
        $privileged = $this->createMemberWithPermission(self::ADMIN_PERMISSION);
        $unprivileged = $this->createMemberWithPermission('SOME_OTHER_PERMISSION');

        $item = $this->objFromFixture(MenuItem::class, 'topLevel');

        $this->logInWithPermission('SOME_OTHER_PERMISSION');
        $this->assertTrue($item->$method($privileged), 'Explicit privileged member is honoured');

        $this->logInWithPermission(self::ADMIN_PERMISSION);
        $this->assertFalse($item->$method($unprivileged), 'Explicit unprivileged member is refused');
    }

    public static function permissionMethods(): array
    {
        return [
            'canCreate' => ['canCreate'],
            'canView' => ['canView'],
            'canEdit' => ['canEdit'],
            'canDelete' => ['canDelete'],
        ];
    }

    public function testGetLinkReturnsNullForUnrecognisedLinkType(): void
    {
        // Rows written before a LinkType case existed (or with an empty column)
        // leave tryFrom() returning null. getLink() must fall through to null
        // rather than blowing up on an unhandled match.
        $item = $this->objFromFixture(MenuItem::class, 'topLevel');
        $item->setField('LinkType', 'legacy-value');

        $this->assertNull($item->getLink());
    }

    public function testLinkingModeReturnsLinkForNonPageTypeWithStaleLinkedPage(): void
    {
        // Switching an item from page to url leaves LinkedPageID populated. The
        // non-page branch must win on LinkType alone — if it instead fell through
        // to the LinkedPageID comparison, this item would light up as 'current'
        // while the user is on the stale page.
        $item = $this->objFromFixture(MenuItem::class, 'topLevel');
        $page = $this->objFromFixture(SiteTree::class, 'homepage');
        $item->LinkedPageID = $page->ID;

        $this->withCurrentController($page->ID, function () use ($item) {
            $this->assertSame('link', $item->LinkingMode());
        });
    }

    /**
     * Sets LastEdited to a known past timestamp via raw SQL so that we can
     * detect cascade-write/delete propagation without relying on sleep().
     */
    private function stampLastEditedBackward(string $class, int $id): void
    {
        $tableName = Config::inst()->get($class, 'table_name');
        DB::prepared_query(
            sprintf('UPDATE "%s" SET "LastEdited" = ? WHERE "ID" = ?', $tableName),
            [self::PAST_DATETIME, $id]
        );
    }

    /**
     * Pushes a real Controller onto the stack with a real request and session
     * so that `Controller::curr()->ID` resolves to the given $id while $body
     * runs. This is the same primitive the framework uses at runtime — we're
     * not mocking, we're configuring framework state for the test.
     *
     * pushCurrent() needs the controller's request to expose a session, so we
     * attach one. popCurrent() runs in finally to keep the stack balanced even
     * when the assertion throws.
     */
    private function withCurrentController(int $id, callable $body): void
    {
        $request = new HTTPRequest('GET', '/');
        $request->setSession(new Session([]));

        $controller = Controller::create();
        $controller->setRequest($request);
        $controller->ID = $id;

        $controller->pushCurrent();
        try {
            $body();
        } finally {
            $controller->popCurrent();
        }
    }
}
