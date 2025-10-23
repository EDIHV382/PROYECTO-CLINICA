<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(): Response
    {
        // Fecha de hoy
        $hoy = new \DateTime('now', new \DateTimeZone('America/Mexico_City'));

        // Pasamos arrays vacíos para que Twig no falle
        return $this->render('home/index.html.twig', [
            'hoy' => $hoy,
            'pacientes' => [],
            'citas_hoy' => [],
            'horasDisponibles' => [],
        ]);
    }
}
