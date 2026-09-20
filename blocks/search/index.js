(function (blocks, element, components, blockEditor, i18n) {
    var el = element.createElement;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var PanelBody = components.PanelBody;

    blocks.registerBlockType('biblia-digital/search', {
        edit: function (props) {
            var attrs = props.attributes;

            // Sem valor salvo, vale o mesmo texto traduzido que o render_callback usa no site.
            var title = undefined !== attrs.title ? attrs.title : __('Pesquisar na Bíblia', 'estudobiblico-biblia-digital');
            var placeholder = undefined !== attrs.placeholder ? attrs.placeholder : __('Digite uma palavra ou frase', 'estudobiblico-biblia-digital');
            var button = undefined !== attrs.button ? attrs.button : __('Pesquisar', 'estudobiblico-biblia-digital');

            function setAttr(name) {
                return function (value) {
                    var update = {};
                    update[name] = value;
                    props.setAttributes(update);
                };
            }

            return el('div', { className: 'bdwp70-search-widget bdwp70-search-widget--editor' },
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Configurações da busca', 'estudobiblico-biblia-digital'), initialOpen: true },
                        el(TextControl, {
                            label: __('Título', 'estudobiblico-biblia-digital'),
                            value: title,
                            onChange: setAttr('title')
                        }),
                        el(TextControl, {
                            label: __('Texto do campo', 'estudobiblico-biblia-digital'),
                            value: placeholder,
                            onChange: setAttr('placeholder')
                        }),
                        el(TextControl, {
                            label: __('Texto do botão', 'estudobiblico-biblia-digital'),
                            value: button,
                            onChange: setAttr('button')
                        }),
                        el(ToggleControl, {
                            label: __('Permitir escolher o livro na busca', 'estudobiblico-biblia-digital'),
                            checked: !!attrs.showBook,
                            onChange: setAttr('showBook')
                        })
                    )
                ),
                title ? el('h2', { className: 'bdwp70-search-widget__title' }, title) : null,
                attrs.showBook ? el('p', {}, __('Campo: pesquisar em todos os livros ou em um livro específico', 'estudobiblico-biblia-digital')) : null,
                el('p', {}, placeholder),
                el('button', { type: 'button' }, button),
                el('p', {}, el('em', {}, __('A busca sempre usa a Bíblia ativa definida em Configurações > Bíblia Digital.', 'estudobiblico-biblia-digital')))
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
