<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Stats;

use App\Application\Message\Event\Stats\StatsCreatedEvent;
use App\Application\Message\Event\Stats\StatsUpdatedEvent;
use App\Domain\Stats\Stats;
use App\Domain\Stats\StatsCollection;
use App\Domain\Stats\StatsNotFoundException;
use App\Domain\Stats\StatsRepositoryInterface;
use Courier\Client\Producer\ProducerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class PdoStatsRepository implements StatsRepositoryInterface {
  private PDO $pdo;
  private ProducerInterface $producer;

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

  public function __construct(PDO $pdo, ProducerInterface $producer) {
    $this->pdo      = $pdo;
    $this->producer = $producer;
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

  public function all(): StatsCollection {
    $stmt = $this->pdo->query(
      <<<SQL
        SELECT *
        FROM "stats"
        ORDER BY "created_at"
      SQL
    );

    $statsCol = new StatsCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $statsCol->add($this->hydrate($row));
    }

    return $statsCol;
  }

  public function findPopular(): StatsCollection {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->query(
        <<<SQL
          SELECT "stats".*
          FROM "stats"
          INNER JOIN "packages" ON ("packages"."name" = "stats"."package_name")
          ORDER BY "stats"."daily_downloads" DESC, "packages"."created_at" ASC
          LIMIT 10
        SQL
      );
    }

    $statsCol = new StatsCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $statsCol->add($this->hydrate($row));
    }

    return $statsCol;
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

  public function find(array $query, int $limit = -1, int $offset = 0): StatsCollection {
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

    $statsCol = new StatsCollection();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $statsCol->add($this->hydrate($row));
    }

    return $statsCol;
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
