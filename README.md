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
	array('username' => 'life', 'email' => 'life@earth.zz', 'address' => 'Earth 001, Milky way'),
	array('username' => 'anonym', 'email' => 'seeyou@friday.zz', 'address' => 'Paris, France')
));
```

In TPL's defined using syntax:
```html
<!-- BEGIN {user} -->
<div class="user">
	<div> Username: {username} </div>
	<div> Email: {email} </div>
	<div> Address: {address} </div>
</div>
<!-- END {user} -->
```

Naming cycle as recommended. You can also use prefixes and nested cycles. Notice BEGIN and END formula for naming cycle and second line for prefix.

```html
<!-- BEGIN {user} AS user -->

	Name: {user.name}
	
	<!-- BEGIN {user.friends} AS friend -->
		Name: {friend.name}
	<!-- END friends -->
	
<!-- END user -->
```

##### Conditions

Are very intuitive. You can use php functions and also modificators (recommended).

```html
<!-- IF {users|count} > 0 -->

	<!-- BEGIN users -->
	...
	<!-- END users -->
	
<!-- ELSE -->
	...
<!-- END -->
```

```html
<!-- IF {day} == 1 -->
Monday
<!-- ELSEIF {day} == 2 -->
Thuesday
<!-- ELSEIF {day} == 3 -->
...
<!-- END -->
```

```html
<!-- IF is_numeric( {pow} ) && pow( {num}, 2 ) > 4 -->
	...
<!-- ELSE -->
	...
<!-- END -->
```

##### Including

You can load one template into another one. Data are global, so can be used in both templates. You are writting filename without extension.

```html
<!-- INCLUDE header -->
```

#### $data

TODO

### theme( $theme_name  )
Your folder structure might look like (if you want to use themes):

```
/views/
	/my_theme 
		/css 
			style.css 
		/img 
		/js 
		/tpl (this folder is required) 
			template_file.tpl

	/my_second_theme 
		/css 
			style.css 
		/img 
		/js 
		/tpl (this folder is required) 
			template_file.tpl
```

You can simply switch between themes:
```php
$this->parser->theme( 'my_second_theme' );
```
