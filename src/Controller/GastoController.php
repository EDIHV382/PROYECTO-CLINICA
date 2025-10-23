<?php

namespace App\Controller;

use App\Entity\Gasto;
use App\Form\GastoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/gastos-ajax')]
class GastoController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/crear', name: 'app_gasto_crear', methods: ['POST'])]
    public function crear(Request $request): JsonResponse
    {
        $gasto = new Gasto();
        
        $form = $this->createForm(GastoType::class, $gasto);
        return $this->handleAjaxForm($request, $form, 'Gasto registrado con éxito.');
    }
    
    private function handleAjaxForm(Request $request, FormInterface $form, string $successMessage): JsonResponse
    {
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gasto = $form->getData();
            $this->em->persist($gasto);
            $this->em->flush();
            $html = $this->renderView('gasto/_gastoFila.html.twig', ['gasto' => $gasto]);
            return new JsonResponse(['success' => true, 'message' => $successMessage, 'newRowHtml' => $html]);
        }
        
        $errors = [];
        foreach ($form->getErrors(true) as $error) { $errors[] = $error->getMessage(); }
        return new JsonResponse(['success' => false, 'errors' => $errors], 400);
    }
}