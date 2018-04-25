# Page Query Builder

Build complex nested queries containing multipple fields and pages and return an
array that can be parsed to JSON. This is usefull to fetch data for SPA and PWA.

You can use it to tranfrom a ProcessWire Page or PageArray, even RepeaterMatrixPageArrays
into an array or JSON. Queries can be nested and contain closures as callback
functions.

## Installation

### Via ProcessWire Backend
It is recommended to install via the ProcessWire admin Modules > Site > Add New > Add Module from Directory using the `PageQueryBuilder` class name.

### Manually

Download the files from github or the ProcessWire repository: https://modules.processwire.com/modules/page-query-builder/

1. Copy all of the files for this module into /site/modules/PageQueryBuilder/
2. Go to “Modules > Refresh” in your admin, and then click “install” for the this module.

## Methods

There are two main methos:

### Return JSON

	$page->pageQueryJson($query);

### Return an Array

	$page->pageQueryArray($query);


## Building the query

The query can be with key value pairs, or only keys. and can be nested.
To ilustrate a short example:

	// simple query:
	$query = [
		'height',
		'floors',
	];
	$pages->find('template=skyscraper')->pageQueryJson($query);

Queries can be nested, call children etc:

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
3. The **fieldname of a child** (child.name); `url` or `filename` or `title`
4. **[A ProcessWire selector](https://processwire.com/api/selectors/)**; `template=building, floors>=25`
5. A **new name** for the returned index passed by a `#` delimiter:

		// the field skyscraper will be renamed to "building":
		$query = ["skyscraper`#building`"]

### Key value pars:
1. Any of the keys above with an new query array:

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

2. A key and a **closure functions** to process and return a query. The closure gets the parent as argument:

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
		// ProcessWire selector > name result "location"
		'template=Location, limit=1#location' => [
			'title#city', // summary title field to city
			'body',
			'country',
			'venue',
			'summary#address', // rename summary field to address
			'link', // ticket link
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

The modules settings are public. They can be adjusted, for example:

	$modules->get('PageQueryBuilder')->debug = true;
	$modules->get('PageQueryBuilder')->defaults = []; // reset all defaults

### Default queries for fields:

Some field types come with default selectors, like Pageimages etc.
These are the default queries for template names or object types:

	public $defaults = [
		'queries' => [
			'Pageimages' => [
				'basename',
				'url',
				'httpUrl',
				'description',
				'ext',
				'focus',
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

These will only be used if there is no nested query for these types. So if
you query a field with complex data and do not provide a sub-query, it will
be transformed acordingly:

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

You can always provide your own sub-query so the defaults will not be used:

	$page->pageQueryArry([
		'images' => [
			'filename',
			'description'
		],
	]);

You can also override these, for example:

	$modules->get('PageQueryBuilder')->defaults['queries']['Pageimages'] = [
		'basename',
		'url',
		'description',
	];


## Index of nested elements

The index for nested elements can be adjusted. This is also done with
defaults. There are 3 possibilities:

1. Nested by name (default)
2. Nested by ID
3. Nested by nummerical indey

#### Named index (default):

This is the default. If you have a field that contains subpages, their key
will be their name:

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

If an object is listed in $defaults['index-id'] their index will be their id.
Currently, no items are listed as defautls:

	$modules->get('PageQueryBuilder')->defaults['index-id']['Page'];
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
By default, a couple of fields are transformed automatically to contain numbered
indexes:

	// objects or template names that should use numerical indexes for children instead of names
	$defaults['index-n'] => [
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

When you remove the key 'Pageimage' from $defaults['index-n'], the index will again
be name-based.

### Debug

The module respects wire('config')->debug. It integrates with TracyDebug.
You can override it like so:

	// turns on debug output no mather what:
	$modules->get('PageQueryBuilder')->debug = true;

### Todos

Make defualts configurable via Backend. **How could that be done in style with
the default queries?**

## License: MIT

See included LICENSE file for full license text.