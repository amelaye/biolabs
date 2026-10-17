<?php
/**
 * Entity Complex - protein complex targeted by the laboratory
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Entity;

use App\Reference\Entity\AbstractReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Complex of proteins (legacy "complex"); its description goes in the notes.
 * The stoichiometry of the subunits is not recorded (see docs/lots/LAB-2.md).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_target_complex')]
#[ORM\UniqueConstraint(name: 'uniq_bl_target_complex_name_key', columns: ['name_key'])]
class Complex extends AbstractReference
{
    /** @var Collection<int, Gene> */
    #[ORM\ManyToMany(targetEntity: Gene::class)]
    #[ORM\JoinTable(name: 'bl_target_complex_gene')]
    private Collection $genes;

    /**
     * Complex constructor: starts with no gene.
     */
    public function __construct()
    {
        $this->genes = new ArrayCollection();
    }

    /**
     * Genes (subunits) of the complex.
     *
     * @return Collection<int, Gene> The genes
     */
    public function getGenes(): Collection
    {
        return $this->genes;
    }

    /**
     * Adds a gene to the complex (ignored if already present).
     *
     * @param Gene $gene Gene to add
     * @return static This entity
     */
    public function addGene(Gene $gene): static
    {
        if (!$this->genes->contains($gene)) {
            $this->genes->add($gene);
        }

        return $this;
    }

    /**
     * Removes a gene from the complex.
     *
     * @param Gene $gene Gene to remove
     * @return static This entity
     */
    public function removeGene(Gene $gene): static
    {
        $this->genes->removeElement($gene);

        return $this;
    }
}
