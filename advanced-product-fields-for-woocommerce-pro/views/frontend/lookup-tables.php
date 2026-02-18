<script>
    var wapfLtbl = function(args,$parent) {
        var findNearest = function(value,axis) {
            
            if( axis[ '' + value ] ) return value;
            
            // Objet.keys does not respect order of insertion if numbers and strings are both used as keys.
            var keys = Object.keys(axis)
                .map(key => parseFloat(key))
                .sort((a, b) => a - b);
            
            value = parseFloat(value);
            if(value < keys[0])
                return keys[0];
            for(var i=0; i < keys.length; i++ ) {
                if(value > keys[i] && value <= keys[i+1])
                    return keys[i+1];
            }
            return keys[i]; // return last
        };
        
        try {
            
            if (!wapf_lookup_tables || !wapf_lookup_tables[args[0]]) return 0;
            
            var lookuptable = wapf_lookup_tables[args[0]];
            var tableValues = [], prev = lookuptable;
            for (var i = 1; i < args.length; i++) {

                var v = '';

                // Assuming [qty] was used
                if (args[i].length < 6) {
                    v = args[i];
                } else {
                    v = WAPF.Util.getFieldValue($parent.find('.input-' + args[i]));
                }

                if (v == '') return 0;
                var n = findNearest(v, prev);
                tableValues.push(n);
                prev = prev[n];
            }
            return tableValues.reduce(function (acc, curr) {
                return acc[curr];
            }, lookuptable);
        } catch( err ) {
            console.error('Error caught: ', err );
            return 0;
        }
    };

    if( window.WAPF) window.WAPF.Util.formulas['lookuptable'] = wapfLtbl;
    else {
        window.customFormulas = window.customFormulas || {};
        window.customFormulas['lookuptable'] = wapfLtbl;
    }
</script>