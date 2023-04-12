<?php

namespace App\Controller\Client;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

use App\Entity\Client;
use App\Entity\ClientUser;
use App\Repository\ClientUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Cache\ItemInterface;

#[OA\Tag(name: "Client User")]
class UserController extends AbstractController
{
    /**
     * Check if the client is allowed to access the resource
     *
     * @param Client $client
     * @return boolean
     */
    private function checkAccess(Client $client): bool
    {
        $clientId = $this->getUser()->getId();

        if( $client->getId() !== $clientId ) {
            throw new AccessDeniedHttpException('You are not allowed to access this resource');
        }

        return true;
    }

    /**
     * Get all users of a client
     * 
     * @param ClientUserRepository $clientUserRepository
     * @param Request $request
     * @param Client $client
     * @param TagAwareCacheInterface $cache
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/clients/{id}/users', name: 'app_client_user', methods: ['GET'])]
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
        ClientUserRepository $clientUserRepository, 
        Request $request, 
        Client $client,
        TagAwareCacheInterface $cache,
        SerializerInterface $serializer
    ): JsonResponse
    {
        $this->checkAccess($client);

        $page = $request->query->get("page", 1);
        $limit = $request->query->get("limit", 10);
        
        $idCache = "client_{$client->getId()}_users_{$page}_{$limit}";

        $data = $cache->get($idCache, function (ItemInterface $item) use ($clientUserRepository, $page, $limit, $client) {
            $item->tag("client_{$client->getId()}");
            return $clientUserRepository->findAllWithPagination($client->getId(), $page, $limit);
        });

        $context = SerializationContext::create()->setGroups(['client_user:read']);
        $data = $serializer->serialize($data, "json", $context);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * Get a client user of a client
     * 
     * @param Client $client
     * @param ClientUser $client_user
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/clients/{client}/users/{client_user}', name: 'app_client_user_show', methods: ['GET'] )]
    public function show(Client $client, ClientUser $client_user, SerializerInterface $serializer): JsonResponse
    {
        $this->checkAccess($client);

        if( $client_user->getClient()->getId() !== $client->getId() ) {
            throw new AccessDeniedHttpException('You are not allowed to access this resource');
        }

        $context = SerializationContext::create()->setGroups(['client_user:read']);
        $data = $serializer->serialize($client_user, "json", $context);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * Create a client user
     *
     * @param Client $client
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/clients/{id}/users', name: 'app_client_user_create', methods: ['POST'])]
    #[OA\RequestBody(
        description: "User object that needs to be added to the store",
        required: true,
        content: new OA\JsonContent(ref: new Model(type: ClientUser::class, groups: ["client_user:write"]))
    )]
    public function create(
        Client $client,
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache,
    ): JsonResponse
    {
        $this->checkAccess($client);

        $data = $request->getContent();

        $clientUser = $serializer->deserialize($data, ClientUser::class, 'json');

        $clientUser->setClient($client);

        $errors = $validator->validate($clientUser);

        if ($errors->count() > 0) {
            return $this->json($errors, 400);
        }

        $emailAlreadyExists = $entityManager->getRepository(ClientUser::class)->findOneBy([
            'email' => $clientUser->getEmail(),
            'client' => $client
        ]);

        if ($emailAlreadyExists) {
            return $this->json([
                'message' => 'Email already exists'
            ], 400);
        }

        $entityManager->persist($clientUser);
        $entityManager->flush();

        $cache->invalidateTags(["client_{$clientUser->getClient()->getId()}"]);

        $context = SerializationContext::create()->setGroups(['client_user:read']);
        $data = $serializer->serialize($clientUser, "json", $context);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }
    
    /**
     * Update a client user
     * 
     * @param Client $client
     * @param ClientUser $clientUser
     * @param Request $request
     * @param ValidatorInterface $validator
     * @param SerializerInterface $serializer
     * @param ClientUserRepository $clientUserRepository
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/clients/{client}/users/{client_user}', name: 'app_client_user_update', methods: ['PUT'])]
    #[OA\RequestBody(
        description: "User object that needs to be updated to the store",
        required: true,
        content: new OA\JsonContent(ref: new Model(type: ClientUser::class, groups: ["client_user:write"]))
    )]
    public function update(
        Client $client,
        ClientUser $client_user,
        Request $request, 
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        ClientUserRepository $clientUserRepository,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $this->checkAccess($client);

        if( $client_user->getClient()->getId() !== $client->getId() ) {
            throw new AccessDeniedHttpException('You are not allowed to access this resource');
        }

        $context = DeserializationContext::create()->setGroups(['client_user:read'])->setAttribute('object_to_populate', $client_user);
        $data = $serializer->deserialize($request->getContent(), ClientUser::class, "json", $context);
        
        $errors = $validator->validate($data);
        
        if ( $errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, "json"), 400, [], true);
        }

        $client_user->setEmail($data->getEmail());
        $client_user->setFirstName($data->getFirstName());
        $client_user->setLastName($data->getLastName());
        $client_user->setClient($client);
        
        $clientUserRepository->save($client_user, true);

        $cache->invalidateTags(["client_{$client_user->getClient()->getId()}"]);

        $context = SerializationContext::create()->setGroups(['client_user:read']);
        $data = $serializer->serialize($client_user, "json", $context);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * Delete a client user
     * 
     * @param Client $client
     * @param ClientUser $clientUser
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    #[Route('/api/clients/{client}/users/{client_user}', name: 'app_client_user_delete', methods: ['DELETE'])]
    public function delete(
        Client $client,
        ClientUser $client_user,
        EntityManagerInterface $entityManager,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $this->checkAccess($client);

        if( $client_user->getClient()->getId() !== $client->getId() ) {
            throw new AccessDeniedHttpException('You are not allowed to access this resource');
        }

        $cache->invalidateTags(["client_{$client_user->getClient()->getId()}"]);
        $entityManager->remove($client_user);
        $entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
