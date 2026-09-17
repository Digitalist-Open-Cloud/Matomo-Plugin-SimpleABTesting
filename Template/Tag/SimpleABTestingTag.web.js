(function () {
  return function (parameters, TagManager) {
      this.fire = function () {
          const experiment = parameters.get("experiment");
          const matomoOrigin = parameters.get("matomoOrigin");
          const parts = String(experiment || "").split(",");
          const _paq = (window._paq = window._paq || []);
          const ORIGINAL = "1";
          const VARIANT = "2";

          if (!experiment) return;

          if (parts.length >= 6) {
              // Legacy format: "id,name,from,to,css,js," — the full
              // snapshot baked in at selection time by an older version of
              // this tag. Kept working as-is (no live fetch) so tags
              // created before this change keep firing; re-select the
              // experiment in this tag (or recreate it) to upgrade it to
              // the live-fetch format below.
              const expId = parts[0];
              const expName = parts[1];
              const start = parts[2] + "T00:00:00Z";
              const stop = parts[3] + "T23:59:00Z";
              const css = decodeURIComponent(parts[4].replace(/\+/g, "%20"));
              const js = decodeURIComponent(parts[5].replace(/\+/g, "%20"));
              initExp(_paq, "sabt_" + expName, start, stop, js, css, expId, expName);
              return;
          }

          // Current format: "id,idSite" — a stable reference. The actual
          // name/dates/css/js are fetched fresh on every page load, so
          // editing the experiment takes effect immediately without
          // republishing this tag.
          const expId = parts[0];
          const expSiteId = parts[1];
          if (!expId || !expSiteId || !matomoOrigin) return;

          fetchExperiment(matomoOrigin, expId, expSiteId, function (data) {
              if (!data || !data.found) return;
              const cookieName = "sabt_" + data.name;
              const start = data.from_date + "T00:00:00Z";
              const stop = data.to_date + "T23:59:00Z";
              initExp(_paq, cookieName, start, stop, data.js_insert || "", data.css_insert || "", expId, data.name);
          });

          function fetchExperiment(origin, id, idSite, callback) {
              var url = origin + "/index.php?module=SimpleABTesting&action=getExperimentPublic&format=json"
                  + "&idSite=" + encodeURIComponent(idSite) + "&id=" + encodeURIComponent(id);
              try {
                  if (window.fetch) {
                      fetch(url, { method: "GET", credentials: "omit" })
                          .then(function (res) { return res.json(); })
                          .then(callback)
                          .catch(function () { /* silent — no experiment shown this load */ });
                  } else {
                      var xhr = new XMLHttpRequest();
                      xhr.open("GET", url, true);
                      xhr.onload = function () {
                          if (xhr.status === 200) {
                              try { callback(JSON.parse(xhr.responseText)); } catch (e) { /* ignore */ }
                          }
                      };
                      xhr.send();
                  }
              } catch (e) {
                  // ignore — no experiment shown this load
              }
          }

          function initExp(_paq, testName, testStartDate, testEndDate, scriptText, cssText, expId, expName) {
              let currentVariant = getCookie(testName);
              const currentDate = new Date();
              const startDate = new Date(testStartDate);
              const endDate = new Date(testEndDate);

              if (currentDate >= startDate && currentDate <= endDate) {
                  if (!currentVariant) {
                      // Randomly assign original or variant.
                      currentVariant = Math.random() < 0.5 ? ORIGINAL : VARIANT;
                      setCookie(testName, currentVariant, testEndDate);
                  }
                  // Send tracking parameters to Matomo
                  _paq.push(['appendToTrackingUrl', 'sabt=' + currentVariant + '&sabi=' + expId + '&sabn=' + encodeURIComponent(expName)]);

                  if (currentVariant === VARIANT) {
                      try {
                          insertCSS(cssText);
                          insertJS(scriptText);
                      } catch (e) {
                          console.error("Error in script execution", e);
                      }
                  }
              }
          }

          /**
           * Function to insert CSS into the document head
           */
          function insertCSS(cssText) {
              const style = document.createElement("style");
              style.type = "text/css";
              style.textContent = cssText;
              document.head.appendChild(style);
          }

          /**
           * Function to insert JS script into the document head
           */
          function insertJS(scriptText) {
              const script = document.createElement("script");
              script.type = "text/javascript";
              script.text = scriptText;
              document.head.appendChild(script);
          }

          /**
           * Function to set a cookie
           */
          function setCookie(name, value, expires) {
              const date = new Date(expires);
              const cookie = name + "=" + encodeURIComponent(value) + ";expires=" + date.toUTCString() + ";path=/";
              document.cookie = cookie;
          }

          /**
           * Function to get a cookie by name
           */
          function getCookie(name) {
              const escapeRegExp = (string) => string.replace(/[.*+\-?^${}()|[\]\\]/g, "\\$&");
              const safeName = escapeRegExp(name);
              const match = document.cookie.match(new RegExp("(^| )" + safeName + "=([^;]+)"));
              return match ? decodeURIComponent(match[2]) : null;
          }
      };
  };
})();
