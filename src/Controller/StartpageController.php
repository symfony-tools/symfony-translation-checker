<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\InvalidVersionException;
use App\Service\DataProvider;
use App\Service\PathProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class StartpageController extends AbstractController
{
    private PathProvider $pathProvider;
    private DataProvider $dataProvider;

    public function __construct(PathProvider $pathProvider, DataProvider $dataProvider)
    {
        $this->pathProvider = $pathProvider;
        $this->dataProvider = $dataProvider;
    }

    public function index($version): Response
    {
        if ('.html' === substr($version, -5)) {
            $version = substr($version, 0, -5);
        }

        try {
            $data = $this->dataProvider->getData($version);
        } catch (InvalidVersionException $e) {
            return new Response('Not Found', 404);
        }

        $versionsAvailable = $this->dataProvider->getAvailableVersions();

        $componentPaths = [];
        foreach ($this->pathProvider->getComponentPaths() as $code => $path) {
            $componentPaths[$this->pathProvider->getComponentName($code)] = $path;
        }

        return $this->render('startpage.html.twig', [
            'version' => $version,
            'data' => $data,
            'availableVersions' => $versionsAvailable,
            'componentPaths' => $componentPaths,
        ]);
    }
}
