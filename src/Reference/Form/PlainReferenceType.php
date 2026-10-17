<?php
/**
 * Form of the entries that only have a name and notes
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Form;

use App\Reference\Entity\AbstractReference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Name and notes, shared by every reference form.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
class PlainReferenceType extends AbstractType
{
    /**
     * Builds the name and notes fields, shared by every reference form.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'reference.field.name', 'attr' => ['maxlength' => 100, 'autofocus' => true]])
            ->add('notes', TextareaType::class, ['label' => 'reference.field.notes', 'required' => false, 'attr' => ['rows' => 3]]);
    }

    /**
     * Binds the form to its entity class.
     *
     * @param OptionsResolver $resolver Resolver of the form options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AbstractReference::class]);
    }
}
