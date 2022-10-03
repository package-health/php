<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Stats;

use Courier\Client\Producer;
use DateTimeImmutable;
use DateTimeInterface;
use Iterator;
use Kolekto\CollectionInterface;
use Kolekto\EagerCollection;
use Kolekto\LazyCollection;
use PackageHealth\PHP\Application\Message\Event\Stats\StatsCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Stats\StatsUpdatedEvent;
use PackageHealth\PHP\Domain\Stats\Stats;
use PackageHealth\PHP\Domain\Stats\StatsNotFoundException;
use PackageHealth\PHP\Domain\Stats\StatsRepositoryInterface;
use PDO;
use PDOStatement;

final class PdoStatsRepository implements StatsRepositoryInterface {
  private PDO $pdo;
  private Producer $producer;
  private bool $lazyFetch = false;

  /**
   * @param array{
   *   package_name: string,
   *   github_stars: int,
   *   github_watchers: int,
   *   github_forks: int,
   *   dependents: int,
   *   suggesters: int,
   *   favers: int,
   *   total_downloads: int,
   *   monthly_downloads: int,
   *   daily_downloads: int,
   *   created_at: string,
   *   updated_at: string|null
   * } $data
   */
  private function hydrate(array $data): Stats {
    return new Stats(
      $data['package_name'],
      $data['github_stars'],
      $data['github_watchers'],
      $data['github_forks'],
      $data['dependents'],
      $data['suggesters'],
      $data['favers'],
      $data['total_downloads'],
      $data['monthly_downloads'],
      $data['daily_downloads'],
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
    string $packageName,
    int $githubStars = 0,
    int $githubWatchers = 0,
    int $githubForks = 0,
    int $dependents = 0,
    int $suggesters = 0,
    int $favers = 0,
    int $totalDownloads = 0,
    int $monthlyDownloads = 0,
    int $dailyDownloads = 0,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Stats {
    return $this->save(
      new Stats(
        $packageName,
        $githubStars,
        $githubWatchers,
        $githubForks,
        $dependents,
        $suggesters,
        $favers,
        $totalDownloads,
        $monthlyDownloads,
        $dailyDownloads,
        $createdAt
      )
    );
  }

  public function all(array $orderBy = []): CollectionInterface {
    $stmt = $this->pdo->query(
      <<<SQL
        SELECT *
        FROM "stats"
        ORDER BY "created_at"
      SQL
    );

    if ($this->lazyFetch) {
      $this->lazyFetch = false;

      return new LazyCollection(
        (function (PDOStatement $stmt): Iterator {
          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $this->hydrate($row);
          }
        })->call($this, $stmt)
      );
    }

    return new EagerCollection(
      array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC))
    );
  }

  public function findPopular(): CollectionInterface {
    $stmt = $this->pdo->query(
      <<<SQL
        SELECT "stats".*
        FROM "stats"
        INNER JOIN "packages" ON ("packages"."name" = "stats"."package_name")
        ORDER BY "stats"."daily_downloads" DESC, "packages"."created_at" ASC
        LIMIT 10
      SQL
    );

    if ($this->lazyFetch) {
      $this->lazyFetch = false;

      return new LazyCollection(
        (function (PDOStatement $stmt): Iterator {
          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $this->hydrate($row);
          }
        })->call($this, $stmt)
      );
    }

    return new EagerCollection(
      array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC))
    );
  }

  public function exists(string $packageName): bool {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "stats"
          WHERE "package_name" = :package_name
          LIMIT 1
        SQL
      );
    }

    $stmt->execute(['package_name' => $packageName]);

    return $stmt->rowCount() === 1;
  }

  public function get(string $packageName): Stats {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          SELECT *
          FROM "stats"
          WHERE "package_name" = :package_name
          LIMIT 1
        SQL
      );
    }

    $stmt->execute(['package_name' => $packageName]);
    if ($stmt->rowCount() === 0) {
      throw new StatsNotFoundException("Stats '{$packageName}' not found");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

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

    $stmt = $this->pdo->prepare(
      <<<SQL
        SELECT *
        FROM "stats"
        WHERE {$where}
        LIMIT {$limit}
        OFFSET {$offset}
      SQL
    );

    $stmt->execute($query);

    if ($this->lazyFetch) {
      $this->lazyFetch = false;

      return new LazyCollection(
        (function (PDOStatement $stmt): Iterator {
          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $this->hydrate($row);
          }
        })->call($this, $stmt)
      );
    }

    return new EagerCollection(
      array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC))
    );
  }

  public function save(Stats $stats): Stats {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          INSERT INTO "stats"
          ("package_name", "github_stars", "github_watchers", "github_forks", "dependents", "suggesters", "favers",
            "total_downloads", "monthly_downloads", "daily_downloads", "created_at")
          VALUES
          (:package_name, :github_stars, :github_watchers, :github_forks, :dependents, :suggesters, :favers,
            :total_downloads, :monthly_downloads, :daily_downloads, :created_at)
        SQL
      );
    }

    $stmt->execute(
      [
        'package_name'      => $stats->getPackageName(),
        'github_stars'      => $stats->getGithubStars(),
        'github_watchers'   => $stats->getGithubWatchers(),
        'github_forks'      => $stats->getGithubForks(),
        'dependents'        => $stats->getDependents(),
        'suggesters'        => $stats->getSuggesters(),
        'favers'            => $stats->getFavers(),
        'total_downloads'   => $stats->getTotalDownloads(),
        'monthly_downloads' => $stats->getMonthlyDownloads(),
        'daily_downloads'   => $stats->getDailyDownloads(),
        'created_at'        => $stats->getCreatedAt()->format(DateTimeInterface::ATOM)
      ]
    );

    $this->producer->sendEvent(
      new StatsCreatedEvent($stats)
    );

    return $stats;
  }

  public function update(Stats $stats): Stats {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(
        <<<SQL
          UPDATE "stats"
          SET
            "github_stars" = :github_stars,
            "github_watchers" = :github_watchers,
            "github_forks" = :github_forks,
            "dependents" = :dependents,
            "suggesters" = :suggesters,
            "favers" = :favers,
            "total_downloads" = :total_downloads,
            "monthly_downloads" = :monthly_downloads,
            "daily_downloads" = :daily_downloads,
            "updated_at" = :updated_at
          WHERE "package_name" = :package_name
        SQL
      );
    }

    if ($stats->isDirty()) {
      $stmt->execute(
        [
          'package_name'      => $stats->getPackageName(),
          'github_stars'      => $stats->getGithubStars(),
          'github_watchers'   => $stats->getGithubWatchers(),
          'github_forks'      => $stats->getGithubForks(),
          'dependents'        => $stats->getDependents(),
          'suggesters'        => $stats->getSuggesters(),
          'favers'            => $stats->getFavers(),
          'total_downloads'   => $stats->getTotalDownloads(),
          'monthly_downloads' => $stats->getMonthlyDownloads(),
          'daily_downloads'   => $stats->getDailyDownloads(),
          'updated_at'        => $stats->getUpdatedAt()?->format(DateTimeInterface::ATOM)
        ]
      );

      $stats = $this->get($stats->getPackageName());

      $this->producer->sendEvent(
        new StatsUpdatedEvent($stats)
      );

      return $stats;
    }

    return $stats;
  }
}
