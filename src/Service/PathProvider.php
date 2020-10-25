<?php

declare(strict_types=1);

namespace App\Service;

class PathProvider
{
    public function getComponentPaths(): array
    {
        return [
            'Form' => 'src/Symfony/Component/Form/Resources/translations',
            'SecurityCore' => 'src/Symfony/Component/Security/Core/Resources/translations',
            'Validator' => 'src/Symfony/Component/Validator/Resources/translations',
        ];
    }

    public function getSourceNames(string $locale = 'en'): array
    {
        return [
            'Form' => sprintf('validators.%s.xlf', $locale),
            'SecurityCore' => sprintf('security.%s.xlf', $locale),
            'Validator' => sprintf('validators.%s.xlf', $locale),
        ];
    }

    public function getPath(string $component, string $locale): string
    {
        return sprintf('%s/%s', $this->getComponentPaths()[$component], $this->getSourceNames($locale)[$component]);
    }

    public function getComponentName(string $code): string
    {
        return [
            'SecurityCore' => 'Security Core',
            'Validator' => 'Validator Component',
            'Form' => 'Form Component',
        ][$code];
    }
}
