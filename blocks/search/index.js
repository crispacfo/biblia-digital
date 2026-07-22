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

            function setAttr(name) {
                return function (value) {
                    var update = {};
                    update[name] = value;
                    props.setAttributes(update);
                };
            }

            return el('div', { className: 'bdwp70-search-widget bdwp70-search-widget--editor' },
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Search settings', 'biblia-digital'), initialOpen: true },
                        el(TextControl, {
                            label: __('Title', 'biblia-digital'),
                            value: attrs.title,
                            onChange: setAttr('title')
                        }),
                        el(TextControl, {
                            label: __('Field text', 'biblia-digital'),
                            value: attrs.placeholder,
                            onChange: setAttr('placeholder')
                        }),
                        el(TextControl, {
                            label: __('Button text', 'biblia-digital'),
                            value: attrs.button,
                            onChange: setAttr('button')
                        }),
                        el(ToggleControl, {
                            label: __('Allow book selection in search', 'biblia-digital'),
                            checked: !!attrs.showBook,
                            onChange: setAttr('showBook')
                        })
                    )
                ),
                attrs.title ? el('h2', { className: 'bdwp70-search-widget__title' }, attrs.title) : null,
                attrs.showBook ? el('p', {}, __('Field: search all books or one specific book', 'biblia-digital')) : null,
                el('p', {}, attrs.placeholder || __('Enter a word or phrase', 'biblia-digital')),
                el('button', { type: 'button' }, attrs.button || __('Search', 'biblia-digital')),
                el('p', {}, el('em', {}, __('Search uses the active Bible configured for this site.', 'biblia-digital')))
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
