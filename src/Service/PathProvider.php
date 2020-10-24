<?php

declare(strict_types=1);


namespace App\Service;

class PathProvider
{
    public function getComponentPaths(): array
    {
        return [
            'Form Component'=>'src/Symfony/Component/Form/Resources/translations',
            'Security Core'=>'src/Symfony/Component/Security/Core/Resources/translations',
            'Validator Component'=>'src/Symfony/Component/Validator/Resources/translations',
        ];
    }

    public function getSourceNames(): array
    {
        return [
            'Form Component'=>'validators.en.xlf',
            'Security Core'=>'security.en.xlf',
            'Validator Component'=>'validators.en.xlf',
        ];
    }
}
