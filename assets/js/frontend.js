(function () {
    'use strict';

    function storageKey(suffix) {
        return 'bdwp70_' + suffix;
    }

    function getStored(suffix, fallback) {
        try {
            var value = window.localStorage.getItem(storageKey(suffix));
            return value === null || typeof value === 'undefined' ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function setStored(suffix, value) {
        try {
            window.localStorage.setItem(storageKey(suffix), value);
        } catch (error) {}
    }

    function clampFontSize(size) {
        var parsed = parseFloat(size || '');
        if (!parsed || parsed < 1 || parsed > 2.2) {
            parsed = 1.36;
        }
        return Math.max(1.0, Math.min(2.2, parsed));
    }

    function fontSizeLabel(size) {
        if (size < 1.18) {
            return 'Pequeno';
        }
        if (size > 1.58) {
            return 'Grande';
        }
        return 'Médio';
    }

    function setActive(root, selector, attr, value) {
        root.querySelectorAll(selector).forEach(function (button) {
            button.classList.toggle('is-active', button.getAttribute(attr) === value);
        });
    }

    function applyReadingState(root) {
        var savedSize = clampFontSize(getStored('font_size', '1.36'));
        var savedBg = getStored('bg', 'light') || 'light';
        var savedFormat = getStored('format', 'verse') || 'verse';
        var savedFontFamily = getStored('font_family', 'default') || 'default';

        if (['light', 'sepia', 'soft', 'dark'].indexOf(savedBg) === -1) {
            savedBg = 'light';
        }
        if (['verse', 'continuous'].indexOf(savedFormat) === -1) {
            savedFormat = 'verse';
        }
        if (['default', 'lexend'].indexOf(savedFontFamily) === -1) {
            savedFontFamily = 'default';
        }

        root.style.setProperty('--bdwp70-reader-font-size', savedSize.toFixed(2) + 'rem');
        root.querySelectorAll('.bdwp70__reader-main').forEach(function (reader) {
            reader.style.setProperty('--bdwp70-reader-font-size', savedSize.toFixed(2) + 'rem');
        });

        root.setAttribute('data-bdwp-bg', savedBg);
        root.setAttribute('data-bdwp-format', savedFormat);
        root.setAttribute('data-bdwp-font-family', savedFontFamily);

        root.querySelectorAll('[data-bdwp70-font-label] small').forEach(function (label) {
            label.textContent = fontSizeLabel(savedSize);
        });

        setActive(root, '[data-bdwp70-bg]', 'data-bdwp70-bg', savedBg);
        setActive(root, '[data-bdwp70-format]', 'data-bdwp70-format', savedFormat);
        setActive(root, '[data-bdwp70-font-family]', 'data-bdwp70-font-family', savedFontFamily);
    }

    function closePanel(root) {
        var toggle = root.querySelector('[data-bdwp70-panel-toggle]');
        var panel = root.querySelector('[data-bdwp70-panel]');

        if (!toggle || !panel) {
            return;
        }

        toggle.setAttribute('aria-expanded', 'false');
        panel.hidden = true;
        root.classList.remove('is-reading-panel-open');
    }

    function openPanel(root) {
        var toggle = root.querySelector('[data-bdwp70-panel-toggle]');
        var panel = root.querySelector('[data-bdwp70-panel]');

        if (!toggle || !panel) {
            return;
        }

        toggle.setAttribute('aria-expanded', 'true');
        panel.hidden = false;
        root.classList.add('is-reading-panel-open');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-bdwp70]').forEach(function (root) {
            var searchForm = root.querySelector('.bdwp70__form--search');
            var search = searchForm ? searchForm.querySelector('input[name="bdwp_pesquisa"]') : null;
            var scope = searchForm ? searchForm.querySelector('select[name="bdwp_livro"]') : null;

            if (search && scope) {
                search.addEventListener('input', function () {
                    if (search.value.trim().length > 0 && !scope.value) {
                        scope.value = '99';
                    }
                });
            }

            if (root.querySelector('.bdwp70__reading-tools')) {
                applyReadingState(root);
            }

            root.querySelectorAll('[data-bdwp70-panel-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var expanded = button.getAttribute('aria-expanded') === 'true';
                    if (expanded) {
                        closePanel(root);
                    } else {
                        openPanel(root);
                    }
                });
            });

            root.querySelectorAll('[data-bdwp70-font]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var current = clampFontSize(root.style.getPropertyValue('--bdwp70-reader-font-size').replace('rem', '') || '1.36');
                    var action = button.getAttribute('data-bdwp70-font');
                    var next = action === 'increase' ? current + 0.08 : current - 0.08;
                    next = clampFontSize(next);
                    setStored('font_size', next.toFixed(2));
                    applyReadingState(root);
                });
            });

            root.querySelectorAll('[data-bdwp70-bg]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var bg = button.getAttribute('data-bdwp70-bg') || 'light';
                    setStored('bg', bg);
                    applyReadingState(root);
                });
            });

            root.querySelectorAll('[data-bdwp70-format]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var format = button.getAttribute('data-bdwp70-format') || 'verse';
                    setStored('format', format);
                    applyReadingState(root);
                });
            });

            root.querySelectorAll('[data-bdwp70-font-family]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var fontFamily = button.getAttribute('data-bdwp70-font-family') || 'default';
                    setStored('font_family', fontFamily);
                    applyReadingState(root);
                });
            });
        });

        document.addEventListener('click', function (event) {
            document.querySelectorAll('[data-bdwp70].is-reading-panel-open').forEach(function (root) {
                if (!root.querySelector('.bdwp70__reading-panel') || root.querySelector('.bdwp70__reading-panel').contains(event.target)) {
                    return;
                }
                closePanel(root);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            document.querySelectorAll('[data-bdwp70].is-reading-panel-open').forEach(function (root) {
                closePanel(root);
            });
        });
    });
}());
