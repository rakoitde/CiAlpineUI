<?php

declare(strict_types=1);

namespace Rakoitde\CiAlpineUI\Cells;

/**
 * Fixture component used exclusively in controller and component tests.
 * Covers the full range of property types and access-control scenarios.
 */
class CiAlpineUiComponentTestCell extends CiAlpineUiComponent
{
    public bool $canAccess   = false;
    public bool $boolVal     = false;
    public int $intVal       = 0;
    public float $floatVal   = 0.00;
    public string $stringVal = '';

    /**
     * @var list<mixed>
     */
    public array $arrayVal = [];

    public function testAsHtml(): void
    {
    }

    public function testAsJson(): void
    {
        $this->asJson();
    }

    public function testAsJsonWithProperties(): void
    {
        $this->asJson(['canAccess']);
    }

    public function canAccessTestNoPermission(): bool
    {
        return false;
    }

    public function testNoPermission(): void
    {
    }
}
