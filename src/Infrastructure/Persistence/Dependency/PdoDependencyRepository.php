<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Dependency;

use App\Domain\Dependency\Dependency;
use App\Domain\Dependency\DependencyCollection;
use App\Domain\Dependency\DependencyNotFoundException;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class PdoDependencyRepository implements DependencyRepositoryInterface {
  private PDO $pdo;

  private function hydrate(array $data): Dependency {
    return new Dependency(
      $data['id'] ?? null,
      $data['version_id'],
      $data['name'],
      $data['constraint'],
      $data['development'],
      DependencyStatusEnum::tryFrom($data['status']),
      new DateTimeImmutable($data['created_at']),
      $data['updated_at'] === null ? null : new DateTimeImmutable($data['updated_at'])
    );
  }

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function create(
    int $versionId,
    string $name,
    string $constraint,
    bool $development = false,
    DependencyStatusEnum $status = DependencyStatusEnum::Unknown,
  ): Dependency {
    return $this->save(
      new Dependency(
        null,
        $versionId,
        $name,
        $constraint,
        $development,
        $status,
        new DateTimeImmutable()
      )
    );
  }

  public function all(): DependencyCollection {
    return new DependencyCollection();
  }

  public function get(int $id): Dependency {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "dependencies"
          WHERE "id" = :id
        SQL
      );
    }

    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() === 0) {
      throw new DependencyNotFoundException("Dependency '${id}' not found");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $this->hydrate($row);
  }

  public function find(array $query): DependencyCollection {
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

    $stmt = $this->pdo->prepare(
      <<<SQL
        SELECT *
        FROM "dependencies"
        WHERE ${where}
        ORDER BY "name" ASC
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

    return new Dependency(
      (int)$this->pdo->lastInsertId('dependencies_id_seq'),
      $dependency->getVersionId(),
      $dependency->getName(),
      $dependency->getConstraint(),
      $dependency->isDevelopment(),
      $dependency->getStatus(),
      $dependency->getCreatedAt()
    );
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

      return $this->get($dependency->getId());
    }

    return $dependency;
  }
}
