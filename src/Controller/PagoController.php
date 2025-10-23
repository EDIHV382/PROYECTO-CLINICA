<?php

namespace App\Controller;

use App\Entity\Gasto;
use App\Entity\Pago;
use App\Form\GastoType;
use App\Form\PagoType;
use App\Repository\GastoRepository;
use App\Repository\PagoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/pagos')]
final class PagoController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator
    ) {}

    #[Route('', name: 'app_pago_index', methods: ['GET'])]
    public function index(Request $request, PagoRepository $pagoRepo, GastoRepository $gastoRepo): Response
    {
        $fechaFiltro = $request->query->get('fecha');
        $mostrarTodos = $request->query->get('todos');
        $fechaParaVista = null;

        $pagosQueryBuilder = $pagoRepo->createQueryBuilder('p')
            ->leftJoin('p.paciente', 'pa')->addSelect('pa')
            ->leftJoin('p.registradoPor', 'u')->addSelect('u')
            ->orderBy('p.fecha_pago', 'DESC');
            
        $gastosQueryBuilder = $gastoRepo->createQueryBuilder('g')
            ->orderBy('g.fecha', 'DESC');

        if (!$mostrarTodos) {
            $fecha = $fechaFiltro ? new \DateTime($fechaFiltro) : new \DateTime('now', new \DateTimeZone('America/Mexico_City'));
            $fechaParaVista = $fecha->format('Y-m-d');
            $inicioDia = (clone $fecha)->setTime(0, 0, 0);
            $finDia = (clone $fecha)->setTime(23, 59, 59);

            $pagosQueryBuilder->where('p.fecha_pago BETWEEN :inicio AND :fin')->setParameter('inicio', $inicioDia)->setParameter('fin', $finDia);
            $gastosQueryBuilder->where('g.fecha BETWEEN :inicio AND :fin')->setParameter('inicio', $inicioDia)->setParameter('fin', $finDia);
        }

        $pagination = $this->paginator->paginate($pagosQueryBuilder, $request->query->getInt('page', 1), 10);
        $gastos = $gastosQueryBuilder->getQuery()->getResult();
        
        $formRegistrarPago = $this->createForm(PagoType::class, null, [
             'action' => $this->generateUrl('app_pago_crear'),
             'method' => 'POST'
        ]);
        $formRegistrarGasto = $this->createForm(GastoType::class, null, [
            'action' => $this->generateUrl('app_gasto_crear'),
            'method' => 'POST'
        ]);

        return $this->render('pago/index.html.twig', [
            'pagos' => $pagination,
            'gastos' => $gastos,
            'formRegistrar' => $formRegistrarPago->createView(),
            'formRegistrarGasto' => $formRegistrarGasto->createView(),
            'fecha_filtro' => $fechaParaVista,
        ]);
    }

    #[Route('/crear', name: 'app_pago_crear', methods: ['POST'])]
    public function crear(Request $request): Response
    {
        $pago = new Pago();
        $pago->setRegistradoPor($this->getUser());
        $pago->setFechaPago(new \DateTime('now', new \DateTimeZone('America/Mexico_City')));
        
        $form = $this->createForm(PagoType::class, $pago);
        return $this->handleAjaxForm($request, $form, 'Pago registrado con éxito.');
    }

    #[Route('/{id}/editar', name: 'app_pago_editar', methods: ['POST'])]
    public function editar(Request $request, Pago $pago): Response
    {
        $form = $this->createForm(PagoType::class, $pago, ['readonly_fecha' => true]);
        return $this->handleAjaxForm($request, $form, 'Pago actualizado con éxito.');
    }

    #[Route('/{id}/eliminar', name: 'app_pago_eliminar', methods: ['POST'])]
    public function eliminar(Request $request, Pago $pago): Response
    {
        if ($this->isCsrfTokenValid('eliminar' . $pago->getId(), $request->request->get('_token'))) {
            $this->em->remove($pago);
            $this->em->flush();
            $this->addFlash('success', 'Pago eliminado correctamente.');
        } else {
            $this->addFlash('danger', 'Token CSRF inválido.');
        }
        return $this->redirectToRoute('app_pago_index');
    }

    private function handleAjaxForm(Request $request, FormInterface $form, string $successMessage): JsonResponse
    {
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pago = $form->getData();
            if (!$this->em->contains($pago)) {
                $this->em->persist($pago);
            }
            $this->em->flush();

            $formEditarView = $this->createForm(PagoType::class, $pago, [
                'action' => $this->generateUrl('app_pago_editar', ['id' => $pago->getId()]),
                'readonly_fecha' => true
            ])->createView();

            return new JsonResponse([
                'success' => true,
                'message' => $successMessage,
                'newRowHtml' => $this->renderView('pago/_pagoFila.html.twig', ['pago' => $pago]),
                'newEditModalHtml' => $this->renderView('pago/modals/editarPago.html.twig', [
                    'pago' => $pago,
                    'form' => $formEditarView
                ]),
                'entidadId' => $pago->getId()
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