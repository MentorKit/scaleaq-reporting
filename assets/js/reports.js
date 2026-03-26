(function () {
    'use strict';

    function updateLabel(ms) {
        var checked = ms.querySelectorAll('.saq-multiselect__dropdown input[type=checkbox]:checked:not([data-saq-all])');
        var total = ms.querySelectorAll('.saq-multiselect__dropdown input[type=checkbox]:not([data-saq-all])');
        var txt = ms.querySelector('.saq-multiselect__text');
        if (checked.length === 0 || checked.length === total.length) {
            txt.textContent = 'All Companies';
        } else if (checked.length === 1) {
            txt.textContent = checked[0].value;
        } else {
            txt.textContent = checked.length + ' companies selected';
        }
    }

    /* Multi-select dropdown: event delegation for all dropdowns. */
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('.saq-multiselect__toggle');
        if (toggle) {
            e.preventDefault();
            var ms = toggle.closest('.saq-multiselect');
            var open = ms.classList.toggle('saq-multiselect--open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.querySelectorAll('.saq-multiselect--open').forEach(function (other) {
                if (other !== ms) {
                    other.classList.remove('saq-multiselect--open');
                    other.querySelector('.saq-multiselect__toggle').setAttribute('aria-expanded', 'false');
                }
            });
            return;
        }
        if (!e.target.closest('.saq-multiselect')) {
            document.querySelectorAll('.saq-multiselect--open').forEach(function (ms) {
                ms.classList.remove('saq-multiselect--open');
                ms.querySelector('.saq-multiselect__toggle').setAttribute('aria-expanded', 'false');
            });
        }
    });

    /* Multi-select checkbox change: toggle all / update label. */
    document.addEventListener('change', function (e) {
        var cb = e.target;
        if (!cb.closest('.saq-multiselect__dropdown')) return;
        var ms = cb.closest('.saq-multiselect');
        if (cb.hasAttribute('data-saq-all')) {
            var dd = cb.closest('.saq-multiselect__dropdown');
            dd.querySelectorAll('input[type=checkbox]:not([data-saq-all])').forEach(function (box) {
                box.checked = cb.checked;
            });
        } else {
            var dd = ms.querySelector('.saq-multiselect__dropdown');
            var boxes = dd.querySelectorAll('input[type=checkbox]:not([data-saq-all])');
            var allCb = dd.querySelector('[data-saq-all]');
            if (allCb) {
                var allChecked = true;
                boxes.forEach(function (b) { if (!b.checked) allChecked = false; });
                allCb.checked = allChecked;
            }
        }
        updateLabel(ms);
    });

    /* Stat card drill-down: exclusive toggle with active state. */
    document.addEventListener('click', function (e) {
        var card = e.target.closest('[data-saq-dd]');
        if (!card) return;
        var targetId = card.getAttribute('data-saq-dd');
        var panel = document.getElementById(targetId);
        if (!panel) return;
        var isOpen = panel.classList.contains('saq-drilldown--open');
        /* Close stat-card panels only (not group drill-down table rows). */
        document.querySelectorAll('[data-saq-dd]').forEach(function (c) {
            c.classList.remove('saq-stat--active');
        });
        document.querySelectorAll('.saq-drilldown[id^="saq-dd-"]').forEach(function (p) {
            if (!p.closest('tr')) p.classList.remove('saq-drilldown--open');
        });
        if (!isOpen) {
            panel.classList.add('saq-drilldown--open');
            card.classList.add('saq-stat--active');
        }
    });
})();
