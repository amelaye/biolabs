<?php
/**
 * Validator of UniqueReferenceName
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Validator;

use App\Reference\Entity\AbstractReference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Refuses a name whose normalised key is already used by another entry of the same list,
 * whether that entry is active or archived (an archived duplicate is offered for restoration).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class UniqueReferenceNameValidator extends ConstraintValidator
{
    /**
     * UniqueReferenceNameValidator constructor.
     *
     * @param EntityManagerInterface $em Entity manager used to look for the other entry
     */
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Refuses a name whose normalised key is already used by another entry of the same list,
     * active or archived (an archived duplicate is offered for restoration).
     *
     * @param mixed $value The entry to validate
     * @param Constraint $constraint The UniqueReferenceName constraint
     * @return void
     * @throws UnexpectedTypeException If the constraint is of another type
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueReferenceName) {
            throw new UnexpectedTypeException($constraint, UniqueReferenceName::class);
        }
        if (!$value instanceof AbstractReference || '' === $value->getNameKey()) {
            return;
        }

        $existing = $this->em->getRepository($value::class)->findOneBy(['nameKey' => $value->getNameKey()]);
        if (null === $existing || $existing === $value) {
            return;
        }

        $this->context->buildViolation($existing->isActive() ? $constraint->message : $constraint->archivedMessage)
            ->setParameter('{{ name }}', $existing->getName())
            ->atPath('name')
            ->addViolation();
    }
}
