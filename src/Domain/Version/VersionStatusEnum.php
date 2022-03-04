<?php
declare(strict_types = 1);

namespace App\Domain\Version;

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
      VersionStatusEnum::Unknown       => '',
      VersionStatusEnum::Outdated      => 'is-warning',
      VersionStatusEnum::Insecure      => 'is-danger',
      VersionStatusEnum::MaybeInsecure => 'is-success',
      VersionStatusEnum::UpToDate      => 'is-success',
      VersionStatusEnum::NoDeps        => 'is-info'
    };
  }
}
