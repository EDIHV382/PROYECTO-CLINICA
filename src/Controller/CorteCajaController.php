<?php

namespace App\Controller;

use App\Entity\CorteCaja;
use App\Form\CorteCajaType;
use App\Repository\CorteCajaRepository;
use App\Repository\GastoRepository;
use App\Repository\PagoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/corte-caja')]
class CorteCajaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator
    ) {}

    #[Route('/', name: 'app_corte_caja_index', methods: ['GET', 'POST'])]
    public function index(Request $request, PagoRepository $pagoRepo, GastoRepository $gastoRepo, CorteCajaRepository $corteCajaRepo, UserRepository $userRepo): Response
    {
        $zona = new \DateTimeZone('America/Mexico_City');
        $usuarios = $userRepo->findAll(); 
        
        $selectedUserId = $request->query->get('usuarioId');
        $fechaStr = $request->query->get('fecha', (new \DateTime('today', $zona))->format('Y-m-d'));
        $fecha = new \DateTime($fechaStr, $zona);

        $corteCaja = null;
        $form = null;
        $pagosDelCorte = [];
        $gastosDelCorte = [];
        $selectedUser = null;

        if ($selectedUserId) {
            $selectedUser = $userRepo->find($selectedUserId);
            if ($selectedUser) {
                $corteExistente = $corteCajaRepo->findOneBy(['fecha' => $fecha, 'usuario' => $selectedUser]);
                if ($corteExistente) {
                    $this->addFlash('info', 'Ya existe un corte de caja para este usuario en la fecha seleccionada.');
                }
                
                $pagosDelCorte = $pagoRepo->findPagosSinCorteDelDiaPorUsuario($fecha, $selectedUser);
                $gastosDelCorte = $gastoRepo->findGastosSinCorteDelDia($fecha);

                $corteCaja = $corteExistente ?? new CorteCaja();
                if (!$corteExistente) {
                    $corteCaja->setUsuario($selectedUser);
                    $corteCaja->setFecha($fecha);
                }
                
                $corteCaja->calcularTotalesParciales($pagosDelCorte, $gastosDelCorte);

                $form = $this->createForm(CorteCajaType::class, $corteCaja);
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    // Volver a asignar los pagos y gastos antes de guardar para asegurar la relación
                    foreach ($pagosDelCorte as $pago) { $corteCaja->addPago($pago); }
                    foreach ($gastosDelCorte as $gasto) { $corteCaja->addGasto($gasto); }
                    $corteCaja->calcularTotales();

                    $this->em->persist($corteCaja);
                    $this->em->flush();
                    $this->addFlash('success', 'Corte de caja para ' . $selectedUser->getName() . ' registrado con éxito.');
                    return $this->redirectToRoute('app_corte_caja_index');
                }
            }
        }
        
        $queryBuilder = $corteCajaRepo->createQueryBuilder('cc')->leftJoin('cc.usuario', 'u')->addSelect('u')->orderBy('cc.fecha', 'DESC');
        $cortesPaginados = $this->paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);

        return $this->render('corte_caja/index.html.twig', [
            'form' => $form?->createView(),
            'cortes' => $cortesPaginados,
            'corte_caja_calculado' => $corteCaja,
            'usuarios' => $usuarios,
            'selectedUser' => $selectedUser,
            'fecha_filtro' => $fecha->format('Y-m-d'),
        ]);
    }
}