# whmcsapi
## Synopsis

API interface handler for WHMCS v6.0 and above

## Installation

Install the latest version with

```bash
$ composer require gohrco/whmcsapi
```

## Basic Usage

```php
<?php

use Gohrco\Whmcsapi;

// create an API handler
$api		=	new Whmcsapi();

// Set options
$api->setUsername( 'apiadmin' );
$api->setPassword( 'password' );
$api->setUrl( 'http://url.to.your/whmcs/' );
$api->setLogpath( '\absolute\path\to\log\entries\' );

// Init
$api->init();

// Call API up
$result		=	$api->getclientsdetails( array( 'userid' => 1 ) );
```

## API Reference

When creating the API handler, you can also pass the options along as an array such as:

```php
$api    = new Whmcsapi( array( 'username' => 'apiadmin', 'password' => 'password', 'url' => 'http://url.to.your/whmcs/', 'logpath' => '\absolute\path\to\log\entries' ) );
$result = $api->getclientsproducts( array( 'userid' => 1 ) );
```

If all four entries are present you can skip the init() call, as that will be done for you.

Any API method supported by WHMCS can be called directly as a method of the object.  For example:

```php
$api->addorder();
$api->getclients();
$api->addbannedip();
...
```
