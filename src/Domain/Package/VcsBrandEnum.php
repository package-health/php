<?php
declare(strict_types = 1);

namespace App\Domain\Package;

enum VcsBrandEnum: string {
  case Unknown = 'cib-git';
  case Bitbucket = 'cib-bitbucket';
  case Gitea = 'cib-gitea';
  case Github = 'cib-github';
  case Gitlab = 'cib-gitlab';
  case Gitpod = 'cib-gitpod';
  case SourceForge = 'cib-sourceforge';

  public function getLabel(): string {
    return $this->value;
  }

  public static function fromUrl(string $url): static {
    return match (true) {
      preg_match('/^https:\/\/bitbucket\.com\//', $url) => static::Bitbucket,
      preg_match('/^https:\/\/gitea\.com\//', $url) => static::Gitea,
      preg_match('/^https:\/\/github\.com\//', $url) => static::Github,
      preg_match('/^https:\/\/gitlab\.com\//', $url) => static::Gitlab,
      preg_match('/^https:\/\/gitpod\.io\//', $url) => static::Gitpod,
      preg_match('/^https:\/\/sourceforge\.com\//', $url) => static::SourceForge,
      default => static::Unknown
    };
  }
}
