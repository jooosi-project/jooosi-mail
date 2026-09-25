#!/usr/bin/env node

import { execFileSync } from "node:child_process";
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { updateReadmeChangelog } from "./update-readme-changelog.mjs";

const root = fileURLToPath(new URL("..", import.meta.url));
const version = process.argv[2];

if (!version || !/^\d+\.\d+\.\d+$/.test(version)) {
  throw new Error("Usage: pnpm run release -- <major.minor.patch>");
}

const status = execFileSync("git", ["status", "--porcelain"], { cwd: root, encoding: "utf8" });

if (status.trim()) {
  throw new Error("The working tree must be clean before preparing a release.");
}

function replace(relativePath, content, replacements) {
  for (const [pattern, replacement] of replacements) {
    if (!pattern.test(content)) {
      throw new Error(`Could not find the version field in ${relativePath}.`);
    }

    content = content.replace(pattern, replacement);
  }

  return content;
}

const readmePath = `${root}/readme.txt`;
const changelogPath = `${root}/CHANGELOG.md`;
const composerPath = `${root}/composer.json`;

let readme = replace(
  "readme.txt",
  readFileSync(readmePath, "utf8"),
  [[/Stable tag: \d+\.\d+\.\d+/, `Stable tag: ${version}`]],
);
const constant = replace(
  "constant.php",
  readFileSync(`${root}/constant.php`, "utf8"),
  [[/define\('JOOOSI_MAIL_VERSION', '\d+\.\d+\.\d+'\);/, `define('JOOOSI_MAIL_VERSION', '${version}');`]],
);
const plugin = replace(
  "jooosi-mail.php",
  readFileSync(`${root}/jooosi-mail.php`, "utf8"),
  [[/( \* Version:\s+)\d+\.\d+\.\d+/, `$1${version}`]],
);
const packageJson = replace(
  "package.json",
  readFileSync(`${root}/package.json`, "utf8"),
  [[/("version": ")\d+\.\d+\.\d+("\s*,)/, `$1${version}$2`]],
);

const composer = JSON.parse(readFileSync(composerPath, "utf8"));
const wordpressPlugin = composer.extra?.["wordpress-plugin"];

if (!wordpressPlugin || typeof wordpressPlugin.version !== "string" || !/^\d+\.\d+\.\d+$/.test(wordpressPlugin.version)) {
  throw new Error('Could not find a semantic version in composer.json at extra.wordpress-plugin.version.');
}

wordpressPlugin.version = version;
const composerJson = `${JSON.stringify(composer, null, 4)}\n`;

let changelog = readFileSync(changelogPath, "utf8");

if (changelog.includes(`## [${version}]`)) {
  throw new Error(`CHANGELOG.md already contains ${version}.`);
}

const unreleasedHeading = /^## \[Unreleased\]$/m;

if (!unreleasedHeading.test(changelog)) {
  throw new Error("Could not find the Unreleased changelog section.");
}

const date = new Date().toISOString().slice(0, 10);
changelog = changelog.replace(unreleasedHeading, `## [Unreleased]\n\n## [${version}] - ${date}`);

const repositoryUrl = "https://github.com/jooosi-project/jooosi-mail";
const comparisonLink = changelog.match(/^\[unreleased]: https:\/\/github\.com\/jooosi-project\/jooosi-mail\/compare\/(.+)\.\.\.HEAD$/m);
const initialLink = `[unreleased]: ${repositoryUrl}/commits/main`;

if (comparisonLink) {
  changelog = changelog.replace(
    comparisonLink[0],
    `[unreleased]: ${repositoryUrl}/compare/${version}...HEAD\n[${version}]: ${repositoryUrl}/compare/${comparisonLink[1]}...${version}`,
  );
} else if (changelog.includes(initialLink)) {
  changelog = changelog.replace(
    initialLink,
    `[unreleased]: ${repositoryUrl}/compare/${version}...HEAD\n[${version}]: ${repositoryUrl}/releases/tag/${version}`,
  );
} else {
  throw new Error("Could not find the Unreleased changelog link.");
}

readme = updateReadmeChangelog(readme, changelog);

const releaseFiles = new Map([
  ["CHANGELOG.md", changelog],
  ["composer.json", composerJson],
  ["constant.php", constant],
  ["jooosi-mail.php", plugin],
  ["package.json", packageJson],
  ["readme.txt", readme],
]);

for (const [relativePath, content] of releaseFiles) {
  writeFileSync(`${root}/${relativePath}`, content, "utf8");
}

execFileSync("composer", ["update", "--lock", "--no-install", "--no-interaction", "--ansi"], { cwd: root, stdio: "inherit" });
execFileSync("git", ["add", "--", ...releaseFiles.keys(), "composer.lock"], { cwd: root, stdio: "inherit" });
execFileSync("git", ["commit", "-m", `Prepare ${version}`], { cwd: root, stdio: "inherit" });
execFileSync("git", ["tag", version], { cwd: root, stdio: "inherit" });

process.stdout.write(`Prepared ${version}. Push the commit and tag when ready.\n`);
