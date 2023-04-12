<?php

namespace App\Controller\Client;

use App\Entity\Client;
use App\Entity\ClientUser;
use App\Repository\ClientUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Cache\ItemInterface;

class UserController extends AbstractController
{
    private function checkAccess(Client $client): bool
    {
        $clientId = $this->getUser()->getId();

        if( $client->getId() !== $clientId ) {
            throw new AccessDeniedHttpException('You are not allowed to access this resource');
        }

        return true;
    }

    #[Route('/api/clients/{id}/users', name: 'app_client_user', methods: ['GET'])]
    public function index(
        ClientUserRepository $clientUserRepository, 
        Request $request, 
        Client $client,
        TagAwareCacheInterface $cache,
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

        return $this->json($data, 200, [], ['groups' => 'client_user:read']);
    }

    #[Route('/api/clients/{client}/users/{client_user}', name: 'app_client_user_show', methods: ['GET'] )]
    public function show(Client $client, ClientUser $client_user): JsonResponse
    {
        $this->checkAccess($client);

        if( $client_user->getClient()->getId() !== $client->getId() ) {
            throw new AccessDeniedHttpException('You are not allowed to access this resource');
        }

        return $this->json($client_user, 200, [], ['groups' => 'client_user:read']);
    }

    /**
     * Create a client user
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/clients/{id}/users', name: 'app_client_user_create', methods: ['POST'])]
    public function create(
        Client $client,
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $this->checkAccess($client);

        $data = $request->getContent();

        $clientUser = $serializer->deserialize($data, ClientUser::class, 'json');

        $clientUser->setClient($this->getUser());

        $errors = $validator->validate($clientUser);

        if ($errors->count() > 0) {
            return $this->json($errors, 400);
        }

        $emailAlreadyExists = $entityManager->getRepository(ClientUser::class)->findOneBy([
            'email' => $clientUser->getEmail(),
            'client' => $this->getUser()
        ]);

        if ($emailAlreadyExists) {
            return $this->json([
                'message' => 'Email already exists'
            ], 400);
        }

        $entityManager->persist($clientUser);
        $entityManager->flush();

        $cache->invalidateTags(["client_{$clientUser->getClient()->getId()}"]);

        return $this->json($clientUser, 201, [], ['groups' => 'client_user:read']);
    }
    
    /**
     * Update a client user
     * 
     * @param ClientUser $clientUser
     * @param Request $request
     * @param ValidatorInterface $validator
     * @param SerializerInterface $serializer
     * @param ClientUserRepository $clientUserRepository
     * 
     * @return JsonResponse
     */
    #[Route('/api/clients/{client}/users/{client_user}', name: 'app_client_user_update', methods: ['PUT'])]
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

        $data = $serializer->deserialize($request->getContent(), ClientUser::class, "json", ['object_to_populate' => $client_user]);

        $errors = $validator->validate($data);
        
        if ( $errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, "json"), 400, [], true);
        }

        $clientUserRepository->save($data, true);

        $cache->invalidateTags(["client_{$client_user->getClient()->getId()}"]);

        return $this->json($data, 200, [], ['groups' => 'client_user:read']);
    }

    /**
     * Delete a client user
     * 
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

        return $this->json(null, 204);
    }
}
