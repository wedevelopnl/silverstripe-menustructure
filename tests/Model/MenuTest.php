<?php

namespace WeDevelop\Menustructure\Tests\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBHTMLText;
use WeDevelop\Menustructure\Model\Menu;
use WeDevelop\Menustructure\Model\MenuItem;

/**
 * @covers \WeDevelop\Menustructure\Model\Menu
 */
class MenuTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/MenuTest.yml';

    private const ADMIN_PERMISSION = 'CMS_ACCESS_WeDevelop\Menustructure\Admin\MenusAdmin';

    public function testSlugIsAutoFilledFromTitleWhenEmpty(): void
    {
        $menu = Menu::create();
        $menu->Title = 'Footer Links';
        $menu->write();

        $this->assertSame('footer-links', $menu->Slug);
    }

    public function testSlugIsNotOverwrittenWhenAlreadySet(): void
    {
        $menu = Menu::create();
        $menu->Title = 'Footer Links';
        $menu->Slug = 'custom-handle';
        $menu->write();

        $this->assertSame('custom-handle', $menu->Slug);
    }

    public function testSlugFiltersSpecialCharacters(): void
    {
        $menu = Menu::create();
        $menu->Title = 'Hôtel & Café (2026)!';
        $menu->write();

        // URLSegmentFilter normalises to lowercase, ascii-folded, hyphenated.
        $this->assertNotEmpty($menu->Slug);
        $this->assertDoesNotMatchRegularExpression('/[ &()!]/', $menu->Slug);
        $this->assertSame(strtolower($menu->Slug), $menu->Slug);
    }

    /**
     * @dataProvider isProtectedProvider
     */
    public function testIsProtectedHonoursProtectedMenusConfig(array $protectedMenus, bool $expected): void
    {
        Config::modify()->set(Menu::class, 'protected_menus', $protectedMenus);

        $menu = $this->objFromFixture(Menu::class, 'protected');

        $this->assertSame($expected, $menu->IsProtected());
    }

    public static function isProtectedProvider(): array
    {
        return [
            "slug appears in protected_menus → IsProtected" => [['main-menu'], true],
            "slug absent from protected_menus → not IsProtected" => [['something-else'], false],
            "empty protected_menus → not IsProtected" => [[], false],
        ];
    }

    public function testCanDeleteIsBlockedWhenMenuIsProtected(): void
    {
        Config::modify()->set(Menu::class, 'protected_menus', ['main-menu']);
        $this->logInWithPermission(self::ADMIN_PERMISSION);

        $menu = $this->objFromFixture(Menu::class, 'protected');

        $this->assertFalse($menu->canDelete());
    }

    /**
     * @dataProvider permissionMatrix
     */
    public function testCanMethodHonoursAdminPermission(string $permission, string $method, bool $expected): void
    {
        // Keep canDelete out of the protected-menu gate; the protected case is
        // covered by testCanDeleteIsBlockedWhenMenuIsProtected.
        Config::modify()->set(Menu::class, 'protected_menus', []);
        $this->logInWithPermission($permission);

        $menu = $this->objFromFixture(Menu::class, 'primary');

        $this->assertSame($expected, $menu->$method());
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
            'canDelete allowed with admin permission when not protected' => [self::ADMIN_PERMISSION, 'canDelete', true],
            'canDelete falls through to parent without admin permission when not protected' => ['SOME_OTHER_PERMISSION', 'canDelete', false],
        ];
    }

    public function testMenustructureMenuReturnsMatchingMenuBySlug(): void
    {
        $menu = Menu::MenustructureMenu('primary');

        $this->assertInstanceOf(Menu::class, $menu);
        $this->assertSame('Primary navigation', $menu->Title);
    }

    public function testMenustructureMenuReturnsNullForUnknownSlug(): void
    {
        $this->assertNull(Menu::MenustructureMenu('does-not-exist'));
    }

    public function testViewableMenustructureMenuRendersWithGivenTemplate(): void
    {
        $rendered = Menu::ViewableMenustructureMenu('primary', 'WeDevelop/Menustructure/Model/Menu');

        $this->assertNotNull($rendered);
        $this->assertStringContainsString('<nav>', (string)$rendered);
        $this->assertStringContainsString('Home', (string)$rendered);
    }

    public function testViewableMenustructureMenuReturnsNullForUnknownSlug(): void
    {
        $this->assertNull(Menu::ViewableMenustructureMenu('does-not-exist', 'WeDevelop/Menustructure/Model/Menu'));
    }

    public function testTemplateGlobalsExposeBothHelpers(): void
    {
        $globals = Menu::get_template_global_variables();

        $this->assertContains('MenustructureMenu', $globals);
        $this->assertContains('ViewableMenustructureMenu', $globals);
    }

    public function testForTemplateRendersBundledTemplateWithItems(): void
    {
        $menu = $this->objFromFixture(Menu::class, 'primary');

        $rendered = $menu->forTemplate();

        $this->assertInstanceOf(DBHTMLText::class, $rendered);
        $html = (string)$rendered;
        $this->assertStringContainsString('<nav>', $html);
        $this->assertStringContainsString('Home', $html);
        $this->assertStringContainsString('About', $html);
    }

    public function testOnBeforeDeleteCascadesToChildItems(): void
    {
        $menu = $this->objFromFixture(Menu::class, 'primary');
        $itemIDs = $menu->Items()->column('ID');
        $this->assertNotEmpty($itemIDs, 'Fixture sanity: primary menu has items');

        $menu->delete();

        $this->assertSame(0, MenuItem::get()->filter('ID', $itemIDs)->count());
    }
}
