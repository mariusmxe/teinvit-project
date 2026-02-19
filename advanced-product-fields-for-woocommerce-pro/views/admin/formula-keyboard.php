<?php
$defs = \SW_WAPF_PRO\Includes\Classes\Config::get_function_definitions();
?>

<div class="wapf-fb-keyboard">
    <div class="wapf-fb-row">
        <div>
            <?php _e('Arithmetic', 'sw-wapf'); ?>
        </div>
        <div>
            <ul>
                <li><button data-type="math" data-symbol="+">+</button></li>
                <li><button data-type="math" data-symbol="-">-</button></li>
                <li><button data-type="math" data-symbol="*">&times</button></li>
                <li><button data-type="math" data-symbol="/">∕</button></li>
                <li><button data-type="math" data-symbol="(">(</button></li>
                <li><button data-type="math" data-symbol=")">)</button></li>
                <li><button data-type="math" data-symbol=".">.</button></li>
                <li><button data-type="math" data-symbol=";">;</button></li>
                <li><button data-type="math" data-symbol="0">0</button></li>
                <li><button data-type="math" data-symbol="1">1</button></li>
                <li><button data-type="math" data-symbol="2">2</button></li>
                <li><button data-type="math" data-symbol="3">3</button></li>
                <li><button data-type="math" data-symbol="4">4</button></li>
                <li><button data-type="math" data-symbol="5">5</button></li>
                <li><button data-type="math" data-symbol="6">6</button></li>
                <li><button data-type="math" data-symbol="7">7</button></li>
                <li><button data-type="math" data-symbol="8">8</button></li>
                <li><button data-type="math" data-symbol="9">9</button></li>
            </ul>
        </div>
    </div>
    <div class="wapf-fb-row wapf-fb-fields">
        <div>
            <?php _e('Fields', 'sw-wapf'); ?>
        </div>
        <div>
            <ul class="wapf-fb-buttons"></ul>
        </div>
    </div>
    <div class="wapf-fb-row wapf-fb-vars">
        <div>
            <?php _e('Variables', 'sw-wapf'); ?>
        </div>
        <div>
            <ul class="wapf-fb-buttons"></ul>
        </div>
    </div>
    <?php ?>
    <div class="wapf-fb-row">
        <div>
            <?php _e('Functions', 'sw-wapf'); ?>
        </div>
        <div>
            <ul>
                <?php
                foreach( $defs as $def) {
                    if( in_array( $def['name'], ['and', 'or', 'if'] ) ) continue;
                    $symbol = $def['name'] . '(';
                    if( $def['name'] === 'today' ) $symbol .= ')';
                    echo '<li><button data-type="func" data-symbol="' . $symbol . '">' . $def['name'] . '</button></li>';
                }
                ?>
            </ul>
        </div>
    </div>
</div>