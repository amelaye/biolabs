<?php
/**
 * Entity SelectionMarker - antibiotic or other selection marker
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Selection marker with the laboratory's working concentration.
 *
 * The units are fixed by the field names: working concentration in µg/mL,
 * stock solution concentration in mg/mL. The values are the laboratory's own choice:
 * they are only checked to be positive.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_selection_marker')]
#[ORM\UniqueConstraint(name: 'uniq_bl_selection_marker_name_key', columns: ['name_key'])]
class SelectionMarker extends AbstractReference
{
    /** Indicative (unsourced) plausibility range of a working concentration, in µg/mL. */
    public const WORKING_CONCENTRATION_USUAL_RANGE_UG_ML = [5.0, 500.0];

    /** Working concentration, µg/mL. */
    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Positive]
    private ?float $workingConcentrationUgMl = null;

    /** Concentration of the stock solution, mg/mL. */
    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Positive]
    private ?float $stockConcentrationMgMl = null;

    /**
     * Working concentration.
     *
     * @return float|null The concentration in µg/mL, null if not provided
     */
    public function getWorkingConcentrationUgMl(): ?float
    {
        return $this->workingConcentrationUgMl;
    }

    /**
     * Sets the working concentration.
     *
     * @param float|null $value Concentration in µg/mL (positive), null if not provided
     * @return static This entity
     */
    public function setWorkingConcentrationUgMl(?float $value): static
    {
        $this->workingConcentrationUgMl = $value;

        return $this;
    }

    /**
     * Concentration of the stock solution.
     *
     * @return float|null The concentration in mg/mL, null if not provided
     */
    public function getStockConcentrationMgMl(): ?float
    {
        return $this->stockConcentrationMgMl;
    }

    /**
     * Sets the concentration of the stock solution.
     *
     * @param float|null $value Concentration in mg/mL (positive), null if not provided
     * @return static This entity
     */
    public function setStockConcentrationMgMl(?float $value): static
    {
        $this->stockConcentrationMgMl = $value;

        return $this;
    }

    /**
     * Name and concentrations: changing them alters the meaning of what uses the marker.
     *
     * @return array<string, scalar|null> Value by field name
     */
    public function significantValues(): array
    {
        return parent::significantValues() + [
            'workingConcentrationUgMl' => $this->workingConcentrationUgMl,
            'stockConcentrationMgMl' => $this->stockConcentrationMgMl,
        ];
    }
}
