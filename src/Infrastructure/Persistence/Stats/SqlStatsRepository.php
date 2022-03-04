<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Stats;

use App\Domain\Stats\Stats;
use App\Domain\Stats\StatsNotFoundException;
use App\Domain\Stats\StatsRepositoryInterface;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class SqlStatsRepository implements StatsRepositoryInterface {
  private PDO $pdo;

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

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function create(
    string $packageName,
    int $githubStars,
    int $githubWatchers,
    int $githubForks,
    int $dependents,
    int $suggesters,
    int $favers,
    int $totalDownloads,
    int $monthlyDownloads,
    int $dailyDownloads
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
        new DateTimeImmutable()
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->query(<<<SQL
        SELECT *
        FROM "stats"
        ORDER BY "created_at"
      SQL);
    }

    $arr = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $arr[] = $this->hydrate($row);
    }

    return $arr;
  }

  /**
   * {@inheritdoc}
   */
  public function exists(string $packageName): bool {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(<<<SQL
        SELECT *
        FROM "stats"
        WHERE "package_name" = :package_name
        LIMIT 1
      SQL);
    }

    $stmt->execute(['package_name' => $packageName]);

    return $stmt->rowCount() === 1;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $packageName): Stats {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(<<<SQL
        SELECT *
        FROM "stats"
        WHERE "package_name" = :package_name
      SQL);
    }

    $stmt->execute(['package_name' => $packageName]);
    if ($stmt->rowCount() === 0) {
      throw new StatsNotFoundException("Stats '${packageName}' not found");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $this->hydrate($row);
  }

  /**
   * {@inheritdoc}
   */
  public function find(array $query): array {
    $where = [];
    foreach (array_keys($query) as $col) {
      $where[] = sprintf(
        '"%1$s" = :%1$s',
        $col
      );
    }

    $where = implode(' AND ', $where);

    $stmt = $this->pdo->prepare(<<<SQL
      SELECT *
      FROM "stats"
      WHERE ${where}
    SQL);

    $stmt->execute($query);

    $arr = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $arr[] = $this->hydrate($row);
    }

    return $arr;
  }

  public function save(Stats $stats): Stats {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(<<<SQL
        INSERT INTO "stats"
        ("package_name", "github_stars", "github_watchers", "github_forks", "dependents", "suggesters", "favers",
          "total_downloads", "monthly_downloads", "daily_downloads", "created_at")
        VALUES
        (:package_name, :github_stars, :github_watchers, :github_forks, :dependents, :suggesters, :favers,
          :total_downloads, :monthly_downloads, :daily_downloads, :created_at)
      SQL);
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
        'created_at'        => $stats->getCreatedAt()->format(DateTimeInterface::ISO8601)
      ]
    );

    return $stats;
  }

  public function update(Stats $stats): Stats {
    static $stmt = null;
    if ($stmt === null) {
      $stmt = $this->pdo->prepare(<<<SQL
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
      SQL);
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
          'updated_at'        => $stats->getUpdatedAt()->format(DateTimeInterface::ISO8601)
        ]
      );

      return $this->get($stats->getPackageName());
    }

    return $stats;
  }
}
