<?php
/**
 * Entity Vector - standard cloning or expression vector
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Entity;

use Amelaye\BioPHP\Domain\Sequence\Exception\InvalidSequenceException;
use Amelaye\BioPHP\Domain\Sequence\ValueObject\CircularDnaSequence;
use App\Cloning\Enum\TagCapabilityState;
use App\Cloning\Enum\TagPosition;
use App\Reference\Entity\AbstractReference;
use App\Reference\Entity\Promoter;
use App\Reference\Entity\ReplicationOrigin;
use App\Reference\Entity\SelectionMarker;
use App\Reference\Entity\Supplier;
use App\Reference\Entity\Tag;
use App\Shared\Audit\TracksFieldHistoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Standard vector. Its sequence is optional: a vector without sequence is explicitly "not
 * available" and every check that needs the sequence is skipped. The sequence is always
 * circular (biophp's {@see CircularDnaSequence}); coordinates are 1-based, the origin being the
 * first nucleotide. Tag capabilities have three states ({@see TagCapabilityState}).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_cloning_vector')]
#[ORM\UniqueConstraint(name: 'uniq_bl_cloning_vector_name_key', columns: ['name_key'])]
class Vector extends AbstractReference implements TracksFieldHistoryInterface, QuarantinableInterface
{
    use QuarantinableTrait;

    /** Longest accepted vector sequence as typed, in characters. */
    public const MAX_SEQUENCE_LENGTH = 300000;

    /** Length option of the sequence columns: MEDIUMTEXT on MySQL (a plain TEXT stops at 64 KB). */
    public const TEXT_COLUMN_LENGTH = 16777215;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Supplier $supplier = null;

    #[ORM\ManyToOne(targetEntity: SelectionMarker::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SelectionMarker $marker = null;

    #[ORM\ManyToOne(targetEntity: ReplicationOrigin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ReplicationOrigin $origin = null;

    #[ORM\ManyToOne(targetEntity: Promoter::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Promoter $promoter = null;

    /** Sequence exactly as entered, null when not available. */
    #[ORM\Column(type: 'text', length: self::TEXT_COLUMN_LENGTH, nullable: true)]
    #[Assert\Length(max: self::MAX_SEQUENCE_LENGTH)]
    private ?string $sequenceRaw = null;

    /** Circular sequence without whitespace, uppercase, null when not available. */
    #[ORM\Column(type: 'text', length: self::TEXT_COLUMN_LENGTH, nullable: true)]
    private ?string $sequence = null;

    #[ORM\Column(type: 'string', length: 20, enumType: TagCapabilityState::class)]
    private TagCapabilityState $tagState = TagCapabilityState::Unknown;

    /** @var Collection<int, VectorTagCapability> */
    #[ORM\OneToMany(targetEntity: VectorTagCapability::class, mappedBy: 'vector', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $tagCapabilities;

    /**
     * Vector constructor: starts without sequence and with unknown tag capabilities.
     */
    public function __construct()
    {
        $this->tagCapabilities = new ArrayCollection();
    }

    /**
     * Supplier of the vector.
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
     * Selection marker of the vector.
     *
     * @return SelectionMarker|null The marker, null if not provided
     */
    public function getMarker(): ?SelectionMarker
    {
        return $this->marker;
    }

    /**
     * Sets the selection marker.
     *
     * @param SelectionMarker|null $marker Marker, null if not provided
     * @return static This entity
     */
    public function setMarker(?SelectionMarker $marker): static
    {
        $this->marker = $marker;

        return $this;
    }

    /**
     * Origin of replication.
     *
     * @return ReplicationOrigin|null The origin, null if not provided
     */
    public function getOrigin(): ?ReplicationOrigin
    {
        return $this->origin;
    }

    /**
     * Sets the origin of replication.
     *
     * @param ReplicationOrigin|null $origin Origin, null if not provided
     * @return static This entity
     */
    public function setOrigin(?ReplicationOrigin $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    /**
     * Promoter of the vector.
     *
     * @return Promoter|null The promoter, null if not provided
     */
    public function getPromoter(): ?Promoter
    {
        return $this->promoter;
    }

    /**
     * Sets the promoter.
     *
     * @param Promoter|null $promoter Promoter, null if not provided
     * @return static This entity
     */
    public function setPromoter(?Promoter $promoter): static
    {
        $this->promoter = $promoter;

        return $this;
    }

    /**
     * The sequence as entered (form property).
     *
     * @return string|null The raw text, null if not available
     */
    public function getSequenceInput(): ?string
    {
        return $this->sequenceRaw;
    }

    /**
     * The sequence exactly as entered.
     *
     * @return string|null The raw text, null if not available
     */
    public function getSequenceRaw(): ?string
    {
        return $this->sequenceRaw;
    }

    /**
     * The circular sequence normalised by biophp.
     *
     * @return string|null The normalised sequence, null if not available
     */
    public function getSequence(): ?string
    {
        return $this->sequence;
    }

    /**
     * Tells whether the sequence of the vector is available.
     *
     * @return bool True if there is a sequence
     */
    public function hasSequence(): bool
    {
        return null !== $this->sequence && '' !== $this->sequence;
    }

    /**
     * Length of the sequence.
     *
     * @return int Number of base pairs, 0 if the sequence is not available
     */
    public function getSequenceLength(): int
    {
        return \strlen($this->sequence ?? '');
    }

    /**
     * Sets the sequence from what the person typed. A blank text means "not available". A text that is
     * not a valid sequence keeps its cleaned-up form so that the validation can report it.
     *
     * @param string|null $raw Sequence as typed
     * @return static This entity
     */
    public function setSequenceInput(?string $raw): static
    {
        if (null === $raw || '' === trim($raw)) {
            $this->sequenceRaw = null;
            $this->sequence = null;

            return $this;
        }
        $this->sequenceRaw = $raw;
        try {
            $this->sequence = (new CircularDnaSequence($raw))->getValue();
        } catch (InvalidSequenceException) {
            $this->sequence = strtoupper((string) preg_replace('/\s+/', '', $raw));
        }

        return $this;
    }

    /**
     * Knowledge of the tag capabilities.
     *
     * @return TagCapabilityState Unknown, none or listed
     */
    public function getTagState(): TagCapabilityState
    {
        return $this->tagState;
    }

    /**
     * Sets the knowledge of the tag capabilities.
     *
     * @param TagCapabilityState|null $tagState Unknown, none or listed (unknown when null)
     * @return static This entity
     */
    public function setTagState(?TagCapabilityState $tagState): static
    {
        $this->tagState = $tagState ?? TagCapabilityState::Unknown;

        return $this;
    }

    /**
     * Tag capabilities of the vector.
     *
     * @return Collection<int, VectorTagCapability> The capabilities
     */
    public function getTagCapabilities(): Collection
    {
        return $this->tagCapabilities;
    }

    /**
     * Tag offered at the N-terminal end (form property).
     *
     * @return Tag|null The tag, null if none
     */
    public function getNTerminalTag(): ?Tag
    {
        return $this->tagAt(TagPosition::NTerminal);
    }

    /**
     * Sets the tag offered at the N-terminal end.
     *
     * @param Tag|null $tag Tag, null for none
     * @return static This entity
     */
    public function setNTerminalTag(?Tag $tag): static
    {
        return $this->setTagAt(TagPosition::NTerminal, $tag);
    }

    /**
     * Tag offered at the C-terminal end (form property).
     *
     * @return Tag|null The tag, null if none
     */
    public function getCTerminalTag(): ?Tag
    {
        return $this->tagAt(TagPosition::CTerminal);
    }

    /**
     * Sets the tag offered at the C-terminal end.
     *
     * @param Tag|null $tag Tag, null for none
     * @return static This entity
     */
    public function setCTerminalTag(?Tag $tag): static
    {
        return $this->setTagAt(TagPosition::CTerminal, $tag);
    }

    /**
     * Blocking rules: the sequence uses the biophp alphabet, and the tag state agrees with the listed capabilities.
     *
     * @param ExecutionContextInterface $context Validation context that receives the violations
     * @return void
     */
    #[Assert\Callback]
    public function validateVector(ExecutionContextInterface $context): void
    {
        if ($this->hasSequence() && !CircularDnaSequence::isValid($this->sequence)) {
            $context->buildViolation('vector.sequence.invalid_symbol')->atPath('sequenceInput')->addViolation();
        }
        $count = $this->tagCapabilities->count();
        if (TagCapabilityState::Listed === $this->tagState && 0 === $count) {
            $context->buildViolation('vector.tags.listed_without_tag')->atPath('tagState')->addViolation();
        }
        if (TagCapabilityState::Listed !== $this->tagState && $count > 0) {
            $context->buildViolation('vector.tags.tag_without_listed')->atPath('tagState')->addViolation();
        }
    }

    /**
     * Fields whose old and new values are kept in the history.
     *
     * @return list<string> The normalised sequence, the sequence as typed and the marker
     */
    public function trackedFields(): array
    {
        return ['sequence', 'sequenceRaw', 'marker'];
    }

    /**
     * Name, sequence and marker: changing them alters the meaning of what uses the vector.
     *
     * @return array<string, scalar|null> Value by field name
     */
    public function significantValues(): array
    {
        return parent::significantValues() + ['sequence' => $this->sequence, 'marker' => $this->marker?->getId()];
    }

    /**
     * Tag offered at one end.
     *
     * @param TagPosition $position N- or C-terminal
     * @return Tag|null The first tag at that end, null if none
     */
    private function tagAt(TagPosition $position): ?Tag
    {
        foreach ($this->tagCapabilities as $capability) {
            if ($capability->getPosition() === $position) {
                return $capability->getTag();
            }
        }

        return null;
    }

    /**
     * Sets the single tag of one end: adds it, updates the existing row in place, or removes it when null.
     *
     * @param TagPosition $position N- or C-terminal
     * @param Tag|null $tag Tag, null to remove the capabilities of that end
     * @return static This entity
     */
    private function setTagAt(TagPosition $position, ?Tag $tag): static
    {
        $atPosition = $this->tagCapabilities->filter(static fn (VectorTagCapability $c): bool => $c->getPosition() === $position)->getValues();
        if (null === $tag) {
            foreach ($atPosition as $capability) {
                $this->tagCapabilities->removeElement($capability);
            }

            return $this;
        }
        if ([] === $atPosition) {
            $this->tagCapabilities->add(new VectorTagCapability($this, $tag, $position));

            return $this;
        }
        // Keep the first row of this end and update it in place (one tag per end).
        $first = array_shift($atPosition);
        if ($first->getTag() !== $tag) {
            $first->setTag($tag);
        }
        foreach ($atPosition as $extra) {
            $this->tagCapabilities->removeElement($extra);
        }

        return $this;
    }
}
