<?php

namespace App\Controller;

use App\Entity\BlogPost;
use App\Repository\BlogPostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/blog')]
final class BlogViewController extends AbstractController
{
    #[Route('', name: 'app_blog_view')]
    public function index(Request $request, BlogPostRepository $blogPostRepository): Response
    {
        $categoryFilter = $request->query->get('categoria');

        $criteria = ['enabled' => true];
        if ($categoryFilter) {
            $criteria['categoria'] = $categoryFilter;
        }

        $posts = $blogPostRepository->findBy($criteria, ['createdAt' => 'DESC']);
        
        $categories = [
            ['label' => 'Prevención', 'value' => 'Prevencion', 'icon' => 'shield-check-outline.png'],
            ['label' => 'Cuidado de Heridas', 'value' => 'Cuidado de Heridas', 'icon' => 'bandage.png'],
            ['label' => 'Nutrición y Diabetes', 'value' => 'Nutricion y Diabetes', 'icon' => 'nutrition.png'],
            ['label' => 'Calzado y Plantillas', 'value' => 'Calzado y Plantillas', 'icon' => 'foot-print.png'],
            ['label' => 'Complicaciones', 'value' => 'Complicaciones', 'icon' => 'alert-circle-outline.png'],
            ['label' => 'Consejos Diarios', 'value' => 'Consejos Diarios', 'icon' => 'lightbulb-on-outline.png'],
        ];

        return $this->render('blog_view/index.html.twig', [
            'posts' => $posts,
            'categories' => $categories,
            'activeCategory' => $categoryFilter,
        ]);
    }

    #[Route('/post/{id}', name: 'app_blog_post_show', methods: ['GET'])]
    public function show(BlogPost $post, ParameterBagInterface $params): JsonResponse
    {
        if (!$post->isEnabled()) {
            return new JsonResponse(['error' => 'Publicación no encontrada'], 404);
        }

        $uploadsBaseUrl = $this->getParameter('uploads_url_base');
        $imageUrl = $post->getImageFilename() ? $uploadsBaseUrl . '/' . $post->getImageFilename() : null;

        return new JsonResponse([
            'title' => $post->getTitle(),
            'category' => $post->getCategoria(),
            'imageUrl' => $imageUrl,
            'createdAt' => $post->getCreatedAt()->format('d F, Y'),
            'content' => $post->getContent(),
        ]);
    }
}