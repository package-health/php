<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Package;

use App\Domain\Package\Package;
use App\Domain\Package\PackageCollection;
use App\Domain\Package\PackageNotFoundException;
use App\Domain\Package\PackageRepositoryInterface;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class PdoPackageRepository implements PackageRepositoryInterface {
  private PDO $pdo;

  private function hydrate(array $data): Package {
    return new Package(
      $data['name'],
      $data['description'],
      $data['latest_version'],
      $data['url'],
      new DateTimeImmutable($data['created_at']),
      $data['updated_at'] === null ? null : new DateTimeImmutable($data['updated_at'])
    );
  }

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function create(string $name): Package {
    return $this->save(
      new Package(
        $name,
        '',
        '',
        '',
        new DateTimeImmutable()
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function all(): PackageCollection {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->query(
        <<<SQL
          SELECT *
          FROM "packages"
          ORDER BY "created_at"
        SQL
      );
    }

    $packageCol = new PackageCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $packageCol->add($this->hydrate($row));
    }

    return $packageCol;
  }

  /**
   * {@inheritdoc}
   */
  public function findPopular(): PackageCollection {
    // static $stmt = null;
    // if ($stmt === null) {
      $stmt = $this->pdo->query(
        <<<SQL
          SELECT "packages".*
          FROM "packages"
          LEFT JOIN "stats" ON ("stats"."package_name" = "packages"."name")
          ORDER BY "stats"."daily_downloads" DESC, "packages"."created_at" ASC
          LIMIT 10
        SQL
      );
    // }

    $packageCol = new PackageCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $packageCol->add($this->hydrate($row));
    }

    return $packageCol;
  }

  /**
   * {@inheritdoc}
   */
  public function exists(string $name): bool {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "packages"
          WHERE "name" = :name
        SQL
      );
    }

    $stmt->execute(['name' => $name]);

    return $stmt->rowCount() === 1;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $name): Package {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "packages"
          WHERE "name" = :name
        SQL
      );
    }

    $stmt->execute(['name' => $name]);
    if ($stmt->rowCount() === 0) {
      throw new PackageNotFoundException("Package '${name}' not found");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $this->hydrate($row);
  }

  /**
   * {@inheritdoc}
   */
  public function find(array $query): PackageCollection {
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
        FROM "packages"
        WHERE ${where}
        ORDER BY "name" ASC
      SQL
    );

    $stmt->execute($query);

    $packageCol = new PackageCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $packageCol->add($this->hydrate($row));
    }

    return $packageCol;
  }

  public function findMatching(array $query): PackageCollection {
    $where = [];
    foreach (array_keys($query) as $col) {
      $where[] = sprintf(
        '"%1$s" ILIKE :%1$s',
        $col
      );
    }

    $where = implode(' AND ', $where);

    $stmt = $this->pdo->prepare(
      <<<SQL
        SELECT *
        FROM "packages"
        WHERE ${where}
        ORDER BY "name" ASC
      SQL
    );

    $stmt->execute($query);

    $packageCol = new PackageCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $packageCol->add($this->hydrate($row));
    }

    return $packageCol;
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

    $stmt->execute(
      [
        'name'           => $package->getName(),
        'description'    => $package->getDescription(),
        'latest_version' => $package->getLatestVersion(),
        'url'            => $package->getUrl(),
        'created_at'     => $package->getCreatedAt()->format(DateTimeInterface::ATOM)
      ]
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
          WHERE "name" = :name
        SQL
      );
    }

    if ($package->isDirty()) {
      $stmt->execute(
        [
          'name'           => $package->getName(),
          'description'    => $package->getDescription(),
          'latest_version' => $package->getLatestVersion(),
          'url'            => $package->getUrl(),
          'updated_at'     => $package->getUpdatedAt()?->format(DateTimeInterface::ATOM)
        ]
      );

      return $this->get($package->getName());
    }

    return $package;
  }
}
