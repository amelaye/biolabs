<?php
/**
 * Form of a complex
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Form;

use App\Reference\Form\PlainReferenceType;
use App\Target\Entity\Complex;
use App\Target\Entity\Gene;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ComplexFormType extends PlainReferenceType
{
    /**
     * Adds the genes of the complex to the common fields. An archived gene is only offered if the complex already contains it.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options ('data' is the edited complex)
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var Complex $complex */
        $complex = $options['data'];

        $builder->add('genes', EntityType::class, [
            'class' => Gene::class,
            'label' => 'target.field.genes',
            'multiple' => true,
            'required' => false,
            'by_reference' => false,
            'query_builder' => static fn ($repository) => $repository->createQueryBuilder('g')->orderBy('g.nameKey'),
            // An archived gene is no longer offered, unless the complex already contains it.
            'choice_filter' => static fn (?Gene $gene): bool => null !== $gene && ($gene->isActive() || $complex->getGenes()->contains($gene)),
            'choice_label' => static fn (Gene $gene): string => $gene->getName(),
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
        $resolver->setDefaults(['data_class' => Complex::class]);
    }
}
