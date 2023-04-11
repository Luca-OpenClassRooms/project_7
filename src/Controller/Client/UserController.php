<?php

namespace App\Controller\Client;

use App\Entity\ClientUser;
use App\Repository\ClientUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\ItemInterface;

class UserController extends AbstractController
{
    #[Route('/api/client/users', name: 'app_client_user', methods: ['GET'])]
    public function index(
        ClientUserRepository $clientUserRepository, 
        Request $request, 
        TagAwareCacheInterface $cache,
    ): JsonResponse
    {
        $page = $request->query->get("page", 1);
        $limit = $request->query->get("limit", 10);

        $clientId = $this->getUser()->getId();

        $idCache = "client_{$clientId}_users_{$page}_{$limit}";

        $data = $cache->get($idCache, function (ItemInterface $item) use ($clientUserRepository, $page, $limit, $clientId) {
            $item->tag("client_{$clientId}");
            return $clientUserRepository->findAllWithPagination($clientId, $page, $limit);
        });

        return $this->json($data, 200, [], ['groups' => 'client_user:read']);
    }

    #[Route('/api/client/users/{id}', name: 'app_client_user_show', methods: ['GET'])]
    public function show(ClientUser $clientUser): JsonResponse
    {
        return $this->json($clientUser, 200, [], ['groups' => 'client_user:read']);
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
    #[Route('/api/client/users', name: 'app_client_user_create', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
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
    #[Route('/api/client/users/{id}', name: 'app_client_user_update', methods: ['PUT'])]
    public function update(
        ClientUser $clientUser,
        Request $request, 
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        ClientUserRepository $clientUserRepository,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $data = $serializer->deserialize($request->getContent(), ClientUser::class, "json", ['object_to_populate' => $clientUser]);

        $errors = $validator->validate($data);
        
        if ( $errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, "json"), 400, [], true);
        }

        $clientUserRepository->save($data, true);

        $cache->invalidateTags(["client_{$clientUser->getClient()->getId()}"]);

        return $this->json($data, 201, [], ['groups' => 'client_user:read']);
    }

    /**
     * Delete a client user
     * 
     * @param ClientUser $clientUser
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    #[Route('/api/client/users/{id}', name: 'app_client_user_delete', methods: ['DELETE'])]
    public function delete(
        ClientUser $clientUser,
        EntityManagerInterface $entityManager,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $cache->invalidateTags(["client_{$clientUser->getClient()->getId()}"]);
        $entityManager->remove($clientUser);
        $entityManager->flush();

        return $this->json(null, 204);
    }
}
