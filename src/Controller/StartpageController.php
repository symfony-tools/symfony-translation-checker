<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\InvalidVersionException;
use App\Model\Project;
use App\Service\DataProvider;
use App\Service\PathProvider;
use App\Service\VersionProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class StartpageController extends AbstractController
{
    public function __construct(
        private PathProvider $pathProvider,
        private DataProvider $dataProvider,
        private VersionProvider $versionProvider
    ) {
    }

    public function index(): Response
    {
        return $this->renderProject(Project::fromName('symfony'));
    }

    public function ux(): Response
    {
        return $this->renderProject(Project::fromName('ux'));
    }

    private function renderProject(Project $project): Response
    {
        $version = $project->branch ?? $this->versionProvider->getLowestSupportedVersion();
        try {
            $data = $this->dataProvider->getData($version, $project);
        } catch (InvalidVersionException) {
            return new Response('Not Found', 404);
        }

        $componentPaths = [];
        if ('symfony' === $project->name) {
            foreach ($this->pathProvider->getComponentPaths() as $code => $path) {
                $componentPaths[$this->pathProvider->getComponentName($code)] = $path;
            }
        } else {
            foreach ($data['English (en)'] ?? [] as $missingTranslation) {
                $componentPaths[$missingTranslation->getComponentName()] = \dirname($missingTranslation->getFile());
            }
        }

        return $this->render('startpage.html.twig', [
            'project' => $project,
            'title' => $project->title,
            'version' => $version,
            'data' => $data,
            'availableVersions' => $this->dataProvider->getAvailableVersions(),
            'componentPaths' => $componentPaths,
        ]);
    }
}
