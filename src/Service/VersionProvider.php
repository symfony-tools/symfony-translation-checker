<?php

namespace App\Service;

class VersionProvider
{
    public function getLowestSupportedVersion(): string
    {
        $releases = $this->getReleases();
        $versions = $releases['supported_versions'];
        usort($versions, fn ($a, $b) => version_compare($a, $b));

        return $versions[0];
    }

    public function getSupportedVersions(): array
    {
        $releases = $this->getReleases();

        return $releases['supported_versions'];
    }

    private function getReleases()
    {
        $response = file_get_contents('https://symfony.com/releases.json');
        $data = json_decode($response, true, flags: \JSON_THROW_ON_ERROR);

        return $data;
    }
}
