<?php
/**
 * Form of a PCR
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Form;

use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use App\Cloning\Entity\Vector;
use App\Reference\Form\PlainReferenceType;
use App\Target\Entity\Gene;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class PcrFormType extends PlainReferenceType
{
    /**
     * Adds the oligos, the template and the thermal program to the common fields. An archived or
     * quarantined entry is only offered if the PCR already uses it.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options ('data' is the edited PCR)
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var Pcr $pcr */
        $pcr = $options['data'];

        $builder
            ->add('forwardOligo', EntityType::class, $this->selector(Oligo::class, 'cloning.field.forward_oligo', $pcr->getForwardOligo(), true))
            ->add('reverseOligo', EntityType::class, $this->selector(Oligo::class, 'cloning.field.reverse_oligo', $pcr->getReverseOligo(), true))
            ->add('templateGene', EntityType::class, $this->selector(Gene::class, 'cloning.field.template_gene', $pcr->getTemplateGene(), false) + ['help' => 'cloning.help.template'])
            ->add('templateVector', EntityType::class, $this->selector(Vector::class, 'cloning.field.template_vector', $pcr->getTemplateVector(), false))
            ->add('cycles', IntegerType::class, ['label' => 'cloning.field.cycles', 'required' => false, 'attr' => ['min' => 1]])
            ->add('denaturationTempC', NumberType::class, $this->number('cloning.field.denaturation_temp'))
            ->add('denaturationSeconds', IntegerType::class, ['label' => 'cloning.field.denaturation_seconds', 'required' => false, 'attr' => ['min' => 1]])
            ->add('annealingTempC', NumberType::class, $this->number('cloning.field.annealing_temp'))
            ->add('annealingSeconds', IntegerType::class, ['label' => 'cloning.field.annealing_seconds', 'required' => false, 'attr' => ['min' => 1]])
            ->add('extensionTempC', NumberType::class, $this->number('cloning.field.extension_temp'))
            ->add('extensionSeconds', IntegerType::class, ['label' => 'cloning.field.extension_seconds', 'required' => false, 'attr' => ['min' => 1]])
            ->add('reactionVolumeUl', NumberType::class, $this->number('cloning.field.reaction_volume'));
    }

    /**
     * Binds the form to its entity class.
     *
     * @param OptionsResolver $resolver Resolver of the form options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Pcr::class]);
    }

    /**
     * Options of a selector of a parent entity: archived and quarantined entries are hidden unless already chosen.
     *
     * @param class-string $class Entity class of the parent
     * @param string $label Translation key of the label
     * @param object|null $current Parent currently set
     * @param bool $required Whether the field is mandatory
     * @return array<string, mixed> The EntityType options
     */
    private function selector(string $class, string $label, ?object $current, bool $required): array
    {
        return [
            'class' => $class,
            'label' => $label,
            'required' => $required,
            'placeholder' => 'reference.placeholder.choose',
            'query_builder' => static fn ($repository) => $repository->createQueryBuilder('e')->orderBy('e.nameKey'),
            'choice_filter' => static fn (?object $entry): bool => null !== $entry
                && $current === $entry
                || (null !== $entry && $entry->isActive() && !(method_exists($entry, 'isQuarantined') && $entry->isQuarantined())),
            'choice_label' => static fn (object $entry): string => $entry->getName(),
        ];
    }

    /**
     * Options of a decimal field.
     *
     * @param string $label Translation key of the label
     * @return array<string, mixed> The NumberType options
     */
    private function number(string $label): array
    {
        return ['label' => $label, 'required' => false, 'html5' => true, 'input' => 'number', 'attr' => ['step' => 'any', 'min' => 0]];
    }
}
