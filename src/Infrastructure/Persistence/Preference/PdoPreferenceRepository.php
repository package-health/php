<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Preference;

use App\Application\Message\Event\Preference\PreferenceCreatedEvent;
use App\Application\Message\Event\Preference\PreferenceUpdatedEvent;
use App\Domain\Preference\Preference;
use App\Domain\Preference\PreferenceCollection;
use App\Domain\Preference\PreferenceNotFoundException;
use App\Domain\Preference\PreferenceRepositoryInterface;
use App\Domain\Preference\PreferenceTypeEnum;
use Courier\Client\Producer\ProducerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class PdoPreferenceRepository implements PreferenceRepositoryInterface {
  private PDO $pdo;
  private ProducerInterface $producer;

  /**
   * @param array{
   *   id?: int,
   *   category: string,
   *   property: string,
   *   value: string,
   *   type: string,
   *   created_at: string,
   *   updated_at: string|null
   * } $data
   */
  private function hydrate(array $data): Preference {
    return new Preference(
      $data['id'] ?? null,
      $data['category'],
      $data['property'],
      $data['value'],
      PreferenceTypeEnum::tryFrom($data['type']) ?? PreferenceTypeEnum::isString,
      new DateTimeImmutable($data['created_at']),
      $data['updated_at'] === null ? null : new DateTimeImmutable($data['updated_at'])
    );
  }

  public function __construct(PDO $pdo, ProducerInterface $producer) {
    $this->pdo      = $pdo;
    $this->producer = $producer;
  }

  public function create(
    string $category,
    string $property,
    string $value,
    PreferenceTypeEnum $type = PreferenceTypeEnum::isString,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Preference {
    return $this->save(
      new Preference(
        null,
        $category,
        $property,
        $value,
        $type,
        $createdAt
      )
    );
  }

  public function all(): PreferenceCollection {
    $stmt = $this->pdo->query(
      <<<SQL
        SELECT *
        FROM "preferences"
        ORDER BY "category" ASC, "property" ASC
      SQL
    );

    $preferenceCol = new PreferenceCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $preferenceCol->add($this->hydrate($row));
    }

    return $preferenceCol;
  }

  public function get(int $id): Preference {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "preferences"
          WHERE "id" = :id
        SQL
      );
    }

    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() === 0) {
      throw new PreferenceNotFoundException("Preference '${id}' not found");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $this->hydrate($row);
  }

  public function find(array $query): PreferenceCollection {
    $where = [];
    foreach (array_keys($query) as $col) {
      $where[] = sprintf(
        '"%1$s" = :%1$s',
        $col
      );
    }

    $where = implode(' AND ', $where);

    $stmt = $this->pdo->prepare(
      <<<SQL
        SELECT *
        FROM "preferences"
        WHERE ${where}
        ORDER BY "category" ASC, "property" ASC
      SQL
    );

    $stmt->execute($query);

    $preferenceCol = new PreferenceCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $preferenceCol->add($this->hydrate($row));
    }

    return $preferenceCol;
  }

  public function save(Preference $preference): Preference {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          INSERT INTO "preferences"
          ("category", "property", "value", "type", "created_at")
          VALUES
          (:category, :property, :value, :type, :created_at)
        SQL
      );
    }

    $stmt->execute(
      [
        'category'   => $preference->getCategory(),
        'property'   => $preference->getProperty(),
        'value'      => $preference->getValueAsString(),
        'type'       => $preference->getType()->value,
        'created_at' => $preference->getCreatedAt()->format(DateTimeInterface::ATOM)
      ]
    );

    $preference = new Preference(
      (int)$this->pdo->lastInsertId('preferences_id_seq'),
      $preference->getCategory(),
      $preference->getProperty(),
      $preference->getValueAsString(),
      $preference->getType(),
      $preference->getCreatedAt()
    );

    $this->producer->sendEvent(
      new PreferenceCreatedEvent($preference)
    );

    return $preference;
  }

  public function update(Preference $preference): Preference {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          UPDATE "preferences"
          SET
            "category" = :category,
            "property" = :property,
            "value" = :value,
            "type" = :type,
            "updated_at" = :updated_at
          WHERE "id" = :id
        SQL
      );
    }

    if ($preference->isDirty()) {
      $stmt->execute(
        [
          'id'         => $preference->getId(),
          'category'   => $preference->getCategory(),
          'property'   => $preference->getProperty(),
          'value'      => $preference->getValueAsString(),
          'type'       => $preference->getType()->value,
          'updated_at' => $preference->getUpdatedAt()?->format(DateTimeInterface::ATOM)
        ]
      );

      $preference = $this->get($preference->getId());

      $this->producer->sendEvent(
        new PreferenceUpdatedEvent($preference)
      );

      return $preference;
    }

    return $preference;
  }
}
