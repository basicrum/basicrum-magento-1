const fs = require("node:fs");
const path = require("node:path");
const { execFileSync } = require("node:child_process");

function releaseVersion(root, tag = "") {
  const version = execFileSync("xmllint", [
    "--xpath", "string(/config/modules/BasicRum_Analytics/version)",
    path.join(root, "app/code/community/BasicRum/Analytics/etc/config.xml")
  ], { encoding: "utf8" }).trim();
  const stableVersion = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/;
  if (!stableVersion.test(version)) {
    throw new Error("Module version must use major.minor.patch without leading zeroes.");
  }

  const manifest = JSON.parse(fs.readFileSync(path.join(root, "package.json"), "utf8"));
  const lock = JSON.parse(fs.readFileSync(path.join(root, "package-lock.json"), "utf8"));
  const readme = fs.readFileSync(path.join(root, "README.md"), "utf8");
  const readmeVersion = readme.match(/^## Version\s+([^\s]+)/m);
  if (manifest.version !== version || lock.version !== version
      || lock.packages[""].version !== version || !readmeVersion || readmeVersion[1] !== version) {
    throw new Error("Module, README, package.json and package-lock.json versions must agree.");
  }

  if (!tag) {
    return version;
  }
  const match = tag.match(/^v((?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*))(-(alpha|beta|rc)\.[1-9]\d*)?$/);
  if (!match || match[1] !== version) {
    throw new Error(`Release tag must be v${version} or v${version}-{alpha,beta,rc}.N (N >= 1).`);
  }
  return tag.slice(1);
}

module.exports = { releaseVersion };

if (require.main === module) {
  try {
    process.stdout.write(releaseVersion(path.resolve(__dirname, ".."), process.env.BASICRUM_RELEASE_TAG) + "\n");
  } catch (error) {
    process.stderr.write(error.message + "\n");
    process.exitCode = 1;
  }
}
