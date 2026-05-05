<?php

namespace WeDevelop\Menustructure\Tests\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use WeDevelop\Menustructure\Model\Menu;
use WeDevelop\Menustructure\Model\MenuItem;

/**
 * @covers \WeDevelop\Menustructure\Model\MenuItem
 */
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

    public function testGetLinkReturnsEmptyStringForNoLinkType(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'emptyLink');

        $this->assertSame('', $item->getLink());
    }

    public function testGetLinkReturnsFileLinkForFileType(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'fileLink');

        // getLink() coerces null → '' via the trailing `?? ''`, so normalise the
        // expected value the same way to keep the test independent of whether
        // the asset has a published Link() in the test environment.
        $this->assertSame((string)$item->File()->Link(), $item->getLink());
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

    /**
     * @dataProvider permissionMatrix
     */
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
