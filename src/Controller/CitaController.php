<?php

namespace App\Controller;

use App\Entity\Cita;
use App\Form\CitaType;
use App\Repository\CitaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('clinica/citas')]
final class CitaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator
    ) {}

    #[Route('', name: 'app_cita_index', methods: ['GET'])]
    public function index(Request $request, CitaRepository $repo): Response
    {
        $formRegistrar = $this->createForm(CitaType::class, new Cita(), [
            'action' => $this->generateUrl('app_cita_crear'),
            'method' => 'POST'
        ]);

        $queryBuilder = $repo->createQueryBuilder('c')
            ->leftJoin('c.paciente', 'p')
            ->addSelect('p')
            ->orderBy('c.fecha', 'DESC');
            
        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );

        $formsEditar = [];
        foreach ($pagination->getItems() as $cita) {
            $formsEditar[$cita->getId()] = $this->createForm(CitaType::class, $cita, [
                'action' => $this->generateUrl('app_cita_editar', ['id' => $cita->getId()])
            ])->createView();
        }

        return $this->render('cita/index.html.twig', [
            'citas' => $pagination,
            'formRegistrar' => $formRegistrar->createView(),
            'formsEditar' => $formsEditar,
        ]);
    }

    #[Route('/crear', name: 'app_cita_crear', methods: ['POST'])]
    public function crear(Request $request): Response
    {
        $cita = new Cita();
        $cita->setEditadoPor($this->getUser());
        $form = $this->createForm(CitaType::class, $cita);
        return $this->handleAjaxForm($request, $form, 'Cita registrada con éxito.');
    }

    #[Route('/{id}/editar', name: 'app_cita_editar', methods: ['POST'])]
    public function editar(Request $request, Cita $cita): Response
    {
        $cita->setEditadoPor($this->getUser());
        $form = $this->createForm(CitaType::class, $cita);
        return $this->handleAjaxForm($request, $form, 'Cita actualizada con éxito.');
    }

    #[Route('/{id}/eliminar', name: 'app_cita_eliminar', methods: ['POST'])]
    public function eliminar(Request $request, Cita $cita): Response
    {
        if ($this->isCsrfTokenValid('eliminar' . $cita->getId(), $request->request->get('_token'))) {
            $this->em->remove($cita);
            $this->em->flush();
            $this->addFlash('success', 'Cita eliminada correctamente.');
        } else {
            $this->addFlash('danger', 'Token CSRF inválido.');
        }
        return $this->redirectToRoute('app_cita_index');
    }

    private function handleAjaxForm(Request $request, FormInterface $form, string $successMessage): JsonResponse
    {
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cita = $form->getData();
            if (!$this->em->contains($cita)) {
                $this->em->persist($cita);
            }
            $this->em->flush();

            $formEditarView = $this->createForm(CitaType::class, $cita, [
                'action' => $this->generateUrl('app_cita_editar', ['id' => $cita->getId()])
            ])->createView();

            return new JsonResponse([
                'success' => true,
                'message' => $successMessage,
                'newRowHtml' => $this->renderView('cita/_citaFila.html.twig', ['cita' => $cita]),
                'newEditModalHtml' => $this->renderView('cita/modals/editarCita.html.twig', [
                    'cita' => $cita,
                    'form' => $formEditarView
                ]),
                'entidadId' => $cita->getId()
            ]);
        }

        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
    }

    private function getFormErrors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) { $errors[] = $error->getMessage(); }
        return $errors;
    }
}