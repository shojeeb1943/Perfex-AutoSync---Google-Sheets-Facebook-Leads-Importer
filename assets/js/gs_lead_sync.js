/**
 * gs_lead_sync — frontend JS
 */

(function ($) {
    'use strict';

    // Called when server responds with 403/419 (CSRF/session expired).
    window.gsHandleSessionExpired = function (statusEl) {
        var msg = 'Session expired — reloading in a moment…';
        if (statusEl && statusEl.length) {
            statusEl.removeClass('text-success').addClass('text-danger').text(msg);
        } else {
            alert(msg);
        }
        setTimeout(function () { location.reload(); }, 1500);
    };

})(jQuery);
