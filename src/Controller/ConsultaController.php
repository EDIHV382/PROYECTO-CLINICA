<?php

namespace App\Controller;

use App\Entity\Consulta;
use App\Entity\Paciente;
use App\Form\ConsultaType;
use App\Repository\ConsultaRepository;
use App\Repository\PacienteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/consultas')]
class ConsultaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator
    ) {}

    #[Route('', name: 'app_consulta_index', methods: ['GET'])]
    public function index(Request $request, PacienteRepository $pacienteRepo): Response
    {
        $queryBuilder = $pacienteRepo->createQueryBuilder('p')->orderBy('p.nombre', 'ASC');
        $pagination = $this->paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 12);

        return $this->render('consulta/index.html.twig', [
            'pacientes' => $pagination,
        ]);
    }

    #[Route('/historial/{id}', name: 'app_consulta_historial', methods: ['GET'])]
    public function historial(Paciente $paciente, ConsultaRepository $consultaRepo): JsonResponse
    {
        $consultas = $consultaRepo->findBy(['paciente' => $paciente], ['fechaConsulta' => 'DESC']);
        
        $formRegistrar = $this->createForm(ConsultaType::class, null, [
            'action' => $this->generateUrl('app_consulta_crear', ['pacienteId' => $paciente->getId()])
        ]);

        $formsEditar = [];
        foreach ($consultas as $consulta) {
            $formsEditar[$consulta->getId()] = $this->createForm(ConsultaType::class, $consulta, [
                'action' => $this->generateUrl('app_consulta_editar', ['id' => $consulta->getId()])
            ])->createView();
        }

        $html = $this->renderView('consulta/_historialContent.html.twig', [
            'paciente' => $paciente,
            'consultas' => $consultas,
            'formRegistrar' => $formRegistrar->createView(),
            'formsEditar' => $formsEditar
        ]);

        return new JsonResponse(['success' => true, 'html' => $html]);
    }

    #[Route('/crear/{pacienteId}', name: 'app_consulta_crear', methods: ['POST'])]
    public function crear(Request $request, int $pacienteId): JsonResponse
    {
        $paciente = $this->em->getRepository(Paciente::class)->find($pacienteId);
        if (!$paciente) { return new JsonResponse(['success' => false, 'errors' => ['Paciente no encontrado']], 404); }
        
        $consulta = new Consulta();
        $consulta->setPaciente($paciente);
        $form = $this->createForm(ConsultaType::class, $consulta);
        return $this->handleAjaxForm($request, $form, 'Consulta registrada con éxito.');
    }

    #[Route('/{id}/editar', name: 'app_consulta_editar', methods: ['POST'])]
    public function editar(Request $request, Consulta $consulta): JsonResponse
    {
        $form = $this->createForm(ConsultaType::class, $consulta);
        return $this->handleAjaxForm($request, $form, 'Consulta actualizada con éxito.');
    }

    #[Route('/{id}/eliminar', name: 'app_consulta_eliminar', methods: ['POST'])]
    public function eliminar(Request $request, Consulta $consulta): JsonResponse
    {
        if ($this->isCsrfTokenValid('eliminar_consulta' . $consulta->getId(), $request->request->get('_token'))) {
            $this->em->remove($consulta);
            $this->em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Consulta eliminada.']);
        }
        return new JsonResponse(['success' => false, 'message' => 'Token no válido.'], 400);
    }

    private function handleAjaxForm(Request $request, FormInterface $form, string $successMessage): JsonResponse
    {
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $consulta = $form->getData();
            $this->em->persist($consulta);
            $this->em->flush();

            $formEditarView = $this->createForm(ConsultaType::class, $consulta, [
                'action' => $this->generateUrl('app_consulta_editar', ['id' => $consulta->getId()])
            ])->createView();

            $newRowHtml = $this->renderView('consulta/_consultaFila.html.twig', ['consulta' => $consulta]);
            $newEditModalHtml = $this->renderView('consulta/modals/editarConsulta.html.twig', ['consulta' => $consulta, 'form' => $formEditarView]);

            return new JsonResponse(['success' => true, 'message' => $successMessage, 'newRowHtml' => $newRowHtml, 'newEditModalHtml' => $newEditModalHtml, 'entidadId' => $consulta->getId()]);
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