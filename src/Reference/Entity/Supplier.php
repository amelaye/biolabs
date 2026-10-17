<?php
/**
 * Entity Supplier - company supplying reagents, strains or oligos
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Supplier of the laboratory. No personal contact and no account number are stored.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_supplier')]
#[ORM\UniqueConstraint(name: 'uniq_bl_supplier_name_key', columns: ['name_key'])]
class Supplier extends AbstractReference
{
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Url(message: 'supplier.url.invalid', protocols: ['http', 'https'], requireTld: true)]
    private ?string $url = null;

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $url = null === $url ? null : trim($url);
        $this->url = '' === $url ? null : $url;

        return $this;
    }
}
