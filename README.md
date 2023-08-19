# WordPress Query Builder Library

[![Latest Stable Version](https://poser.pugx.org/10quality/wp-query-builder/v/stable)](https://packagist.org/packages/10quality/wp-query-builder)
![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/10quality/wp-query-builder/test.yml)
[![Total Downloads](https://poser.pugx.org/10quality/wp-query-builder/downloads)](https://packagist.org/packages/10quality/wp-query-builder)
[![License](https://poser.pugx.org/10quality/wp-query-builder/license)](https://packagist.org/packages/10quality/wp-query-builder)

This package provides a SQL query builder class built on top of WordPress core Database accessor. Usability is similar to Laravel's Eloquent.

The library also provides an abstract class and a trait to be used on data models built for custom tables.

This package is inspired by the [WordPress MVC's](https://www.wordpress-mvc.com/) Query Builder.

## Install

This package / library requires composer.

```bash
composer require ignitekit/wp-query-builder
```

## Usage & Documentation

Please read the [wiki](https://github.com/ignitekit/wp-query-builder/wiki) for documentation.

Quick snippet sample:
```php
$books = wp_query_builder()
    ->select( 'ID' )
    ->select( 'post_name AS name' )
    ->from( 'posts' )
    ->where( ['post_type' => 'book'] )
    ->get();

foreach ( $books as $book ) {
    echo $book->ID;
    echo $book->name;
}
```

## Coding Guidelines

PSR-2 coding guidelines.

## License

MIT License (c) 2019 [10 Quality](https://www.10quality.com/).
MIT License (c) 2023 [Darko G](https://darkog.com/).