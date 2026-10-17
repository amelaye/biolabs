<?php
/**
 * Form of a gene
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Form;

use App\Reference\Entity\Organism;
use App\Reference\Form\PlainReferenceType;
use App\Target\Entity\Gene;
use App\Target\Enum\SourceDatabase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class GeneFormType extends PlainReferenceType
{
    /**
     * Adds the organism, product name, source database and CDS to the common fields. An archived organism is only offered if the gene already uses it.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options ('data' is the edited gene)
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var Gene $gene */
        $gene = $options['data'];

        $builder
            ->add('organism', EntityType::class, [
                'class' => Organism::class,
                'label' => 'target.field.organism',
                'placeholder' => 'reference.placeholder.choose',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('o')->orderBy('o.nameKey'),
                // An archived organism stays on the genes that already use it, but is not offered to others.
                'choice_filter' => static fn (?Organism $organism): bool => null !== $organism && ($organism->isActive() || $gene->getOrganism() === $organism),
                'choice_label' => static fn (Organism $organism): string => $organism->getName(),
            ])
            ->add('productName', TextType::class, ['label' => 'target.field.product_name', 'required' => false, 'attr' => ['maxlength' => 50]])
            ->add('sourceDatabase', EnumType::class, [
                'class' => SourceDatabase::class,
                'label' => 'target.field.source_database',
                'required' => false,
                'placeholder' => 'reference.placeholder.unspecified',
                'choice_label' => static fn (SourceDatabase $database): string => 'target.source_database.'.$database->value,
            ])
            ->add('sourceDefinition', TextareaType::class, ['label' => 'target.field.source_definition', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('cds', TextareaType::class, [
                'label' => 'target.field.cds',
                'help' => 'target.help.cds',
                'attr' => ['rows' => 10, 'spellcheck' => 'false', 'autocomplete' => 'off', 'class' => 'sequence'],
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
        $resolver->setDefaults(['data_class' => Gene::class]);
    }
}
