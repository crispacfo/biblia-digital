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
            var title = undefined !== attrs.title ? attrs.title : __('Search the Bible', 'estudobiblico-biblia-digital');
            var placeholder = undefined !== attrs.placeholder ? attrs.placeholder : __('Enter a word or phrase', 'estudobiblico-biblia-digital');
            var button = undefined !== attrs.button ? attrs.button : __('Search', 'estudobiblico-biblia-digital');

            function setAttr(name) {
                return function (value) {
                    var update = {};
                    update[name] = value;
                    props.setAttributes(update);
                };
            }

            return el('div', { className: 'bdwp70-search-widget bdwp70-search-widget--editor' },
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Search settings', 'estudobiblico-biblia-digital'), initialOpen: true },
                        el(TextControl, {
                            label: __('Title', 'estudobiblico-biblia-digital'),
                            value: title,
                            onChange: setAttr('title')
                        }),
                        el(TextControl, {
                            label: __('Field text', 'estudobiblico-biblia-digital'),
                            value: placeholder,
                            onChange: setAttr('placeholder')
                        }),
                        el(TextControl, {
                            label: __('Button text', 'estudobiblico-biblia-digital'),
                            value: button,
                            onChange: setAttr('button')
                        }),
                        el(ToggleControl, {
                            label: __('Allow choosing the book in the search', 'estudobiblico-biblia-digital'),
                            checked: !!attrs.showBook,
                            onChange: setAttr('showBook')
                        })
                    )
                ),
                title ? el('h2', { className: 'bdwp70-search-widget__title' }, title) : null,
                attrs.showBook ? el('p', {}, __('Field: search all books or one specific book', 'estudobiblico-biblia-digital')) : null,
                el('p', {}, placeholder),
                el('button', { type: 'button' }, button),
                el('p', {}, el('em', {}, __('Search always uses the active Bible set in Settings > Bíblia Digital.', 'estudobiblico-biblia-digital')))
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
