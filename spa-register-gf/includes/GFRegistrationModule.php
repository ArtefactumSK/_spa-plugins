<?php
declare(strict_types=1);

namespace SpaRegisterGF;

use SpaSystem\Settings\RegistrationModuleInterface;

class GFRegistrationModule implements RegistrationModuleInterface
{
    public function getKey(): string
    {
        return 'gravity_forms';
    }

    public function getName(): string
    {
        return 'Gravity Forms Registration';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function isAvailable(): bool
    {
        return class_exists('GFForms');
    }
}
