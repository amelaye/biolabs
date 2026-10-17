<?php
/**
 * Form of a fusion tag
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Form;

use App\Reference\Entity\Tag;
use App\Reference\Enum\TagPurpose;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class TagType extends PlainReferenceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder
            ->add('purpose', EnumType::class, [
                'class' => TagPurpose::class,
                'label' => 'reference.field.purpose',
                'placeholder' => 'reference.placeholder.choose',
                'choice_label' => static fn (TagPurpose $purpose): string => 'reference.tag_purpose.'.$purpose->value,
            ])
            ->add('dnaSequence', TextareaType::class, ['label' => 'reference.field.dna_sequence', 'required' => false, 'help' => 'reference.help.dna_sequence', 'attr' => ['rows' => 2, 'spellcheck' => 'false', 'autocomplete' => 'off']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Tag::class]);
    }
}
