<?php

namespace SW_WAPF_PRO\Includes\Classes {

    use ArrayIterator;

    class Enumerable
    {
        private $iterator;

        private function __construct (ArrayIterator $iterator) {
            $this->iterator = $iterator;
            $this->iterator->rewind();
        }

        public static function from ($source) {
            $iterator = null;

            if ($source instanceof Enumerable)
                return $source;
            if (is_array($source))
                $iterator = new ArrayIterator($source);

            if ($iterator !== null)
            {
            	$iterator->rewind();
                return new Enumerable($iterator);
            }

            return new Enumerable(new ArrayIterator([]));
        }

        #region Query functions

        public function select($predicate) {
            $this->iterator->rewind();

            $objects = [];

            while ($this->iterator->valid())
            {
                $objects[] = $predicate( $this->iterator->current(), $this->iterator->key() );
                $this->iterator->next();
            }
            return self::from( $objects );
        }

        public function where ($predicate): Enumerable {
            $this->iterator->rewind();

            $keys = [];
            while ($this->iterator->valid())
            {
                if(!$predicate($this->iterator->current(), $this->iterator->key()))
                    $keys[] = $this->iterator->key();
                $this->iterator->next();
            }

            foreach( $keys as $key ) {
                $this->iterator->offsetUnset( $key );
            }

            return $this;
        }

        public function firstOrDefault($predicate) {

            $this->iterator->rewind();
            if(!$this->iterator->valid()) return null;

            while ($this->iterator->valid())
            {
                if($predicate($this->iterator->current(), $this->iterator->key()))
                    return $this->iterator->current();
                $this->iterator->next();
            }

            return null;
        }

        public function orderBy( $predicate ): Enumerable {

            $comparer = function($a,$b)use($predicate){
                if($predicate($a) === $predicate($b) )
                    return 0;
                return ($predicate($a) < $predicate($b)) ? -1 : 1;
            };

            $this->iterator->uasort( $comparer );
            return $this;
        }

        #endregion

        #region Boolean Functions
        public function any($predicate = null): bool {
            if($predicate === null)
                return iterator_count($this->iterator) > 0;

            return $this->firstOrDefault($predicate) != null;
        }

        #endregion

        #region String Functions

        public function join( $value_predicate, $separator ): string {
            $this->iterator->rewind();

            $result = [];
            while ($this->iterator->valid())
            {
                $result[] = $value_predicate( $this->iterator->current(), $this->iterator->key() );
                $this->iterator->next();
            }

            return join($separator, $result);
        }

        #endregion

        #region Operations

        public function merge($predicate) {

            $merged = [];

            $this->iterator->rewind();
            while ($this->iterator->valid())
            {
                $value = $predicate($this->iterator->current(),$this->iterator->key());
                $merged = array_merge($merged, is_array($value) ? $value : [$value]);
                $this->iterator->next();
            }

            return self::from($merged);

        }

        public function groupBy($predicate) {
        	$new = [];

	        $this->iterator->rewind();
	        while ($this->iterator->valid()) {

		        $value = $predicate($this->iterator->current());
		        if(!$value) $value = '';

		        if(!isset($new[$value]))
		        	$new[$value] = [];

	            $new[$value][] = $this->iterator->current();

		        $this->iterator->next();
	        }

	        return self::from($new);
        }
        #endregion

        #region Conversion Functions
        public function toArray(): array {
            $this->iterator->rewind();

            if ($this->iterator instanceof ArrayIterator)
                return $this->iterator->getArrayCopy();

            $result = [];
            foreach ($this->iterator as $k => $v) {
                $result[ $k ] = $v;
            }
            return $result;
        }
        #endregion

    }
}