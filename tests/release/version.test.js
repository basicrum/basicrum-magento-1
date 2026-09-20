const assert = require("node:assert/strict");
const fs = require("node:fs");
const os = require("node:os");
const path = require("node:path");
const { test } = require("node:test");
const { releaseVersion } = require("../../tools/release-version");

function fixture(t) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), "basicrum-version-"));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  const configDir = path.join(root, "app/code/community/BasicRum/Analytics/etc");
  fs.mkdirSync(configDir, { recursive: true });
  fs.writeFileSync(path.join(configDir, "config.xml"),
    "<config><modules><BasicRum_Analytics><version>1.1.0</version></BasicRum_Analytics></modules></config>");
  fs.writeFileSync(path.join(root, "README.md"), "# Extension\n\n## Version\n\n1.1.0\n");
  fs.writeFileSync(path.join(root, "package.json"), JSON.stringify({ version: "1.1.0" }));
  fs.writeFileSync(path.join(root, "package-lock.json"),
    JSON.stringify({ version: "1.1.0", packages: { "": { version: "1.1.0" } } }));
  return root;
}

test("untagged CI builds and stable release tags use the module version", (t) => {
  const root = fixture(t);
  assert.equal(releaseVersion(root), "1.1.0");
  assert.equal(releaseVersion(root, "v1.1.0"), "1.1.0");
});

test("prerelease suffixes preserve the base module version", (t) => {
  const root = fixture(t);
  for (const suffix of ["alpha.1", "beta.2", "rc.12"]) {
    assert.equal(releaseVersion(root, `v1.1.0-${suffix}`), `1.1.0-${suffix}`);
  }
});

test("rejects mismatched versions and unsupported tag names", (t) => {
  const root = fixture(t);
  for (const tag of ["v1.2.0", "1.1.0", "/release", "v1.1.0-rc.0", "v1.1.0-rc.01",
    "v1.1.0-preview.1", "v01.1.0", "v1.1.0+build", "v1.1.0-rc.1\n"]) {
    assert.throws(() => releaseVersion(root, tag), /Release tag/);
  }
});

for (const file of ["README.md", "package.json", "package-lock.json"]) {
  test(`rejects stale ${file} metadata`, (t) => {
    const root = fixture(t);
    const filename = path.join(root, file);
    fs.writeFileSync(filename, fs.readFileSync(filename, "utf8").replaceAll("1.1.0", "1.0.1"));
    assert.throws(() => releaseVersion(root), /versions must agree/);
  });
}

test("checks the lockfile root package independently", (t) => {
  const root = fixture(t);
  fs.writeFileSync(path.join(root, "package-lock.json"),
    JSON.stringify({ version: "1.1.0", packages: { "": { version: "1.0.1" } } }));
  assert.throws(() => releaseVersion(root), /versions must agree/);
});
