/* NutriDeq CSRF bridge for fetch().
   Patches window.fetch so every same-origin non-GET request automatically
   carries the X-CSRF-Token header (token mirrored into the nd_csrf cookie by
   session_bootstrap.php). Include on any page that uses fetch():
   <script src="scripts/csrf-fetch.js"></script>
   Form-based pages keep using the hidden csrf_token field — both are accepted
   server-side by verify_csrf(). */
(function () {
    'use strict';
    if (window.__ndCsrfFetchPatched) return;
    window.__ndCsrfFetchPatched = true;

    function getCookie(name) {
        var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : '';
    }

    var original = window.fetch;
    if (typeof original !== 'function') return;

    window.fetch = function (input, init) {
        try {
            var url = (typeof input === 'string') ? input : ((input && input.url) || '');
            var method = ((init && init.method) || (input && input.method) || 'GET').toUpperCase();

            // Never touch cross-origin requests
            var crossOrigin = url.indexOf('http') === 0 && url.indexOf(location.origin) !== 0;

            if (!crossOrigin && method !== 'GET' && method !== 'HEAD') {
                var token = getCookie('nd_csrf');
                if (token) {
                    init = init || {};
                    var headers = new Headers(init.headers || (input && input.headers) || undefined);
                    if (!headers.has('X-CSRF-Token')) {
                        headers.set('X-CSRF-Token', token);
                    }
                    init.headers = headers;
                }
            }
        } catch (e) {
            /* never break application requests because of the patch */
        }
        return original.call(this, input, init);
    };
})();
