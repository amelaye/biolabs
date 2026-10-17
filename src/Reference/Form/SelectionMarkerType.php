<?php
/**
 * Form of a selection marker
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Form;

use App\Reference\Entity\SelectionMarker;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class SelectionMarkerType extends PlainReferenceType
{
    /**
     * Adds the working and stock concentrations to the common fields.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder
            ->add('workingConcentrationUgMl', NumberType::class, ['label' => 'reference.field.working_concentration', 'required' => false, 'html5' => true, 'input' => 'number', 'attr' => ['step' => 'any', 'min' => 0]])
            ->add('stockConcentrationMgMl', NumberType::class, ['label' => 'reference.field.stock_concentration', 'required' => false, 'html5' => true, 'input' => 'number', 'attr' => ['step' => 'any', 'min' => 0]]);
    }

    /**
     * Binds the form to its entity class.
     *
     * @param OptionsResolver $resolver Resolver of the form options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SelectionMarker::class]);
    }
}
