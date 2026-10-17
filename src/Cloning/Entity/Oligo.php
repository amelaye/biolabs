<?php
/**
 * Entity Oligo - synthetic oligonucleotide (primer)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Entity;

use Amelaye\BioPHP\Domain\Sequence\Exception\InvalidSequenceException;
use Amelaye\BioPHP\Domain\Sequence\ValueObject\DnaSequence;
use App\Cloning\Validator\OligoSequenceLocked;
use App\Reference\Entity\AbstractReference;
use App\Reference\Entity\Supplier;
use App\Shared\Audit\TracksFieldHistoryInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Oligonucleotide, always read 5'->3'. The sequence is kept as typed (raw) and normalised
 * (whitespace removed, uppercase) by biophp's {@see DnaSequence}, whose alphabet (ACGT and the
 * IUPAC codes) is the blocking rule. The melting temperature is never stored: it is computed
 * when the oligo is displayed. The sequence is locked as soon as a PCR uses the oligo
 * ({@see OligoSequenceLocked}).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_cloning_oligo')]
#[ORM\UniqueConstraint(name: 'uniq_bl_cloning_oligo_name_key', columns: ['name_key'])]
#[OligoSequenceLocked]
class Oligo extends AbstractReference implements TracksFieldHistoryInterface, QuarantinableInterface
{
    use QuarantinableTrait;

    /** Longest accepted oligo, in nucleotides. */
    public const MAX_LENGTH = 255;

    /** Length option of the sequence columns: MEDIUMTEXT on MySQL (a plain TEXT stops at 64 KB). */
    public const TEXT_COLUMN_LENGTH = 16777215;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Supplier $supplier = null;

    /** Sequence exactly as entered, 5'->3'. */
    #[ORM\Column(type: 'text', length: self::TEXT_COLUMN_LENGTH)]
    private string $sequenceRaw = '';

    /** Sequence without whitespace, uppercase, 5'->3'. */
    #[ORM\Column(type: 'text', length: self::TEXT_COLUMN_LENGTH)]
    private string $sequence = '';

    /**
     * Supplier the oligo was ordered from.
     *
     * @return Supplier|null The supplier, null if not provided
     */
    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    /**
     * Sets the supplier.
     *
     * @param Supplier|null $supplier Supplier, null if not provided
     * @return static This entity
     */
    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    /**
     * The sequence as entered (form property).
     *
     * @return string The raw text
     */
    public function getSequenceInput(): string
    {
        return $this->sequenceRaw;
    }

    /**
     * The sequence exactly as entered.
     *
     * @return string The raw text, whitespace and case included
     */
    public function getSequenceRaw(): string
    {
        return $this->sequenceRaw;
    }

    /**
     * The sequence normalised by biophp (whitespace removed, uppercase), 5'->3'.
     *
     * @return string The normalised sequence, empty if none
     */
    public function getSequence(): string
    {
        return $this->sequence;
    }

    /**
     * Length of the normalised sequence.
     *
     * @return int Number of nucleotides
     */
    public function getLength(): int
    {
        return \strlen($this->sequence);
    }

    /**
     * Sets the sequence from what the person typed: the raw text is kept and the normalised form is biophp's
     * DnaSequence. A text that is not a valid sequence keeps its cleaned-up form so that the validation can report it.
     *
     * @param string|null $raw Sequence as typed
     * @return static This entity
     */
    public function setSequenceInput(?string $raw): static
    {
        $this->sequenceRaw = $raw ?? '';
        try {
            $this->sequence = (new DnaSequence($this->sequenceRaw))->getValue();
        } catch (InvalidSequenceException) {
            $this->sequence = strtoupper((string) preg_replace('/\s+/', '', $this->sequenceRaw));
        }

        return $this;
    }

    /**
     * Blocking rules of the sequence: not empty, nucleotide symbols of the biophp alphabet only, at most 255 nt.
     *
     * @param ExecutionContextInterface $context Validation context that receives the violations
     * @return void
     */
    #[Assert\Callback]
    public function validateSequence(ExecutionContextInterface $context): void
    {
        if ('' === $this->sequence) {
            $context->buildViolation('oligo.sequence.empty')->atPath('sequenceInput')->addViolation();

            return;
        }
        if (!DnaSequence::isValid($this->sequence)) {
            $context->buildViolation('oligo.sequence.invalid_symbol')->atPath('sequenceInput')->addViolation();

            return;
        }
        if ($this->getLength() > self::MAX_LENGTH) {
            $context->buildViolation('oligo.sequence.too_long')
                ->setParameter('{{ max }}', (string) self::MAX_LENGTH)
                ->atPath('sequenceInput')
                ->addViolation();
        }
    }

    /**
     * Fields whose old and new values are kept in the history.
     *
     * @return list<string> The normalised sequence and the sequence as typed (case marks non-hybridising bases)
     */
    public function trackedFields(): array
    {
        return ['sequence', 'sequenceRaw'];
    }

    /**
     * Name and sequence: changing them alters the meaning of what uses the oligo.
     *
     * @return array<string, scalar|null> Value by field name
     */
    public function significantValues(): array
    {
        return parent::significantValues() + ['sequence' => $this->sequence];
    }
}
