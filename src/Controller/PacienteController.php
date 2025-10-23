<?php

namespace App\Controller;

use App\Entity\Paciente;
use App\Form\PacienteType;
use App\Repository\PacienteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('clinica/pacientes')]
final class PacienteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator
    ) {}

    #[Route('', name: 'app_paciente_index', methods: ['GET'])]
    public function index(Request $request, PacienteRepository $repo): Response
    {
        $formRegistrar = $this->createForm(PacienteType::class, new Paciente(), [
            'action' => $this->generateUrl('app_paciente_crear'),
            'method' => 'POST'
        ]);

        $queryBuilder = $repo->createQueryBuilder('p')->orderBy('p.nombre', 'ASC');
        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );

        $formsEditar = [];
        foreach ($pagination->getItems() as $paciente) {
            $formsEditar[$paciente->getId()] = $this->createForm(PacienteType::class, $paciente, [
                'action' => $this->generateUrl('app_paciente_editar', ['id' => $paciente->getId()])
            ])->createView();
        }

        return $this->render('paciente/index.html.twig', [
            'pacientes' => $pagination,
            'formRegistrar' => $formRegistrar->createView(),
            'formsEditar' => $formsEditar,
        ]);
    }

    #[Route('/crear', name: 'app_paciente_crear', methods: ['POST'])]
    public function crear(Request $request): Response
    {
        $form = $this->createForm(PacienteType::class, new Paciente());
        return $this->handleAjaxForm($request, $form, 'Paciente registrado con éxito.');
    }

    #[Route('/{id}/editar', name: 'app_paciente_editar', methods: ['POST'])]
    public function editar(Request $request, Paciente $paciente): Response
    {
        $form = $this->createForm(PacienteType::class, $paciente);
        return $this->handleAjaxForm($request, $form, 'Paciente actualizado con éxito.');
    }

    #[Route('/{id}/eliminar', name: 'app_paciente_eliminar', methods: ['POST'])]
    public function eliminar(Request $request, Paciente $paciente): Response
    {
        if ($this->isCsrfTokenValid('eliminar' . $paciente->getId(), $request->request->get('_token'))) {
            $this->em->remove($paciente);
            $this->em->flush();
            $this->addFlash('success', 'Paciente eliminado correctamente.');
        } else {
            $this->addFlash('danger', 'Token CSRF inválido.');
        }

        return $this->redirectToRoute('app_paciente_index');
    }

    private function handleAjaxForm(Request $request, FormInterface $form, string $successMessage): JsonResponse
    {
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $paciente = $form->getData();
            if (!$this->em->contains($paciente)) {
                $this->em->persist($paciente);
            }
            $this->em->flush();

            $formEditarView = $this->createForm(PacienteType::class, $paciente, [
                'action' => $this->generateUrl('app_paciente_editar', ['id' => $paciente->getId()])
            ])->createView();

            return new JsonResponse([
                'success' => true,
                'message' => $successMessage,
                'newRowHtml' => $this->renderView('paciente/_pacienteFila.html.twig', ['paciente' => $paciente]),
                'newEditModalHtml' => $this->renderView('paciente/modals/editarPaciente.html.twig', [
                    'paciente' => $paciente,
                    'form' => $formEditarView
                ]),
                'entidadId' => $paciente->getId()
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