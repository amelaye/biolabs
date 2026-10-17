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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder
            ->add('taxId', IntegerType::class, ['label' => 'reference.field.tax_id', 'required' => false])
            ->add('geneticCode', ChoiceType::class, ['label' => 'reference.field.genetic_code', 'choices' => self::choices(Organism::GENETIC_CODES), 'placeholder' => 'reference.placeholder.choose'])
            ->add('mitochondrialGeneticCode', ChoiceType::class, ['label' => 'reference.field.mitochondrial_genetic_code', 'required' => false, 'choices' => self::choices(Organism::MITOCHONDRIAL_GENETIC_CODES), 'placeholder' => 'reference.placeholder.unspecified']);
    }

    /**
     * @param list<int> $codes
     *
     * @return array<string, int> "11 - Bacterial, Archaeal and Plant Plastid" => 11
     */
    private static function choices(array $codes): array
    {
        $choices = [];
        foreach (Organism::geneticCodeNames($codes) as $code => $name) {
            $choices[$code.' - '.$name] = $code;
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Organism::class]);
    }
}
