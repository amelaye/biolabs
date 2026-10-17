<?php
/**
 * Form of a strain
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Form;

use App\Reference\Entity\SelectionMarker;
use App\Reference\Entity\Strain;
use App\Reference\Enum\StrainUsage;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class StrainType extends PlainReferenceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var Strain $strain */
        $strain = $options['data'];

        $builder
            ->add('usage', EnumType::class, [
                'class' => StrainUsage::class,
                'label' => 'reference.field.usage',
                'required' => false,
                'placeholder' => 'reference.placeholder.unspecified',
                'choice_label' => static fn (StrainUsage $usage): string => 'reference.strain_usage.'.$usage->value,
            ])
            ->add('genotype', TextareaType::class, ['label' => 'reference.field.genotype', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('de3', CheckboxType::class, ['label' => 'reference.field.de3', 'required' => false])
            ->add('resistances', EntityType::class, [
                'class' => SelectionMarker::class,
                'label' => 'reference.field.resistances',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('m')->orderBy('m.nameKey'),
                // An archived marker is no longer offered, unless the strain already carries it.
                'choice_filter' => static fn (?SelectionMarker $marker): bool => null !== $marker
                    && ($marker->isActive() || $strain->getResistances()->contains($marker)),
                'choice_label' => static fn (SelectionMarker $marker): string => $marker->getName(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Strain::class]);
    }
}
