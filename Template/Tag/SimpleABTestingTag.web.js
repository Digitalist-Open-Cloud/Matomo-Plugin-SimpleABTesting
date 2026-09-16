(function () {
  return function (parameters, TagManager) {
      this.fire = function () {
          const experiment = parameters.get("experiment");
          const parts = experiment.split(",");
          const expId = parts[0];
          const expName = parts[1];
          const cookieName = "sabt_" + expName;
          const start = parts[2] + "T00:00:00Z";
          const stop = parts[3] + "T23:59:00Z";
          const css = decodeURIComponent(parts[4].replace(/\+/g, "%20"));
          const js = decodeURIComponent(parts[5].replace(/\+/g, "%20"));
          const _paq = (window._paq = window._paq || []);
          const ORIGINAL = "1";
          const VARIANT = "2";

          initExp(_paq, cookieName, start, stop, js, css, expId, expName);

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