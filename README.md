# Page Query Boss

Build complex nested queries containing multiple fields and pages and return an
array that can be parsed to JSON. This is use-full to fetch data for SPA and PWA.

You can use the Module to tranfrom a ProcessWire Page or PageArray – even
RepeaterMatrixPageArrays – into an array or JSON. Queries can be nested and
contain closures as callback functions. Some field-types are transformed
automatically, like Pageimages or MapMarker.

## Installation

### Via ProcessWire Backend
It is recommended to install via the ProcessWire admin "*Modules*" > "*Site*" > "*Add New*" > "*Add Module from Directory*" using the `PageQueryBoss` class name.

### Manually

Download the files from Github or the ProcessWire repository: https://modules.processwire.com/modules/page-query-builder/

1. Copy all of the files for this module into /site/modules/PageQueryBoss/
2. Go to “Modules > Refresh” in your admin, and then click “install” for the this module.

## Methods

There are two main methods:

### Return JSON

	$page->pageQueryJson($query);

### Return an Array

	$page->pageQueryArray($query);


## Building the query

The query can contain key and value pairs, or only keys. It can be nested and
contain closures for dynamic values. To illustrate a short example:

	// simple query:
	$query = [
		'height',
		'floors',
	];
	$pages->find('template=skyscraper')->pageQueryJson($query);

Queries can be nested, contain page names, template names or contain functions and ProcessWire selectors:

	// simple query:
	$query = [
		'height',
		'floors',
		'images', // < some fileds contain default sub-queries to return data
		'files' => [ // but you can also overrdide these defaults:
			'filename'
			'ext',
			'url',
		],
		// Assuming there are child pages with the architec template, or a
		// field name with a page relation to architects
		'architect' => [ // sub-query
			'name',
			'email'
		],
		// queries can contain closure functions
		'querytime' => function($parent){
			return "Query for $parent->title was built ".time();
		}
	];
	$pages->find('template=skyscraper')->pageQueryJson($query);

### Keys:

1. A single **[fieldname](https://processwire.com/api/selectors/#fields)**; `height` or `floors` or `architects` <br/>
	The Module can handle the following fields:
	* Strings, Dates, Integer…
	* Page references
	* Pageimages
	* Pagefiles
	* PageArray
	* MapMarker
	* FieldtypeFunctional
2. A **[template name](https://processwire.com/api/selectors/#finding1)**; `skyscraper` or `city`
3. **Name of a child page** (page.child.name=pagename); `my-page-name`
4. **[A ProcessWire selector](https://processwire.com/api/selectors/)**; `template=building, floors>=25`
5. A **new name** for the returned index passed by a `#` delimiter:

		// the field skyscraper will be renamed to "building":
		$query = ["skyscraper`#building`"]

### Key value pars:
1. Any of the keys above (1-5) with an new nested sub-query array:

		$query = [
			'skyscraper' => [
				'height',
				'floors'
			],
			'architect' => [
				'title',
				'email'
			],
		]

2. A named key and a **closure function** to process and return a query. The closure gets the parent object as argument:

		$query = [
			'architecs' => function($parent)
				{
					$architects = $parent->find('template=architect');
					return $architects->arrayQuery(['name', 'email']);
					// or return $architects->explode('name, email');
				}
		]

#### Real life example:

	$query = [
		'title',
		'subtitle',
		// naming the key invitation
		'template=Invitation, limit=1#invitation' => [
			'title',
			'subtitle',
			'body',
		],
		// returns global speakers and local ones...
		'speakers' => function($page){
			$speakers = $page->speaker_relation;
			$speakers = $speakers->prepend(wire('pages')->find('template=Speaker, global=1, sort=-id'));

			// build a query of the speakers with
			return $speakers->arrayQuery([
				'title#name', // rename title field to name
				'subtitle#ministry', // rename subtitle field to ministry
				'links' => [
					'linklabel#label', // rename linklabel field to minlabelistry
					'link'
				],
			]);
		},
		// Child Pages with template=Program
		'Program' => [
			'title',
			'summary',
			'start' => function($parent){ // calculate the startdate from timetables
				return $parent->children->first->date;
			},
			'end' => function($parent){ // calculate the endate from timetables
				return $parent->children->last->date;
			},
			'Timetable' => [
				'date', // date
				'timetable#entry'=> [
					'time#start', // time
					'time_until#end', // time
					'subtitle#description', // entry title
				],
			],
		],
		// ProcessWire selector, selecting children > name result "location"
		'template=Location, limit=1#location' => [
			'title#city', // summary title field to city
			'body',
			'country',
			'venue',
			'summary#address', // rename summary field to address
			'link#tickets', // rename ticket link
			'map', // Mapmarker field, automatically transformed
			'images',
			'infos#categories' => [ // repeater matrix! > rename to categories
				'title#name', // rename title field to name
				'entries' => [ // nested repeater matrix!
					'title',
					'body'
				]
			],
		],
	];

	if ($input->urlSegment1 === 'json') {
		header('Content-type: application/json');
		echo $page->pageQueryJson($query);
		exit();
	}

## Module default settings

The modules settings are public. They can be directly modified, for example:

	$modules->get('PageQueryBoss')->debug = true;
	$modules->get('PageQueryBoss')->defaults = []; // reset all defaults

### Default queries for fields:

Some field-types or templates come with default selectors, like Pageimages etc.
These are the default queries:

	// Access and modify default queries: $modules->get('PageQueryBoss')->defaults['queries'] = …
	public $defaults = [
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

These defaults will only be used if there is no nested sub-query for the respective type.
If you query a field with complex data and do not provide a sub-query, it will be
transformed accordingly:

	$page->pageQueryArry(['images']);

	// returns something like this
	'images' => [
		'basename',
		'url',
		'httpUrl',
		'description',
		'ext',
		'focus'=> [
			'top',
			'left',
			'zoom',
			'default',
			'str',
		]
	];

You can always provide your own sub-query, so the defaults will not be used:

	$page->pageQueryArry([
		'images' => [
			'filename',
			'description'
		],
	]);

### Overriding default queries:

You can also override the defaults, for example

	$modules->get('PageQueryBoss')->defaults['queries']['Pageimages'] = [
		'basename',
		'url',
		'description',
	];


## Index of nested elements

The index for nested elements can be adjusted. This is also done with defaults.
There are 3 possibilities:

1. Nested by name (default)
2. Nested by ID
3. Nested by nummerical index

#### Named index (default):

This is the default setting. If you have a field that contains sub-items,
the name will be the key in the results:

	// example
	$pagesByName = [
		'page-1-name' => [
			'title' => "Page one title",
			'name' => 'page-1-name',
		],
		'page-2-name' => [
			'title' => "Page two title",
			'name' => 'page-2-name',
		]
	]

#### ID based index:

If an object is listed in $defaults['index-id'] the id will be the key in the results.
Currently, no items are listed as defaults for id-based index:

	$modules->get('PageQueryBoss')->defaults['index-id']['Page'];
	// example
	$pagesById = [
		123 => [
			'title' => "Page one title",
			'name' => 123,
		],
		124 => [
			'title' => "Page two title",
			'name' => 124,
		]
	]


#### Number based index
By default, a couple of fields are transformed automatically to contain numbered indexes:

	// objects or template names that should use numerical indexes for children instead of names
	$defaults['index-n'] => [
		'skyscraper', // template name
		'Pageimage',
		'Pagefile',
		'RepeaterMatrixPage',
	];

	// example
	$images = [
		0 => [
			'filename' => "image1.jpg",
		],
		1 => [
			'filename' => "image2.jpg",
		]
	]

**Tipp:** When you remove the key `Pageimage` from $defaults['index-n'], the index will
again be name-based.

## Helpfull closures & tipps

These are few helpfill closure functions you might want to use or could help as a
starting point for your own (let me know if you have your own):


### Get an overview of languages:

	$query = ['languages' => function($page){
		$ar = [];
		$l=0;
		foreach (wire('languages') as $language) {
			// build the json url with segment 1
			$ar[$l]['url']= $page->localHttpUrl($language).wire('input')->urlSegment1;
			$ar[$l]['name'] = $language->name == 'default' ? 'en' : $language->name;
			$ar[$l]['title'] = $language->getLanguageValue($language, 'title');
			$ar[$l]['active'] = $language->id == wire('user')->language->id;
			$l++;
		}
		return $ar;
	}];

### Get county info from ContinentsAndCountries Module

Using the [ContinentsAndCountries Module](https://modules.processwire.com/modules/continents-and-countries/) you can extract iso
code and names for countries:

	$query = ['country' => function($page){
		$c = wire('modules')->get('ContinentsAndCountries')->findBy('countries', array('name', 'iso', 'code'),['code' =>$page->country]);
		return count($c) ? (array) $c[count($c)-1] : null;
	}];

### Custom strings from a RepeaterTable for interface

Using a RepeaterMatrix you can create template string for your frontend. This is
usefull for buttons, labels etc. The following code uses a repeater with the
name `strings` has a `key` and a `body` field, the returned array contains the `key` field as,
you guess, keys and the `body` field as values:

	// build custom translations
	$query = ['strings' => function($page){
		return array_column($page->get('strings')->each(['key', 'body']), 'body', 'key');
	}];

### Multilanguage with default language fallback

Using the following setup you can handle multilanguage and return your default
language if the requested language does not exist. The url is composed like so:
`page/path/{language}/{content-type}` for example: `api/icf/zurich/conference/2019/de/json`


	// get contenttype and language (or default language if not exists)
	$lang = wire('languages')->get($input->urlSegment1);
	if(!$lang instanceof Nullpage){
		$user->language = $lang;
	} else {
		$lang = $user->language;
	}

	// contenttype segment 2 or 1 if language not present
	$contenttype = $input->urlSegment2 ? $input->urlSegment2 : $input->urlSegment1;

	if ($contenttype === 'json') {
		header('Content-type: application/json');
		echo $page->pageQueryJson($query);
		exit();
	}

## Debug

The module respects wire('config')->debug. It integrates with TracyDebug.
You can override it like so:

	// turns on debug output no mather what:
	$modules->get('PageQueryBoss')->debug = true;

## Todos

Make defaults configurable via Backend. **How could that be done in style with
the default queries?**

## License: MIT

See included LICENSE file for full license text.

© noelboss.com