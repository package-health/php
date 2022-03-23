<?php
declare(strict_types = 1);

namespace App\Domain\Preference;

use DateTimeImmutable;

interface PreferenceRepositoryInterface {

  public function create(
    string $category,
    string $property,
    string $value,
    PreferenceTypeEnum $type = PreferenceTypeEnum::isString,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Preference;

  public function all(): PreferenceCollection;

  /**
   * @throws \App\Domain\Preference\PreferenceNotFoundException
   */
  public function get(int $id): Preference;

  public function find(array $query): PreferenceCollection;

  public function save(Preference $preference): Preference;

  public function update(Preference $preference): Preference;
}
