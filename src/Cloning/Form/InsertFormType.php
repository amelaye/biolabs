<?php
/**
 * Form of an insert
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Form;

use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Pcr;
use App\Cloning\Enum\InsertType;
use App\Reference\Form\PlainReferenceType;
use App\Target\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class InsertFormType extends PlainReferenceType
{
    /**
     * Adds the type, the sites, the PCR and the product to the common fields. An archived or
     * quarantined entry is only offered if the insert already uses it.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options ('data' is the edited insert)
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var Insert $insert */
        $insert = $options['data'];

        $builder
            ->add('type', EnumType::class, [
                'class' => InsertType::class,
                'label' => 'cloning.field.insert_type',
                'placeholder' => 'reference.placeholder.choose',
                'choice_label' => static fn (InsertType $type): string => 'cloning.insert_type.'.$type->value,
            ])
            ->add('fivePrimeSite', TextType::class, ['label' => 'cloning.field.five_prime_site', 'required' => false, 'help' => 'cloning.help.site', 'attr' => ['maxlength' => 50, 'autocomplete' => 'off']])
            ->add('threePrimeSite', TextType::class, ['label' => 'cloning.field.three_prime_site', 'required' => false, 'attr' => ['maxlength' => 50, 'autocomplete' => 'off']])
            ->add('directional', CheckboxType::class, ['label' => 'cloning.field.directional', 'required' => false, 'help' => 'cloning.help.directional'])
            ->add('pcr', EntityType::class, [
                'class' => Pcr::class,
                'label' => 'cloning.field.pcr',
                'required' => false,
                'placeholder' => 'reference.placeholder.unspecified',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('p')->orderBy('p.nameKey'),
                'choice_filter' => static fn (?Pcr $pcr): bool => null !== $pcr && ($insert->getPcr() === $pcr || ($pcr->isActive() && !$pcr->isQuarantined())),
                'choice_label' => static fn (Pcr $pcr): string => $pcr->getName(),
            ])
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'label' => 'cloning.field.product',
                'required' => false,
                'placeholder' => 'reference.placeholder.unspecified',
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('p')->orderBy('p.nameKey'),
                'choice_filter' => static fn (?Product $product): bool => null !== $product && ($insert->getProduct() === $product || $product->isActive()),
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
        $resolver->setDefaults(['data_class' => Insert::class]);
    }
}
