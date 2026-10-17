<?php
/**
 * Validator of OligoSequenceLocked
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Validator;

use App\Cloning\Entity\Oligo;
use App\Cloning\Service\OligoLock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Compares the sequence of a stored oligo with the one it had when it was loaded: a different
 * sequence is refused as soon as a PCR uses the oligo (the PCR would silently change meaning).
 * This gives a readable form error; {@see \App\Cloning\Service\OligoLockSubscriber} enforces the same rule on any write path.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class OligoSequenceLockedValidator extends ConstraintValidator
{
    /**
     * OligoSequenceLockedValidator constructor.
     *
     * @param EntityManagerInterface $em Gives the original data of the oligo
     * @param OligoLock $lock Counts the PCRs that use the oligo
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OligoLock $lock,
    ) {
    }

    /**
     * Refuses a changed sequence when a PCR uses the oligo.
     *
     * @param mixed $value The oligo to validate
     * @param Constraint $constraint The OligoSequenceLocked constraint
     * @return void
     * @throws UnexpectedTypeException If the constraint is of another type
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof OligoSequenceLocked) {
            throw new UnexpectedTypeException($constraint, OligoSequenceLocked::class);
        }
        if (!$value instanceof Oligo || null === $value->getId()) {
            return;
        }

        $original = $this->em->getUnitOfWork()->getOriginalEntityData($value)['sequence'] ?? null;
        if (null === $original || $original === $value->getSequence()) {
            return;
        }

        $uses = $this->lock->pcrCount($value);
        if ($uses > 0) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ count }}', (string) $uses)
                ->atPath('sequenceInput')
                ->addViolation();
        }
    }
}
