<?php

declare(strict_types=1);

namespace Rakoitde\CiAlpineUI\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * This class describes the default form configuration.
 */
class CiAlpineUI extends BaseConfig
{
    /**
     * Encrypt component names
     *
     * @var boolean
     */
    public bool $encrypt = true;
}
