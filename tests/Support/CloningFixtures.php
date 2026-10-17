<?php
/**
 * Synthetic cloning objects shared by the LAB-3 tests
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Support;

use App\Cloning\Entity\Construct;
use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use App\Cloning\Entity\Vector;
use App\Cloning\Enum\InsertType;
use App\Reference\Entity\Organism;
use App\Target\Entity\Gene;

/**
 * Builders of synthetic cloning objects. The filler of the vectors is made of A and T only, so
 * that no restriction site appears by accident: the only sites are the ones the tests add.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
trait CloningFixtures
{
    private int $fixtureCounter = 0;

    /**
     * Synthetic circular vector sequence with one NcoI site (CCATGG) and one NotI site (GCGGCCGC).
     *
     * @return string The sequence
     */
    private function vectorSequence(): string
    {
        return str_repeat('AT', 50).'CCATGG'.str_repeat('AT', 20).'GCGGCCGC'.str_repeat('AT', 50);
    }

    /**
     * Synthetic circular vector sequence whose only NcoI site straddles the origin (CCA at the end, TGG at the start).
     *
     * @return string The sequence
     */
    private function originSpanningSequence(): string
    {
        return 'TGG'.str_repeat('AT', 60).'GCGGCCGC'.str_repeat('AT', 40).'CCA';
    }

    /**
     * Stores an oligo with a unique name.
     *
     * @param string $sequence Sequence as typed
     * @param string|null $name Name, generated if null
     * @return Oligo The stored oligo
     */
    private function oligo(string $sequence = 'ATGGCCAAATAAGCTAGCTAGGATCC', ?string $name = null): Oligo
    {
        $oligo = (new Oligo())->setName($name ?? 'OLIGO-'.++$this->fixtureCounter)->setSequenceInput($sequence);
        $this->em->persist($oligo);
        $this->em->flush();

        return $oligo;
    }

    /**
     * Stores a vector with a unique name.
     *
     * @param string|null $sequence Circular sequence, null for "not available"
     * @param string|null $name Name, generated if null
     * @return Vector The stored vector
     */
    private function vector(?string $sequence = null, ?string $name = null): Vector
    {
        $vector = (new Vector())->setName($name ?? 'VECTOR-'.++$this->fixtureCounter)->setSequenceInput($sequence);
        $this->em->persist($vector);
        $this->em->flush();

        return $vector;
    }

    /**
     * Stores a gene of a synthetic organism.
     *
     * @return Gene The stored gene
     */
    private function gene(): Gene
    {
        $organism = (new Organism())->setName('Synthetic organism '.++$this->fixtureCounter)->setGeneticCode(11);
        $this->em->persist($organism);
        $gene = (new Gene())->setName('GENE-'.$this->fixtureCounter)->setOrganism($organism)->setCds('ATGGCCAAATAA');
        $this->em->persist($gene);
        $this->em->flush();

        return $gene;
    }

    /**
     * Stores a PCR between two new oligos on a vector template.
     *
     * @param Vector|null $template Template, a new vector without sequence if null
     * @return Pcr The stored PCR
     */
    private function pcr(?Vector $template = null): Pcr
    {
        $pcr = (new Pcr())
            ->setName('PCR-'.++$this->fixtureCounter)
            ->setForwardOligo($this->oligo())
            ->setReverseOligo($this->oligo())
            ->setTemplateVector($template ?? $this->vector());
        $this->em->persist($pcr);
        $this->em->flush();

        return $pcr;
    }

    /**
     * Stores a PCR-fragment insert with the given enzymes.
     *
     * @param string|null $five Enzyme at the 5' end
     * @param string|null $three Enzyme at the 3' end
     * @return Insert The stored insert
     */
    private function insert(?string $five = 'NcoI', ?string $three = 'NotI'): Insert
    {
        $insert = (new Insert())
            ->setName('INSERT-'.++$this->fixtureCounter)
            ->setType(InsertType::PcrFragment)
            ->setPcr($this->pcr())
            ->setFivePrimeSite($five)
            ->setThreePrimeSite($three);
        $this->em->persist($insert);
        $this->em->flush();

        return $insert;
    }

    /**
     * Builds a construct (not stored).
     *
     * @param Vector $vector Vector
     * @param Insert $insert Insert
     * @return Construct A new construct
     */
    private function construct(Vector $vector, Insert $insert): Construct
    {
        return (new Construct())->setName('CONSTRUCT-'.++$this->fixtureCounter)->setVector($vector)->setInsert($insert);
    }
}
