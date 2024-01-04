<?php

namespace App\Service;

final class VersionProvider
{
    public function getLowestSupportedVersion(): string
    {
        $versions = $this->getSupportedVersions();
        usort($versions, fn ($a, $b) => version_compare($a, $b));

        return $versions[0];
    }

    public function getSupportedVersions(): array
    {
        $releases = $this->getReleases();

        return $releases['supported_versions'];
    }

    private function getReleases(): array
    {
        $response = file_get_contents('https://symfony.com/releases.json');

        return json_decode($response, true, flags: \JSON_THROW_ON_ERROR);
    }
}
