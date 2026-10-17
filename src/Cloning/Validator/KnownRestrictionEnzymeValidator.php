<?php
/**
 * Validator of KnownRestrictionEnzyme
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Validator;

use Amelaye\BioPHP\Domain\Sequence\Interfaces\RestrictionEnzymeCatalogInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Refuses an enzyme name that the biophp catalogue does not know. The catalogue is the only
 * source of enzymes: biolabs keeps no list of its own.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class KnownRestrictionEnzymeValidator extends ConstraintValidator
{
    /**
     * KnownRestrictionEnzymeValidator constructor.
     *
     * @param RestrictionEnzymeCatalogInterface $catalog biophp restriction enzyme catalogue
     */
    public function __construct(private readonly RestrictionEnzymeCatalogInterface $catalog)
    {
    }

    /**
     * Refuses a non-empty enzyme name that is not in the catalogue.
     *
     * @param mixed $value Enzyme name, null or empty for none
     * @param Constraint $constraint The KnownRestrictionEnzyme constraint
     * @return void
     * @throws UnexpectedTypeException If the constraint is of another type
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof KnownRestrictionEnzyme) {
            throw new UnexpectedTypeException($constraint, KnownRestrictionEnzyme::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!\is_string($value) || null === $this->catalog->findByName($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ name }}', \is_string($value) ? mb_substr($value, 0, 50) : '')
                ->addViolation();
        }
    }
}
