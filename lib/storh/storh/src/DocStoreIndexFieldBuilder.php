<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class DocStoreIndexFieldBuilder
{
    public function __construct(
        private readonly DocStoreIndexManager $manager,
        private readonly string $field
    ) {
    }

    public function index(): DocStoreIndexManager
    {
        return $this->manager->define_field($this->field);
    }

    public function unique(): DocStoreIndexManager
    {
        return $this->manager->define_field($this->field, unique: true);
    }

    public function range(): DocStoreIndexManager
    {
        return $this->manager->define_field($this->field, range: true);
    }

    public function field(string $field): self
    {
        $this->index();

        return $this->manager->field($field);
    }

    public function sync(bool $rebuild = true): void
    {
        $this->index()->sync($rebuild);
    }
}
