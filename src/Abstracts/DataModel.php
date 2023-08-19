<?php

namespace IgniteKit\WP\QueryBuilder\Abstracts;

use Exception;
use IgniteKit\WP\QueryBuilder\Contracts\Arrayable;
use IgniteKit\WP\QueryBuilder\Contracts\JSONable;
use IgniteKit\WP\QueryBuilder\Contracts\Stringable;
use IgniteKit\WP\QueryBuilder\QueryBuilder;

/**
 * Custom table data model.
 *
 * @copyright 10 Quality <info@10quality.com>
 * @copyright Darko G <dg@darkog.com>
 * @license MIT
 * @package wp-query-builder
 * @version 1.0.12
 */
abstract class DataModel implements Arrayable, Stringable, JSONable {

	const TABLE = '';

	/**
	 * Holds the model data.
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $attributes = [];
	/**
	 * Holds the list of attributes or properties that should be part of the data casted to array or string.
	 * Those not listed in this array will remain as hidden.
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $properties = [];

	/**
	 * Database table name.
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = '';
	/**
	 * Reference to primary key column name.
	 * @since 1.0.0
	 * @var string
	 */
	protected $primary_key = 'ID';
	/**
	 * List of properties used for keyword search.
	 * @since 1.0.0
	 * @var array
	 */
	protected static $keywords = [];

	/**
	 * Default model constructor.
	 *
	 * @param  array  $attributes
	 * @param  mixed  $id
	 *
	 * @since 1.0.0
	 *
	 */
	public function __construct( $attributes = [], $id = null ) {
		$this->attributes = $attributes;
		if ( ! empty( $id ) ) {
			$this->attributes[ $this->primary_key ] = $id;
		}
	}

	/**
	 * Getter property.
	 * Returns value as reference, reference to aliases based on functions will not work.
	 * @since 1.0.0
	 */
	public function &__get( $property ) {
		$value = null;
		// Protected properties
		if ( property_exists( $this, $property ) ) {
			return $this->$property;
		}
		// Normal data handled in attributes
		if ( isset( $this->attributes[ $property ] ) ) {
			return $this->attributes[ $property ];
		}
		// Aliases
		if ( method_exists( $this, 'get' . ucfirst( $property ) . 'Alias' ) ) {
			$value = call_user_func_array( [ &$this, 'get' . ucfirst( $property ) . 'Alias' ], [] );
		}

		return $value;
	}

	/**
	 * Setter property values.
	 * @since 1.0.0
	 */
	public function __set( $property, $value ) {
		if ( property_exists( $this, $property ) ) {
			// Protected properties
			$this->$property = $value;
		} elseif ( method_exists( $this, 'set' . ucfirst( $property ) . 'Alias' ) ) {
			// Aliases
			call_user_func_array( [ &$this, 'set' . ucfirst( $property ) . 'Alias' ], [ $value ] );
		} else {
			// Normal attribute
			$this->attributes[ $property ] = $value;
		}
	}

	/**
	 * Static constructor that finds recond in database
	 * and fills model.
	 *
	 * @param  mixed  $id
	 *
	 * @return DataModel|null
	 * @throws Exception
	 * @since 1.0.0
	 *
	 */
	public static function find( $id ) {
		$model = new static( [], $id );

		return $model->load();
	}

	/**
	 * Static constructor that finds recond in database
	 * and fills model using where statement.
	 *
	 * @param  array  $args  Where query statement arguments. See non-static method.
	 *
	 * @return DataModel
	 * @throws Exception
	 * @since 1.0.0
	 *
	 */
	public static function find_where( $args ) {
		$model = new static;

		return $model->load_where( $args );
	}

	/**
	 * Static constructor that inserts recond in database and fills model.
	 *
	 * @param  array  $attributes
	 *
	 * @return DataModel
	 * @since 1.0.0
	 *
	 */
	public static function insert( $attributes ) {
		$model = new static( $attributes );

		return $model->save( true ) ? $model : null;
	}

	/**
	 * Static constructor that deletes records
	 *
	 * @param  array  $args  Where query statement arguments. See non-static method.
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public static function delete_where( $args ) {
		$model = new static( [] );

		return $model->_delete_where( $args );
	}

	/**
	 * Returns a collection of models.
	 * @return array
	 * @throws Exception
	 * @since 1.0.0
	 *
	 */
	public static function where( $args = [] ) {
		// Pull specific data from args
		$limit = isset( $args['limit'] ) ? $args['limit'] : null;
		unset( $args['limit'] );
		$offset = isset( $args['offset'] ) ? $args['offset'] : 0;
		unset( $args['offset'] );
		$keywords = isset( $args['keywords'] ) ? $args['keywords'] : null;
		unset( $args['keywords'] );
		$keywords_separator = isset( $args['keywords_separator'] ) ? $args['keywords_separator'] : ' ';
		unset( $args['keywords_separator'] );
		$order_by = isset( $args['order_by'] ) ? $args['order_by'] : null;
		unset( $args['order_by'] );
		$order = isset( $args['order'] ) ? $args['order'] : 'ASC';
		unset( $args['order'] );
		// Build query and retrieve
		$builder = new QueryBuilder( static::TABLE . '_where' );

		return array_map(
			function ( $attributes ) {
				return new static( $attributes );
			},
			$builder->select( '*' )
			        ->from( static::TABLE . ' as `' . static::TABLE . '`' )
			        ->keywords( $keywords, static::$keywords, $keywords_separator )
			        ->where( $args )
			        ->order_by( $order_by, $order )
			        ->limit( $limit )
			        ->offset( $offset )
			        ->get( ARRAY_A )
		);
	}

	/**
	 * Returns count.
	 * @return int
	 * @throws Exception
	 * @since 1.0.0
	 *
	 */
	public static function count( $args = [] ) {
		// Pull specific data from args
		unset( $args['limit'] );
		unset( $args['offset'] );
		$keywords = isset( $args['keywords'] ) ? sanitize_text_field( $args['keywords'] ) : null;
		unset( $args['keywords'] );
		// Build query and retrieve
		$builder = new QueryBuilder( static::TABLE . '_count' );

		return $builder->from( static::TABLE . ' as `' . static::TABLE . '`' )
		               ->keywords( $keywords, static::$keywords )
		               ->where( $args )
		               ->count();
	}

	/**
	 * Returns initialized builder with model set in from statement.
	 * @return QueryBuilder
	 * @since 1.0.0
	 *
	 */
	public static function builder() {
		$builder = new QueryBuilder( static::TABLE . '_custom' );

		return $builder->from( static::TABLE . ' as `' . static::TABLE . '`' );
	}

	/**
	 * Returns a collection with all models found in the database.
	 * @return array
	 * @since 1.0.7
	 *
	 */
	public static function all() {
		// Build query and retrieve
		$builder = new QueryBuilder( static::TABLE . '_all' );

		return array_map(
			function ( $attributes ) {
				return new static( $attributes );
			},
			$builder->select( '*' )
			        ->from( static::TABLE . ' as `' . static::TABLE . '`' )
			        ->get( ARRAY_A )
		);
	}

	/**
	 * Returns query results from mass update.
	 *
	 * @param  array  $set  Set of column => data to update.
	 * @param  array  $where  Where condition.
	 *
	 * @return bool
	 * @throws Exception
	 * @since 1.0.12
	 */
	public static function update_all( $set, $where = [] ) {
		$builder = new QueryBuilder( static::TABLE . '_static_update' );

		return $builder->from( static::TABLE )
		               ->set( $set )
		               ->where( $where )
		               ->update();
	}

	/**
	 * Returns `tablename` property.
	 * @return string
	 * @since 1.0.0
	 *
	 */
	protected function getTablenameAlias() {
		global $wpdb;

		return $wpdb->prefix . $this->table;
	}

	/**
	 * Returns list of protected/readonly properties for
	 * when saving or updating.
	 * @return array
	 * @since 1.0.0
	 *
	 */
	protected function protected_properties() {
		return apply_filters(
			'data_model_' . $this->table . '_excluded_save_fields',
			[ $this->primary_key, 'created_at', 'updated_at' ],
			$this->table_name()
		);
	}

	/**
	 * Saves data attributes in database.
	 * Returns flag indicating if save process was successful.
	 *
	 * @param  bool  $force_insert  Flag that indicates if should insert regardless of ID.
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public function save( $force_insert = false ) {
		global $wpdb;
		$protected = $this->protected_properties();
		if ( ! $force_insert && $this->{$this->primary_key} ) {
			// Update
			$success = $wpdb->update( $this->table_name(), array_filter( $this->attributes, function ( $key ) use ( $protected ) {
				return ! in_array( $key, $protected );
			}, ARRAY_FILTER_USE_KEY ), [ $this->primary_key => $this->attributes[ $this->primary_key ] ] );
			if ( $success ) {
				do_action( 'data_model_' . $this->table . '_updated', $this );
			}
		} else {
			// Insert
			$success                    = $wpdb->insert( $this->table_name(), array_filter( $this->attributes, function ( $key ) use ( $protected ) {
				return ! in_array( $key, $protected );
			}, ARRAY_FILTER_USE_KEY ) );
			$this->{$this->primary_key} = $wpdb->insert_id;
			$date                       = date( 'Y-m-d H:i:s' );
			$this->created_at           = $date;
			$this->updated_at           = $date;
			if ( $success ) {
				do_action( 'data_model_' . $this->table . '_inserted', $this );
			}
		}
		if ( $success ) {
			do_action( 'data_model_' . $this->table . '_save', $this );
		}

		return $success;
	}

	/**
	 * Loads attributes from database.
	 * @return DataModel|null
	 * @throws Exception
	 * @since 1.0.0
	 *
	 */
	public function load() {
		$builder          = new QueryBuilder( $this->table . '_load' );
		$this->attributes = $builder->select( '*' )
		                            ->from( $this->table )
		                            ->where( [ $this->primary_key => $this->attributes[ $this->primary_key ] ] )
		                            ->first( ARRAY_A );

		return ! empty( $this->attributes )
			? apply_filters( 'data_model_' . $this->table, $this )
			: null;
	}

	/**
	 * Loads attributes from database based on custome where statements
	 *
	 * Samples:
	 *     // Simple query
	 *     $this->load_where( ['slug' => 'this-example-1'] );
	 *     // Compound query with OR statement
	 *     $this->load_where( ['ID' => 77, 'ID' => ['OR', 546]] );
	 *
	 * @param  array  $args  Query arguments.
	 *
	 * @return DataModel|null
	 * @throws Exception
	 *
	 * @since 1.0.0
	 *
	 */
	public function load_where( $args ) {
		if ( empty( $args ) ) {
			return null;
		}
		if ( ! is_array( $args ) ) {
			throw new Exception( 'Arguments parameter must be an array.', 10100 );
		}
		$builder          = new QueryBuilder( $this->table . '_load_where' );
		$this->attributes = $builder->select( '*' )
		                            ->from( $this->table )
		                            ->where( $args )
		                            ->first( ARRAY_A );

		return ! empty( $this->attributes )
			? apply_filters( 'data_model_' . $this->table, $this )
			: null;
	}

	/**
	 * Deletes record.
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public function delete() {
		global $wpdb;
		$deleted = $this->{$this->primary_key}
			? $wpdb->delete( $this->table_name(), [ $this->primary_key => $this->attributes[ $this->primary_key ] ] )
			: false;
		if ( $deleted ) {
			do_action( 'data_model_' . $this->table . '_deleted', $this );
		}

		return $deleted;
	}

	/**
	 * Updates specific columns of the model (not the whole object like save()).
	 *
	 * @param  array  $data  Data to update.
	 *
	 * @return bool
	 * @since 1.0.12
	 *
	 */
	public function update( $data = [] ) {
		// If not data, let save() handle this
		if ( empty( $data ) || ! is_array( $data ) ) {
			return $this->save();
		}
		global $wpdb;
		$success   = false;
		$protected = $this->protected_properties();
		if ( $this->{$this->primary_key} ) {
			// Update
			$success = $wpdb->update( $this->table_name(), array_filter( $data, function ( $key ) use ( $protected ) {
				return ! in_array( $key, $protected );
			}, ARRAY_FILTER_USE_KEY ), [ $this->primary_key => $this->attributes[ $this->primary_key ] ] );
			if ( $success ) {
				foreach ( $data as $key => $value ) {
					$this->$key = $value;
				}
				do_action( 'data_model_' . $this->table . '_updated', $this );
			}
		}

		return $success;
	}

	/**
	 * Deletes where query.
	 *
	 * @param  array  $args  Query arguments.
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	protected function _delete_where( $args ) {
		global $wpdb;

		return $wpdb->delete( $this->table_name(), $args );
	}

	/**
	 * Prints the full table name
	 *
	 * @return string
	 * @since 1.1.0
	 *
	 */
	public function table_name() {
		global $wpdb;
		return $wpdb->prefix . $this->table;
	}

	/**
	 * Returns model as array.
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public function __toArray()
	{
		$output = [];
		foreach ($this->properties as $property) {
			if ($this->$property !== null)
				$output[$property] = $this->__getCleaned($this->$property);
		}
		return $output;
	}
	/**
	 * Returns model as array.
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public function toArray()
	{
		return $this->__toArray();
	}
	/**
	 * Returns model as json string.
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function __toString()
	{
		return json_encode($this->__toArray());
	}
	/**
	 * Returns cleaned value for casting.
	 * @since 1.1.0
	 *
	 * @param mixed $value Value to clean.
	 *
	 * @return mixed
	 */
	private function __getCleaned($value)
	{
		switch (gettype($value)) {
			case 'object':
				return method_exists($value, '__toArray')
					? $value->__toArray()
					: (method_exists($value, 'toArray')
						? $value->toArray()
						:(array)$value
					);
			case 'array':
				$output = [];
				foreach ($value as $key => $data) {
					if ($data !== null)
						$output[$key] = $this->__getCleaned($data);
				}
				return $output;
		}
		return $value;
	}
	/**
	 * Returns object as JSON string.
	 * @since 1.1.0
	 *
	 * @link http://php.net/manual/en/function.json-encode.php
	 *
	 * @param int $options JSON encoding options. See @link.
	 * @param int $depth   JSON encoding depth. See @link.
	 *
	 * @return string
	 */
	public function __toJSON($options = 0, $depth = 512)
	{
		return json_encode($this->__toArray(), $options, $depth);
	}
	/**
	 * Returns object as JSON string.
	 * @since 1.1.0
	 *
	 * @link http://php.net/manual/en/function.json-encode.php
	 *
	 * @param int $options JSON encoding options. See @link.
	 * @param int $depth   JSON encoding depth. See @link.
	 *
	 * @return string
	 */
	public function toJSON($options = 0, $depth = 512)
	{
		return $this->__toJSON($options, $depth);
	}
}