<?php

namespace App\Controller;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\Cache\CacheItem;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: "Product")]
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
    #[OA\Response(
        response: 200,
        description: "Returns all products",
        content: new OA\JsonContent(
            type: "array",
            items: new OA\Items(ref: new Model(type: Product::class, groups: ["product:read"]))
        )
    )]
    #[OA\Parameter(
        name: "page",
        in: "query",
        description: "The page number",
        schema: new OA\Schema(type: "integer")
    )]
    #[OA\Parameter(
        name: "limit",
        in: "query",
        description: "The number of products per page",
        schema: new OA\Schema(type: "integer")
    )]
    public function index(
        ProductRepository $productRepository, 
        Request $request, 
        TagAwareCacheInterface $cache,
        SerializerInterface $serializer
    ): JsonResponse
    {
        $page = $request->query->get("page", 1);
        $limit = $request->query->get("limit", 10);

        $idCache = "product_list_{$page}_{$limit}";

        $data = $cache->get($idCache, function (CacheItem $item) use ($productRepository, $page, $limit) {
            $item->tag("products");
            return $productRepository->findAllWithPagination($page, $limit);
        });

        $context = SerializationContext::create()->setGroups(['product:read']);
        $data = $serializer->serialize($data, "json", $context);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * Get one product 
     * 
     * @param Product $product
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: 'app_product', methods: ['GET'])]
    public function show(
        Product $product,
        SerializerInterface $serializer
    ): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['product:read']);
        $data = $serializer->serialize($product, "json", $context);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }   

    /**
     * Create a product
     * 
     * @param Request $request
     * @param ValidatorInterface $validator
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/products', name: 'app_product_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function store(
        Request $request, 
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $data = $serializer->deserialize($request->getContent(), Product::class, "json");

        $errors = $validator->validate($data);
        
        if ( $errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, "json"), 400, [], true);
        }

        $em->persist($data);
        $em->flush();

        $cache->invalidateTags(["products"]);

        $context = SerializationContext::create()->setGroups(['product:read']);
        $data = $serializer->serialize($data, "json", $context);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
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
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        Product $product,
        Request $request, 
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        ProductRepository $productRepository,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $context = DeserializationContext::create()->setGroups(['product:read'])->setAttribute('object_to_populate', $product);
        $data = $serializer->deserialize($request->getContent(), Product::class, "json", $context);

        $errors = $validator->validate($data);
        
        if ( $errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, "json"), 400, [], true);
        }

        $productRepository->save($data, true);

        $cache->invalidateTags(["products"]);

        $context = SerializationContext::create()->setGroups(['product:read']);
        $data = $serializer->serialize($data, "json", $context);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * Delete a product
     * 
     * @param Product $product
     * @param EntityManagerInterface $m
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: 'app_product_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Product $product, EntityManagerInterface $m, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["products"]);
        
        $m->remove($product);
        $m->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
