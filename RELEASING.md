# Releasing Ticketstation

These steps are for the maintainer. Users install and update through Joomla, see the [README](README.md).

1. Raise `<version>` (and `<creationDate>`) to the same new version in all three manifests: `pkg_ticketstation.xml`, `packages/com_ticketstation/ticketstation.xml` and `packages/mod_ticketstation_basket/mod_ticketstation_basket.xml`, also when only one extension changed. The build refuses to run when they differ. Commit and push.
2. Tag the commit with that version and push the tag:

   ```
   git tag v<version>
   git push origin v<version>
   ```

The [release workflow](.github/workflows/release.yml) then builds the package and publishes a GitHub release with the zip and the update feed. Joomla sites read the feed from the latest release.

## Release candidates

Give the version a suffix, such as `2.4.5-rc1` (also `-beta1`, `-alpha1` or `-dev`), in all three manifests and tag it `v2.4.5-rc1`. The workflow publishes it as a GitHub pre-release and adds it, tagged with its stability, to the update feed of the latest stable release. Joomla only offers it to sites whose Minimum Extension Stability (Extensions: Update → Options) is set to that level or lower, so production on Stable does not see it. A newer pre-release replaces the previous one in that feed, and the next stable release starts with a clean feed.
