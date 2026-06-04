<?php

declare(strict_types=1);

namespace Rakoitde\CiAlpineUI\Config;

/**
 * Registers the make:uicomponent spark generator into CI4's Generators config.
 */
class Registrar
{
    /**
     * @return array<string, mixed>
     */
    public static function Generators(): array
    {
        return [
            'views' => [
                'make:uicomponent' => [
                    'class' => 'Rakoitde\CiAlpineUI\Commands\Views\uicomponent.tpl.php',
                    'view'  => 'Rakoitde\CiAlpineUI\Commands\Views\uicomponent_view.tpl.php',
                ],
            ],
        ];
    }
}
