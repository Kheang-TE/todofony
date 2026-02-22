<?php

namespace App\Entity;

use App\Model\TaskStatusEnum;
use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[UniqueEntity(
    fields: ['title', 'person'],
    message: 'You already have a task with this title.',
)]
#[ORM\Table(
    name: 'task',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'UNIQ_TASK_TITLE_PERSON', columns: ['title', 'person_id'])
    ]
)]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['task:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank()]
    #[Groups(['task:read'])]
    private ?string $title = null;

    #[ORM\Column(enumType: TaskStatusEnum::class)]
    #[Assert\NotBlank()]
    #[Groups(['task:read'])]
    private TaskStatusEnum $status;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['task:read'])]
    private ?User $person = null;

    #[ORM\Column]
    #[Groups(['task:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['task:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStatus(): TaskStatusEnum
    {
        return $this->status;
    }

    public function setStatus(TaskStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPerson(): ?User
    {
        return $this->person;
    }

    public function setPerson(?User $person): static
    {
        $this->person = $person;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
