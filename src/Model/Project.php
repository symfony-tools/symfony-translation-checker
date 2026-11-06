<?php

declare(strict_types=1);

namespace App\Model;

final class Project
{
    /**
     * @param string|null $branch null means the lowest supported Symfony version
     * @param string      $page   the page of the website, relative to its root
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $org,
        public readonly string $repo,
        public readonly ?string $branch,
        public readonly string $page,
    ) {
    }

    public static function fromName(string $name): self
    {
        return match ($name) {
            'symfony' => new self('symfony', 'Symfony', 'symfony', 'symfony', null, ''),
            'ux' => new self('ux', 'Symfony UX', 'symfony', 'ux', '3.x', 'ux.html'),
            default => throw new \InvalidArgumentException(sprintf('Unknown project "%s", expected one of "symfony", "ux".', $name)),
        };
    }
}
