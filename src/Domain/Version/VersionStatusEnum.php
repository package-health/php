<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Version;

enum VersionStatusEnum: string {
  case Unknown = 'unknown';
  case Outdated = 'outdated';
  case Insecure = 'insecure';
  case MaybeInsecure = 'maybe insecure';
  case UpToDate = 'up to date';
  case NoDeps = 'no deps';

  public function getLabel(): string {
    return $this->value;
  }

  public function getColor(): string {
    return match ($this) {
      self::Unknown       => '',
      self::Outdated      => 'is-warning',
      self::Insecure      => 'is-danger',
      self::MaybeInsecure => 'is-success',
      self::UpToDate      => 'is-success',
      self::NoDeps        => 'is-info'
    };
  }
}
