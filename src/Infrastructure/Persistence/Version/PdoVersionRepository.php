<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Version;

use App\Domain\Version\Version;
use App\Domain\Version\VersionNotFoundException;
use App\Domain\Version\VersionRepositoryInterface;
use App\Domain\Version\VersionStatusEnum;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class PdoVersionRepository implements VersionRepositoryInterface {
  private PDO $pdo;

  private function hydrate(array $data): Version {
    return new Version(
      $data['id'] ?? null,
      $data['number'],
      $data['normalized'],
      $data['package_name'],
      $data['release'],
      VersionStatusEnum::tryFrom($data['status']),
      new DateTimeImmutable($data['created_at']),
      $data['updated_at'] === null ? null : new DateTimeImmutable($data['updated_at'])
    );
  }

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function create(
    string $number,
    string $normalized,
    string $packageName,
    bool $release,
    VersionStatusEnum $status = VersionStatusEnum::Unknown
  ): Version {
    return $this->save(
      new Version(
        null,
        $number,
        $normalized,
        $packageName,
        $release,
        $status,
        new DateTimeImmutable()
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function get(int $id): Version {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "versions"
          WHERE "id" = :id
        SQL
      );
    }

    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() === 0) {
      throw new VersionNotFoundException("Version '${id}' not found");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $this->hydrate($row);
  }

  /**
   * {@inheritdoc}
   */
  public function find(array $query): array {
    $where = [];
    $cols = array_keys($query);

    // handle "release" boolean column
    $devPos = array_search('release', $cols, true);
    if ($devPos !== false) {
      $where[] = sprintf(
        '"release" IS %s',
        $query['release'] ? 'TRUE' : 'FALSE'
      );
      unset($cols[$devPos], $query['release']);
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
        FROM "versions"
        WHERE ${where}
      SQL
    );

    $stmt->execute($query);

    $arr = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $arr[] = $this->hydrate($row);
    }

    return $arr;
  }

  public function save(Version $version): Version {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          INSERT INTO "versions"
          ("number", "normalized", "package_name", "release", "status", "created_at")
          VALUES
          (:number, :normalized, :package_name, :release, :status, :created_at)
        SQL
      );
    }

    $stmt->execute(
      [
        'number'       => $version->getNumber(),
        'normalized'   => $version->getNormalized(),
        'package_name' => $version->getPackageName(),
        'release'      => $version->isRelease() ? 1 : 0,
        'status'       => $version->getStatus()->getLabel(),
        'created_at'   => $version->getCreatedAt()->format(DateTimeInterface::ATOM)
      ]
    );

    return new Version(
      (int)$this->pdo->lastInsertId('versions_id_seq'),
      $version->getNumber(),
      $version->getNormalized(),
      $version->getPackageName(),
      $version->isRelease(),
      $version->getStatus(),
      $version->getCreatedAt()
    );
  }

  public function update(Version $version): Version {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          UPDATE "versions"
          SET
            "number" = :number,
            "normalized" = :normalized,
            "package_name" = :package_name,
            "release" = :release,
            "status" = :status,
            "updated_at" = :updated_at
          WHERE "id" = :id
        SQL
      );
    }

    if ($version->isDirty()) {
      $stmt->execute(
        [
          'id'           => $version->getId(),
          'number'       => $version->getNumber(),
          'normalized'   => $version->getNormalized(),
          'package_name' => $version->getPackageName(),
          'release'      => $version->isRelease() ? 1 : 0,
          'status'       => $version->getStatus()->getLabel(),
          'updated_at'   => $version->getUpdatedAt()?->format(DateTimeInterface::ATOM)
        ]
      );

      return $this->get($version->getId());
    }

    return $version;
  }
}
