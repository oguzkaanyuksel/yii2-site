(function ($) {
    'use strict';

    // Works for every module list on the page (settings, preferences): each one drives the form it sits in.
    $('.setting-nav').each(function () {
        var $form = $(this).closest('form');
        var $links = $(this).find('a[data-bs-toggle="tab"]');

        // The URL hash is the module key (#auth), not the pane id (#setting-auth): a hash matching an element id
        // would make the browser scroll down to that pane on load.
        function moduleHash($link) {
            return '#' + $link.attr('href').replace(/^#setting-/, '');
        }

        // The form posts to its action with the open tab as the hash, so the redirect after save comes back to the same tab.
        function keepTab(hash) {
            if ($form.length) {
                $form.attr('action', $form.attr('action').replace(/#.*$/, '') + hash);
            }
        }

        // The list scrolls on its own (a vertical rail, or a horizontal strip on phones); keep the open tab in view
        // without moving the page.
        function reveal(link) {
            var nav = link.closest('.setting-nav');
            if (!nav) {
                return;
            }
            var navBox = nav.getBoundingClientRect();
            var linkBox = link.getBoundingClientRect();
            if (nav.scrollWidth > nav.clientWidth) {
                nav.scrollLeft += linkBox.left - navBox.left - (nav.clientWidth - linkBox.width) / 2;
            }
            if (nav.scrollHeight > nav.clientHeight) {
                nav.scrollTop += linkBox.top - navBox.top - (nav.clientHeight - linkBox.height) / 2;
            }
        }

        $links.on('shown.bs.tab', function () {
            var link = this;
            window.requestAnimationFrame(function () {
                reveal(link);
            });
            var hash = moduleHash($(this));
            keepTab(hash);
            if (window.history.replaceState) {
                window.history.replaceState(null, '', hash);
            }
        });

        function openFromHash() {
            var hash = window.location.hash.replace(/^#setting-/, '#');
            var $current = hash ? $links.filter('[href="#setting-' + hash.substring(1) + '"]') : $();
            if ($current.length) {
                bootstrap.Tab.getOrCreateInstance($current[0]).show();
                return true;
            }
            return false;
        }

        if (!openFromHash() && $links.length) {
            keepTab(moduleHash($links.first()));
        }
        // A hash typed into the address bar (or a bookmark on the same page) opens that tab too.
        $(window).on('hashchange', openFromHash);
    });
})(jQuery);
