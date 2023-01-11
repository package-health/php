<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Preference;

use Courier\Client\Producer;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Iterator;
use Kolekto\CollectionInterface;
use Kolekto\EagerCollection;
use Kolekto\LazyCollection;
use PackageHealth\PHP\Application\Message\Event\Preference\PreferenceCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Preference\PreferenceUpdatedEvent;
use PackageHealth\PHP\Domain\Preference\Preference;
use PackageHealth\PHP\Domain\Preference\PreferenceNotFoundException;
use PackageHealth\PHP\Domain\Preference\PreferenceRepositoryInterface;
use PackageHealth\PHP\Domain\Preference\PreferenceTypeEnum;
use PDO;
use PDOStatement;

final class PdoPreferenceRepository implements PreferenceRepositoryInterface {
  private PDO $pdo;
  private Producer $producer;
  private bool $lazyFetch = false;

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

  public function __construct(PDO $pdo, Producer $producer) {
    $this->pdo      = $pdo;
    $this->producer = $producer;
  }

  public function withLazyFetch(): static {
    $this->lazyFetch = true;

    return $this;
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

  public function all(array $orderBy = []): CollectionInterface {
    $order = '"category" ASC, "property" ASC';
    if (count($orderBy) > 0) {
      $order = [];
      foreach ($orderBy as $column => $sort) {
        if (in_array($sort, ['ASC', 'DESC']) === false) {
          throw new InvalidArgumentException('Order by requires "ASC" or "DESC" order');
        }

        $order[] = sprintf('"%s" %s', $column, strtoupper($sort));
      }

      $order = implode(', ', $order);
    }

    $stmt = $this->pdo->query(
      <<<SQL
        SELECT *
        FROM "preferences"
        ORDER BY {$order}
      SQL
    );

    if ($this->lazyFetch) {
      $this->lazyFetch = false;

      return new LazyCollection(
        (function (PDOStatement $stmt): Iterator {
          foreach ($stmt as $row) {
            yield $this->hydrate($row);
          }
        })->call($this, $stmt)
      );
    }

    return new EagerCollection(
      array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC))
    );
  }

  public function get(int $id): Preference {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "preferences"
          WHERE "id" = :id
          LIMIT 1
        SQL
      );
    }

    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
      throw new PreferenceNotFoundException("Preference '{$id}' not found");
    }

    return $this->hydrate($row);
  }

  public function find(
    array $query,
    int $limit = -1,
    int $offset = 0,
    array $orderBy = []
  ): CollectionInterface {
    $where = [];
    foreach (array_keys($query) as $col) {
      $where[] = sprintf(
        '"%1$s" = :%1$s',
        $col
      );
    }

    $where = implode(' AND ', $where);

    if ($limit === -1) {
      $limit = 'ALL';
    }

    $order = '"category" ASC, "property" ASC';
    if (count($orderBy) > 0) {
      $order = [];
      foreach ($orderBy as $column => $sort) {
        if (in_array($sort, ['ASC', 'DESC']) === false) {
          throw new InvalidArgumentException('Order by requires "ASC" or "DESC" order');
        }

        $order[] = sprintf('"%s" %s', $column, strtoupper($sort));
      }

      $order = implode(', ', $order);
    }

    $stmt = $this->pdo->prepare(
      <<<SQL
        SELECT *
        FROM "preferences"
        WHERE {$where}
        ORDER BY {$order}
        LIMIT {$limit}
        OFFSET {$offset}
      SQL
    );

    $stmt->execute($query);

    if ($this->lazyFetch) {
      $this->lazyFetch = false;

      return new LazyCollection(
        (function (PDOStatement $stmt): Iterator {
          foreach ($stmt as $row) {
            yield $this->hydrate($row);
          }
        })->call($this, $stmt)
      );
    }

    return new EagerCollection(
      array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC))
    );
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
          RETURNING *
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

    $preference = $this->hydrate($stmt->fetch(PDO::FETCH_ASSOC));

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
            "value" = :value,
            "type" = :type,
            "updated_at" = :updated_at
          WHERE "id" = :id
          RETURNING *
        SQL
      );
    }

    if ($preference->getId() === null) {
      throw new InvalidArgumentException();
    }

    if ($preference->isDirty()) {
      $stmt->execute(
        [
          'id'         => $preference->getId(),
          'value'      => $preference->getValueAsString(),
          'type'       => $preference->getType()->value,
          'updated_at' => $preference->getUpdatedAt()?->format(DateTimeInterface::ATOM)
        ]
      );

      $preference = $this->hydrate($stmt->fetch(PDO::FETCH_ASSOC));

      $this->producer->sendEvent(
        new PreferenceUpdatedEvent($preference)
      );

      return $preference;
    }

    return $preference;
  }
}
