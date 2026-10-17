<?php
/**
 * Form of an organism
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Form;

use App\Reference\Entity\Organism;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class OrganismType extends PlainReferenceType
{
    /**
     * Adds the taxid and the genetic code tables (names from biophp) to the common fields.
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Form options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder
            ->add('taxId', IntegerType::class, ['label' => 'reference.field.tax_id', 'required' => false])
            ->add('geneticCode', ChoiceType::class, ['label' => 'reference.field.genetic_code', 'choices' => self::choices(Organism::GENETIC_CODES), 'placeholder' => 'reference.placeholder.choose'])
            ->add('mitochondrialGeneticCode', ChoiceType::class, ['label' => 'reference.field.mitochondrial_genetic_code', 'required' => false, 'choices' => self::choices(Organism::MITOCHONDRIAL_GENETIC_CODES), 'placeholder' => 'reference.placeholder.unspecified']);
    }

    /**
     * Choices of a genetic code select.
     *
     * @param list<int> $codes NCBI table numbers
     * @return array<string, int> Label ("11 - Bacterial, Archaeal and Plant Plastid") => table number
     */
    private static function choices(array $codes): array
    {
        $choices = [];
        foreach (Organism::geneticCodeNames($codes) as $code => $name) {
            $choices[$code.' - '.$name] = $code;
        }

        return $choices;
    }

    /**
     * Binds the form to its entity class.
     *
     * @param OptionsResolver $resolver Resolver of the form options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Organism::class]);
    }
}
