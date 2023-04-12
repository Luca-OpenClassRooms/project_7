<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ClientUserRepository;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;

/**
 * @Hateoas\Relation(
 *      "self",
 *      href = @Hateoas\Route(
 *          "app_client_user_show",
 *          parameters = { 
 *              "client" = "expr(object.getClientId())",
 *              "client_user" = "expr(object.getId())" 
 *          }
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="client_user:read")
 * )
 * 
 * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "app_client_user_delete",
 *          parameters = { 
 *              "client" = "expr(object.getClientId())",
 *              "client_user" = "expr(object.getId())" 
 *          }
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="client_user:read")
 * )
 * 
 * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "app_client_user_update",
 *          parameters = { 
 *              "client" = "expr(object.getClientId())",
 *              "client_user" = "expr(object.getId())" 
 *          }
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="client_user:read")
 * )
 */
#[ORM\Entity(repositoryClass: ClientUserRepository::class)]
class ClientUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['client_user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['client_user:read', "client_user:write"])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $first_name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['client_user:read', "client_user:write"])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $last_name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    #[Assert\Email]
    #[Groups(['client_user:read', "client_user:write"])]
    private ?string $email = null;

    #[ORM\ManyToOne(targetEntity: Client::class, fetch: 'LAZY')]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Client $client;

    public function getClientId(): ?int
    {
        return $this->client->getId();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(string $first_name): self
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(string $last_name): self
    {
        $this->last_name = $last_name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }
}
