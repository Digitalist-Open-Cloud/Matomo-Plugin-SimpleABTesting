import { assertEquals, assert, assertMatch } from "jsr:@std/assert@1";
import { loadTag, makeEnv, makeStorage, today } from "./harness.js";

const live = (over = {}) => ({
  found: true, name: "Rubrik test", from_date: today(-5), to_date: today(5),
  css_insert: "h1{color:red}", js_insert: "window.x=1", ...over,
});
const P = { experiment: "7,1", matomoOrigin: "https://m.example" };

Deno.test("ny besökare får variant, id och ping", async () => {
  const env = makeEnv({ fetchData: live() });
  const orig = Math.random; Math.random = () => 0.9;
  try { await loadTag(P, env).fire(); } finally { Math.random = orig; }
  assertEquals(env._paq.length, 2);
  assertEquals(env._paq[0][0], "appendToTrackingUrl");
  assertMatch(env._paq[0][1], /^sabt=2&sabi=7&sabn=Rubrik%20test&saba=[0-9a-f]{32}$/);
  assertEquals(env._paq[1], ["ping"]);
  const rec = JSON.parse(env.localStorage.getItem("sabt:7"));
  assertEquals(rec.v, "2");
  assertMatch(rec.a, /^[0-9a-f]{32}$/);
  assertEquals(rec.exp, today(5));
  assertEquals(env._paq[0][1].split("saba=")[1], rec.a);
});

Deno.test("återkommande behåller variant och id", async () => {
  const a = "0123456789abcdef0123456789abcdef";
  const ls = makeStorage({ "sabt:7": JSON.stringify({ v: "2", a, exp: today(5) }) });
  const env = makeEnv({ fetchData: live(), localStorage: ls });
  await loadTag(P, env).fire();
  assert(env._paq[0][1].endsWith("saba=" + a));
  assert(env._paq[0][1].startsWith("sabt=2&"));
  assertEquals(env.appended.length, 2);
});

Deno.test("förlängt test behåller variant", async () => {
  const a = "fedcba9876543210fedcba9876543210";
  const ls = makeStorage({ "sabt:7": JSON.stringify({ v: "1", a, exp: today(-1) }) });
  const env = makeEnv({ fetchData: live({ to_date: today(10) }), localStorage: ls });
  await loadTag(P, env).fire();
  const rec = JSON.parse(ls.getItem("sabt:7"));
  assertEquals(rec, { v: "1", a, exp: today(10) });
  assert(env._paq[0][1].startsWith("sabt=1&sabi=7"));
});

Deno.test("utgången post för annat test städas", async () => {
  const ls = makeStorage({
    "sabt:3": JSON.stringify({ v: "1", a: "a".repeat(32), exp: today(-2) }),
    "other": "x",
  });
  const env = makeEnv({ fetchData: live(), localStorage: ls });
  await loadTag(P, env).fire();
  assertEquals(ls.getItem("sabt:3"), null);
  assertEquals(ls.getItem("other"), "x");
  assert(ls.getItem("sabt:7") !== null);
});

Deno.test("blockerad localStorage", async () => {
  for (const opts of [{ throwOnGet: true }, { throwOnSet: true }]) {
    const env = makeEnv({ fetchData: live(), localStorage: makeStorage({}, opts) });
    await loadTag(P, env).fire();
    assertEquals(env._paq.length, 0);
    assertEquals(env.appended.length, 0);
  }
});

Deno.test("originalet pingar också", async () => {
  const env = makeEnv({ fetchData: live() });
  const orig = Math.random; Math.random = () => 0.1;
  try { await loadTag(P, env).fire(); } finally { Math.random = orig; }
  assert(env._paq[0][1].startsWith("sabt=1&"));
  assertEquals(env._paq[1], ["ping"]);
  assertEquals(env.appended.length, 0);
});

Deno.test("utanför datum", async () => {
  const env = makeEnv({ fetchData: live({ from_date: today(2), to_date: today(9) }) });
  await loadTag(P, env).fire();
  assertEquals(env._paq.length, 0);
  assertEquals(env.localStorage.length, 0);
});

Deno.test("ingen cookie", async () => {
  const env = makeEnv({ fetchData: live() });
  await loadTag(P, env).fire();
  assertEquals(env.cookieWrites, []);
});

Deno.test("legacy-vägen använder localStorage", async () => {
  const env = makeEnv();
  const csv = ["9", "Gammal", today(-1), today(3), encodeURIComponent("a{}"), encodeURIComponent("1"), ""].join(",");
  const orig = Math.random; Math.random = () => 0.9;
  try { await loadTag({ experiment: csv }, env).fire(); } finally { Math.random = orig; }
  assertMatch(env._paq[0][1], /^sabt=2&sabi=9&sabn=Gammal&saba=[0-9a-f]{32}$/);
  assertEquals(env._paq[1], ["ping"]);
  assertEquals(JSON.parse(env.localStorage.getItem("sabt:9")).exp, today(3));
  assertEquals(env.cookieWrites, []);
});

Deno.test("andra testers poster tas bort, även giltiga (ett test per sajt)", async () => {
  const ls = makeStorage({
    "sabt:3": JSON.stringify({ v: "2", a: "b".repeat(32), exp: today(30) }),
    "sabt:4": "inte json",
    "other": "x",
  });
  const env = makeEnv({ fetchData: live(), localStorage: ls });
  await loadTag(P, env).fire();
  assertEquals(ls.getItem("sabt:3"), null);
  assertEquals(ls.getItem("sabt:4"), null);
  assertEquals(ls.getItem("other"), "x");
  assert(ls.getItem("sabt:7") !== null);
});
