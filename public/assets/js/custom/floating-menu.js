/**
 * Menu flutuante — comportamento
 * - Flyouts do rail (desktop): hover com intenção + clique para fixar, Esc/clique-fora fecha
 * - Bottom sheet (mobile): abre pelo botão Menu do dock, fecha por backdrop/handle/Esc
 * - Oculta o dock quando o teclado virtual está aberto (visualViewport)
 */
(function () {
    'use strict';

    var OPEN_DELAY = 130;   // ms de intenção antes de abrir no hover
    var CLOSE_DELAY = 260;  // ms de tolerância ao mover o mouse até o flyout

    var rail = document.getElementById('fm_rail');
    var dock = document.getElementById('fm_dock');
    var sheet = document.getElementById('fm_sheet');

    /* ========================================================================
       FLYOUTS DO RAIL
       ======================================================================== */
    if (rail) {
        var triggers = rail.querySelectorAll('[data-fm-flyout]');
        var openTimer = null;
        var closeTimer = null;
        var current = null; // { trigger, flyout }

        function positionFlyout(trigger, flyout) {
            var rect = trigger.getBoundingClientRect();
            // Medir com o flyout visível
            flyout.style.visibility = 'hidden';
            flyout.classList.add('fm-open');
            var height = flyout.offsetHeight;
            var top = rect.top + rect.height / 2 - height / 2;
            var min = 12;
            var max = window.innerHeight - height - 12;
            top = Math.max(min, Math.min(top, Math.max(min, max)));
            flyout.style.top = top + 'px';
            flyout.style.visibility = '';
        }

        function openFlyout(trigger) {
            var flyout = document.getElementById(trigger.getAttribute('data-fm-flyout'));
            if (!flyout) return;
            if (current && current.flyout !== flyout) {
                closeFlyout();
            }
            clearTimeout(closeTimer);
            positionFlyout(trigger, flyout);
            trigger.classList.add('fm-open');
            trigger.setAttribute('aria-expanded', 'true');
            current = { trigger: trigger, flyout: flyout };
        }

        function closeFlyout() {
            clearTimeout(openTimer);
            clearTimeout(closeTimer);
            if (!current) return;
            current.flyout.classList.remove('fm-open');
            current.trigger.classList.remove('fm-open');
            current.trigger.setAttribute('aria-expanded', 'false');
            current = null;
        }

        function scheduleClose() {
            clearTimeout(closeTimer);
            closeTimer = setTimeout(closeFlyout, CLOSE_DELAY);
        }

        triggers.forEach(function (trigger) {
            var flyout = document.getElementById(trigger.getAttribute('data-fm-flyout'));
            if (!flyout) return;

            trigger.addEventListener('mouseenter', function () {
                clearTimeout(openTimer);
                clearTimeout(closeTimer);
                openTimer = setTimeout(function () { openFlyout(trigger); }, OPEN_DELAY);
            });

            trigger.addEventListener('mouseleave', function () {
                clearTimeout(openTimer);
                scheduleClose();
            });

            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                clearTimeout(openTimer);
                if (current && current.trigger === trigger) {
                    closeFlyout();
                } else {
                    openFlyout(trigger);
                }
            });

            flyout.addEventListener('mouseenter', function () {
                clearTimeout(closeTimer);
            });

            flyout.addEventListener('mouseleave', scheduleClose);
        });

        document.addEventListener('click', function (event) {
            if (!current) return;
            if (rail.contains(event.target) || current.flyout.contains(event.target)) return;
            closeFlyout();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeFlyout();
        });

        window.addEventListener('resize', closeFlyout);
    }

    /* ========================================================================
       BOTTOM SHEET (mobile)
       ======================================================================== */
    if (sheet) {
        var menuBtn = document.getElementById('fm_dock_menu_btn');

        function openSheet() {
            sheet.hidden = false;
            document.body.classList.add('fm-sheet-open');
            if (menuBtn) menuBtn.setAttribute('aria-expanded', 'true');
        }

        function closeSheet() {
            sheet.hidden = true;
            document.body.classList.remove('fm-sheet-open');
            if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
        }

        if (menuBtn) {
            menuBtn.addEventListener('click', function () {
                if (sheet.hidden) openSheet();
                else closeSheet();
            });
        }

        sheet.querySelectorAll('[data-fm-sheet-close]').forEach(function (el) {
            el.addEventListener('click', closeSheet);
        });

        // Fechar ao navegar (permite o browser seguir o link normalmente)
        sheet.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeSheet);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !sheet.hidden) closeSheet();
        });

        // Gesto simples de arrastar para baixo no painel para fechar
        var panel = sheet.querySelector('.fm-sheet-panel');
        var touchStartY = null;
        if (panel) {
            panel.addEventListener('touchstart', function (event) {
                var body = sheet.querySelector('.fm-sheet-body');
                // Só inicia o gesto se o conteúdo estiver no topo do scroll
                if (!body || body.scrollTop <= 0) {
                    touchStartY = event.touches[0].clientY;
                }
            }, { passive: true });

            panel.addEventListener('touchmove', function (event) {
                if (touchStartY === null) return;
                var delta = event.touches[0].clientY - touchStartY;
                if (delta > 80) {
                    touchStartY = null;
                    closeSheet();
                }
            }, { passive: true });

            panel.addEventListener('touchend', function () {
                touchStartY = null;
            });
        }
    }

    /* ========================================================================
       TECLADO VIRTUAL: ocultar o dock enquanto digita (mobile)
       ======================================================================== */
    if (dock && window.visualViewport) {
        var baseHeight = window.visualViewport.height;

        window.visualViewport.addEventListener('resize', function () {
            var shrunk = baseHeight - window.visualViewport.height > 150;
            document.body.classList.toggle('fm-keyboard-open', shrunk);
            if (!shrunk) {
                baseHeight = Math.max(baseHeight, window.visualViewport.height);
            }
        });

        window.addEventListener('orientationchange', function () {
            setTimeout(function () {
                baseHeight = window.visualViewport.height;
            }, 300);
        });
    }
})();
