<?php

namespace IgniteKit\WP\QueryBuilder;

use Exception;

/**
 * Database query builder.
 *
 * @copyright 10 Quality <info@10quality.com>
 * @copyright Darko G <dg@darkog.com>
 * @license MIT
 * @package wp-query-builder
 */
class QueryBuilder {

	/**
	 * Builder ID for hook references.
	 * @since 1.0.0
	 * @var string
	 */
	protected $id;
	/**
	 * Builder statements.
	 * @since 1.0.0
	 * @var array
	 */
	protected $builder;
	/**
	 * Builder options.
	 * @since 1.0.11
	 * @var array
	 */
	protected $options;

	/**
	 * Builder constructor.
	 *
	 * @param  string|null  $id
	 *
	 * @since 1.0.0
	 *
	 */
	public function __construct( $id = null ) {
		$this->id      = ! empty( $id ) ? $id : uniqid();
		$this->builder = [
			'select' => [],
			'from'   => null,
			'join'   => [],
			'where'  => [],
			'order'  => [],
			'group'  => [],
			'having' => null,
			'limit'  => null,
			'offset' => 0,
			'set'    => [],
		];
		$this->options = [
			'wildcard'         => '{%}',
			'default_wildcard' => '{%}',
		];
	}

	/**
	 * Static constructor.
	 *
	 * @param  string  $id
	 *
	 * @since 1.0.0
	 *
	 */
	public static function create( $id = null ) {
		return new self( $id );
	}

	/**
	 * Adds select statement.
	 *
	 * @param  array|string  $statement
	 *
	 * @return QueryBuilder this for chaining.
	 * @since 1.0.0
	 *
	 */
	public function select( $statement ) {
		$this->builder['select'][] = $statement;

		return $this;
	}

	/**
	 * Adds from statement.
	 *
	 * @param  string  $from
	 * @param  bool  $add_prefix  Should DB prefix be added.
	 *
	 * @return QueryBuilder this for chaining.
	 *
	 * @since 1.0.0
	 *
	 */
	public function from( $from, $add_prefix = true ) {
		global $wpdb;
		$this->builder['from'] = ( $add_prefix ? $wpdb->prefix : '' ) . $from;

		return $this;
	}

	/**
	 * Adds keywords search statement.
	 *
	 * @param  string  $keywords  Searched keywords.
	 * @param  array  $columns  Column or fields where to search.
	 * @param  string  $separator  Keyword separator within keywords string.
	 *
	 * @return QueryBuilder this for chaining.
	 * @since 1.0.0
	 **
	 */
	public function keywords( $keywords, $columns, $separator = ' ' ) {
		if ( ! empty( $keywords ) ) {
			global $wpdb;
			foreach ( explode( $separator, $keywords ) as $keyword ) {
				$keyword                  = '%' . $this->sanitize_value( true, $keyword ) . '%';
				$this->builder['where'][] = [
					'joint'     => 'AND',
					'condition' => '(' . implode( ' OR ', array_map( function ( $column ) use ( &$wpdb, &$keyword ) {
							return $wpdb->prepare( $column . ' LIKE %s', $keyword );
						}, $columns ) ) . ')',
				];
			}
		}

		return $this;
	}

	/**
	 * Adds where statement.
	 *
	 * @param  array  $args  Multiple where arguments.
	 *
	 * @return QueryBuilder this for chaining.
	 * @throws Exception
	 * @since 1.0.0
	 *
	 */
	public function where( $args ) {

		global $wpdb;
		foreach ( $args as $key => $value ) {
			// Options - set
			if ( is_array( $value ) && array_key_exists( 'wildcard', $value ) && ! empty( $value['wildcard'] ) ) {
				$this->options['wildcard'] = trim( $value['wildcard'] );
			}
			// Value
			$arg_value = is_array( $value ) && array_key_exists( 'value', $value ) ? $value['value'] : $value;
			if ( is_array( $value ) && array_key_exists( 'min', $value ) ) {
				$arg_value = $value['min'];
			}
			$sanitize_callback = is_array( $value ) && array_key_exists( 'sanitize_callback', $value )
				? $value['sanitize_callback']
				: true;
			if ( $sanitize_callback
			     && $key !== 'raw'
			     && ( ! is_array( $value ) || ! array_key_exists( 'key', $value ) )
			) {
				$arg_value = $this->sanitize_value( $sanitize_callback, $arg_value );
			}

			// Create statement
			// Auto-detect IN operator when value is an array and no operator is specified
			$prepared_operator = $this->determine_operator( $value, $arg_value );

			if ( is_array( $value ) && array_key_exists( 'key', $value )  ) {
				if ( strtoupper($value['operator']) === 'BETWEEN' ) {
					$prepared_value = $value['key'];
				} else {
					if ( is_array( $arg_value ) ) {
						$prepared_value = $this->prepare_array_value( $arg_value, $value );
					} else if ( is_string( $arg_value ) && ! is_numeric( $arg_value ) ) {
						$prepared_value = sprintf( "'%s'", $arg_value );
					} else {
						$prepared_value = $arg_value;
					}
				}
			} else {
				if ( is_array( $arg_value ) ) {
					// Handle IN and NOT IN operators with proper escaping
					$prepared_value = $this->prepare_array_value( $arg_value, $value );
				} else {
					if ( is_null( $arg_value ) ) {
						$prepared_value = 'null';
					} else {
						$prepared_value = $wpdb->prepare( ( ! is_array( $value ) || ! array_key_exists( 'force_string', $value ) || ! $value['force_string'] ) && is_numeric( $arg_value ) ? '%d' : '%s', $arg_value );
					}
				}
			}
			$statement = $key === 'raw'
				? [ $arg_value ]
				: [
					$key,
					$prepared_operator,
					$prepared_value
				];

			// Between?
			if ( is_array( $value ) ) {
				if ( strpos( $prepared_operator, 'BETWEEN' ) !== false ) {
					if ( array_key_exists( 'max', $value ) || array_key_exists( 'key_b', $value ) ) {
						if ( array_key_exists( 'max', $value ) ) {
							$arg_value = $value['max'];
						}
						if ( array_key_exists( 'sanitize_callback2', $value ) ) {
							$sanitize_callback = $value['sanitize_callback2'];
						}
						if ( $sanitize_callback && ! array_key_exists( 'key_b', $value ) ) {
							$arg_value = $this->sanitize_value( $sanitize_callback, $arg_value );
						}
						$statement[] = 'AND';
						$statement[] = array_key_exists( 'key_b', $value )
							? $value['key_b']
							: ( is_array( $arg_value )
								? $this->prepare_array_value( $arg_value, $value )
								: $wpdb->prepare( ( ! array_key_exists( 'force_string', $value ) || ! $value['force_string'] ) && is_numeric( $arg_value ) ? '%d' : '%s', $arg_value )
							);
					} else {
						throw new Exception( '"max" or "key_b "parameter must be indicated when using the BETWEEN operator.', 10202 );
					}
				}
			}
			$this->builder['where'][] = [
				'joint'     => is_array( $value ) && isset( $value['joint'] ) ? $value['joint'] : 'AND',
				'condition' => $this->buildStatement( $statement ),
			];
			// Options - reset
			if ( is_array( $value ) && array_key_exists( 'wildcard', $value ) && ! empty( $value['wildcard'] ) ) {
				$this->options['wildcard'] = $this->options['default_wildcard'];
			}
		}

		return $this;
	}

	/**
	 * Adds join statement.
	 *
	 * @param  string  $table  Join table.
	 * @param  array  $args  Join arguments.
	 * @param  bool|string  $type  Flag that indicates if it is "LEFT or INNER", also accepts direct join string.
	 * @param  bool  $add_prefix  Should DB prefix be added.
	 *
	 * @return QueryBuilder this for chaining.
	 * @throws Exception
	 *
	 * @since 1.0.0
	 *
	 */
	public function join( $table, $args, $type = false, $add_prefix = true ) {
		$type = is_string( $type ) ? strtoupper( trim( $type ) ) : ( $type ? 'LEFT' : '' );
		if ( ! in_array( $type, [ '', 'LEFT', 'RIGHT', 'INNER', 'CROSS', 'LEFT OUTER', 'RIGHT OUTER' ] ) ) {
			throw new Exception( 'Invalid join type.', 10201 );
		}
		global $wpdb;
		$join = [
			'table' => ( $add_prefix ? $wpdb->prefix : '' ) . $table,
			'type'  => $type,
			'on'    => [],
		];
		foreach ( $args as $argument ) {
			// Options - set
			if ( array_key_exists( 'wildcard', $argument ) && ! empty( $argument['wildcard'] ) ) {
				$this->options['wildcard'] = trim( $argument['wildcard'] );
			}
			// Value
			$arg_value = isset( $argument['value'] ) ? $argument['value'] : null;
			if ( array_key_exists( 'min', $argument ) ) {
				$arg_value = $argument['min'];
			}
			$sanitize_callback = array_key_exists( 'sanitize_callback', $argument ) ? $argument['sanitize_callback'] : true;
			if ( $sanitize_callback
			     && ! array_key_exists( 'raw', $argument )
			     && ! array_key_exists( 'key_b', $argument )
			) {
				$arg_value = $this->sanitize_value( $sanitize_callback, $arg_value );
			}
			$statement = array_key_exists( 'raw', $argument )
				? [ $argument['raw'] ]
				: [
					isset( $argument['key_a'] ) ? $argument['key_a'] : $argument['key'],
					isset( $argument['operator'] ) ? strtoupper( $argument['operator'] ) : ( $arg_value === null && ! isset( $argument['key_b'] ) ? 'is' : '=' ),
					array_key_exists( 'key_b', $argument )
						? $argument['key_b']
						: ( is_array( $arg_value )
						? $this->prepare_array_value( $arg_value, null )
						: ( $arg_value === null
							? 'null'
							: $wpdb->prepare( ( ! array_key_exists( 'force_string', $argument ) || ! $argument['force_string'] ) && is_numeric( $arg_value ) ? '%d' : '%s', $arg_value )
						)
					),
				];
			// Between?
			if ( isset( $argument['operator'] ) ) {
				$argument['operator'] = strtoupper( $argument['operator'] );
				if ( strpos( $argument['operator'], 'BETWEEN' ) !== false ) {
					if ( array_key_exists( 'max', $argument ) || array_key_exists( 'key_c', $argument ) ) {
						if ( array_key_exists( 'max', $argument ) ) {
							$arg_value = $argument['max'];
						}
						if ( array_key_exists( 'sanitize_callback2', $argument ) ) {
							$sanitize_callback = $argument['sanitize_callback2'];
						}
						if ( $sanitize_callback && ! array_key_exists( 'key_c', $argument ) ) {
							$arg_value = $this->sanitize_value( $sanitize_callback, $arg_value );
						}
						$statement[] = 'AND';
						$statement[] = array_key_exists( 'key_c', $argument )
							? $argument['key_c']
							: ( is_array( $arg_value )
								? $this->prepare_array_value( $arg_value, null )
								: $wpdb->prepare( ( ! array_key_exists( 'force_string', $argument ) || ! $argument['force_string'] ) && is_numeric( $arg_value ) ? '%d' : '%s', $arg_value )
							);
					} else {
						throw new Exception( '"max" or "key_c" parameter must be indicated when using the BETWEEN operator.', 10203 );
					}
				}
			}
			$join['on'][] = [
				'joint'     => isset( $argument['joint'] ) ? $argument['joint'] : 'AND',
				'condition' => implode( ' ', $statement ),
			];
			// Options - reset
			if ( array_key_exists( 'wildcard', $argument ) && ! empty( $argument['wildcard'] ) ) {
				$this->options['wildcard'] = $this->options['default_wildcard'];
			}
		}
		$this->builder['join'][] = $join;

		return $this;
	}

	/**
	 * Adds limit statement.
	 *
	 * @param  int  $limit
	 *
	 * @return QueryBuilder this for chaining.
	 * @since 1.0.0
	 *
	 */
	public function limit( $limit ) {
		$this->builder['limit'] = $limit;

		return $this;
	}

	/**
	 * Adds offset statement.
	 *
	 * @param  int  $offset
	 *
	 * @return QueryBuilder this for chaining.
	 * @since 1.0.0
	 *
	 */
	public function offset( $offset ) {
		$this->builder['offset'] = $offset;

		return $this;
	}

	/**
	 * Adds order by statement.
	 *
	 * @param  string  $key
	 * @param  string  $direction
	 *
	 * @return QueryBuilder this for chaining.
	 * @throws Exception
	 * @since 1.0.0
	 *
	 */
	public function order_by( $key, $direction = 'ASC' ) {
		$direction = trim( strtoupper( $direction ) );
		if ( $direction !== 'ASC' && $direction !== 'DESC' ) {
			throw new Exception( 'Invalid direction value.', 10200 );
		}
		if ( ! empty( $key ) ) {
			$this->builder['order'][] = $key . ' ' . $direction;
		}

		return $this;
	}

	/**
	 * Adds group by statement.
	 *
	 * @param  string  $statement
	 *
	 * @return QueryBuilder this for chaining.
	 * @since 1.0.0
	 *
	 */
	public function group_by( $statement ) {
		if ( ! empty( $statement ) ) {
			$this->builder['group'][] = $statement;
		}

		return $this;
	}

	/**
	 * Adds having statement.
	 *
	 * @param  string  $statement
	 *
	 * @return QueryBuilder this for chaining.
	 * @since 1.0.0
	 *
	 */
	public function having( $statement ) {
		if ( ! empty( $statement ) ) {
			$this->builder['having'] = $statement;
		}

		return $this;
	}

	/**
	 * Adds set statement (for update).
	 *
	 * @param  array  $args  Multiple where arguments.
	 *
	 * @return QueryBuilder this for chaining.
	 * @since 1.0.12
	 *
	 */

	/**
	 * Adds set statement (for update).
	 *
	 * @param array $args Multiple where arguments.
	 *
	 * @return QueryBuilder this for chaining.
	 * @since 1.0.12
	 * @since 1.1.0  Modified line 39 to wrap the key in `..`
	 *
	 * @global object $wpdb
	 *
	 */
	public function set( $args ) {

		foreach ( $args as $key => $value ) {

			$preparedKey = sprintf( '`%s`', $key );

			if ( 'raw' === $key ) {
				$statement = [$value];
			} else {
				$parsed = $this->parse_value( $key, $value );
				$statement   = [ $preparedKey, '=', $parsed ];
			}

			$this->builder['set'][] = $this->buildStatement( $statement );
		}

		return $this;
	}

	/**
	 * Adds values statement
	 *
	 * @param $args
	 *
	 * @return $this
	 */
	public function values( $args ) {

		if ( ! isset( $this->builder['values'] ) ) {
			$this->builder['values'] = [];
		}

		foreach ( $args as $key => $value ) {
			$preparedKey = sprintf( '`%s`', $key );

			$this->builder['values'][ $preparedKey ] = $this->parse_value( $key, $value );

		}

		return $this;
	}

	/**
	 * Retunrs results from builder statements.
	 *
	 * @param  int  $output  WPDB output type.
	 * @param  callable  $callable_mapping  Function callable to filter or map results to.
	 * @param  bool  $calc_rows  Flag that indicates to SQL if rows should be calculated or not.
	 *
	 * @return array
	 * @since 1.0.0
	 *
	 */
	public function get( $output = OBJECT, $callable_mapping = null, $calc_rows = false ) {
		global $wpdb;
		$this->builder = apply_filters( 'query_builder_get_builder', $this->builder );
		$this->builder = apply_filters( 'query_builder_get_builder_' . $this->id, $this->builder );
		// Build
		// Query
		$query = '';
		$this->_query_select( $query, $calc_rows );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		$this->_query_order( $query );
		$this->_query_limit( $query );
		$this->_query_offset( $query );
		// Process
		$query   = apply_filters( 'query_builder_get_query', $query );
		$query   = apply_filters( 'query_builder_get_query_' . $this->id, $query );
		$results = $wpdb->get_results( $query, $output );
		if ( $callable_mapping ) {
			$results = array_map( function ( $row ) use ( &$callable_mapping ) {
				return call_user_func_array( $callable_mapping, [ $row ] );
			}, $results );
		}

		return $results;
	}

	/**
	 * Returns first row found.
	 *
	 * @param  int  $output  WPDB output type.
	 *
	 * @return object|array
	 * @since 1.0.0
	 *
	 */
	public function first( $output = OBJECT ) {
		global $wpdb;
		$this->builder = apply_filters( 'query_builder_first_builder', $this->builder );
		$this->builder = apply_filters( 'query_builder_first_builder_' . $this->id, $this->builder );
		// Build
		// Query
		$query = '';
		$this->_query_select( $query );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		$this->_query_order( $query );
		$query .= ' LIMIT 1';
		$this->_query_offset( $query );
		// Process
		$query = apply_filters( 'query_builder_first_query', $query );
		$query = apply_filters( 'query_builder_first_query_' . $this->id, $query );

		return $wpdb->get_row( $query, $output );
	}

	/**
	 * Returns a value.
	 *
	 * @param  int  $x  Column of value to return. Indexed from 0.
	 * @param  int  $y  Row of value to return. Indexed from 0.
	 *
	 * @return mixed
	 *
	 * @since 1.0.0
	 *
	 */
	public function value( $x = 0, $y = 0 ) {
		global $wpdb;
		$this->builder = apply_filters( 'query_builder_value_builder', $this->builder );
		$this->builder = apply_filters( 'query_builder_value_builder_' . $this->id, $this->builder );
		// Build
		// Query
		$query = '';
		$this->_query_select( $query );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		$this->_query_order( $query );
		$this->_query_limit( $query );
		$this->_query_offset( $query );
		// Process
		$query = apply_filters( 'query_builder_value_query', $query );
		$query = apply_filters( 'query_builder_value_query_' . $this->id, $query );

		return $wpdb->get_var( $query, $x, $y );
	}

	/**
	 * Returns the count.
	 *
	 * @param  string|int  $column  Count column.
	 * @param  bool  $bypass_limit  Flag that indicates if limit + offset should be considered on count.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 *
	 */
	public function count( $column = 1, $bypass_limit = true ) {
		global $wpdb;
		$this->builder = apply_filters( 'query_builder_count_builder', $this->builder );
		$this->builder = apply_filters( 'query_builder_count_builder_' . $this->id, $this->builder );
		// Build
		// Query
		$query = 'SELECT count(' . $column . ') as `count`';
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		if ( ! $bypass_limit ) {
			$this->_query_limit( $query );
			$this->_query_offset( $query );
		}
		// Process
		$query = apply_filters( 'query_builder_count_query', $query );
		$query = apply_filters( 'query_builder_count_query_' . $this->id, $query );

		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * Returns column results from builder statements.
	 *
	 * @param  int  $x  Column index number.
	 * @param  bool  $calc_rows  Flag that indicates to SQL if rows should be calculated or not.
	 *
	 * @return array
	 *
	 * @since 1.0.6
	 *
	 */
	public function col( $x = 0, $calc_rows = false ) {
		global $wpdb;
		$this->builder = apply_filters( 'query_builder_col_builder', $this->builder );
		$this->builder = apply_filters( 'query_builder_col_builder_' . $this->id, $this->builder );
		// Build
		// Query
		$query = '';
		$this->_query_select( $query, $calc_rows );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		$this->_query_order( $query );
		$this->_query_limit( $query );
		$this->_query_offset( $query );
		// Process
		$query = apply_filters( 'query_builder_col_query', $query );
		$query = apply_filters( 'query_builder_col_query_' . $this->id, $query );

		return $wpdb->get_col( $query, $x );
	}

	/**
	 * Returns flag indicating if query has been executed.
	 *
	 * @param  string  $sql
	 *
	 * @return bool
	 * @since 1.0.8
	 *
	 */
	public function query( $sql = '' ) {
		global $wpdb;
		$this->builder = apply_filters( 'query_builder_query_builder', $this->builder );
		$this->builder = apply_filters( 'query_builder_query_builder_' . $this->id, $this->builder );
		// Build
		// Query
		$query = $sql;
		if ( empty( $query ) ) {
			$this->_query_select( $query );
			$this->_query_from( $query );
			$this->_query_join( $query );
			$this->_query_where( $query );
			$this->_query_group( $query );
			$this->_query_having( $query );
			$this->_query_order( $query );
			$this->_query_limit( $query );
			$this->_query_offset( $query );
		}
		// Process
		$query = apply_filters( 'query_builder_query_query', $query );
		$query = apply_filters( 'query_builder_query_query_' . $this->id, $query );

		return $wpdb->query( $query );
	}

	/**
	 * Returns flag indicating if query has been executed.
	 *
	 * @param  string  $sql
	 *
	 * @return bool
	 * @since 1.0.8
	 *
	 * @see self::query()
	 *
	 */
	public function raw( $sql ) {
		return $this->query( $sql );
	}

	/**
	 * Returns flag indicating if delete query has been executed.
	 * @return bool
	 *
	 * @since 1.0.8
	 *
	 */
	public function delete() {
		global $wpdb;
		$this->builder = apply_filters( 'query_builder_delete_builder', $this->builder );
		$this->builder = apply_filters( 'query_builder_delete_builder_' . $this->id, $this->builder );
		// Build
		// Query
		$query = '';
		$this->_query_delete( $query );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		// Process
		$query = apply_filters( 'query_builder_delete_query', $query );
		$query = apply_filters( 'query_builder_delete_query_' . $this->id, $query );

		return $wpdb->query( $query );
	}

	/**
	 * The insert query
	 * @since 1.1.0
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public function insert() {
		global $wpdb;
		$this->builder = apply_filters( 'query_builder_insert_builder', $this->builder );
		$this->builder = apply_filters( 'query_builder_insert_builder_' . $this->id, $this->builder );
		// Build
		// Query
		$query = '';
		$this->_query_insert( $query );
		// Process
		$query = apply_filters( 'query_builder_insert_query', $query );
		$query = apply_filters( 'query_builder_insert_query_' . $this->id, $query );

		$result = $wpdb->query( $query );

		if ( false !== $result ) {
			return $wpdb->insert_id;
		} else {
			return null;
		}
	}

	/**
	 * Returns flag indicating if update query has been executed.
	 * @return bool
	 *
	 * @since 1.0.12
	 *
	 */
	public function update() {
		global $wpdb;
		$this->builder = apply_filters( 'query_builder_update_builder', $this->builder );
		$this->builder = apply_filters( 'query_builder_update_builder_' . $this->id, $this->builder );
		// Build
		// Query
		$query = '';
		$this->_query_update( $query );
		$this->_query_join( $query );
		$this->_query_set( $query );
		$this->_query_where( $query );
		// Process
		$query = apply_filters( 'query_builder_update_query', $query );
		$query = apply_filters( 'query_builder_update_query_' . $this->id, $query );

		return $wpdb->query( $query );
	}

	/**
	 * Retunrs found rows in last query, if SQL_CALC_FOUND_ROWS is used and is supported.
	 * @return array
	 *
	 * @since 1.0.6
	 *
	 */
	public function rows_found() {
		global $wpdb;
		$query = 'SELECT FOUND_ROWS()';
		// Process
		$query = apply_filters( 'query_builder_found_rows_query', $query );
		$query = apply_filters( 'query_builder_found_rows_query_' . $this->id, $query );

		return $wpdb->get_var( $query );
	}

	/**
	 * Builds query's select statement.
	 *
	 * @param  string &$query
	 * @param  bool  $calc_rows
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_select( &$query, $calc_rows = false ) {
		$query = 'SELECT ' . ( $calc_rows ? 'SQL_CALC_FOUND_ROWS ' : '' ) . (
			is_array( $this->builder['select'] ) && count( $this->builder['select'] )
				? implode( ',', $this->builder['select'] )
				: '*'
			);
	}

	/**
	 * Builds query's from statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_from( &$query ) {
		$query .= ' FROM ' . $this->builder['from'];
	}

	/**
	 * Builds query's join statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_join( &$query ) {
		foreach ( $this->builder['join'] as $join ) {
			$query .= ( ! empty( $join['type'] ) ? ' ' . $join['type'] . ' JOIN ' : ' JOIN ' ) . $join['table'];
			for ( $i = 0; $i < count( $join['on'] ); ++ $i ) {
				$query .= ( $i === 0 ? ' ON ' : ' ' . $join['on'][ $i ]['joint'] . ' ' )
				          . $join['on'][ $i ]['condition'];
			}
		}
	}

	/**
	 * Builds query's where statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_where( &$query ) {
		for ( $i = 0; $i < count( $this->builder['where'] ); ++ $i ) {
			$query .= ( $i === 0 ? ' WHERE ' : ' ' . $this->builder['where'][ $i ]['joint'] . ' ' )
			          . $this->builder['where'][ $i ]['condition'];
		}
	}

	/**
	 * Builds query's group by statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_group( &$query ) {
		if ( count( $this->builder['group'] ) ) {
			$query .= ' GROUP BY ' . implode( ',', $this->builder['group'] );
		}
	}

	/**
	 * Builds query's having statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_having( &$query ) {
		if ( $this->builder['having'] ) {
			$query .= ' HAVING ' . $this->builder['having'];
		}
	}

	/**
	 * Builds query's order by statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_order( &$query ) {
		if ( count( $this->builder['order'] ) ) {
			$query .= ' ORDER BY ' . implode( ',', $this->builder['order'] );
		}
	}

	/**
	 * Builds query's limit statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_limit( &$query ) {
		global $wpdb;
		if ( $this->builder['limit'] ) {
			$query .= $wpdb->prepare( ' LIMIT %d', $this->builder['limit'] );
		}
	}

	/**
	 * Builds query's offset statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_offset( &$query ) {
		global $wpdb;
		if ( $this->builder['offset'] ) {
			$query .= $wpdb->prepare( ' OFFSET %d', $this->builder['offset'] );
		}
	}

	/**
	 * Builds query's delete statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.8
	 *
	 */
	private function _query_delete( &$query ) {
		$query .= trim( 'DELETE ' . ( count( $this->builder['join'] )
				? preg_replace( '/\s[aA][sS][\s\S]+.*?/', '', $this->builder['from'] )
				: ''
			) );
	}

	/**
	 * Prepares the insert query
	 *
	 * @param $query
	 * @since 1.1.0
	 *
	 * @return void
	 */
	protected function _query_insert( &$query ) {
		$query = trim( 'INSERT INTO ' . $this->builder['from'] . ' ' . '(' . implode( ', ', array_keys( $this->builder['values'] ) ) . ')' . ' VALUES(' . implode( ', ', array_values( $this->builder['values'] ) ) . ')' );
	}

	/**
	 * Builds query's update statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.12
	 *
	 */
	private function _query_update( &$query ) {
		$query .= trim( 'UPDATE ' . ( count( $this->builder['join'] )
				? $this->builder['from'] . ',' . implode( ',', array_map( function ( $join ) {
					return $join['table'];
				}, $this->builder['join'] ) )
				: $this->builder['from']
			) );
	}

	/**
	 * Builds query's set statement.
	 *
	 * @param  string &$query
	 *
	 * @since 1.0.12
	 *
	 */
	private function _query_set( &$query ) {
		$query .= $this->builder['set'] ? ' SET ' . implode( ',', $this->builder['set'] ) : '';
	}

	/**
	 * Build statement
	 *
	 * @param array $statement
	 *
	 * @return string
	 */
	protected function buildStatement( $statement ) {
		$imploded = implode( ' ', $statement );

		return str_replace( [ "'NOT NULL'", "'not null'", "'NULL'", "'null'" ], [ "NOT NULL", "not null", "NULL", "null" ], $imploded );
	}

	/**
	 * Returns value escaped with WPDB `esc_like`,
	 *
	 * @param  mixed  $value
	 *
	 * @return string
	 * @since 1.0.6
	 *
	 */
	private function _builder_esc_like( $value ) {
		global $wpdb;
		$wildcard = $this->options['wildcard'];

		return implode( '%', array_map( function ( $part ) use ( &$wpdb, &$wildcard ) {
			return $wpdb->esc_like( $part );
		}, explode( $wildcard, $value ) ) );
	}

	/**
	 * Returns escaped value for LIKE comparison and appends wild card at the beggining.
	 *
	 * @param  mixed  $value
	 *
	 * @return string
	 * @since 1.0.6
	 *
	 */
	private function _builder_esc_like_wild_value( $value ) {
		return '%' . $this->_builder_esc_like( $value );
	}

	/**
	 * Returns escaped value for LIKE comparison and appends wild card at the end.
	 *
	 * @param  mixed  $value
	 *
	 * @return string
	 * @since 1.0.6
	 *
	 */
	private function _builder_esc_like_value_wild( $value ) {
		return $this->_builder_esc_like( $value ) . '%';
	}

	/**
	 * Returns escaped value for LIKE comparison and appends wild cards at both ends.
	 *
	 * @param  mixed  $value
	 *
	 * @return string
	 * @since 1.0.6
	 *
	 */
	private function _builder_esc_like_wild_wild( $value ) {
		return '%' . $this->_builder_esc_like( $value ) . '%';
	}

	/**
	 * Determines the appropriate operator based on the value and its type.
	 *
	 * @param mixed $value The full value array or simple value
	 * @param mixed $arg_value The actual value to be used in the query
	 * @return string The SQL operator
	 * @since 1.3.0
	 */
	protected function determine_operator( $value, $arg_value ) {
		// If operator is explicitly set, use it
		if ( is_array( $value ) && isset( $value['operator'] ) ) {
			return strtoupper( $value['operator'] );
		}

		// Auto-detect based on value type
		if ( $arg_value === null ) {
			return 'IS';
		}

		if ( is_array( $arg_value ) ) {
			return 'IN';
		}

		return '=';
	}

	/**
	 * Prepares array values for IN/NOT IN operators with proper escaping.
	 *
	 * @param array $arg_value The array of values
	 * @param mixed $value The full value configuration
	 * @return string Properly formatted array for SQL
	 * @since 1.3.0
	 */
	protected function prepare_array_value( $arg_value, $value ) {
		global $wpdb;

		$escaped_values = array();
		foreach ( $arg_value as $single_value ) {
			if ( is_null( $single_value ) ) {
				$escaped_values[] = 'NULL';
			} else {
				$force_string = is_array( $value ) && array_key_exists( 'force_string', $value ) && $value['force_string'];
				$escaped_values[] = $wpdb->prepare(
					( ! $force_string && is_numeric( $single_value ) ) ? '%d' : '%s',
					$single_value
				);
			}
		}

		return '(' . implode( ',', $escaped_values ) . ')';
	}

	/**
	 * Parses value from parameterized statement
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return mixed|string
	 */
	protected function parse_value( $key, $value ) {

		global $wpdb;

		$arg_value         = is_array( $value ) && array_key_exists( 'value', $value ) ? $value['value'] : $value;
		$sanitize_callback = is_array( $value ) && array_key_exists( 'sanitize_callback', $value ) ? $value['sanitize_callback'] : true;
		if ( $sanitize_callback && $key !== 'raw' && ( ! is_array( $value ) || ! array_key_exists( 'raw', $value ) ) ) {
			$arg_value = $this->sanitize_value( $sanitize_callback, $arg_value );
		}

		if ( is_array( $value ) && array_key_exists( 'raw', $value ) ) {
			return $value['raw'];
		} else {
			if ( is_array( $arg_value ) ) {
				return ( '\'' . implode( ',', $arg_value ) . '\'' ); // Should not use prepare_array_value() !
			} else {
				return ( $arg_value === null
					? 'null'
					: $wpdb->prepare( ( ! is_array( $value ) || ! array_key_exists( 'force_string', $value ) || ! $value['force_string'] ) && is_numeric( $arg_value ) ? '%d' : '%s', $arg_value )
				);
			}
		}
	}

	/**
	 * Sanitize value.
	 *
	 * @param string|bool $callback Sanitize callback.
	 * @param mixed $value
	 *
	 * @return mixed
	 * @since 1.0.0
	 *
	 */
	private function sanitize_value( $callback, $value ) {
		if ( $callback === true ) {
			$callback = ( is_numeric( $value ) && strpos( $value, '.' ) !== false )
				? 'floatval'
				: ( is_numeric( $value )
					? 'intval'
					: ( is_string( $value )
						? 'sanitize_text_field'
						: null
					)
				);
		}
		if ( $callback && strpos( $callback, '_builder' ) !== false ) {
			$callback = [ &$this, $callback ];
		}
		if ( is_array( $value ) ) {
			for ( $i = count( $value ) - 1; $i >= 0; -- $i ) {
				$value[ $i ] = $this->sanitize_value( true, $value[ $i ] );
			}
		}

		return $callback && is_callable( $callback ) ? call_user_func_array( $callback, [ $value ] ) : $value;
	}

	/**
	 * Parses value from parameterized statement
	 *
	 * @param $key
	 * @param $value
	 *
	 * @since 1.0.0
	 * @depecated 1.3.0
	 *
	 * @return mixed|string
	 */
	protected function parseValue( $key, $value ) {
		return $this->parse_value( $key, $value );
	}
}