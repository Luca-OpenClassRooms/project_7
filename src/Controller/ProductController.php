<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ProductController extends AbstractController
{
    #[Route('/api/products', name: 'app_products')]
    public function index(
        ProductRepository $productRepository, 
        Request $request, 
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $page = $request->query->get("page", 1);
        $limit = $request->query->get("limit", 10);

        $idCache = "product_list_{$page}_{$limit}";

        $data = $cache->get($idCache, function () use ($productRepository, $page, $limit) {
            return $productRepository->findAllWithPagination($page, $limit);
        });

        return $this->json($data, 200, [], ['groups' => 'product:read']);
    }

    #[Route('/api/products/{id}', name: 'app_product')]
    public function show(Product $product): JsonResponse
    {
        return $this->json($product, 200, [], ['groups' => 'product:read']);
    }
}
