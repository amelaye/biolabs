<?php
/**
 * Form of an oligo
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Form;

use App\Cloning\Entity\Oligo;
use App\Reference\Entity\Supplier;
use App\Reference\Form\PlainReferenceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class OligoFormType extends PlainReferenceType
{
    /**
     * Adds the sequence and the supplier to the common fields. An archived supplier is only offered if the oligo already uses it.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options ('data' is the edited oligo)
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var Oligo $oligo */
        $oligo = $options['data'];

        $builder
            ->add('sequenceInput', TextareaType::class, [
                'label' => 'cloning.field.oligo_sequence',
                'help' => 'cloning.help.oligo_sequence',
                'attr' => ['rows' => 3, 'spellcheck' => 'false', 'autocomplete' => 'off', 'class' => 'sequence'],
            ])
            ->add('supplier', EntityType::class, [
                'class' => Supplier::class,
                'label' => 'cloning.field.supplier',
                'required' => false,
                'placeholder' => 'reference.placeholder.unspecified',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('s')->orderBy('s.nameKey'),
                'choice_filter' => static fn (?Supplier $supplier): bool => null !== $supplier && ($supplier->isActive() || $oligo->getSupplier() === $supplier),
                'choice_label' => static fn (Supplier $supplier): string => $supplier->getName(),
            ]);
    }

    /**
     * Binds the form to its entity class.
     *
     * @param OptionsResolver $resolver Resolver of the form options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Oligo::class]);
    }
}
