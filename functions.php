<?php

use IgniteKit\WP\QueryBuilder\QueryBuilder;
/**
 * Global functions.
 *
 * @author 10 Quality <info@10quality.com>
 * @author Darko G. <dg@darkog.com>
 * @license MIT
 * @package wp-query-builder
 * @version 1.0.9
 */
if ( !function_exists( 'wp_query_builder' ) ) {
    /**
     * Returns initialized QueryBuilder instance.
     * @since 1.0.9
     * 
     * @param string|null $query_id
     * 
     * @return \IgniteKit\WP\QueryBuilder\QueryBuilder
     */
    function wp_query_builder( $query_id = null )
    {
        return QueryBuilder::create( $query_id );
    }
}