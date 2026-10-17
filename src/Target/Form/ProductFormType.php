<?php
/**
 * Form of a product
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Form;

use App\Reference\Form\PlainReferenceType;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use App\Target\Enum\ProductType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ProductFormType extends PlainReferenceType
{
    /**
     * Adds the type, gene and window to the common fields. An archived gene is only offered if the product already uses it.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options ('data' is the edited product)
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var Product $product */
        $product = $options['data'];

        $builder
            ->add('type', EnumType::class, [
                'class' => ProductType::class,
                'label' => 'target.field.product_type',
                'placeholder' => 'reference.placeholder.choose',
                'choice_label' => static fn (ProductType $type): string => 'target.product_type.'.$type->value,
            ])
            ->add('gene', EntityType::class, [
                'class' => Gene::class,
                'label' => 'target.field.gene',
                'placeholder' => 'reference.placeholder.choose',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('g')->orderBy('g.nameKey'),
                'choice_filter' => static fn (?Gene $gene): bool => null !== $gene && ($gene->isActive() || $product->getGene() === $gene),
                'choice_label' => static fn (Gene $gene): string => $gene->getName(),
            ])
            ->add('startNt', IntegerType::class, ['label' => 'target.field.start_nt', 'required' => false, 'help' => 'target.help.window', 'attr' => ['min' => 1]])
            ->add('endNt', IntegerType::class, ['label' => 'target.field.end_nt', 'required' => false, 'attr' => ['min' => 1]]);
    }

    /**
     * Binds the form to its entity class.
     *
     * @param OptionsResolver $resolver Resolver of the form options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }
}
