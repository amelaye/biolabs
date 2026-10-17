<?php
/**
 * Form of a construct
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Form;

use App\Cloning\Entity\Construct;
use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Vector;
use App\Reference\Form\PlainReferenceType;
use App\Target\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The status is derived by the application and has no field; the sequence is neither typed nor computed.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ConstructFormType extends PlainReferenceType
{
    /**
     * Adds the vector, the insert and the products to the common fields. An archived or quarantined
     * entry is only offered if the construct already uses it.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options ('data' is the edited construct)
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var Construct $construct */
        $construct = $options['data'];

        $builder
            ->add('vector', EntityType::class, [
                'class' => Vector::class,
                'label' => 'cloning.field.vector',
                'placeholder' => 'reference.placeholder.choose',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('v')->orderBy('v.nameKey'),
                'choice_filter' => static fn (?Vector $vector): bool => null !== $vector && ($construct->getVector() === $vector || ($vector->isActive() && !$vector->isQuarantined())),
                'choice_label' => static fn (Vector $vector): string => $vector->getName(),
            ])
            ->add('insert', EntityType::class, [
                'class' => Insert::class,
                'label' => 'cloning.field.insert',
                'placeholder' => 'reference.placeholder.choose',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('i')->orderBy('i.nameKey'),
                'choice_filter' => static fn (?Insert $insert): bool => null !== $insert && ($construct->getInsert() === $insert || ($insert->isActive() && !$insert->isQuarantined())),
                'choice_label' => static fn (Insert $insert): string => $insert->getName(),
            ])
            ->add('products', EntityType::class, [
                'class' => Product::class,
                'label' => 'cloning.field.products',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('p')->orderBy('p.nameKey'),
                'choice_filter' => static fn (?Product $product): bool => null !== $product && ($product->isActive() || $construct->getProducts()->contains($product)),
                'choice_label' => static fn (Product $product): string => $product->getName(),
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
        $resolver->setDefaults(['data_class' => Construct::class]);
    }
}
