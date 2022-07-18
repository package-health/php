<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Version;

use Courier\Client\Producer\ProducerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use PackageHealth\PHP\Application\Message\Event\Version\VersionCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Version\VersionUpdatedEvent;
use PackageHealth\PHP\Domain\Version\Version;
use PackageHealth\PHP\Domain\Version\VersionCollection;
use PackageHealth\PHP\Domain\Version\VersionNotFoundException;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionStatusEnum;
use PDO;

final class PdoVersionRepository implements VersionRepositoryInterface {
  private PDO $pdo;
  private ProducerInterface $producer;

  /**
   * @param array{
   *   id?: int,
   *   package_id: int,
   *   number: string,
   *   normalized: string,
   *   release: bool,
   *   status: string,
   *   created_at: string,
   *   updated_at: string|null
   * } $data
   */
  private function hydrate(array $data): Version {
    return new Version(
      $data['id'] ?? null,
      $data['package_id'],
      $data['number'],
      $data['normalized'],
      $data['release'],
      VersionStatusEnum::tryFrom($data['status']) ?? VersionStatusEnum::Unknown,
      new DateTimeImmutable($data['created_at']),
      $data['updated_at'] === null ? null : new DateTimeImmutable($data['updated_at'])
    );
  }

  public function __construct(PDO $pdo, ProducerInterface $producer) {
    $this->pdo      = $pdo;
    $this->producer = $producer;
  }

  public function create(
    int $packageId,
    string $number,
    string $normalized,
    bool $release,
    VersionStatusEnum $status = VersionStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Version {
    return $this->save(
      new Version(
        null,
        $packageId,
        $number,
        $normalized,
        $release,
        $status,
        $createdAt
      )
    );
  }

  public function all(): VersionCollection {
    $stmt = $this->pdo->query(
      <<<SQL
        SELECT *
        FROM "versions"
        ORDER BY "created_at"
      SQL
    );

    $versionCol = new VersionCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $versionCol->add($this->hydrate($row));
    }

    return $versionCol;
  }

  public function get(int $id): Version {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "versions"
          WHERE "id" = :id
          LIMIT 1
        SQL
      );
    }

    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() === 0) {
      throw new VersionNotFoundException("Version '{$id}' not found");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $this->hydrate($row);
  }

  public function find(array $query, int $limit = -1, int $offset = 0): VersionCollection {
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

    if ($limit === -1) {
      $limit = 'ALL';
    }

    $stmt = $this->pdo->prepare(
      <<<SQL
        SELECT *
        FROM "versions"
        WHERE {$where}
        LIMIT {$limit}
        OFFSET {$offset}
      SQL
    );

    $stmt->execute($query);

    $versionCol = new VersionCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $versionCol->add($this->hydrate($row));
    }

    return $versionCol;
  }

  public function save(Version $version): Version {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          INSERT INTO "versions"
          ("package_id", "number", "normalized", "release", "status", "created_at")
          VALUES
          (:package_id, :number, :normalized, :release, :status, :created_at)
        SQL
      );
    }

    if ($version->getId() !== null) {
      throw new InvalidArgumentException();
    }

    $stmt->execute(
      [
        'package_id' => $version->getPackageId(),
        'number'     => $version->getNumber(),
        'normalized' => $version->getNormalized(),
        'release'    => $version->isRelease() ? 1 : 0,
        'status'     => $version->getStatus()->getLabel(),
        'created_at' => $version->getCreatedAt()->format(DateTimeInterface::ATOM)
      ]
    );

    $version = new Version(
      (int)$this->pdo->lastInsertId('versions_id_seq'),
      $version->getPackageId(),
      $version->getNumber(),
      $version->getNormalized(),
      $version->isRelease(),
      $version->getStatus(),
      $version->getCreatedAt()
    );

    $this->producer->sendEvent(
      new VersionCreatedEvent($version)
    );

    return $version;
  }

  public function update(Version $version): Version {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          UPDATE "versions"
          SET
            "package_id" = :package_id,
            "number" = :number,
            "normalized" = :normalized,
            "release" = :release,
            "status" = :status,
            "updated_at" = :updated_at
          WHERE "id" = :id
        SQL
      );
    }

    if ($version->getId() === null) {
      throw new InvalidArgumentException();
    }

    if ($version->isDirty()) {
      $stmt->execute(
        [
          'id'         => $version->getId(),
          'package_id' => $version->getPackageId(),
          'number'     => $version->getNumber(),
          'normalized' => $version->getNormalized(),
          'release'    => $version->isRelease() ? 1 : 0,
          'status'     => $version->getStatus()->getLabel(),
          'updated_at' => $version->getUpdatedAt()?->format(DateTimeInterface::ATOM)
        ]
      );

      $version = $this->get($version->getId());

      $this->producer->sendEvent(
        new VersionUpdatedEvent($version)
      );

      return $version;
    }

    return $version;
  }
}
