<?php
/** @var array $model */

$theme_notices = [
    'shopkeeper'    => 'Your theme (Shopkeeper) is adding some styles that we can sadly not override, such as the border color of input elements.',
    'yith proteo'   => '<p>Your theme (Proteo) applies custom styles to select lists, radio buttons, and checkboxes. To enable our styling, you have to disable theirs. Go to Appearance > Customize > Miscellaneous > Forms and set the following options to "No": <ul><li>Use custom style on select elements<li>Use custom style on checkbox and radio button elements</ul>.',
];

?>

<style>
    
    /* Better tooltip */
    .components-tooltip {
        max-width: 264px;
        width: auto;
        white-space: normal;
        overflow: hidden;
        text-align: center;
        padding:9px 6px;
    }
    
    /* Change GB styles for toggles */
    .components-form-toggle, .components-form-toggle .components-form-toggle__track{
        height: 18px;
        width:34px;
    }

    .components-form-toggle .components-form-toggle__track{
        border-radius: 26px;
        border-color:#c3c3c3;
    }

    .components-form-toggle .components-form-toggle__thumb{
        height: 12px;
        width:12px;
        top:3px;
        left:4px;
        background-color:#3c434a;
    }

    .components-form-toggle.is-checked .components-form-toggle__thumb{
        transform: translateX(14px);
    }
    
    /* Change default GB styles for input fields because we like our style better! */
    div.components-input-control__backdrop{
        border-color:#c3c3c3!important;
        box-shadow:0 1px 2px rgba(16,24,40,.08);
        border-radius:4px!important;
    }

    input.components-input-control__input:focus .components-input-control__backdrop{
        border-color: var(--wp-admin-theme-color)!important;
    }

    .components-range-control .components-base-control__field{
        margin:0;
    }
    
    .components-unit-control__select{
        border-left:1px solid #c3c3c3!important;
        background:#f4f4f4!important;
    }
    
    .components-panel__row{ margin-top:1rem;width:100%;display:inline-flex;}
    
    /* Change input fields from 32px to 36px */
    .components-input-control__input{
        height:36px!important;
    }
    
    /* Color picker, hide copy button and hex/hsl switcher */
    .components-color-picker [data-wp-component="HStack"]{display:none;}
    
    /* 100% inputs */
    .components-base-control{flex:1;}

    /* enlarge suffixes in unti-control textboxes */
    .components-unit-control__unit-label,.components-unit-control__select{ 
        font-size:11px!important;
        width: 46px!important;
    }
    
    /* Set panel titles bold rather than 500. */
    .components-panel__body-toggle.components-button{
        font-weight: bold!important;
    }
    .components-color-picker .components-flex{ padding: 0 8px; }
    
    .apf-row-wrap{
        padding-bottom: 1.2rem;
        width:100%;
        display: inline-flex;
    }
    
    button.apf-clear-color{
        right:2px;
        border-radius: 0;
        margin-left: auto;
    }
    
    .apf-colorpicker-setting.colorpicker--default {
        width: 100%;
    }
    
    .apf-colorpicker-actions{
        position: absolute;
        bottom: 13px;
        right:10px;
    }
    
    .apf-colorpicker-container {
        display: flex;
        align-items: center;
        cursor: pointer;
        padding:0 0 0 10px;
        border-radius: 4px;
        flex-grow:1;
        height:36px;
        box-shadow:0 1px 2px rgba(16,24,40,.08);
        box-sizing: border-box;
    }

    .colorpicker--default .apf-colorpicker-container {
        border:1px solid #c3c3c3 /*#d0d0d0*/;
    }
    
    .apf-colorpicker-label{
        display: inline-block;
        padding-left:10px;
    }
    
    .apf-setting-row{
        display: flex;
        width: 100%;
        flex:1;
    }
    
    .apf-setting-title {
        font-weight: 500;
        margin-right:15px;
        width:152px;
        padding-top: 5px;
    }
    
    .apf-setting{
        display: flex;
        align-items: center;
        width: 100%;
        flex: 1;
    }
    
    .apf-border-setting{
        display: flex;
        width: 100%;
    }

    .apf-border-setting-inner{
        display:flex;
        border-radius: 4px;
        border:1px solid #c3c3c3;
        width:100%;
        align-items: center;
    }
    
    .apf-border-setting .components-input-control__suffix{
        border-right: 1px solid #c3c3c3;
    }
    .apf-border-setting .components-range-control{
        margin: 0 15px;
    }
    .apf-border-setting .apf-colorpicker-container {
        border-radius: 0;
        padding: 0 10px;
        border-right: 1px solid #c3c3c3;
    }
    
    .apf-border-setting .components-dropdown-menu{
        border-radius: 0;
        border-right: 1px solid #c3c3c3;
    }

    .apf-border-setting .components-input-control__backdrop{ 
        border-color:transparent!important;
    }
    
    .apf-border-width{
        position: relative;
    }
    
    .apf-border-setting input::-webkit-outer-spin-button,
    .apf-border-setting input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .apf-border-setting input[type=number]{
        -moz-appearance: textfield;
    }

    .apf-border-suffix{
        position: absolute;
        right: 6px;
        font-size: 11px;
        text-transform: uppercase;
        top: 50%;
        transform: translateY(-50%);
    }
    
    /* Style tab panels */
    .components-tab-panel__tabs{
        background: #f0f0f1;
        border-radius:6px;
    }

    .components-tab-panel__tabs button {
        height:30px;
        margin:8px;
        padding: 0 30px;
        display: flex;
        justify-content: center;
        width: 30%;
    }

    .components-tab-panel__tabs button.is-active:after {
        content:none;
    }

    .components-tab-panel__tabs button.is-active{
        background:white;
        border-radius:6px;
        box-shadow: 0 1px 2px 0 rgba(16, 24, 40, 0.06), 0 1px 3px 0 rgba(16, 24, 40, 0.1);
    }
    
    .components-tab-panel__tab-content{
        padding: 1rem .4rem;
    }
    
    .apf-tab-list{ width: 100%; }

    .apf-color-indicator{
        width:20px;
        height:20px;
    }

    .apf-color-inherit .component-color-indicator,
    .apf-color-null .component-color-indicator,
    .apf-color- .component-color-indicator,
    .apf-color-currentColor .component-color-indicator {
        /*background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHN0eWxlPSJzaGFwZS1yZW5kZXJpbmc6Z2VvbWV0cmljUHJlY2lzaW9uO3RleHQtcmVuZGVyaW5nOmdlb21ldHJpY1ByZWNpc2lvbjtpbWFnZS1yZW5kZXJpbmc6b3B0aW1pemVRdWFsaXR5O2ZpbGwtcnVsZTpldmVub2RkO2NsaXAtcnVsZTpldmVub2RkIiB2aWV3Qm94PSIwIDAgNTEyIDUxMiI+PHBhdGggc3R5bGU9Im9wYWNpdHk6MSIgZmlsbD0iIzIwMjAyMCIgZD0iTTQ4NC41IDE0OC41YzEuOTE1LS4yODQgMy41ODEuMDQ5IDUgMWE3MzkwLjI3MSA3MzkwLjI3MSAwIDAgMS0xMjQuNSAxMjZsLTE2OS0xNjljLS42NjctLjY2Ny0uNjY3LTEuMzMzIDAtMkwyMzQuNSA2NmEzNy4wOTEgMzcuMDkxIDAgMCAxIDE1LTVjMjIuODU4LTIuMjI5IDQ0LjE5MSAyLjQzOCA2NCAxNGE2MzQuODYgNjM0Ljg2IDAgMCAxIDU0IDQxYzI3Ljk0OCAyMC4yMDUgNTkuMTE1IDMxLjcwNSA5My41IDM0LjVhNDkzLjU1OCA0OTMuNTU4IDAgMCAxIDIzLjUtMlpNNjEuNSAzNDUuNWMxLjQwNi0uNDczIDIuMDczLTEuNDczIDItMyA3LjY3Ny0xMS4xODYgMTYuNjc3LTIxLjM1MyAyNy0zMC41YTc5Ny4wNzkgNzk3LjA3OSAwIDAgMSA1My0zOSAxMDQuNDE2IDEwNC40MTYgMCAwIDAgMjEuNS0yMC41YzQuMzc4LTguMDY0IDQuMDQ1LTE2LjA2NC0xLTI0YTY1NS45NDkgNjU1Ljk0OSAwIDAgMC0zMC0zMWMtNi44MTQtOS44MjItNy4xNDctMTkuODIyLTEtMzBsNDYtNDYgMTY5IDE2OWMuNjY3LjY2Ny42NjcgMS4zMzMgMCAyTDMwMy41IDMzN2MtOC41ODkgNS4xOTktMTcuNTg5IDUuODY2LTI3IDJhMzE4LjM5MyAzMTguMzkzIDAgMCAxLTMwLTMwYy0xNC4xNjMtOS43Ni0yNi45OTYtOC4yNi0zOC41IDQuNWExNTI4LjE2NyAxNTI4LjE2NyAwIDAgMC00NCA2MGMtMTguMDExIDIxLjM1MS0zOS41MTEgMzguMTg0LTY0LjUgNTAuNS0xMi4zNiA1LjYyLTI1LjAyNiA2LjYyLTM4IDMtMTguMTA4LTEwLjA3Ny0yNC4yNzQtMjUuMjQzLTE4LjUtNDUuNSAzLjgzOC0xMy4zNDYgMTAuMDA1LTI1LjM0NiAxOC41LTM2WiIvPjxwYXRoIHN0eWxlPSJvcGFjaXR5OjEiIGZpbGw9IiM3ODc4NzgiIGQ9Ik00ODkuNSAxNDkuNWMtMS40MTktLjk1MS0zLjA4NS0xLjI4NC01LTEgMi41Mi0xLjAzMiA1LjE4Ni0xLjUzMiA4LTEuNWExMC41MTUgMTAuNTE1IDAgMCAxLTMgMi41WiIvPjxwYXRoIHN0eWxlPSJvcGFjaXR5OjEiIGZpbGw9IiM4YThhOGEiIGQ9Ik02My41IDM0Mi41Yy4wNzMgMS41MjctLjU5NCAyLjUyNy0yIDMtLjA3My0xLjUyNy41OTQtMi41MjcgMi0zWiIvPjwvc3ZnPg==");*/
        background: linear-gradient(90deg, rgba(55, 55, 55, 1) 0%, rgba(9, 9, 121, 1) 8%, rgba(0, 212, 255, 1) 100%) !important;
        /*background: conic-gradient(red, yellow, lime, aqua, blue, fuchsia, red) !important;*/
        background:conic-gradient(rgba(255,0,0,.5), rgba(255,154,0,.6), rgba(79,220,74,.5), rgba(63,218,216,.55), rgba(47,201,226,.6), rgba(28,127,238,.5), rgba(95,21,242,.5), rgba(186, 12, 248,.5), rgba(251, 7, 217,.5)) !important;
    }
    
    .apf-border-unit .components-button.is-compact {
        height:36px;
        border:1px solid #c3c3c3;
        border-left:none;
        border-radius: 0 4px 4px 0;
        box-shadow: 0 1px 2px rgba(16,24,40,.08);
    }
    
    .apf-border-unit .components-input-control__backdrop{
        border-bottom-right-radius: 0!important;
        border-top-right-radius: 0!important;
    }

</style>

<div id="wapf-admin-settings-wrapper" style="max-width: 750px"></div>

<p class="submit">
    <button name="apf-revert-design" class="components-button is-secondary" type="submit" value="apf_revert_design"><?php esc_html_e( 'Revert changes', 'sw-wapf' ); ?></button>
    <button name="save" class="woocommerce-save-button components-button is-primary" type="submit" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"><?php esc_html_e( 'Save changes', 'woocommerce' ); ?></button>
</p>

<script>
    
    const apfThemeNotices = <?php echo json_encode( $theme_notices ); ?>;
    const apfActiveTheme = '<?php echo strtolower( wp_get_theme()->Name ) ?>';
    const { createElement, createRoot, Fragment, useState, useEffect, useRef } = wp.element; 
    const { Notice, SelectControl, ToggleControl, DropdownMenu, Button, PanelBody, Panel, ColorPicker, ColorIndicator, Tooltip, Icon, TabPanel, RangeControl, __experimentalUnitControl: UnitControl } = wp.components;
    
    ( function () {

        const RemoveIcon = createElement(
            'svg',
            { style: { fill: '#b96262' }, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 512 512", width: '24px', height: '24px' },
            createElement(
                'path',
                { d: 'M256 32a224 224 0 1 1 0 448 224 224 0 1 1 0-448zm0 480A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM191.4 168.8l-22.6 22.6 11.3 11.3L233.4 256l-53.3 53.3-11.3 11.3 22.6 22.6 11.3-11.3L256 278.6l53.3 53.3 11.3 11.3 22.6-22.6-11.3-11.3L278.6 256l53.3-53.3 11.3-11.3-22.6-22.6-11.3 11.3L256 233.4l-53.3-53.3-11.3-11.3z' }
            )
        );
        
        const AppearanceIcon = createElement(
            'svg',
            { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 512 512", width: '20px', height: '20px' },
            createElement(
                'path',
                {d: 'M484.5 148.5c1.915-.284 3.581.049 5 1a7390.271 7390.271 0 0 1-124.5 126l-169-169c-.667-.667-.667-1.333 0-2L234.5 66a37.091 37.091 0 0 1 15-5c22.858-2.229 44.191 2.438 64 14a634.86 634.86 0 0 1 54 41c27.948 20.205 59.115 31.705 93.5 34.5a493.558 493.558 0 0 1 23.5-2ZM61.5 345.5c1.406-.473 2.073-1.473 2-3 7.677-11.186 16.677-21.353 27-30.5a797.079 797.079 0 0 1 53-39 104.416 104.416 0 0 0 21.5-20.5c4.378-8.064 4.045-16.064-1-24a655.949 655.949 0 0 0-30-31c-6.814-9.822-7.147-19.822-1-30l46-46 169 169c.667.667.667 1.333 0 2L303.5 337c-8.589 5.199-17.589 5.866-27 2a318.393 318.393 0 0 1-30-30c-14.163-9.76-26.996-8.26-38.5 4.5a1528.167 1528.167 0 0 0-44 60c-18.011 21.351-39.511 38.184-64.5 50.5-12.36 5.62-25.026 6.62-38 3-18.108-10.077-24.274-25.243-18.5-45.5 3.838-13.346 10.005-25.346 18.5-36Z' }
            ),
            createElement(
                'path',
                { d: 'M489.5 149.5c-1.419-.951-3.085-1.284-5-1 2.52-1.032 5.186-1.532 8-1.5a10.515 10.515 0 0 1-3 2.5Z' }
            ),
            createElement(
                'path',
                { d: 'M63.5 342.5c.073 1.527-.594 2.527-2 3-.073-1.527.594-2.527 2-3Z' }
            )
        );

        const chevronDownIcon = createElement(
            'svg', { viewBox: '0 0 512 512', xmlns: 'http://www.w3.org/2000/svg' },
            createElement(
                'path',
                { d: 'M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z' }
            )
        );
        
        const DottedIcon = createElement(
            'svg',
            { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24',  width: '24px', height: '24px' },
            React.createElement('path', {
                fillRule: "evenodd",
                d: "M5.25 11.25h1.5v1.5h-1.5v-1.5zm3 0h1.5v1.5h-1.5v-1.5zm4.5 0h-1.5v1.5h1.5v-1.5zm1.5 0h1.5v1.5h-1.5v-1.5zm4.5 0h-1.5v1.5h1.5v-1.5z",
                clipRule: "evenodd"
            })
        );

        const DashedIcon = createElement(
            'svg',
            { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24",  width: '24px', height: '24px' },
            createElement('path', {
                fillRule: "evenodd",
                d: "M5 11.25h3v1.5H5v-1.5zm5.5 0h3v1.5h-3v-1.5zm8.5 0h-3v1.5h3v-1.5z",
                clipRule: "evenodd"
            })
        );

        const SolidIcon = createElement(
            'svg',
            { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24",  width: '24px', height: '24px' },
            createElement('path', {
                d: "M5 11.25h14v1.5H5z"
            })
        );
        
        // Parses a css border like "2px dashed #fff" into separate parts.
        function parseBorderString( borderString ) {

            if( ! borderString || borderString === 'none' || borderString === '0px' ) {
                return { style: 'solid', border: null, color: null };
            }

            let styles = ['solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset', 'none', 'hidden'];

            let style = null;
            let width = null;
            let color = null;

            let parts = borderString.trim().split(/\s+/);

            for ( let part of parts ) {
                if (styles.includes(part)) {
                    style = part;
                } else if (/^[0-9]+(px|rem|em|pt|%)$/.test(part)) {
                    width = part;
                } else {
                    color = part;
                }
            }

            style = style || 'none'; // Default to 'none' if no style found

            return {
                style: style,
                width: style === 'none' ? null : ( width || '0px' ),  // Default to '0px' if no width found
                color: color,
            };
        }
        
        // Creates the "units" prop for WordPress' UnitControl component.
        function makeUnits( theUnits ) {
            
            var units = [ { label: 'px', step: 1, value: 'px' } ];
            
            if( theUnits && theUnits.length ) {
                units = [];
                theUnits.forEach( function(u) {
                    units.push({
                        label: u,
                        value: u,
                        step: u === 'em' || u === 'rem' ? 0.1 : 1
                    })
                })
            }   
            
            return units;
        }
        
        // Component renders a row with setting title + the actual setting.
        function SettingRow( props ) {

            function IconToolTip( props ) {

                return createElement(
                    Tooltip,
                    { text: props.text || '', delay: 0, position: 'top center' },
                    createElement( 'div', { style: {display: 'inline-block', marginRight: '10px', color: '#565c62', marginTop: '5px' } }, createElement(Icon, { icon: 'editor-help'} ) )
                );

            }

            return createElement(
                'div',
                { className: 'apf-row-wrap', style: { width: '' + (props.width || 100) + '%' } },
                createElement(
                    'div',
                    { className: 'apf-setting-row' },
                    props.title && createElement( 'div', { className: 'apf-setting-title' }, props.title || '' ),
                    props.tooltip ? createElement(IconToolTip, { text: props.tooltip }) : createElement( 'div', { style: { display: 'inline-block', width: '30px' } } ),
                    createElement( 'div', {className: 'apf-setting' }, props.children )
                )
            );

        }

        // Component to select a border color, type, and thickness.
        function SimpleBorderSetting( props ) {

            let parts = parseBorderString( props.value || null );

            const [ style, setStyle ] = useState( parts.style );
            const [ color, setColor ] = useState( parts.color );
            const [ width, setWidth ] = useState( parts.width );
            const [ selectedIcon, setSelectedIcon ] = useState( parts.style === 'solid' ? SolidIcon : ( parts.style === 'dashed' ? DashedIcon : DottedIcon ) );

            const handleChange = ( newValues ) => {

                const newWidth = newValues.width ?? width;
                const newColor = newValues.color ?? color;
                const newStyle = newValues.style ?? style;

                if ('width' in newValues) setWidth(newWidth);
                if ('color' in newValues) setColor(newColor);
                if ('style' in newValues) setStyle(newStyle);

                const combinedValue = `${newWidth} ${newStyle} ${newColor}`.trim();
                props.onChange(combinedValue);

            };

            let getColorTooltip = () => {

                if( ! color ) return 'Select border color';

                var tt = 'Border color'; // + (color ? (' - ' + color ) : ''),

                if( color ) {
                    tt += ' - ' + ( color === 'currentColor' ? 'inherit from theme' : color );
                }

                return tt;
            }

            return createElement(
                'div',
                { className: 'apf-border-setting' },
                createElement(
                    'div',
                    { className: 'apf-border-setting-inner' },
                    createElement(
                        ColorPickerSetting,
                        {
                            view: 'compact',
                            color: color ,
                            colorTooltip: getColorTooltip(),
                            onChange: ( v ) => {
                                if (v && !width) {
                                    handleChange({ color: v, width: '1px' });
                                } else {
                                    handleChange({ color: v });
                                }
                            },
                            showActions: true,
                        }
                    ),
                    createElement(
                        DropdownMenu,
                        {
                            label: 'Select border style',
                            icon: selectedIcon,
                            controls: [
                                { icon: SolidIcon, title: 'Solid', onClick: () => { setSelectedIcon( SolidIcon ); handleChange( { style: 'solid' } ); } },
                                { icon: DashedIcon, title: 'Dashed', onClick: () => { setSelectedIcon( DashedIcon ); handleChange( { style: 'dashed' } ); } },
                                { icon: DottedIcon, title: 'Dotted', onClick: () => { setSelectedIcon( DottedIcon ); handleChange( { style: 'dotted' } ); } },
                            ]
                        },
                    ),
                    createElement(
                        'div',
                        null,
                        createElement(
                            UnitControl,
                            {
                                __unstableInputWidth: '100%',
                                step:1,
                                min: 0,
                                max: 50,
                                units: makeUnits( null ),
                                value: width,
                                onChange: ( v ) => {
                                    handleChange( { width: v } );
                                }
                            }
                        )
                    ),
                    createElement(
                        RangeControl,
                        {
                            withInputField: false,
                            min: 0,
                            max: 50,
                            initialPosition: 0,
                            value: width !== null ? parseInt( (''+width).replace('px','') ) : null,
                            onChange: ( v ) => {
                                handleChange( { width: v + 'px' } );
                            }
                        }
                    ),
                    ( width || color ) && !props.hideClear && createElement( Button, {
                        icon: RemoveIcon,
                        iconSize: 20,
                        size: 'compact',
                        label: 'Remove border',
                        showTooltip: true,
                        variant: 'tertiary',
                        style: { borderLeft: '1px solid #c3c3c3', borderRadius: 0, height: '36px' },
                        onClick: (e) => {
                            e.preventDefault();
                            setWidth( null );
                            setStyle( 'solid' );
                            setColor( null );
                            props.onChange( 'none' );
                        }
                    })
                )
            );
        }
        
        function BorderUnitWithRangeSetting( props ) {
            
            const [value, setValue] = useState( props.value? parseInt( ( '' + props.value).replace('px','') ) : null );
            
            const clear = () => {
                setValue('0px');
                props.onChange( '0px' );
            };
            
            const handleChange = (newValue) => {

                if( newValue === null ) {
                    if ( props.onChange ) props.onChange( null );
                    return;
                }
                
                newValue = '' + newValue;
                
                var cleanValue = parseFloat( newValue.replace('px','') );
                
                if( cleanValue === 0 ) {
                    clear();
                    return;
                }
                
                setValue( cleanValue );

                if ( props.onChange ) {
                    props.onChange(  '' + cleanValue + 'px' );
                }
            };

            return createElement(
                'div',
                {
                    className: 'apf-border-unit',
                    style: {
                        display: 'flex',
                        width: '100%'
                    }
                },
                createElement(
                    'div',
                    { style: { flex: 1 } },
                    createElement(
                        RangeControl,
                        {
                            min: props.min,
                            max: props.max,
                            value: parseFloat((''+value).replace('px','')),
                            step: 1,
                            withInputField: false,
                            onChange: handleChange,
                        }
                    )
                ),
                createElement(
                    'div',
                    { style: { marginLeft: '1rem' } },
                    createElement(
                        UnitControl,
                        {
                            __unstableInputWidth: '100%',
                            units: makeUnits(),
                            min: props.min,
                            max: props.max,
                            step: 1,
                            value: '' + value + 'px',
                            onChange: handleChange,
                            style: { width: '100px'}
                        }
                    )
                ), 
                value > 0 && createElement( Button, {
                    icon: RemoveIcon,
                    iconSize: 20,
                    size: 'compact',
                    label: 'Remove border',
                    showTooltip: true,
                    variant: 'tertiary',
                    onClick: (e) => {
                        e.preventDefault();
                        clear();
                    }
                })
                
            );

        }
        
        // This component renders a UnitControl and RangeControl (both are interlinked).
        function UnitWithRangeSetting( props ) {

            var units = makeUnits( props.units );
            const [value, setValue] = useState( null );
            const [activeUnit, setActiveUnit] = useState( null );
            const [step, setStep ] = useState( 1 );
            const [min, setMin] = useState( props.min || 0);
            const [max, setMax] = useState(props.max || 250 );
            
            const handleChange = (newValue, skipOnChange = false ) => {

                if( newValue === null ) {
                    setValue( null );
                    if( ! skipOnChange && props.onChange )
                        props.onChange(  null );
                    setActiveUnit( 'px' );
                    return;
                }
                
                newValue = '' + newValue;
                var unit = activeUnit;
                
                if( newValue.indexOf('px') > -1 ) { unit = 'px'; }
                else if( newValue.indexOf('rem') > -1 ) { unit = 'rem'; }
                else if( newValue.indexOf('em') > -1 ) { unit = 'em'; }
                else if( newValue.indexOf('%') > -1 ) { unit = '%'; }
                else if( newValue.indexOf('pt') > -1 ) { unit = 'pt'; }

                var cleanValue = parseFloat( newValue.replace('px','').replace('rem','').replace('%','').replace('em','').replace('pt','') );
                
                if( unit !== activeUnit ) {
                    setActiveUnit(unit);
                    //setStep( units.find(u => u.value === unit).step || 1 );
                    var mi,ma,st = 0;
                    if( unit === '%' ) { mi = 0; ma = 100; st = 1; }
                    if( unit === 'px' || unit === 'pt' ) { mi = props.min || 0; ma = props.max || 250; st = 1; }
                    if( unit === 'rem' || unit === 'em' ) { mi = 0; ma=5;st = 0.1; }
                    if( cleanValue < mi || cleanValue > ma ) cleanValue = Math.floor(ma/2);
                    setStep(st);setMax(ma);setMin(mi);
                }

                setValue( cleanValue );
                
                // SkipOnChange can also be an event.
                if( typeof skipOnChange === 'boolean' && skipOnChange === true ) return;
                
                if( props.onChange ) props.onChange(  '' + cleanValue + unit );
                    
                
            };

            // Already do the handleChange on component load so we can get correct min,max or step.
            useEffect(() => {
                handleChange(props.value || null, true);
            }, [props.value]);

            return createElement( 
                'div',
                {
                    style: {
                        display: 'flex',
                        width: '100%'
                    }
                },
                createElement( 
                    'div',
                    { style: { flex: 1 } },
                    createElement(
                        RangeControl,
                        {
                            min: min,
                            max: max,
                            value: parseFloat((''+value).replace('px','').replace('%','').replace('rem','').replace('em','').replace('pt','')),
                            step: step,
                            withInputField: false,
                            onChange: handleChange,
                        }
                    )
                ),
               createElement( 
                   'div',
                   { style: { marginLeft: '1rem' } },
                   createElement(
                       UnitControl,
                       {
                           __unstableInputWidth: '100%',
                           units: units,
                           unit: activeUnit,
                           min: min,
                           max: max,
                           step: step,
                           value: '' + value + activeUnit,
                           onChange: handleChange,
                       }
                   )
               ),
                props.canClear && value !== null && createElement( Button, {
                    icon: RemoveIcon,
                    iconSize: 20,
                    size: 'compact',
                    label: 'Clear setting',
                    showTooltip: true,
                    variant: 'tertiary',
                    style: { borderLeft: '1px solid #c3c3c3', borderRadius: 0, height: '36px' },
                    onClick: (e) => {
                        handleChange( null );
                    }
                })
            );
            
        }

        // This component renders a popup color picker with color indicator.
        function ColorPickerSetting( props ) {

            var view = props.view ? props.view : 'default';
            const [color, setColor] = useState( props.color );
            const [isPickerOpen, setIsPickerOpen] = useState(false);

            const pickerRef = useRef(null);

            // Update local state when prop changes.
            useEffect( () => {
                setColor(props.color);
            }, [props.color] );
            
            // When clicking outside the element, close the color picker.
            useEffect(() => {
                function handleOutsideClick(event) {
                    if (pickerRef.current && !pickerRef.current.contains(event.target)) {
                        setIsPickerOpen(false);
                    }
                }
                document.addEventListener('mousedown', handleOutsideClick);
                return () => {
                    document.removeEventListener('mousedown', handleOutsideClick);
                };
            }, []);

            const clearButton = createElement(
                Tooltip,
                { text: props.clearButtonText || 'Clear color setting', delay: 500 },
                createElement( Button,
                    {
                        icon: RemoveIcon,
                        iconSize: 20,
                        label: 'Clear color',
                        showTooltip: true,
                        className: 'apf-clear-color',
                        onClick: (e) => {
                            e.preventDefault();
                            e.stopPropagation(); // prevent picker form opening.
                            var v = props.value_def ? props.value_def.id : null;
                            setColor( null );
                            props.onChange( v );
                        }
                    }
                ),
            );

            const EnhancedColorIndicator = createElement(
                'div',
                { className: 'apf-color-indicator' },
                createElement(
                    ColorIndicator,
                    { colorValue: color }
                )
            );
            
            let getColorLabel = () => {
                if( color ) {
                    if( props.value_def && color === props.value_def.id ) return props.value_def.label;
                    return color;
                }
                
                if( ! props.value_def ) return 'None';
                
                return props.value_def.label;
                
            };
            
            let canClearColor = props.allowClear && color && (!props.value_def || color !== props.value_def.id);
            
            let colorClass = function() {
                
                 var label = color;
                 
                 if( !color ) {
                     label = props.value_def ? props.value_def.id : 'none';
                 }
                 
                 return label;
            }
            
            return createElement(
                'div',
                { className: 'apf-color-'  + colorClass() + ' apf-colorpicker-setting colorpicker--' + view, ref: pickerRef, style: { position: 'relative' } },
                // Color Indicator
                createElement(
                    'div',
                    {
                        onClick: function () {
                            setIsPickerOpen(!isPickerOpen);
                        },
                        className: 'apf-colorpicker-container',
                    },
                    ( props.colorTooltip
                        ? createElement(
                            Tooltip,
                            { delay: 500, text: props.colorTooltip },
                            EnhancedColorIndicator
                        )
                        : EnhancedColorIndicator
                    ),
                    canClearColor && view === 'compact' && clearButton,
                    view === 'default' && createElement( 'span', { className : 'apf-colorpicker-label' }, getColorLabel() ),
                    view === 'default' && ! canClearColor && createElement( Icon, { icon: chevronDownIcon, style: { width: '10px', height: '10px', position: 'absolute', top: 'calc(50% - 5px)', right: '10px', fill: '#1e1e1e' } } ),
                    canClearColor && view === 'default' && clearButton
                ),
                isPickerOpen && createElement(
                    'div',
                    { style: { position: 'absolute', zIndex: 10, marginTop: '6px', border: '1px solid #e0e0e0', background: '#fff', padding: '8px',borderRadius: '4px', boxShadow: '0 2px 4px rgba(0, 0, 0, 0.1)' } },
                    createElement( ColorPicker, {
                        color: color,
                        copyFormat: 'hex',
                        onChange: (v) => {
                            setColor( v );
                            props.onChange(v);
                        }
                    }),
                    props.showActions && createElement(
                        'div',
                        { className: 'apf-colorpicker-actions' },
                        createElement( Button, {
                            icon: AppearanceIcon,
                            iconSize: 20,
                            size: 'compact',
                            label: 'Use the theme\'s active text color',
                            showTooltip: true,
                            variant: 'secondary',
                            onClick: () => {
                                setColor( 'currentColor' );
                                props.onChange( 'currentColor' );
                                setIsPickerOpen( false );
                            }
                        })
                    )
                )
            );
        }
        
        // App wrapper.
        function SettingsApp() {

            // Allow conditional logic if dependencies are defined.
            const shouldHide = ( setting ) => {
                
                if ( setting.dependencies ) {
                    
                    let dependenciesMet = setting.dependencies.every( dep => {

                        let depValue = settingValues[dep.setting];
                        if( dep.value === '!empty' && depValue && depValue !== '0px')
                            return true;

                        return depValue === dep.value;
                    } );
                    
                    if (!dependenciesMet) 
                        return true;
                    
                }
                
                return false;
                
            }
            
            // The saved settings (coming from WP options table).
            const [ settingValues, setSettingValues ] = useState(<?php echo json_encode( $model['setting_values'] ); ?>);
            
            // Settings defintions.
            const settings = <?php echo json_encode( $model['settings'] ); ?>;
                
            // Update a settingValue.
            function updateSetting(id, value) {
                
                const update = (id, value) => {
                    setSettingValues(prevValues => ({
                        ...prevValues,
                        [id]: value,
                    }));
                };

                // The "primary color" setting changes some other color settings that are linked to it too.
                if (id === 'apf-primary-color') {
                    settings.forEach((section) => {
                        section.settings.forEach((s) => {
                            if (s.inner_settings) {
                                s.inner_settings.forEach((is) => {
                                    if (is.linkToPrimary) {
                                        update(is.id, value);
                                    }
                                });
                            } else if (s.linkToPrimary) {
                                update(s.id, value);
                            }
                        });
                    });
                }
                
                update(id, value);
                
            }

            // Render a setting based on the setting type.
            function renderSetting( settings, setting, settingValues, updateSetting ) {
                
                switch ( setting.type ) {
                    case 'color':
                        //if( ( '' + v ).startsWith('--var') || ( '' + v ).startsWith( 'setting:') ) v = setting.value_def.id;
                        return createElement( ColorPickerSetting, { 
                            color: settingValues[ setting.id ],
                            tooltip: setting.tooltip,
                            allowClear: setting.allowClear,
                            noneText: setting.noneText,
                            value_def: setting.value_def,
                            onChange: (newColor) => {
                                updateSetting(setting.id, newColor);
                            }
                        });
                        
                    case 'border-unit':

                        return createElement( BorderUnitWithRangeSetting, {
                            min: setting.min,
                            max: setting.max,
                            value: settingValues[ setting.id ],
                            onChange: (newValue) => {
                                updateSetting(setting.id, newValue);
                            }
                        });
                    
                    case 'unit':

                        return createElement( UnitWithRangeSetting, {
                            units: setting.units,
                            min: setting.min,
                            max: setting.max,
                            value: settingValues[ setting.id ],
                            canClear: setting.can_clear || false,
                            onChange: (newValue) => {
                                updateSetting(setting.id, newValue);
                            }
                        });
                    
                    case 'border':
                        return createElement( SimpleBorderSetting, {
                            value: settingValues[ setting.id ],
                            hideClear: setting.hide_clear || false,
                            onChange: (newValue) => { // todo: this isnt finished yet.
                                updateSetting(setting.id, newValue);
                            }
                        });
                    case 'toggle':
                        return createElement( 
                            'div',
                            {
                                style: { paddingTop: '8px', display: 'flex' }
                            },
                            createElement(
                                ToggleControl,
                                {
                                    label: setting.toggleLabel,
                                    checked: settingValues[ setting.id ],
                                    onChange:  (newValue) => {
                                        updateSetting(setting.id, newValue);
                                    }
                                }
                            ),
                            setting.note && createElement( 'div', null, setting.note )
                        );
                    case 'dropdown': 
                        return createElement(
                            SelectControl,
                            {
                                options: setting.options,
                                value: settingValues[ setting.id ],
                                onChange:  (newValue) => {
                                    updateSetting(setting.id, newValue);
                                }
                            }
                        )
                    case 'states': 
                        return createElement(SettingRow, { title:null, }, renderStates( setting, settingValues, updateSetting ) );

                    default:
                        return null;

                }
            }

            // Render a setting of type "states".
            // //setting.id, setting.states, setting.inner_settings
            // settingId, states, innerSettings
            function renderStates( theSetting, settingValues, updateSetting ) {
                
                return createElement(
                    TabPanel,
                    { 
                        className: 'apf-tab-list', 
                        tabs: theSetting.states.map( s => { return { name: s.id, title: s.label }; } ) 
                    },
                    function(selectedState) {
                        return createElement(
                            'div',
                            null,
                            theSetting.inner_settings.map( ( setting ) => {
                                
                                    // Setting is not part of this state, so we do not want to show this.
                                    if( setting.states && setting.states.indexOf( selectedState.name ) === -1 ) {
                                        return null;
                                    }
                                    
                                    var newSetting = JSON.parse( JSON.stringify( setting ) );
                                    var stateIdx = setting.states ? setting.states.indexOf( selectedState.name) : theSetting.states.findIndex( function(st) { return st.id === selectedState.name; } );

                                    // Set some data on the setting, such as the full setting ID (which includes parent setting ID).
                                    newSetting.id = theSetting.id + '-' + setting.partial_id;
                                    if( selectedState.name !== 'default' ) newSetting.id += ( '-' + selectedState.name );
                                
                                    // can be an array of objects or an object
                                    if( setting.value_def ) {
                                        newSetting.value_def = Array.isArray( setting.value_def ) ? setting.value_def[ stateIdx ] : setting.value_def;
                                    }
                                    
                                    // Conditional logic.
                                    if( shouldHide( newSetting ) )
                                        return null;

                                    return createElement(
                                        SettingRow,
                                        {key: newSetting.id, width: newSetting.width || 100, title: newSetting.label, tooltip: newSetting.tooltip, settingValues: settingValues },
                                        renderSetting(theSetting.inner_settings, newSetting, settingValues, updateSetting)
                                    );
                                }
                            )
                        );
                    }
                );
                
            }
            
            return createElement(
                'div',
                null,
                createElement(
                    'div',
                    { style: { marginBottom: '20px', marginTop: '20px' } },
                    createElement(
                        Notice,
                        {
                            isDismissible: false,
                            __unstableHTML: true,
                        },
                        '<p><strong>Please note: </strong>Saving these settings may override some of your theme\'s or custom styles, but only for fields created with our plugin.</p><p>You can easily revert these changes anytime by clicking "Revert changes" below to restore your theme\'s defaults.</p>'
                    ), 
                    apfThemeNotices[apfActiveTheme] && createElement(
                        Notice,
                        {
                            status: 'warning',
                            isDismissible: false,
                            __unstableHTML: true,
                        },
                        apfThemeNotices[apfActiveTheme]
                    ),
                ),
                createElement(
                    Panel,
                    { header: "" },
                    settings.map( (section) =>
                        
                        createElement(
                            PanelBody,
                            { key: section.title, title: section.title, initialOpen: false },
                            section.desc && createElement(
                                'div',
                                { className: 'apf-row-wrap' },
                                createElement( 'p', null, section.desc)
                            ),
                            section.settings.map((setting) => {
    
                                // Conditional logic.
                                if( shouldHide( setting ) ) 
                                    return null;
                                
                                return createElement(
                                    SettingRow,
                                    { key: setting.id, width: setting.width || 100, title: setting.label, tooltip: setting.tooltip, setting: setting },
                                    renderSetting( settings, setting, settingValues, updateSetting)
                                );
                                
                            } )
                        ),
                        
                    ),
                ),
                // Store settings in a hidden input. The backend parses this and saves to WP's options table.
                createElement(
                    'input',
                    {
                        type: 'hidden',
                        name: 'wapf-design-settings',
                        value: JSON.stringify( settingValues )
                    }
                )
            );

        }
        
        // Start.
        document.addEventListener( 'DOMContentLoaded', function () {
            var root = createRoot( document.getElementById( 'wapf-admin-settings-wrapper' ) ); 
            root.render( createElement( SettingsApp ) );
        });
        
    })();

</script>