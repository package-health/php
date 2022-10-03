<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Package;

use Courier\Client\Producer;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Iterator;
use Kolekto\CollectionInterface;
use Kolekto\EagerCollection;
use Kolekto\LazyCollection;
use PackageHealth\PHP\Application\Message\Event\Package\PackageCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Package\PackageUpdatedEvent;
use PackageHealth\PHP\Domain\Package\Package;
use PackageHealth\PHP\Domain\Package\PackageNotFoundException;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PDO;
use PDOStatement;

final class PdoPackageRepository implements PackageRepositoryInterface {
  private PDO $pdo;
  private Producer $producer;
  private bool $lazyFetch = false;

  /**
   * @param array{
   *   id?: int,
   *   name: string,
   *   description: string,
   *   latest_version: string,
   *   url: string,
   *   created_at: string,
   *   updated_at: string|null
   * } $data
   */
  private function hydrate(array $data): Package {
    return new Package(
      $data['id'] ?? null,
      $data['name'],
      $data['description'],
      $data['latest_version'],
      $data['url'],
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
    string $name,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Package {
    return $this->save(
      new Package(
        null,
        $name,
        '',
        '',
        '',
        $createdAt
      )
    );
  }

  public function all(array $orderBy = []): CollectionInterface {
    $order = '"name" ASC';
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
        FROM "packages"
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

  public function findPopular(int $limit = 10): CollectionInterface {
    $stmt = $this->pdo->query(
      <<<SQL
        SELECT *
        FROM "packages"
        INNER JOIN (
          SELECT "dependencies"."name", COUNT("dependencies".*) AS "total"
          FROM "packages"
          INNER JOIN "versions" ON (
            "versions"."package_id" = "packages"."id" AND
            "versions"."number" = "packages"."latest_version"
          )
          INNER JOIN "dependencies" ON ("dependencies"."version_id" = "versions"."id")
          WHERE "packages"."latest_version" != '' AND "versions"."release" IS TRUE
          GROUP BY "dependencies"."name"
          ORDER BY "total" DESC
          LIMIT {$limit}
        ) AS "popular" ON ("popular"."name" = "packages"."name")
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

  public function exists(string $name): bool {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT 1
          FROM "packages"
          WHERE "name" = :name
          LIMIT 1
        SQL
      );
    }

    $stmt->execute(['name' => $name]);

    return $stmt->fetchColumn() === 1;
  }

  public function get(int $id): Package {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "packages"
          WHERE "id" = :id
          LIMIT 1
        SQL
      );
    }

    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
      throw new PackageNotFoundException("Package '{$id}' not found");
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

    $order = '"name" ASC';
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
        FROM "packages"
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

  public function findMatching(array $query, array $orderBy = []): CollectionInterface {
    $where = [];
    foreach (array_keys($query) as $col) {
      $where[] = sprintf(
        '"%1$s" ILIKE :%1$s',
        $col
      );
    }

    $where = implode(' AND ', $where);

    $order = '"name" ASC';
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
        FROM "packages"
        WHERE {$where}
        ORDER BY {$order}
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

  public function save(Package $package): Package {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          INSERT INTO "packages"
          ("name", "description", "latest_version", "url", "created_at")
          VALUES
          (:name, :description, :latest_version, :url, :created_at)
        SQL
      );
    }

    if ($package->getId() !== null) {
      throw new InvalidArgumentException(
      );
    }

    $stmt->execute(
      [
        'name'           => $package->getName(),
        'description'    => $package->getDescription(),
        'latest_version' => $package->getLatestVersion(),
        'url'            => $package->getUrl(),
        'created_at'     => $package->getCreatedAt()->format(DateTimeInterface::ATOM)
      ]
    );

    $package = new Package(
      (int)$this->pdo->lastInsertId('packages_id_seq'),
      $package->getName(),
      $package->getDescription(),
      $package->getLatestVersion(),
      $package->getUrl(),
      $package->getCreatedAt()
    );

    $this->producer->sendEvent(
      new PackageCreatedEvent($package)
    );

    return $package;
  }

  public function update(Package $package): Package {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          UPDATE "packages"
          SET
            "description" = :description,
            "latest_version" = :latest_version,
            "url" = :url,
            "updated_at" = :updated_at
          WHERE "id" = :id
        SQL
      );
    }

    if ($package->getId() === null) {
      throw InvalidArgumentException();
    }

    if ($package->isDirty()) {
      $stmt->execute(
        [
          'id'             => $package->getId(),
          'description'    => $package->getDescription(),
          'latest_version' => $package->getLatestVersion(),
          'url'            => $package->getUrl(),
          'updated_at'     => $package->getUpdatedAt()?->format(DateTimeInterface::ATOM)
        ]
      );

      $package = $this->get($package->getId());

      $this->producer->sendEvent(
        new PackageUpdatedEvent($package)
      );

      return $package;
    }

    return $package;
  }
}
