# COMPER Template Parser


## Docs

### parse( $source, $data, $config  )

#### $source

##### Parsing files

Put filename without extension as first parameter.

```php
$this->parser->parse('template_file');
```

##### Parsing strings
```php
$this->parser->parse('Hello!', array(), array('is_string' => TRUE));
```


#### $data

##### Pseudo-variables

Defined as simple array.

```php
$data = array
(
	'pseudo-variable' => 'Some data',
	'content' => 'Some new exciting content',
	'author' => 'That is me!'
);
 
$this->parser->parse('template_file', $data);
```

Shown using {} brackets.
```html
<h1>{pseudo-variable}</h1>
<p>{content}</p>
<small>Author: {author}</small>
```

Multi-dimensional array:
```php
$data = array
(
	'pseudo-variable' => 'Some data',
	'content' => 'Some new exciting content',
	'author' => array( 'name' => 'Tomas', 'email' => 'tomas@home.com' )
);
```

Can be displayed using arrow ( -> ):
```html
<h1>{pseudo-variable}</h1>
<p>{content}</p>
<small>Author: {author->name} ({author->email})</small>
```

You can go to unlimited deep.

##### Cycles

```php
$data = array('user' => array
(
	1 => array('username' => 'life', 'email' => 'life@earth.zz', 'address' => 'Earth 001, Milky way'),
	2 => array('username' => 'anonym', 'email' => 'seeyou@friday.zz', 'address' => 'Paris, France')
));
```

