<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*************************************************************************************/

namespace Comment\Model\Base;

/**
 * Stand-in for the Propel base class of Comment\Model\Comment, for unit tests only.
 *
 * Propel builds the real one into var/propel/<env>/model from Config/schema.xml when the
 * module is activated, so it is absent from the repository and from any checkout that has
 * not been installed. The columns and the accessor signatures below are copied from that
 * generated class: nullable everywhere, `static` returned by every setter, TINYINT and
 * INTEGER columns exposed as `?int`, which is what makes a `true` passed to setVerified()
 * a TypeError in production.
 *
 * save() records the call rather than writing, so a test can assert what the module decided
 * to persist without a database.
 */
class Comment
{
    protected ?int $id = null;
    protected ?string $username = null;
    protected ?int $customer_id = null;
    protected ?string $ref = null;
    protected ?int $ref_id = null;
    protected ?string $email = null;
    protected ?string $title = null;
    protected ?string $content = null;
    protected ?int $rating = null;
    protected ?int $status = 0;
    protected ?int $verified = null;
    protected ?int $abuse = null;
    protected ?string $locale = null;
    protected string|int|\DateTimeInterface|null $created_at = null;
    protected string|int|\DateTimeInterface|null $updated_at = null;

    /** How many times save() was called on this object. */
    public int $saveCount = 0;

    /** How many times delete() was called on this object. */
    public int $deleteCount = 0;

    private bool $new = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $v = null): static
    {
        $this->id = $v;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $v = null): static
    {
        $this->username = $v;

        return $this;
    }

    public function getCustomerId(): ?int
    {
        return $this->customer_id;
    }

    public function setCustomerId(?int $v = null): static
    {
        $this->customer_id = $v;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $v = null): static
    {
        $this->ref = $v;

        return $this;
    }

    public function getRefId(): ?int
    {
        return $this->ref_id;
    }

    public function setRefId(?int $v = null): static
    {
        $this->ref_id = $v;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $v = null): static
    {
        $this->email = $v;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $v = null): static
    {
        $this->title = $v;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $v = null): static
    {
        $this->content = $v;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $v = null): static
    {
        $this->rating = $v;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $v = null): static
    {
        $this->status = $v;

        return $this;
    }

    public function getVerified(): ?int
    {
        return $this->verified;
    }

    public function setVerified(?int $v = null): static
    {
        $this->verified = $v;

        return $this;
    }

    public function getAbuse(): ?int
    {
        return $this->abuse;
    }

    public function setAbuse(?int $v = null): static
    {
        $this->abuse = $v;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $v = null): static
    {
        $this->locale = $v;

        return $this;
    }

    public function getCreatedAt(?string $format = null): string|\DateTimeInterface|null
    {
        return $this->created_at;
    }

    public function setCreatedAt(string|int|\DateTimeInterface|null $v = null): static
    {
        $this->created_at = $v;

        return $this;
    }

    public function getUpdatedAt(?string $format = null): string|\DateTimeInterface|null
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(string|int|\DateTimeInterface|null $v = null): static
    {
        $this->updated_at = $v;

        return $this;
    }

    public function isNew(): bool
    {
        return $this->new;
    }

    public function setNew(bool $new): void
    {
        $this->new = $new;
    }

    public function save(?object $con = null): int
    {
        ++$this->saveCount;

        if ($this->new) {
            $this->id ??= 1;
            $this->new = false;
        }

        return 1;
    }

    public function delete(?object $con = null): void
    {
        ++$this->deleteCount;
    }
}
