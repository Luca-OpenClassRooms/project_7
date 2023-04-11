<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProductController extends AbstractController
{
    /**
     * Get all products
     *
     * @param ProductRepository $productRepository
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/products', name: 'app_products', methods: ['GET'])]
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

    /**
     * Get one product 
     * 
     * @param Product $product
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: 'app_product', methods: ['GET'])]
    public function show(Product $product): JsonResponse
    {
        return $this->json($product, 200, [], ['groups' => 'product:read']);
    }   

    /**
     * Create a product
     * 
     * @param Request $request
     * @param ValidatorInterface $validator
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/products', name: 'app_product_create', methods: ['POST'])]
    public function store(
        Request $request, 
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $data = $serializer->deserialize($request->getContent(), Product::class, "json");

        $errors = $validator->validate($data);
        
        if ( $errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, "json"), 400, [], true);
        }

        $em->persist($data);
        $em->flush();

        return $this->json($data, 201);
    }

    /**
     * Update a product
     * 
     * @param Product $product
     * @param Request $request
     * @param ValidatorInterface $validator
     * @param SerializerInterface $serializer
     * @param ProductRepository $productRepository
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: 'app_product_update', methods: ['PUT'])]
    public function update(
        Product $product,
        Request $request, 
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        ProductRepository $productRepository
    ): JsonResponse
    {
        $data = $serializer->deserialize($request->getContent(), Product::class, "json", ['object_to_populate' => $product]);

        $errors = $validator->validate($data);
        
        if ( $errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, "json"), 400, [], true);
        }

        $productRepository->save($data, true);

        return $this->json($data, 201);
    }

    /**
     * Delete a product
     * 
     * @param Product $product
     * @param ProductRepository $productRepository
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: 'app_product_delete', methods: ['DELETE'])]
    public function delete(Product $product, ProductRepository $productRepository, EntityManagerInterface $m): JsonResponse
    {
        $m->remove($product);
        $m->flush();
        return $this->json(null, 204);
    }
}