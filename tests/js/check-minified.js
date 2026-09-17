const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const UglifyJS = require("uglify-js");

const root = path.resolve(__dirname, "../..");

for (const loader of ["consent-boomerang-loader-v1-15"]) {
  const source = fs.readFileSync(
    path.join(root, "js/basicrum/loaders", `${loader}.js`),
    "utf8"
  );
  const actual = fs.readFileSync(
    path.join(root, "js/basicrum/loaders", `${loader}.min.js`),
    "utf8"
  );
  const result = UglifyJS.minify(source, {
    compress: false,
    mangle: true,
    output: { comments: /^!/ }
  });

  if (result.error) {
    throw result.error;
  }

  assert.equal(actual, result.code, `${loader}.min.js must be regenerated from source`);
}

console.log("Minified loader checks passed.");
