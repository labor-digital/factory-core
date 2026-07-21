// standard-version updater — keeps ext_emconf.php's version in lockstep with
// composer.json on release (conventional-release wraps standard-version, which
// reads .versionrc.json bumpFiles). Replaces release-please's extra-files.
module.exports.readVersion = function (contents) {
  const m = contents.match(/'version'\s*=>\s*'([^']+)'/);
  return m ? m[1] : '0.0.0';
};
module.exports.writeVersion = function (contents, version) {
  return contents.replace(/('version'\s*=>\s*')[^']+(')/, `$1${version}$2`);
};
