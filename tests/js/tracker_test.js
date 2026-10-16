import { assertEquals } from "jsr:@std/assert@1";
import { loadTracker, makeEnv, makeStorage, today } from "./harness.js";

const A = "0123456789abcdef0123456789abcdef";

Deno.test("skickar aktiv tilldelning", () => {
  const ls = makeStorage({
    "sabt:3": JSON.stringify({ v: "1", a: "b".repeat(32), exp: today(-1) }),
    "sabt:7": JSON.stringify({ v: "2", a: A, exp: today(0) }),
  });
  const env = makeEnv({ localStorage: ls });
  assertEquals(loadTracker(env).log(), `&sabt=2&sabi=7&saba=${A}`);
});

Deno.test("ignorerar utgången", () => {
  const ls = makeStorage({ "sabt:3": JSON.stringify({ v: "1", a: A, exp: today(-1) }) });
  assertEquals(loadTracker(makeEnv({ localStorage: ls })).log(), "");
});

Deno.test("tom när localStorage kastar", () => {
  const env = makeEnv({ localStorage: makeStorage({}, { throwOnGet: true }) });
  assertEquals(loadTracker(env).log(), "");
});

Deno.test("registreras via matomoPluginAsyncInit och läser aldrig cookie", () => {
  const ls = makeStorage({ "sabt:7": JSON.stringify({ v: "1", a: A, exp: today(2) }) });
  const env = makeEnv({ localStorage: ls });
  assertEquals(loadTracker(env, { matomoReady: false }).log(), `&sabt=1&sabi=7&saba=${A}`);
  assertEquals(env.cookieWrites, []);
});
