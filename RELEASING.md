# Releasing Ticketstation

These steps are for the maintainer. Users install and update through Joomla, see the [README](README.md).

1. Raise `<version>` (and `<creationDate>`) to the same new version in all three manifests: `pkg_ticketstation.xml`, `packages/com_ticketstation/ticketstation.xml` and `packages/mod_ticketstation_basket/mod_ticketstation_basket.xml`, also when only one extension changed. The build refuses to run when they differ.
2. Write the release notes in `release-notes/<version>.md`, for the people who use Ticketstation: start with `## What's new in <version>`, use `###` subheadings and `- ` list items, and add a `## Check before you update` section when admins need to check something. Leave out the install line; the workflow adds it to the GitHub release. The file ends up in the package, and after a successful install or update Joomla shows it on the installer result page (`pkg_script.php`). Only headings, list items, paragraphs, `**bold**`, `*italic*`, `` `code` `` and `[links](https://...)` are converted there. Commit it together with the version bump and push.
3. Tag the commit with that version and push the tag:

   ```
   git tag v<version>
   git push origin v<version>
   ```

The [release workflow](.github/workflows/release.yml) then builds the package and publishes a GitHub release with the zip and the update feed, using `release-notes/<version>.md` as its text. Without that file the release lists the commits since the previous release instead, and Joomla shows no notes after the install. Joomla sites read the feed from the latest release.

## Release candidates

Release candidates are not published. Give the version a suffix, such as `2.4.5-rc1` (also `-beta1`, `-alpha1` or `-dev`), in all three manifests, commit it, build the package locally and install `dist/pkg_ticketstation_<version>.zip` on a test site through Joomla's Extension Manager. Don't tag it and don't push it on its own: the candidate's commits reach GitHub together with the stable release that follows. A release-notes file is optional for a candidate; without one, Joomla shows no notes after the install.

The release workflow can still publish a suffixed tag (`v2.4.5-rc1`) as a GitHub pre-release and add it, tagged with its stability, to the update feed of the latest stable release, where Joomla offers it only to sites whose Minimum Extension Stability allows it. That route is no longer used.
