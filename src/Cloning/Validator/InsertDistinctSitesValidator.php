<?php
/**
 * Validator of InsertDistinctSites
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 18 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Validator;

use App\Cloning\Entity\Insert;
use App\Cloning\Service\VectorSiteCounter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * A directional insert needs two ends cut by two different enzymes. Names are compared by the
 * canonical name of the biophp catalogue (aliases and isoschizomers collapse, case is ignored);
 * the stored names are not changed.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class InsertDistinctSitesValidator extends ConstraintValidator
{
    /**
     * InsertDistinctSitesValidator constructor.
     *
     * @param VectorSiteCounter $sites Gives the canonical name of an enzyme
     */
    public function __construct(private readonly VectorSiteCounter $sites)
    {
    }

    /**
     * Refuses a directional insert whose two ends are the same enzyme.
     *
     * @param mixed $value The insert to validate
     * @param Constraint $constraint The InsertDistinctSites constraint
     * @return void
     * @throws UnexpectedTypeException If the constraint is of another type
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof InsertDistinctSites) {
            throw new UnexpectedTypeException($constraint, InsertDistinctSites::class);
        }
        if (!$value instanceof Insert || !$value->isDirectional()) {
            return;
        }
        $five = $value->getFivePrimeSite();
        $three = $value->getThreePrimeSite();
        if (null === $five || null === $three) {
            return;
        }
        $fiveKey = strtoupper($this->sites->canonicalName($five) ?? $five);
        $threeKey = strtoupper($this->sites->canonicalName($three) ?? $three);
        if ($fiveKey === $threeKey) {
            $this->context->buildViolation($constraint->message)->atPath('threePrimeSite')->addViolation();
        }
    }
}
