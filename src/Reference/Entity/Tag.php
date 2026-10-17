<?php
/**
 * Entity Tag - fusion tag for purification or detection
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Entity;

use Amelaye\BioPHP\Domain\Sequence\Exception\InvalidSequenceException;
use Amelaye\BioPHP\Domain\Sequence\ValueObject\DnaSequence;
use App\Reference\Enum\TagPurpose;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fusion tag (purification or detection). Merges the legacy puri_fusion and detect_fusion.
 *
 * The DNA sequence is optional and entered by the laboratory. Its normalisation (whitespace
 * removed, uppercase) is biophp's {@see DnaSequence}; the laboratory rule on top of it is that
 * only A, C, G, T are accepted (no degenerate code in a real tag), so nothing is lost
 * by normalising. Position (N/C-terminal) and cleavage site belong to LAB-3.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_tag')]
#[ORM\UniqueConstraint(name: 'uniq_bl_tag_name_key', columns: ['name_key'])]
class Tag extends AbstractReference
{
    #[ORM\Column(type: 'string', length: 20, enumType: TagPurpose::class)]
    #[Assert\NotNull]
    private ?TagPurpose $purpose = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Assert\Regex(pattern: '/^[ACGT]+$/', message: 'tag.dna_sequence.invalid')]
    private ?string $dnaSequence = null;

    public function getPurpose(): ?TagPurpose
    {
        return $this->purpose;
    }

    public function setPurpose(?TagPurpose $purpose): static
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getDnaSequence(): ?string
    {
        return $this->dnaSequence;
    }

    public function setDnaSequence(?string $dnaSequence): static
    {
        $this->dnaSequence = null;
        if (null === $dnaSequence || '' === trim($dnaSequence)) {
            return $this;
        }

        try {
            $this->dnaSequence = (new DnaSequence($dnaSequence))->getValue();
        } catch (InvalidSequenceException) {
            // Kept as typed (uppercase, no whitespace) so that validation reports it.
            $this->dnaSequence = strtoupper((string) preg_replace('/\s+/', '', $dnaSequence));
        }

        return $this;
    }
}
