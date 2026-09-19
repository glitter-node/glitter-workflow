import { readFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const names = [
  "admin_workflow_list",
  "admin_workflow_create",
  "admin_workflow_detail",
  "admin_workflow_edit",
  "admin_execution_list",
  "admin_execution_detail",
  "admin_workflow_settings",
];
const locales = ["en", "ko"];
const visibleFields = new Set([
  "title", "description", "label", "text", "placeholder",
  "emptyText", "helpText", "hint", "tooltip", "ariaLabel", "message",
]);
const errors = [];
const readJson = path => JSON.parse(readFileSync(path, "utf8"));
const dictionaries = Object.fromEntries(locales.map(locale => [
  locale, readJson(join(root, "resources/lang", locale + ".json")),
]));
const leaves = (value, prefix = "", result = new Map()) => {
  for (const [key, child] of Object.entries(value)) {
    const path = prefix ? prefix + "." + key : key;
    if (typeof child === "string") result.set(path, child);
    else if (child && typeof child === "object" && !Array.isArray(child)) leaves(child, path, result);
    else errors.push("Invalid locale leaf: " + path);
  }
  return result;
};
const localeKeys = Object.fromEntries(locales.map(locale => [
  locale, leaves(dictionaries[locale]),
]));
for (const key of new Set(locales.flatMap(locale => [...localeKeys[locale].keys()]))) {
  for (const locale of locales) {
    if (!localeKeys[locale].has(key)) errors.push(locale + " missing parity key " + key);
  }
}
let references = 0;
const checkToken = (token, where) => {
  const key = token.split("|", 1)[0];
  if (!key.startsWith("glitter-workflow.")) {
    errors.push(where + ": wrong namespace " + key);
    return;
  }
  const localKey = key.slice("glitter-workflow.".length);
  for (const locale of locales) {
    const value = localeKeys[locale].get(localKey);
    if (typeof value !== "string" || value.length === 0) errors.push(where + ": " + locale + " missing " + key);
  }
  references++;
};
for (const name of names) {
  const path = join(root, "resources/layouts/admin", name + ".json");
  const layout = readJson(path);
  const walk = (node, location) => {
    if (Array.isArray(node)) return node.forEach((item, index) => walk(item, location + "[" + index + "]"));
    if (!node || typeof node !== "object") return;
    for (const [field, value] of Object.entries(node)) {
      const where = name + ":" + location + "." + field;
      if (typeof value !== "string") { walk(value, location + "." + field); continue; }
      if (visibleFields.has(field) && !/^\$t:glitter-workflow\.[A-Za-z0-9._-]+(?:\|[^\n]+)?$/.test(value)) {
        errors.push(where + ": visible text must be a module translation reference: " + value);
      }
      if (value.includes("$t:")) {
        const matches = [...value.matchAll(/\$t:([A-Za-z0-9._-]+(?:\|[^\n]+)?)/g)];
        if (matches.length === 0) errors.push(where + ": invalid translation syntax");
        for (const match of matches) checkToken(match[1], where);
      }
    }
  };
  walk(layout, "$");
}
const adminRoutes = readJson(join(root, "resources/routes/admin.json"));
for (const route of adminRoutes.routes) {
  if (!names.includes(route.layout)) errors.push("Unexpected admin route layout: " + route.layout);
  const title = route.meta?.title;
  if (typeof title !== "string" || !/^\$t:glitter-workflow\.[A-Za-z0-9._-]+$/.test(title)) {
    errors.push("Admin route title lacks module translation: " + route.path);
  } else checkToken(title.slice(3), "route:" + route.path);
}
if (errors.length) {
  for (const error of errors) process.stderr.write(error + "\n");
  process.exitCode = 1;
} else {
  process.stdout.write("PASS: " + names.length + " admin layouts, " + references + " translation references, en/ko key parity\n");
}
