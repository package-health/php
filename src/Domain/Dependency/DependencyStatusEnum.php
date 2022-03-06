<?php
declare(strict_types = 1);

namespace App\Domain\Dependency;

enum DependencyStatusEnum: string {
  case Unknown = 'unknown';
  case Outdated = 'outdated';
  case Insecure = 'insecure';
  case MaybeInsecure = 'maybe insecure';
  case UpToDate = 'up to date';

  public function getLabel(): string {
    return $this->value;
  }

  public function getColor(): string {
    return match ($this) {
      self::Unknown       => '',
      self::Outdated      => 'is-warning',
      self::Insecure      => 'is-danger',
      self::MaybeInsecure => 'is-success',
      self::UpToDate      => 'is-success'
    };
  }
}
