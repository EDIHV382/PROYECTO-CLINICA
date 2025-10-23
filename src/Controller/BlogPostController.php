<?php

namespace App\Controller;

use App\Entity\BlogPost;
use App\Form\BlogPostType;
use App\Repository\BlogPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/clinica/blog')]
class BlogPostController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
        private readonly SluggerInterface $slugger
    ) {}

    #[Route('', name: 'app_blog_index', methods: ['GET'])]
    public function index(Request $request, BlogPostRepository $repo): Response
    {
        $formRegistrar = $this->createForm(BlogPostType::class, new BlogPost(), [
            'action' => $this->generateUrl('app_blog_new'),
            'method' => 'POST'
        ]);
        
        $queryBuilder = $repo->createQueryBuilder('bp')->orderBy('bp.createdAt', 'DESC');
        $pagination = $this->paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 5);

        $formsEditar = [];
        foreach ($pagination->getItems() as $post) {
            $formsEditar[$post->getId()] = $this->createForm(BlogPostType::class, $post, [
                'action' => $this->generateUrl('app_blog_edit', ['id' => $post->getId()])
            ])->createView();
        }

        return $this->render('blog_post/index.html.twig', [
            'posts' => $pagination,
            'formRegistrar' => $formRegistrar->createView(),
            'formsEditar' => $formsEditar,
        ]);
    }

    #[Route('/new', name: 'app_blog_new', methods: ['POST'])]
    public function new(Request $request): Response
    {
        $post = new BlogPost();
        $form = $this->createForm(BlogPostType::class, $post);
        return $this->handleAjaxForm($request, $form, 'Publicación creada con éxito.');
    }

    #[Route('/{id}/edit', name: 'app_blog_edit', methods: ['POST'])]
    public function edit(Request $request, BlogPost $post): Response
    {
        $form = $this->createForm(BlogPostType::class, $post);
        return $this->handleAjaxForm($request, $form, 'Publicación actualizada con éxito.');
    }

    #[Route('/{id}/delete', name: 'app_blog_delete', methods: ['POST'])]
    public function delete(Request $request, BlogPost $post): Response
    {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            if ($post->getImageFilename()) {
                $imagePath = $this->getParameter('uploads_directory') . '/' . $post->getImageFilename();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $this->em->remove($post);
            $this->em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Publicación eliminada correctamente.']);
        }
        return new JsonResponse(['success' => false, 'message' => 'Token CSRF inválido.'], 400);
    }

    private function handleAjaxForm(Request $request, FormInterface $form, string $successMessage): JsonResponse
    {
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('uploads_directory'), $newFilename);
                    if ($post->getImageFilename()) {
                        $oldImagePath = $this->getParameter('uploads_directory') . '/' . $post->getImageFilename();
                        if (file_exists($oldImagePath)) unlink($oldImagePath);
                    }
                    $post->setImageFilename($newFilename);
                } catch (FileException $e) {
                    return new JsonResponse(['success' => false, 'errors' => ['Error al subir la imagen.']], 500);
                }
            }

            if (!$this->em->contains($post)) $this->em->persist($post);
            $this->em->flush();

            $formEditarView = $this->createForm(BlogPostType::class, $post, [
                'action' => $this->generateUrl('app_blog_edit', ['id' => $post->getId()])
            ])->createView();

            return new JsonResponse([
                'success' => true,
                'message' => $successMessage,
                'newRowHtml' => $this->renderView('blog_post/_postFila.html.twig', ['post' => $post]),
                'newEditModalHtml' => $this->renderView('blog_post/modals/editarPost.html.twig', [
                    'post' => $post,
                    'form' => $formEditarView
                ]),
                'entidadId' => $post->getId()
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