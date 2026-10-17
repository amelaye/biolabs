<?php
/**
 * Warnings on genes and products (reading frame, start/stop codons)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Service;

use Amelaye\BioPHP\Domain\Sequence\ValueObject\DnaSequence;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use App\Target\Enum\ProductType;

/**
 * Non-blocking biological checks. Stops are read under the organism's genetic code, so the
 * result is only as good as the organism's table; they are warnings because a stop inside a CDS
 * can be a legitimate readthrough, a selenocysteine or a wrong table.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class TargetChecks
{
    /**
     * TargetChecks constructor.
     *
     * @param SequenceAnalysis $analysis Translation under the organism's genetic code
     */
    public function __construct(private readonly SequenceAnalysis $analysis)
    {
    }

    /**
     * Non-blocking checks on a gene: start codon, ambiguity codes, final stop codon and in-frame stops
     * read under the organism's genetic code.
     *
     * @param Gene $gene Gene to check
     * @return list<array{key: string, params: array<string, scalar>}> Translation key and parameters of each warning
     */
    public function forGene(Gene $gene): array
    {
        $cds = $gene->getCdsSequence();
        if ('' === $cds || !DnaSequence::isValid($cds) || 0 !== \strlen($cds) % 3) {
            return [];
        }

        $warnings = [];
        if (!str_starts_with($cds, 'ATG')) {
            $warnings[] = ['key' => 'target.warning.no_start_codon', 'params' => []];
        }
        if (1 === preg_match('/[^ACGT]/', $cds)) {
            $warnings[] = ['key' => 'target.warning.ambiguity_codes', 'params' => []];
        }

        $protein = $this->analysis->geneProtein($gene);
        if (null !== $protein) {
            $endsWithStop = str_ends_with($protein, '*');
            if (!$endsWithStop) {
                $warnings[] = ['key' => 'target.warning.no_stop_codon', 'params' => []];
            }
            $internal = substr_count($protein, '*') - ($endsWithStop ? 1 : 0);
            if ($internal > 0) {
                $warnings[] = ['key' => 'target.warning.internal_stop', 'params' => ['%count%' => $internal]];
            }
        }

        return $warnings;
    }

    /**
     * Non-blocking checks on a product: a protein window that contains a stop codon.
     *
     * @param Product $product Product to check
     * @return list<array{key: string, params: array<string, scalar>}> Translation key and parameters of each warning
     */
    public function forProduct(Product $product): array
    {
        if (ProductType::Protein !== $product->getType()) {
            return [];
        }
        $dna = $this->analysis->productDna($product);
        $code = $product->getGene()?->getOrganism()?->getGeneticCode();
        if (null === $dna || null === $code || 0 !== \strlen($dna) % 3) {
            return [];
        }

        return str_contains($this->analysis->translate($dna, $code), '*')
            ? [['key' => 'target.warning.window_has_stop', 'params' => []]]
            : [];
    }
}
