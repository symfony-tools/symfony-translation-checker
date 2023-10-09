<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\InvalidVersionException;
use App\Service\DataProvider;
use App\Service\PathProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
final class StartpageController
{
    public function __construct(
        private PathProvider $pathProvider,
        private DataProvider $dataProvider
    ) {
    }

    #[Route(
        path:'/{version}',
        name: 'startpage',
        methods: ['GET'],
        defaults: [
            'version' => '%pr_target_branch%'
        ],
    )]
    public function __invoke(string $version): Response
    {
        if (str_ends_with($version, '.html')) {
            $version = substr($version, 0, -5);
        }

        try {
            $data = $this->dataProvider->getData($version);
        } catch (InvalidVersionException) {
            return new Response('Not Found', 404);
        }

        $componentPaths = [];
        foreach ($this->pathProvider->getComponentPaths() as $code => $path) {
            $componentPaths[$this->pathProvider->getComponentName($code)] = $path;
        }

        return $this->render('startpage.html.twig', [
            'version' => $version,
            'data' => $data,
            'availableVersions' => $this->dataProvider->getAvailableVersions(),
            'componentPaths' => $componentPaths,
        ]);
    }
}
