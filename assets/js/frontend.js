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

    // Rótulos traduzidos vêm do PHP em data-label-*; sem eles, fica o texto atual.
    function fontSizeLabel(size, holder) {
        var key = size < 1.18 ? 'small' : (size > 1.58 ? 'large' : 'medium');
        return holder ? holder.getAttribute('data-label-' + key) : null;
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

        /*
         * Só grava o tamanho inline quando o leitor escolheu um. Gravar sempre
         * anulava o passo menor que o CSS ja define para telas estreitas, e o
         * texto ficava em 22px numa coluna de ~300px — cerca de 26 caracteres
         * por linha.
         */
        var escolheuFonte = getStored('font_size', null) !== null;

        if (escolheuFonte) {
            root.style.setProperty('--bdwp70-reader-font-size', savedSize.toFixed(2) + 'rem');
            root.querySelectorAll('.bdwp70__reader-main').forEach(function (reader) {
                reader.style.setProperty('--bdwp70-reader-font-size', savedSize.toFixed(2) + 'rem');
            });
        } else {
            root.style.removeProperty('--bdwp70-reader-font-size');
            root.querySelectorAll('.bdwp70__reader-main').forEach(function (reader) {
                reader.style.removeProperty('--bdwp70-reader-font-size');
            });
        }

        root.setAttribute('data-bdwp-bg', savedBg);
        root.setAttribute('data-bdwp-format', savedFormat);
        root.setAttribute('data-bdwp-font-family', savedFontFamily);

        root.querySelectorAll('[data-bdwp70-font-label] small').forEach(function (label) {
            var texto = fontSizeLabel(savedSize, label.closest('[data-bdwp70-font-label]'));
            if (texto) {
                label.textContent = texto;
            }
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

/*
 * 1.1.72 — Comportamentos de leitura.
 *
 * - Linha inteira do versiculo como alvo, preservando a selecao de texto.
 * - Setas esquerda/direita mudam de capitulo.
 * - Barra fixa some ao rolar para baixo e volta ao rolar para cima (mobile).
 */
(function () {
    'use strict';

    var SELETOR_RAIZ = '[data-bdwp70]';

    function ehInterativo(alvo) {
        return !!(alvo && alvo.closest && alvo.closest('a, button, input, select, textarea, summary, label, [contenteditable="true"]'));
    }

    function temSelecaoDeTexto() {
        try {
            var selecao = window.getSelection();
            return !!(selecao && String(selecao).trim().length > 0);
        } catch (erro) {
            return false;
        }
    }

    /* --------------------------------------------------- linha do versiculo */
    function ligarLinhasDeVersiculo(raiz) {
        raiz.addEventListener('click', function (evento) {
            var linha = evento.target.closest ? evento.target.closest('.bdwp70__verse-line') : null;
            if (!linha || !raiz.contains(linha)) {
                return;
            }

            // Um clique em link ou botao segue o proprio destino.
            if (ehInterativo(evento.target)) {
                return;
            }

            // Selecionar e copiar o texto continua funcionando.
            if (temSelecaoDeTexto()) {
                return;
            }

            var destino = linha.getAttribute('data-bdwp70-verse-url');
            if (!destino) {
                return;
            }

            raiz.querySelectorAll('.bdwp70__verse-line.is-selected').forEach(function (outra) {
                if (outra !== linha) {
                    outra.classList.remove('is-selected');
                }
            });
            linha.classList.add('is-selected');

            window.location.href = destino;
        });
    }

    /* ------------------------------------------------------------- teclado */
    function ligarTeclado(raiz) {
        var anterior = raiz.getAttribute('data-bdwp70-prev');
        var proximo = raiz.getAttribute('data-bdwp70-next');

        if (!anterior && !proximo) {
            return;
        }

        document.addEventListener('keydown', function (evento) {
            if (evento.defaultPrevented || evento.altKey || evento.ctrlKey || evento.metaKey || evento.shiftKey) {
                return;
            }

            if (evento.key !== 'ArrowLeft' && evento.key !== 'ArrowRight') {
                return;
            }

            // Nao sequestra as setas enquanto o leitor digita ou navega num campo.
            var ativo = document.activeElement;
            if (ehInterativo(ativo) || (ativo && ativo.isContentEditable)) {
                return;
            }

            var destino = evento.key === 'ArrowLeft' ? anterior : proximo;
            if (!destino) {
                return;
            }

            evento.preventDefault();
            window.location.href = destino;
        });
    }

    /* ----------------------------------------------------------- barra fixa */
    function ligarBarraFixa(raiz) {
        var barra = raiz.querySelector('[data-bdwp70-sticky-bar]');
        if (!barra) {
            return;
        }

        var ultimo = window.pageYOffset || 0;
        var agendado = false;

        function avaliar() {
            agendado = false;

            var atual = window.pageYOffset || 0;
            var delta = atual - ultimo;

            // Margem morta evita tremor em rolagens minimas.
            if (Math.abs(delta) < 8) {
                return;
            }

            if (delta > 0 && atual > 160) {
                raiz.classList.add('is-bar-hidden');
            } else {
                raiz.classList.remove('is-bar-hidden');
            }

            ultimo = atual;
        }

        window.addEventListener('scroll', function () {
            if (agendado) {
                return;
            }
            agendado = true;
            window.requestAnimationFrame(avaliar);
        }, { passive: true });
    }

    /* ------------------------------------------------- deep link /cap/versiculo/ */
    /*
     * A URL de versiculo entrega o capitulo inteiro com o versiculo destacado no
     * servidor. Sem isto o leitor caia no topo do capitulo e precisava procurar o
     * destaque. Uma ancora explicita na URL tem prioridade e nao e sobrescrita.
     */
    function posicionarVersiculo(raiz) {
        if (window.location.hash) {
            return;
        }

        var alvo = raiz.querySelector('.bdwp70__verse-line.is-selected');
        if (!alvo || !alvo.scrollIntoView) {
            return;
        }

        alvo.setAttribute('tabindex', '-1');

        window.requestAnimationFrame(function () {
            alvo.scrollIntoView({ block: 'center', behavior: 'auto' });
            try {
                alvo.focus({ preventScroll: true });
            } catch (erro) {}
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll(SELETOR_RAIZ).forEach(function (raiz) {
            ligarLinhasDeVersiculo(raiz);
            ligarTeclado(raiz);
            ligarBarraFixa(raiz);
            posicionarVersiculo(raiz);
        });
    });
}());
