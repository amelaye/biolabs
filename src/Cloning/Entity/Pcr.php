<?php
/**
 * Entity Pcr - polymerase chain reaction record
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Entity;

use App\Reference\Entity\AbstractReference;
use App\Target\Entity\Gene;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A PCR: forward and reverse oligos, one template (a gene of the targets or a vector, never
 * both) and the thermal program, each value with its unit in the field name. No product is
 * computed in this version: the expected amplicon needs a PCR simulation on full sequences,
 * which the libraries do not provide yet.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_cloning_pcr')]
#[ORM\UniqueConstraint(name: 'uniq_bl_cloning_pcr_name_key', columns: ['name_key'])]
class Pcr extends AbstractReference implements QuarantinableInterface
{
    use QuarantinableTrait;

    #[ORM\ManyToOne(targetEntity: Oligo::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Oligo $forwardOligo = null;

    #[ORM\ManyToOne(targetEntity: Oligo::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Oligo $reverseOligo = null;

    #[ORM\ManyToOne(targetEntity: Gene::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Gene $templateGene = null;

    #[ORM\ManyToOne(targetEntity: Vector::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Vector $templateVector = null;

    /** Number of cycles. */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $cycles = null;

    /** Denaturation temperature, °C. */
    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Positive]
    private ?float $denaturationTempC = null;

    /** Denaturation duration, seconds. */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $denaturationSeconds = null;

    /** Annealing temperature, °C. */
    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Positive]
    private ?float $annealingTempC = null;

    /** Annealing duration, seconds. */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $annealingSeconds = null;

    /** Extension temperature, °C. */
    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Positive]
    private ?float $extensionTempC = null;

    /** Extension duration, seconds. */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $extensionSeconds = null;

    /** Reaction volume, µL. */
    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Positive]
    private ?float $reactionVolumeUl = null;

    /**
     * Forward oligo.
     *
     * @return Oligo|null The oligo
     */
    public function getForwardOligo(): ?Oligo
    {
        return $this->forwardOligo;
    }

    /**
     * Sets the forward oligo.
     *
     * @param Oligo|null $oligo Forward oligo
     * @return static This entity
     */
    public function setForwardOligo(?Oligo $oligo): static
    {
        $this->forwardOligo = $oligo;

        return $this;
    }

    /**
     * Reverse oligo.
     *
     * @return Oligo|null The oligo
     */
    public function getReverseOligo(): ?Oligo
    {
        return $this->reverseOligo;
    }

    /**
     * Sets the reverse oligo.
     *
     * @param Oligo|null $oligo Reverse oligo
     * @return static This entity
     */
    public function setReverseOligo(?Oligo $oligo): static
    {
        $this->reverseOligo = $oligo;

        return $this;
    }

    /**
     * Template when it is a gene of the targets.
     *
     * @return Gene|null The gene, null if the template is a vector
     */
    public function getTemplateGene(): ?Gene
    {
        return $this->templateGene;
    }

    /**
     * Sets the gene used as template.
     *
     * @param Gene|null $gene Gene, null if the template is a vector
     * @return static This entity
     */
    public function setTemplateGene(?Gene $gene): static
    {
        $this->templateGene = $gene;

        return $this;
    }

    /**
     * Template when it is a vector.
     *
     * @return Vector|null The vector, null if the template is a gene
     */
    public function getTemplateVector(): ?Vector
    {
        return $this->templateVector;
    }

    /**
     * Sets the vector used as template.
     *
     * @param Vector|null $vector Vector, null if the template is a gene
     * @return static This entity
     */
    public function setTemplateVector(?Vector $vector): static
    {
        $this->templateVector = $vector;

        return $this;
    }

    /**
     * Number of cycles.
     *
     * @return int|null The cycles, null if not provided
     */
    public function getCycles(): ?int
    {
        return $this->cycles;
    }

    /**
     * Sets the number of cycles.
     *
     * @param int|null $cycles Cycles (positive), null if not provided
     * @return static This entity
     */
    public function setCycles(?int $cycles): static
    {
        $this->cycles = $cycles;

        return $this;
    }

    /**
     * Denaturation temperature.
     *
     * @return float|null Temperature in °C, null if not provided
     */
    public function getDenaturationTempC(): ?float
    {
        return $this->denaturationTempC;
    }

    /**
     * Sets the denaturation temperature.
     *
     * @param float|null $value Temperature in °C (positive), null if not provided
     * @return static This entity
     */
    public function setDenaturationTempC(?float $value): static
    {
        $this->denaturationTempC = $value;

        return $this;
    }

    /**
     * Denaturation duration.
     *
     * @return int|null Duration in seconds, null if not provided
     */
    public function getDenaturationSeconds(): ?int
    {
        return $this->denaturationSeconds;
    }

    /**
     * Sets the denaturation duration.
     *
     * @param int|null $value Duration in seconds (positive), null if not provided
     * @return static This entity
     */
    public function setDenaturationSeconds(?int $value): static
    {
        $this->denaturationSeconds = $value;

        return $this;
    }

    /**
     * Annealing temperature.
     *
     * @return float|null Temperature in °C, null if not provided
     */
    public function getAnnealingTempC(): ?float
    {
        return $this->annealingTempC;
    }

    /**
     * Sets the annealing temperature.
     *
     * @param float|null $value Temperature in °C (positive), null if not provided
     * @return static This entity
     */
    public function setAnnealingTempC(?float $value): static
    {
        $this->annealingTempC = $value;

        return $this;
    }

    /**
     * Annealing duration.
     *
     * @return int|null Duration in seconds, null if not provided
     */
    public function getAnnealingSeconds(): ?int
    {
        return $this->annealingSeconds;
    }

    /**
     * Sets the annealing duration.
     *
     * @param int|null $value Duration in seconds (positive), null if not provided
     * @return static This entity
     */
    public function setAnnealingSeconds(?int $value): static
    {
        $this->annealingSeconds = $value;

        return $this;
    }

    /**
     * Extension temperature.
     *
     * @return float|null Temperature in °C, null if not provided
     */
    public function getExtensionTempC(): ?float
    {
        return $this->extensionTempC;
    }

    /**
     * Sets the extension temperature.
     *
     * @param float|null $value Temperature in °C (positive), null if not provided
     * @return static This entity
     */
    public function setExtensionTempC(?float $value): static
    {
        $this->extensionTempC = $value;

        return $this;
    }

    /**
     * Extension duration.
     *
     * @return int|null Duration in seconds, null if not provided
     */
    public function getExtensionSeconds(): ?int
    {
        return $this->extensionSeconds;
    }

    /**
     * Sets the extension duration.
     *
     * @param int|null $value Duration in seconds (positive), null if not provided
     * @return static This entity
     */
    public function setExtensionSeconds(?int $value): static
    {
        $this->extensionSeconds = $value;

        return $this;
    }

    /**
     * Reaction volume.
     *
     * @return float|null Volume in µL, null if not provided
     */
    public function getReactionVolumeUl(): ?float
    {
        return $this->reactionVolumeUl;
    }

    /**
     * Sets the reaction volume.
     *
     * @param float|null $value Volume in µL (positive), null if not provided
     * @return static This entity
     */
    public function setReactionVolumeUl(?float $value): static
    {
        $this->reactionVolumeUl = $value;

        return $this;
    }

    /**
     * Blocking rules: both oligos, exactly one template, and no oligo or template in quarantine.
     *
     * @param ExecutionContextInterface $context Validation context that receives the violations
     * @return void
     */
    #[Assert\Callback]
    public function validateReaction(ExecutionContextInterface $context): void
    {
        if (null === $this->forwardOligo) {
            $context->buildViolation('pcr.forward.required')->atPath('forwardOligo')->addViolation();
        }
        if (null === $this->reverseOligo) {
            $context->buildViolation('pcr.reverse.required')->atPath('reverseOligo')->addViolation();
        }
        if ((null === $this->templateGene) === (null === $this->templateVector)) {
            $context->buildViolation('pcr.template.exactly_one')->atPath('templateGene')->addViolation();
        }
        foreach ([
            'forwardOligo' => $this->forwardOligo,
            'reverseOligo' => $this->reverseOligo,
            'templateVector' => $this->templateVector,
        ] as $path => $parent) {
            if ($parent?->isQuarantined()) {
                $context->buildViolation('cloning.parent.quarantined')->atPath($path)->addViolation();
            }
        }
    }

    /**
     * Oligos, template and program: changing them alters the meaning of the inserts built on the PCR.
     *
     * @return array<string, scalar|null> Value by field name
     */
    public function significantValues(): array
    {
        return parent::significantValues() + [
            'forward' => $this->forwardOligo?->getId(),
            'reverse' => $this->reverseOligo?->getId(),
            'templateGene' => $this->templateGene?->getId(),
            'templateVector' => $this->templateVector?->getId(),
        ];
    }
}
