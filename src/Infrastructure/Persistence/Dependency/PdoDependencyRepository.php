<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Dependency;

use App\Application\Message\Event\Dependency\DependencyCreatedEvent;
use App\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use App\Domain\Dependency\Dependency;
use App\Domain\Dependency\DependencyCollection;
use App\Domain\Dependency\DependencyNotFoundException;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use Courier\Client\Producer\ProducerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class PdoDependencyRepository implements DependencyRepositoryInterface {
  private PDO $pdo;
  private ProducerInterface $producer;

  /**
   * @param array{
   *   id?: int,
   *   version_id: int,
   *   name: string,
   *   constraint: string,
   *   development: bool,
   *   status: string,
   *   created_at: string,
   *   updated_at: string|null
   * } $data
  */
  private function hydrate(array $data): Dependency {
    return new Dependency(
      $data['id'] ?? null,
      $data['version_id'],
      $data['name'],
      $data['constraint'],
      $data['development'],
      DependencyStatusEnum::tryFrom($data['status']) ?? DependencyStatusEnum::Unknown,
      new DateTimeImmutable($data['created_at']),
      $data['updated_at'] === null ? null : new DateTimeImmutable($data['updated_at'])
    );
  }

  public function __construct(PDO $pdo, ProducerInterface $producer) {
    $this->pdo      = $pdo;
    $this->producer = $producer;
  }

  public function create(
    int $versionId,
    string $name,
    string $constraint,
    bool $development = false,
    DependencyStatusEnum $status = DependencyStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Dependency {
    return $this->save(
      new Dependency(
        null,
        $versionId,
        $name,
        $constraint,
        $development,
        $status,
        $createdAt
      )
    );
  }

  public function all(): DependencyCollection {
    $stmt = $this->pdo->query(
      <<<SQL
        SELECT *
        FROM "dependencies"
        ORDER BY "created_at"
      SQL
    );

    $dependencyCol = new DependencyCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $dependencyCol->add($this->hydrate($row));
    }

    return $dependencyCol;
  }

  public function get(int $id): Dependency {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "dependencies"
          WHERE "id" = :id
          LIMIT 1
        SQL
      );
    }

    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() === 0) {
      throw new DependencyNotFoundException("Dependency '{$id}' not found");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $this->hydrate($row);
  }

  public function find(array $query, int $limit = -1, int $offset = 0): DependencyCollection {
    $where = [];
    $cols = array_keys($query);

    // handle "development" boolean column
    $devPos = array_search('development', $cols, true);
    if ($devPos !== false) {
      $where[] = sprintf(
        '"development" IS %s',
        $query['development'] ? 'TRUE' : 'FALSE'
      );
      unset($cols[$devPos], $query['development']);
    }

    foreach ($cols as $col) {
      $where[] = sprintf(
        '"%1$s" = :%1$s',
        $col
      );
    }

    $where = implode(' AND ', $where);

    if ($limit === -1) {
      $limit = 'ALL';
    }

    $stmt = $this->pdo->prepare(
      <<<SQL
        SELECT *
        FROM "dependencies"
        WHERE {$where}
        ORDER BY "name" ASC
        LIMIT {$limit}
        OFFSET {$offset}
      SQL
    );

    $stmt->execute($query);

    $dependencyCol = new DependencyCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $dependencyCol->add($this->hydrate($row));
    }

    return $dependencyCol;
  }

  public function save(Dependency $dependency): Dependency {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          INSERT INTO "dependencies"
          ("version_id", "name", "constraint", "development", "status", "created_at")
          VALUES
          (:version_id, :name, :constraint, :development, :status, :created_at)
        SQL
      );
    }

    $stmt->execute(
      [
        'version_id'  => $dependency->getVersionId(),
        'name'        => $dependency->getName(),
        'constraint'  => $dependency->getConstraint(),
        'development' => $dependency->isDevelopment() ? 1 : 0,
        'status'      => $dependency->getStatus()->getLabel(),
        'created_at'  => $dependency->getCreatedAt()->format(DateTimeInterface::ATOM)
      ]
    );

    $dependency = new Dependency(
      (int)$this->pdo->lastInsertId('dependencies_id_seq'),
      $dependency->getVersionId(),
      $dependency->getName(),
      $dependency->getConstraint(),
      $dependency->isDevelopment(),
      $dependency->getStatus(),
      $dependency->getCreatedAt()
    );

    $this->producer->sendEvent(
      new DependencyCreatedEvent($dependency)
    );

    return $dependency;
  }

  public function update(Dependency $dependency): Dependency {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          UPDATE "dependencies"
          SET
            "constraint" = :constraint,
            "development" = :development,
            "status" = :status,
            "updated_at" = :updated_at
          WHERE "id" = :id
        SQL
      );
    }

    if ($dependency->isDirty()) {
      $stmt->execute(
        [
          'id'          => $dependency->getId(),
          'constraint'  => $dependency->getConstraint(),
          'development' => $dependency->isDevelopment() ? 1 : 0,
          'status'      => $dependency->getStatus()->getLabel(),
          'updated_at'  => $dependency->getUpdatedAt()?->format(DateTimeInterface::ATOM)
        ]
      );

      $dependency = $this->get($dependency->getId());

      $this->producer->sendEvent(
        new DependencyUpdatedEvent($dependency)
      );

      return $dependency;
    }

    return $dependency;
  }
}
