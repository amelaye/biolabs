<?php
/**
 * Entity VectorTagCapability - tag a vector can fuse to the protein
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Entity;

use App\Cloning\Enum\TagPosition;
use App\Reference\Entity\Tag;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One tag capability of a vector (a pET-28a(+) has an N-terminal and a C-terminal one: at most
 * one per end, enforced by a unique constraint). It says
 * what the vector can provide; whether a construct really carries the tag depends on the sites
 * and on the reading frame, which are not computed in this version.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_cloning_vector_tag_capability')]
#[ORM\UniqueConstraint(name: 'uniq_bl_cloning_vector_tag_capability_position', columns: ['vector_id', 'position'])]
class VectorTagCapability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vector::class, inversedBy: 'tagCapabilities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Vector $vector;

    #[ORM\ManyToOne(targetEntity: Tag::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private Tag $tag;

    #[ORM\Column(type: 'string', length: 20, enumType: TagPosition::class)]
    private TagPosition $position;

    /**
     * VectorTagCapability constructor.
     *
     * @param Vector $vector Vector that offers the tag
     * @param Tag $tag Tag of the reference list
     * @param TagPosition $position N- or C-terminal
     */
    public function __construct(Vector $vector, Tag $tag, TagPosition $position)
    {
        $this->vector = $vector;
        $this->tag = $tag;
        $this->position = $position;
    }

    /**
     * Primary key.
     *
     * @return int|null The identifier, null until stored
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Vector that offers the tag.
     *
     * @return Vector The vector
     */
    public function getVector(): Vector
    {
        return $this->vector;
    }

    /**
     * Tag offered.
     *
     * @return Tag The tag
     */
    public function getTag(): Tag
    {
        return $this->tag;
    }

    /**
     * Replaces the tag offered at this end (the row is updated in place, so that the unique
     * constraint on vector and position is never violated by an insert-before-delete flush).
     *
     * @param Tag $tag New tag
     * @return static This entity
     */
    public function setTag(Tag $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Position of the tag on the protein.
     *
     * @return TagPosition N- or C-terminal
     */
    public function getPosition(): TagPosition
    {
        return $this->position;
    }
}
