<?php
/**
 * Orchestration of the biophp/biotools calls used by the targets
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Service;

use Amelaye\BioPHP\Domain\Sequence\Interfaces\SequenceInterface;
use Amelaye\BioPHP\Domain\Sequence\ValueObject\DnaSequence;
use Amelaye\BioPHP\Domain\Tools\Interfaces\AlternateGeneticCodeTranslatorInterface;
use Amelaye\BioTools\Service\ProteinPropertiesManager;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use App\Target\Enum\ProductType;

/**
 * Thin layer over biophp and biotools: no biological algorithm lives here. It only chooses the
 * organism's genetic code, cuts the CDS by the product's coordinates and feeds the library.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class SequenceAnalysis
{
    /**
     * SequenceAnalysis constructor.
     *
     * @param AlternateGeneticCodeTranslatorInterface $translator biophp translator of a codon under an NCBI table
     * @param ProteinPropertiesManager $proteins biotools protein properties (molecular weight)
     * @param SequenceInterface $sequences biophp sequence manager (nucleic acid weight)
     */
    public function __construct(
        private readonly AlternateGeneticCodeTranslatorInterface $translator,
        private readonly ProteinPropertiesManager $proteins,
        private readonly SequenceInterface $sequences,
    ) {
    }

    /**
     * Translation of a coding sequence codon by codon under an NCBI genetic code table. A trailing incomplete codon is ignored.
     *
     * @param string $dna Coding sequence (IUPAC DNA, uppercase)
     * @param int $geneticCode NCBI table number supported by biophp
     * @return string One-letter protein; "*" is a stop and "X" an ambiguous codon
     * @throws \InvalidArgumentException If the table is not supported by biophp
     */
    public function translate(string $dna, int $geneticCode): string
    {
        $protein = '';
        foreach (str_split($dna, 3) as $codon) {
            if (3 === \strlen($codon)) {
                $protein .= $this->translator->translateCodon($codon, $geneticCode);
            }
        }

        return $protein;
    }

    /**
     * Protein encoded by the whole CDS of a gene, under its organism's genetic code.
     *
     * @param Gene $gene Gene to translate
     * @return string|null The protein (stop included), null if the gene has no organism or no valid CDS
     */
    public function geneProtein(Gene $gene): ?string
    {
        $code = $gene->getOrganism()?->getGeneticCode();
        if (null === $code || '' === $gene->getCdsSequence() || !DnaSequence::isValid($gene->getCdsSequence())) {
            return null;
        }

        return $this->translate($gene->getCdsSequence(), $code);
    }

    /**
     * DNA region of a product on the CDS of its gene.
     *
     * @param Product $product Product with its window (1-based, both ends included)
     * @return string|null The region, null if the gene has no valid CDS or the window is not inside it
     */
    public function productDna(Product $product): ?string
    {
        $window = $product->window();
        $gene = $product->getGene();
        if (null === $window || null === $gene || !DnaSequence::isValid($gene->getCdsSequence())) {
            return null;
        }
        [$start, $end] = $window;
        if ($start < 1 || $end > $gene->getCdsLength() || $start > $end) {
            return null;
        }

        return (new DnaSequence($gene->getCdsSequence()))->subSequence($start - 1, $end - $start + 1)->getValue();
    }

    /**
     * Everything shown on the page of a product, derived from its gene.
     *
     * @param Product $product Product to analyse
     * @return array{dna: string, rna: ?string, protein: ?string, mass: ?array{min: float, max: float}, unit: string}|null The sequences and the mass, null if no sequence can be derived
     */
    public function analyseProduct(Product $product): ?array
    {
        $dna = $this->productDna($product);
        if (null === $dna) {
            return null;
        }
        $code = $product->getGene()?->getOrganism()?->getGeneticCode();

        return match ($product->getType()) {
            ProductType::Protein => $this->proteinResult($dna, $code),
            ProductType::Rna => ['dna' => $dna, 'rna' => (new DnaSequence($dna))->toRna()->getValue(), 'protein' => null, 'mass' => $this->nucleicMass($dna, 'RNA'), 'unit' => 'Da'],
            default => ['dna' => $dna, 'rna' => null, 'protein' => null, 'mass' => $this->nucleicMass($dna, 'DNA'), 'unit' => 'Da'],
        };
    }

    /**
     * Molecular weight range of a protein (biotools).
     *
     * @param string $protein One-letter protein; a "*" stop is ignored
     * @return array{min: float, max: float}|null Weight in Da (the range spans B, Z and X residues), null when it cannot be computed
     */
    public function proteinMass(string $protein): ?array
    {
        $residues = str_replace('*', '', $protein);
        if ('' === $residues) {
            return null;
        }
        try {
            return $this->proteins->proteinMolecularWeightRange($this->proteins->aminoacidContent($residues));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Result block of a protein product.
     *
     * @param string $dna DNA region of the product
     * @param int|null $code NCBI genetic code table of the organism, null if unknown
     * @return array{dna: string, rna: null, protein: ?string, mass: ?array{min: float, max: float}, unit: string} The result
     */
    private function proteinResult(string $dna, ?int $code): array
    {
        $protein = null === $code ? null : $this->translate($dna, $code);

        return ['dna' => $dna, 'rna' => null, 'protein' => $protein, 'mass' => null === $protein ? null : $this->proteinMass($protein), 'unit' => 'Da'];
    }

    /**
     * Molecular weight range of a DNA or RNA fragment (biophp).
     *
     * @param string $dna DNA region
     * @param string $molType 'DNA' or 'RNA' (the fragment is transcribed first for RNA)
     * @return array{min: float, max: float}|null Weight in Da, null when it cannot be computed (ambiguity codes)
     */
    private function nucleicMass(string $dna, string $molType): ?array
    {
        try {
            $sequence = 'RNA' === $molType ? (new DnaSequence($dna))->toRna()->getValue() : $dna;

            return [
                'min' => $this->sequences->molwt('lowerlimit', $sequence, $molType, \strlen($sequence)),
                'max' => $this->sequences->molwt('upperlimit', $sequence, $molType, \strlen($sequence)),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
