<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Package;

enum VcsBrandEnum: string {
  case Unknown = 'cib-git';
  case Bitbucket = 'cib-bitbucket';
  case Gitea = 'cib-gitea';
  case Github = 'cib-github';
  case Gitlab = 'cib-gitlab';
  case Gitpod = 'cib-gitpod';
  case SourceForge = 'cib-sourceforge';

  public static function fromUrl(string $url): self {
    return match (1) {
      preg_match('/^https:\/\/bitbucket\.com\//', $url) => self::Bitbucket,
      preg_match('/^https:\/\/gitea\.com\//', $url) => self::Gitea,
      preg_match('/^https:\/\/github\.com\//', $url) => self::Github,
      preg_match('/^https:\/\/gitlab\.com\//', $url) => self::Gitlab,
      preg_match('/^https:\/\/gitpod\.io\//', $url) => self::Gitpod,
      preg_match('/^https:\/\/sourceforge\.com\//', $url) => self::SourceForge,
      default => self::Unknown
    };
  }
}
