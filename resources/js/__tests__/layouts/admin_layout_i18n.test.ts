import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";

describe("admin layout i18n source contract", () => {
  it("resolves every visible key in Korean and English", () => {
    const checker = fileURLToPath(new URL("../../../../scripts/check-admin-layout-i18n.mjs", import.meta.url));
    expect(execFileSync(process.execPath, [checker], { encoding: "utf8" }))
      .toMatch(/PASS: 7 admin layouts/);
  });
});
