<?php
/**
 * Validator of ConstructSites
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Validator;

use App\Cloning\Entity\Construct;
use App\Cloning\Service\CloningChecks;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Each enzyme of the insert is evaluated on its own against the vector (the legacy checked one
 * enzyme only when the other was fine). Without a vector sequence nothing can be checked and
 * nothing is refused: the construct is "not evaluable".
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ConstructSitesValidator extends ConstraintValidator
{
    /**
     * ConstructSitesValidator constructor.
     *
     * @param CloningChecks $checks Counts the cuts of each enzyme with biophp
     */
    public function __construct(private readonly CloningChecks $checks)
    {
    }

    /**
     * Refuses a construct whose insert enzymes do not cut the vector exactly once.
     *
     * @param mixed $value The construct to validate
     * @param Constraint $constraint The ConstructSites constraint
     * @return void
     * @throws UnexpectedTypeException If the constraint is of another type
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ConstructSites) {
            throw new UnexpectedTypeException($constraint, ConstructSites::class);
        }
        if (!$value instanceof Construct) {
            return;
        }

        foreach ($this->checks->siteProblems($value) as $problem) {
            $this->context->buildViolation('construct.site.cuts')
                ->setParameter('{{ enzyme }}', $problem['enzyme'])
                ->setParameter('{{ count }}', (string) $problem['count'])
                ->atPath('vector')
                ->addViolation();
        }
    }
}
