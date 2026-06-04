<?php

declare(strict_types=1);

namespace Rakoitde\CiAlpineUI\Cells;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CiAlpineUiComponentTest extends CIUnitTestCase
{
    public function testReturnAsHtml(): void
    {
        $ciAlpineUiComponent = new CiAlpineUiComponent();

        $this->assertTrue($ciAlpineUiComponent->returnAsHtml());

        $privateMethod = $this->getPrivateMethodInvoker($ciAlpineUiComponent, 'asJson');
        $privateMethod(['test']);
        $this->assertFalse($ciAlpineUiComponent->returnAsHtml());

        $this->setPrivateProperty($ciAlpineUiComponent, 'returnAsHtml', true);
        $this->assertTrue($this->getPrivateProperty($ciAlpineUiComponent, 'returnAsHtml'));

        $this->setPrivateProperty($ciAlpineUiComponent, 'returnAsHtml', false);
        $this->assertFalse($this->getPrivateProperty($ciAlpineUiComponent, 'returnAsHtml'));
    }

    public function testGetOnlyPublicProperties(): void
    {
        $ciAlpineUiComponent = new CiAlpineUiComponent();

        $this->assertIsArray($ciAlpineUiComponent->getOnlyPublicProperties());
        $this->assertSame([], $ciAlpineUiComponent->getOnlyPublicProperties());
    }

    public function testGetXDataTag(): void
    {
        $ciAlpineUiComponent = new CiAlpineUiComponent();
        $this->assertSame('x-data="[]"', $ciAlpineUiComponent->getXDataTag());
    }

    public function testGetXComponentTag(): void
    {
        $ciAlpineUiComponent = new CiAlpineUiComponent();
        $this->assertSame('x-component="Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponent"', $ciAlpineUiComponent->getXComponentTag());
    }

    public function testGetXTags(): void
    {
        $ciAlpineUiComponent = new CiAlpineUiComponent();
        $this->assertSame('x-data="[]" x-component="Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponent"', $ciAlpineUiComponent->getXTags());
    }
}
