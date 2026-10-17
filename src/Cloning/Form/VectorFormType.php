<?php
/**
 * Form of a vector
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Form;

use App\Cloning\Entity\Vector;
use App\Cloning\Enum\TagCapabilityState;
use App\Reference\Entity\Promoter;
use App\Reference\Entity\ReplicationOrigin;
use App\Reference\Entity\SelectionMarker;
use App\Reference\Entity\Supplier;
use App\Reference\Entity\Tag;
use App\Reference\Form\PlainReferenceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class VectorFormType extends PlainReferenceType
{
    /**
     * Adds the references, the optional circular sequence and the tag capabilities to the common fields.
     * An archived reference is only offered if the vector already uses it.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options ('data' is the edited vector)
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var Vector $vector */
        $vector = $options['data'];

        $builder
            ->add('supplier', EntityType::class, $this->referenceOptions(Supplier::class, 'cloning.field.supplier', $vector->getSupplier()))
            ->add('marker', EntityType::class, $this->referenceOptions(SelectionMarker::class, 'cloning.field.marker', $vector->getMarker()))
            ->add('origin', EntityType::class, $this->referenceOptions(ReplicationOrigin::class, 'cloning.field.origin', $vector->getOrigin()))
            ->add('promoter', EntityType::class, $this->referenceOptions(Promoter::class, 'cloning.field.promoter', $vector->getPromoter()))
            ->add('sequenceInput', TextareaType::class, [
                'label' => 'cloning.field.vector_sequence',
                'help' => 'cloning.help.vector_sequence',
                'required' => false,
                'attr' => ['rows' => 8, 'spellcheck' => 'false', 'autocomplete' => 'off', 'class' => 'sequence'],
            ])
            ->add('tagState', EnumType::class, [
                'class' => TagCapabilityState::class,
                'label' => 'cloning.field.tag_state',
                'help' => 'cloning.help.tag_state',
                'choice_label' => static fn (TagCapabilityState $state): string => 'cloning.tag_state.'.$state->value,
            ])
            ->add('nTerminalTag', EntityType::class, $this->tagOptions('cloning.field.n_terminal_tag', $vector->getNTerminalTag()))
            ->add('cTerminalTag', EntityType::class, $this->tagOptions('cloning.field.c_terminal_tag', $vector->getCTerminalTag()));
    }

    /**
     * Binds the form to its entity class.
     *
     * @param OptionsResolver $resolver Resolver of the form options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Vector::class]);
    }

    /**
     * Options of an optional selector of a reference list, archived entries hidden unless already chosen.
     *
     * @param class-string $class Entity class of the reference list
     * @param string $label Translation key of the label
     * @param object|null $current Reference currently set
     * @return array<string, mixed> The EntityType options
     */
    private function referenceOptions(string $class, string $label, ?object $current): array
    {
        return [
            'class' => $class,
            'label' => $label,
            'required' => false,
            'placeholder' => 'reference.placeholder.unspecified',
            'query_builder' => static fn ($repository) => $repository->createQueryBuilder('r')->orderBy('r.nameKey'),
            'choice_filter' => static fn (?object $entry): bool => null !== $entry && ($entry->isActive() || $current === $entry),
            'choice_label' => static fn (object $entry): string => $entry->getName(),
        ];
    }

    /**
     * Options of the selector of the tag at one end of the protein.
     *
     * @param string $label Translation key of the label
     * @param Tag|null $current Tag currently set
     * @return array<string, mixed> The EntityType options
     */
    private function tagOptions(string $label, ?Tag $current): array
    {
        return $this->referenceOptions(Tag::class, $label, $current);
    }
}
