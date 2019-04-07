<?php


/**
 * ArrayQueryBuilder
 *
 * Build complex nested queries containing multipple fields and pages and return an array that can be parsed to JSON
 *
 * This source file is subject to the license file that is bundled
 * with this source code in the file LICENSE.
 *
 * @author Noel Bossart <me@noelboss.com>
 */

namespace ProcessWire;

class PageQueryBoss extends WireData implements Module {

	/**
	 * Module defualts…
	 *
	 * @var bool $debug
	 */
	public $debug = false;

	/**
	 * Module defualts…
	 *
	 * @var array $defaults
	 */
	public $defaults = [
		// objects or templates names that should use IDs for children instead of names
		'index-id' => [
			//"Page",
		],
		// objects or template names that should use numerical indexes for children instead of names
		'index-n' => [
			'Pageimage',
			'Pagefile',
			'RepeaterMatrixPage',
		],
		// default queries for template names or object types
		'queries' => [
			'Pageimage' => [
				'basename',
				'url',
				'httpUrl',
				'description',
				'ext',
				'focus',
			],
			'Pageimages' => [
				'basename',
				'url',
				'httpUrl',
				'description',
				'ext',
				'focus',
			],
			'Pagefile' => [
				'basename',
				'url',
				'httpUrl',
				'description',
				'ext',
				'filesize',
				'filesizeStr',
				'hash',
			],
			'Pagefiles' => [
				'basename',
				'url',
				'httpUrl',
				'description',
				'ext',
				'filesize',
				'filesizeStr',
				'hash',
			],
			'MapMarker' => [
				'lat',
				'lng',
				'zoom',
				'address',
			],
			'User' => [
				'name',
				'email',
			],
		],
	];

	/**
	 * Init function
	 *
	 * @return void
	 * @author `Noel Bossart`
	 */
	public function init()
	{
		// set debug output
		$this->debug = wire('config')->debug;
		/*
		//explode whole elements…
		wire()->addHookMethod('Page::explode', function (HookEvent $e) {
		    $arr = new WireArray();
		    $arr->add($e->object);
		    $e->return = $arr->explode($e->arguments(0))[0];
		});*/
		$this->addHook('Page::pageQueryArray', $this, 'pageQueryArray');
		$this->addHook('WireArray::pageQueryArray', $this, 'pageQueryArray');


		$this->addHook('Page::pageQueryJson', $this, 'pageQueryJson');
		$this->addHook('WireArray::pageQueryJson', $this, 'pageQueryJson');
	}


	/**
	 * Hook to fetch all elements and save it as array to $event->return
	 *
	 * @return void
	 */
	protected function pageQueryArray($event, $return = false) {
		$item = $event->object;
		$query = $event->arguments();
		$event->return = $this->processItems($item,  array_pop($query));
	}


	/**
	 * Hook to fetch all elements and save it as json to $event->return:
	 * calles $this->arrayQuery
	 *
	 * @return void
	 */
	protected function pageQueryJson($event){
		$this->pageQueryArray($event);
		$event->return = json_encode($event->return);
	}

	/**
	 * Method to process all items based on shema. If items is array, call
	 * recursively. Applies default shema if needed
	 *
	 * @return array
	 */
	private function processItems($items, $query) {
		$ar = [];
		$this->d($items, "get (".get_class($items).")");

		$className = $this->getClassName($items);
		$query = $this->processShema($items, $query);

		// handle items based on tyoe
		switch (true) {
			// for array types, we call recursively
			case $items instanceof FunctionalWireData:
				$ar = [];
				foreach ($items as $key => $value) {
					//dump($items->getLanguageValue(wire('user')->language), $key);
					$ar[$key] = $value;
				}
				break;
			case $items instanceof Pageimages:
			case $items instanceof PageArray:
			case $items instanceof RepeaterMatrixPageArray:

				$n = 0;
				$this->d([$items, $query], "get – call recursively for ($className)");

				foreach ($items as $item) {
					$index = $this->processIndex($item, $n);
					$ar[$index] = $this->processItems($item ,$query);
					$n++;
				}
				break;

			default:
				$map = $this->getMap($query);
				if(is_array($map)){
					$ar = $this->getFields($items, $map);
				}
				break;
		}


		return $ar;
	}

	private function processShema($item, $query){
		$className = $this->getClassName($item);

		// if no query, we search for a default query in defaults
		if(!$query){
			if(array_key_exists($className,$this->defaults['queries'])){
				$query = $this->defaults['queries'][$className];
			}
			if(!$query){
				$this->d($className, 'get - NO query!');
				return null;
			} else {
				$this->d($query, "get - default query for ($className)");
			}
		}
		return $query;
	}


	private function processIndex($item, $n){
		$className = $this->getClassName($item);


		// if item is of this kind, use numeric index:
		$indexn = in_array($className, $this->defaults['index-n']);
		$label = in_array($className, $this->defaults['index-id']) ? 'id|name' : 'name|id';

		if($item->template){
			$indexn = $indexn || in_array($item->template->name, $this->defaults['index-n']);
			$label = in_array($item->template->name, $this->defaults['index-id']) ? 'id|name|id' : $label;
		}

		// else use name if present…
		$index = $item->get($label) && $indexn === false ? $item->get($label) : $n;
		$this->d(["item"=>$item,"index"=> $index, "n?" => $indexn, "label?" => $label], "processIndex for $className > $index");
		return $index;
	}


	public function ___getFields($item, array $map){
		$ar = [];
		$n = 0;
		foreach ($map as $m) {
			$value = $this->getField($item, $m['selector'], $m['transformer']);


			$index = lcfirst($m['selector']);
			if($m['name']){
				$index =  $m['name'];
			}

			$ar[$index] = $value;
			$n++;
		}

		//$this->d($ar, "getFields $item->path");
		return $ar;
	}


	public function ___getField($item, string $selector, $transformer){
		$value = null;

		if ($this->_is_closure($transformer)) { // if we have a closure, call it...
			$value = $transformer($item);
		} else {
			$value = $item->get($selector);

			//dump(wire('sanitizer')->selectorValue($selector));

			// we have no results, we check if there are children…
			if( ($value instanceof Nullpage == true || null === $value) && method_exists( $item, 'child')){
				$value = $item->child('name='.wire('sanitizer')->selectorValue($selector));
			}
			if( ($value instanceof Nullpage == true || null === $value) && method_exists( $item, 'children')){
				$value = $item->children('template='.wire('sanitizer')->selectorValue($selector));
			}
			if( ($value instanceof Nullpage == true || null === $value) && method_exists( $item, 'children')){
				$value = $item->children($selector);
			}
		}

		if($value && $value instanceof Nullpage !== true){
			$value = $this->processField($value, $transformer, $selector);
		} else {
			//$this-d([$item,$selector], "getField – could not find elements with '$selector'");
			$value = null;
		}
		return $value;
	}

	/**
	 * @param string $string
	 * @return bool
	 */
	public function isTimestamp($string)
	{
	    try {
	        new \DateTime('@' . (string) $string);
	    } catch(Exception $e) {
	        return false;
	    }
	    return true;
	}

	public	 function ___processField($value, $transformer = null, $selector = null){
		$className = $this->getClassName($value);

		$this->d([$value, $transformer, $selector], "processField ($className)");

		// handle return values according to their content and transformer / subquery
		switch (true) {
			// date
			/*case $value instanceof FunctionalWireDat:
				if(!$transformer){

				}
				break;*/
			case is_int($value) && $value > 1 && $this->isTimestamp($value):
				$this->d([$value, $selector], "processField ($className) – $value could be a date");
				$value = date(wire('config')->dateFormat, $value);
				break;

			// string, arrays and integers
			case is_int($value):
			case $className == 'string':
			case $className == 'array':
				break;

			default:
				$value = $this->processItems($value, $transformer);
				break;
		}

		return $value;
	}

	private function getMap($query){

		if(!$query){
			$this->d($query, 'getMap - no shema!');
			return;
		}

		$map = [];
		$n = 0;

		if($this->_is_closure($query)){
			$this->d($query, 'Shema is transformer');
			return $map[$n]['transformer'] = $query;
		}

		foreach ($query as $key => $value) {
			$n++;
			// handle each key value
			if(is_string($key)){ // $key is stirng = selector
				$map[$n]['selector'] = $key;
				$map[$n]['transformer'] = $value;
			} else if(is_int($key) && $key < count($query)){ // key is integer, value is selector
				$map[$n]['selector'] = $value;
				$map[$n]['transformer'] = false;
			}

			$map[$n]['name'] = lcfirst($map[$n]['selector']);
			if(count($selector = explode('#', $map[$n]['name']))>1){
				$map[$n]['selector'] = $selector[0];
				$map[$n]['name'] = $selector[1];
			}
		}
		$this->d($map, 'Map');
		return $map;
	}



	private function getClassName($items){
		// get the type
		$className = gettype($items);
		if(is_object($items)){
			$reflect = new \ReflectionClass($items);
			$className = $reflect->getShortName();
		}
		return $className;
	}


	// is closure?
	private function _is_closure($t) {
		return is_object($t) && ($t instanceof \Closure);
	}


	// debug … uses TracyDebug if available
	private function d(...$args) {
		if(!$this->debug) return;
		if(is_callable("bd")){
			bd(...$args);
		} else if(is_callable("d")) {
			d(...$args);
		}
	}

}
