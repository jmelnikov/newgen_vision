<?php

namespace App\Entity;

use App\Repository\ArtistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArtistRepository::class)]
class Artist
{
    #[ORM\Id]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $listeners_count = null;

    #[ORM\Column]
    private ?int $followers_count = null;

    #[ORM\Column]
    private ?int $albums_count = null;

    #[ORM\ManyToMany(targetEntity: Track::class, mappedBy: 'artist')]
    private Collection $tracks;

    public function __construct()
    {
        $this->tracks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getListenersCount(): ?int
    {
        return $this->listeners_count;
    }

    public function setListenersCount(int $listeners_count): self
    {
        $this->listeners_count = $listeners_count;

        return $this;
    }

    public function getFollowersCount(): ?int
    {
        return $this->followers_count;
    }

    public function setFollowersCount(int $followers_count): self
    {
        $this->followers_count = $followers_count;

        return $this;
    }

    public function getAlbumsCount(): ?int
    {
        return $this->albums_count;
    }

    public function setAlbumsCount(int $albums_count): self
    {
        $this->albums_count = $albums_count;

        return $this;
    }

    /**
     * @return Collection<int, Track>
     */
    public function getTracks(): Collection
    {
        return $this->tracks;
    }

    public function addTrack(Track $track): self
    {
        if (!$this->tracks->contains($track)) {
            $this->tracks->add($track);
            $track->addArtist($this);
        }

        return $this;
    }

    public function removeTrack(Track $track): self
    {
        if ($this->tracks->removeElement($track)) {
            $track->removeArtist($this);
        }

        return $this;
    }
}
