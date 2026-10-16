// Test harness: evaluates the MTM tag / tracker plugin source in a faked browser.
const root = new URL("../../", import.meta.url);

export function today(offsetDays = 0) {
  return new Date(Date.now() + offsetDays * 86400000).toISOString().slice(0, 10);
}

export function makeStorage(initial = {}, opts = {}) {
  const m = new Map(Object.entries(initial));
  const s = {
    get length() { return m.size; },
    key(i) { return Array.from(m.keys())[i] ?? null; },
    getItem(k) { if (opts.throwOnGet) throw new Error("blocked"); return m.has(k) ? m.get(k) : null; },
    setItem(k, v) { if (opts.throwOnSet) throw new Error("quota"); m.set(k, String(v)); },
    removeItem(k) { m.delete(k); },
    _map: m,
  };
  return s;
}

export function makeEnv(opts = {}) {
  const cookieWrites = [];
  const appended = [];
  const document = {
    head: { appendChild(n) { appended.push(n); } },
    createElement(tag) { return { tagName: tag }; },
    get cookie() { return ""; },
    set cookie(v) { cookieWrites.push(v); },
  };
  const fetchCalls = [];
  const env = {
    appended, cookieWrites, fetchCalls,
    localStorage: opts.localStorage ?? makeStorage(),
    _paq: [],
    document,
    crypto: {
      getRandomValues(a) { for (let i = 0; i < a.length; i++) a[i] = (i * 17 + 3) & 255; return a; },
    },
    fetchData: opts.fetchData,
  };
  env.fetch = (url) => {
    fetchCalls.push(url);
    return Promise.resolve({ json: () => Promise.resolve(env.fetchData) });
  };
  env.window = { _paq: env._paq, fetch: env.fetch, localStorage: env.localStorage, crypto: env.crypto };
  return env;
}

function evalSource(rel, env, extraArgs = {}) {
  const src = Deno.readTextFileSync(new URL(rel, root));
  const names = ["window", "document", "localStorage", "crypto", "fetch", ...Object.keys(extraArgs)];
  const vals = [env.window, env.document, env.localStorage, env.crypto, env.fetch, ...Object.values(extraArgs)];
  return new Function(...names, "return " + src.trim().replace(/;$/, "")).call(undefined, ...vals);
}

export function loadTag(params, env) {
  const Ctor = evalSource("Template/Tag/SimpleABTestingTag.web.js", env);
  const tag = new Ctor({ get: (n) => params[n] }, {});
  return {
    env,
    async fire() {
      tag.fire();
      // let the fetch -> json -> callback chain settle
      await new Promise((r) => setTimeout(r, 0));
    },
  };
}

export function loadTracker(env, { matomoReady = true } = {}) {
  let plugin;
  const Matomo = { addPlugin(_n, p) { plugin = p; } };
  const asyncInit = [];
  if (matomoReady) env.window.Matomo = Matomo;
  env.window.matomoPluginAsyncInit = asyncInit;
  const src = Deno.readTextFileSync(new URL("tracker.js", root));
  // The IIFE refers to window.Matomo and bare Matomo
  new Function("window", "document", "localStorage", "Matomo", src)(
    env.window, env.document, env.localStorage, Matomo);
  if (!matomoReady) asyncInit.forEach((f) => f());
  return { log: () => plugin.log() };
}
