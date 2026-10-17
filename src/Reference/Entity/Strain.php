<?php
/**
 * Entity Strain - bacterial host strain
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Entity;

use App\Reference\Enum\StrainUsage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Host strain (cloning or expression), with its genotype and its own resistances.
 *
 * Deliberately not linked to an {@see Organism}: the legacy had no such link and
 * all the strains are Escherichia coli.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_strain')]
#[ORM\UniqueConstraint(name: 'uniq_bl_strain_name_key', columns: ['name_key'])]
class Strain extends AbstractReference
{
    #[ORM\Column(name: 'strain_usage', type: 'string', length: 20, nullable: true, enumType: StrainUsage::class)]
    private ?StrainUsage $usage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $genotype = null;

    /** Carries the λDE3 lysogen (T7 RNA polymerase under lacUV5). */
    #[ORM\Column(name: 'is_de3', type: 'boolean')]
    private bool $de3 = false;

    /** @var Collection<int, SelectionMarker> */
    #[ORM\ManyToMany(targetEntity: SelectionMarker::class)]
    #[ORM\JoinTable(name: 'bl_strain_resistance')]
    private Collection $resistances;

    public function __construct()
    {
        $this->resistances = new ArrayCollection();
    }

    public function getUsage(): ?StrainUsage
    {
        return $this->usage;
    }

    public function setUsage(?StrainUsage $usage): static
    {
        $this->usage = $usage;

        return $this;
    }

    public function getGenotype(): ?string
    {
        return $this->genotype;
    }

    public function setGenotype(?string $genotype): static
    {
        $genotype = null === $genotype ? null : trim($genotype);
        $this->genotype = '' === $genotype ? null : $genotype;

        return $this;
    }

    public function isDe3(): bool
    {
        return $this->de3;
    }

    public function setDe3(bool $de3): static
    {
        $this->de3 = $de3;

        return $this;
    }

    /**
     * @return Collection<int, SelectionMarker>
     */
    public function getResistances(): Collection
    {
        return $this->resistances;
    }

    public function addResistance(SelectionMarker $marker): static
    {
        if (!$this->resistances->contains($marker)) {
            $this->resistances->add($marker);
        }

        return $this;
    }

    public function removeResistance(SelectionMarker $marker): static
    {
        $this->resistances->removeElement($marker);

        return $this;
    }
}
