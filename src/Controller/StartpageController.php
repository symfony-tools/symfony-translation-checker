<?php

declare(strict_types=1);


namespace App\Controller;

use App\Exception\InvalidVersionException;
use App\Service\DataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class StartpageController extends AbstractController
{
    private DataProvider $provider;

    public function __construct(DataProvider $provider)
    {
        $this->provider = $provider;
    }

    public function index($version)
    {
        try {
            $data = $this->provider->getData($version);
        } catch (InvalidVersionException $e) {
            return new Response('Not Found', 404);
        }

        $versionsAvailable = $this->provider->getAvailableVersions();

        return $this->render('startpage.html.twig', [
            'version' => $version,
            'data' => $data,
            'availableVersions' => $versionsAvailable,
        ]);
    }
}
